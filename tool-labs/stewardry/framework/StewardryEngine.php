<?php
declare(strict_types=1);

/**
 * Provides methods for stewardry.
 */
class StewardryEngine extends Base
{
    ##########
    ## Properties
    ##########
    /**
     * The predefined user groups which can be analyzed through this tool.
     * @var array<string, string[]>
     */
    public array $presetGroups = [
        'sysop' => ['abusefilter', 'block', 'delete', 'protect', 'rights'],
        'bureaucrat' => ['rights'],
        'interface-admin' => [],
        'checkuser' => [],
        'suppress' => [],
        'bot' => []
    ];

    /**
     * The input dbname to analyze.
     */
    public ?string $dbname = null;

    /**
     * The current wiki.
     */
    public ?Wiki $wiki = null;

    /**
     * Maps the selected group names (like 'sysop') to the relevant log types.
     * @var array<string, string[]>
     */
    public array $groups = [];

    /**
     * The database handler from which to query data.
     */
    public Toolserver $db;



    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Backend $backend The backend framework.
     */
    public function __construct(Backend $backend)
    {
        parent::__construct();

        // set values
        $this->db = $backend->getDatabase();

        // parse query
        $this->dbname = $this->db->normalizeDbn($backend->getRouteValue() ?? $backend->getString('wiki', allowBlank: false));
        $this->wiki = $this->db->getWiki($this->dbname);
        foreach ($this->presetGroups as $group => $logTypes) {
            if ($backend->getBool($group))
                $this->groups[$group] = $logTypes;
        }

        // normalise
        if (!$this->groups)
            $this->groups = ['sysop' => $this->presetGroups['sysop']];
    }

    /**
     * Generate a SQL query which returns activity metrics for the selected groups.
     * @returns array<string, mixed>[] A lookup of metrics by user.
     */
    public function fetchMetrics(): array
    {
        $this->db->Connect($this->wiki->name);

        $groupNames = array_keys($this->groups);
        $rights = $this->groups;

        // fetch users
        $users = $this->db->query('
            SELECT
                user_name,
                actor_id,
                GROUP_CONCAT(ug_group SEPARATOR ",") AS user_groups,
                (SELECT rev_timestamp FROM revision_userindex WHERE rev_actor = actor_id ORDER BY rev_timestamp DESC LIMIT 1) AS last_edit
            FROM
                user
                INNER JOIN user_groups ON user_id = ug_user AND ug_group IN(\'' . implode('\',\'', $groupNames) . '\')
                INNER JOIN actor ON actor_user = user_id
            GROUP BY user_name
        ')->fetchAllAssoc();

        // fetch user info
        foreach ($users as &$user)
        {
            // prefill group values
            foreach ($groupNames as $groupName)
            {
                $user["user_has_$groupName"] = false;
                $user["last_$groupName"] = null;
            }

            // last group action
            $userGroups = explode(',', $user['user_groups']);
            foreach ($userGroups as $groupName)
            {
                $user["user_has_$groupName"] = true;

                if ($rights[$groupName])
                    $user["last_$groupName"] = $this->fetchLastLogTimestamp($user['actor_id'], $rights[$groupName]);
            }
        }

        return $users;
    }

    /**
     * Get the HTML for a color-coded date cell.
     * @param string|false|null $dateStr The date string to display.
     */
    public function getDateCellHtml(string|false|null $dateStr): string
    {
        if ($dateStr) {
            $date = DateTime::createFromFormat('YmdGis', $dateStr, new DateTimeZone('UTC'));
            if ($date > new DateTime('-1 week'))
                $color = 'CFC';
            elseif ($date > new DateTime('-3 week'))
                $color = 'FFC';
            else
                $color = 'FCC';
            return "<td style='background:#$color;'>" . $date->format('Y-m-d H:i') . "</td>";
        }

        return '<td style="background:#FCC;">never</td>';
    }


    ##########
    ## Private methods
    ##########
    /**
     * Get the timestamp of the user's most recent action for the given log types.
     *
     * @param int|string $actorId The actor ID whose log entries to search.
     * @param string[] $logTypes The log types to match.
     */
    private function fetchLastLogTimestamp(int|string $actorId, array $logTypes): mixed
    {
        // note: a separate subquery per log type seems inefficient, but it's faster than `WHERE IN`
        // since each subquery can use the `log_actor_type_time` index.

        $subqueries = [];
        $values = [];
        foreach ($logTypes as $logType) {
            $subqueries[] = '(SELECT log_timestamp AS timestamp FROM logging_userindex WHERE log_actor = ? AND log_type = ? ORDER BY log_timestamp DESC LIMIT 1)';
            $values[] = $actorId;
            $values[] = $logType;
        }

        return $this->db
            ->query('SELECT MAX(timestamp) FROM (' . implode(' UNION ALL ', $subqueries) . ') AS matches', $values)
            ->fetchValue();
    }
}

<?php
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
     * @var array
     */
    public $presetGroups = [
        'sysop' => ['abusefilter', 'block', 'delete', 'protect', 'rights'],
        'bureaucrat' => ['rights'],
        'interface-admin' => [],
        'checkuser' => [],
        'oversight' => [],
        'bot' => []
    ];

    /**
     * The input dbname to analyze.
     * @var string
     */
    public $dbname = null;

    /**
     * The current wiki.
     * @var Wiki
     */
    public $wiki = null;

    /**
     * Maps the selected group names (like 'sysop') to the relevant log types.
     * @var array<string, string[]>
     */
    public $groups = [];

    /**
     * The database handler from which to query data.
     * @var Toolserver
     */
    public $db = null;



    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Backend $backend The backend framework.
     */
    public function __construct($backend)
    {
        parent::__construct();

        // set values
        $this->db = $backend->getDatabase();

        // parse query
        $this->dbname = $this->db->normalizeDbn($backend->get('wiki') ?: $backend->getRouteValue());
        $this->wiki = $this->db->getWiki($this->dbname);
        foreach ($this->presetGroups as $group => $logTypes) {
            if ($backend->get($group))
                $this->groups[$group] = $logTypes;
        }

        // normalise
        if (!$this->groups)
            $this->groups = ['sysop' => $this->presetGroups['sysop']];
    }

    /**
     * Generate a SQL query which returns activity metrics for the selected groups.
     */
    public function fetchMetrics()
    {
        $this->db->Connect($this->wiki->name);

        $groupNames = array_keys($this->groups);
        $rights = $this->groups;

        // fetch users
        $users = $this->db->query('
            SELECT
                user_id,
                user_name,
                GROUP_CONCAT(ug_group SEPARATOR ",") AS user_groups
            FROM
                user
                INNER JOIN user_groups ON user_id = ug_user AND ug_group IN(\'' . implode('\',\'', $groupNames) . '\')
            GROUP BY user_name
        ')->fetchAllAssoc();

        // fetch user info
        foreach ($users as &$user)
        {
            // actor ID/name
            $user['actor_id'] = $this->db->query('SELECT actor_id FROM actor WHERE actor_user = ? LIMIT 1', [$user['user_id']])->fetchValue();

            // last edit
            $user['last_edit'] = $this->db->query('SELECT rev_timestamp FROM revision_userindex WHERE rev_actor = ? ORDER BY rev_id DESC LIMIT 1', [$user['actor_id']])->fetchValue();

            // last group action
            $userGroups = explode(',', $user['user_groups']);
            foreach ($userGroups as $groupName)
            {
                $user["user_has_$groupName"] = true;

                if ($rights[$groupName])
                    $user["last_$groupName"] = $this->db->query('SELECT log_timestamp FROM logging_userindex WHERE log_actor = ? AND log_type IN (\'' . implode('\',\'', $rights[$groupName]) . '\') ORDER BY log_id DESC LIMIT 1', [$user['actor_id']])->fetchValue();
            }
        }

        return $users;
    }

    /**
     * Get the HTML for a color-coded date cell.
     * @param string $dateStr The date string to display.
     * @return string
     */
    public function getDateCellHtml($dateStr)
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
}

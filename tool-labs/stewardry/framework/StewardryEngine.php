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
        'sysop' => ['abusefilter', 'block', 'delete', 'protect'],
        'bureaucrat' => ['makebot', 'renameuser', 'rights'],
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
     * @var Wiki[]
     */
    public $wiki = null;

    /**
     * The selected groups.
     * @var string
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
        $names = array_keys($this->groups);
        $rights = $this->groups;

        // build SQL fragments
        $outerSelects = [];
        $innerSelects = [];
        foreach ($names as $group) {
            $outerSelects[] = "user_has_$group";
            if ($rights[$group])
                $outerSelects[] = "CASE WHEN user_has_$group<>0 THEN (SELECT log_timestamp FROM logging_userindex WHERE log_user=user_id AND log_type IN ('" . implode("','", $rights[$group]) . "') ORDER BY log_id DESC LIMIT 1) END AS last_$group";
            $innerSelects[] = "COUNT(CASE WHEN ug_group='$group' THEN 1 END) AS user_has_$group";
        }

        // execute SQL
        $this->db->Connect($this->wiki->name);
        $sql = "SELECT * FROM (SELECT user_name,(SELECT rev_timestamp FROM revision_userindex WHERE rev_user=user_id ORDER BY rev_timestamp DESC LIMIT 1) AS last_edit," . implode(",", $outerSelects) . " FROM (SELECT user_id,user_name," . implode(",", $innerSelects) . " FROM user INNER JOIN user_groups ON user_id = ug_user AND ug_group IN('" . implode("','", $names) . "') GROUP BY ug_user) AS t_users) AS t_metrics ORDER BY last_edit DESC";
        return $this->db->query($sql)->fetchAllAssoc();
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

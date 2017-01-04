<?php
require_once('../backend/modules/Backend.php');
$backend = Backend::Create('Stewardry', 'Estimates which users in a group are available based on their last edit or action.')
    ->link('/content/jquery.tablesorter.js', true)
    ->link('/stewardry/scripts.js', true)
    ->header();

##########
## Engine
##########
/**
 * Provides methods for stewardry.
 */
class Engine
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


##########
## Process request
##########
$engine = new Engine($backend);
$data = [];


##########
## Render form
##########
echo "
    <form action='{$backend->url('/stewardry')}' method='get'>
        <label for='wiki'>Wiki:</label>
        <select name='wiki' id='wiki'>
    ";
foreach ($engine->db->getDomains() as $dbname => $domain)
    echo "<option value='$dbname' ", ($dbname == $engine->wiki->name ? ' selected="selected"' : ''), ">$domain</option>";
echo "
    </select><br/>

    Groups to display (uncheck some for faster results):<br/>
    <div style='margin-left:3em;'>
    ";
foreach ($engine->presetGroups as $group => $rights) {
    echo "
        <input type='checkbox' id='$group' name='$group' value='1'", (isset($engine->groups[$group]) ? ' checked="checked"' : ''), " />
        <label for='$group'>$group</label><br/>
   ";
}
echo "
        </div>
        <input type='submit' value='Analyze' />
    </form>
    ";

/***************
 * Get & process data
 ***************/
do {
    /***************
     * Error-check
     ***************/
    // form not filled
    if (!$engine->dbname || !count($engine->groups))
        break;

    // invalid input
    if (!$engine->wiki) {
        print '<div class="fail">There is no wiki matching the selected database.</div>';
        break;
    }

    // disallowed queries
    if ($engine->dbname == 'enwiki' && $engine->groups['sysop'])
        die('<div class="fail">Sysop statistics are disabled for en.wikipedia.org because the result set is too large to process.</div>');
    if ($engine->dbname == 'enwiki' && ($engine->groups['bureaucrat'] + $engine->groups['checkuser'] + $engine->groups['oversight'] + $engine->groups['bot']) > 1)
        die('<div class="fail">Only one group (except sysop) can be selected for en.wikipedia.org because the result set is too large to process.</div>');


    /***************
     * Get data
     ***************/
    $backend->profiler->start('fetch data');
    $data = $engine->fetchMetrics();
    $backend->profiler->stop('fetch data');


    /***************
     * Output
     ***************/
    $backend->profiler->start('analyze and output');
    // table of contents
    echo '<h2>Generated data</h2>',
    '<div id="toc"><b>Table of contents</b><ol>';
    foreach ($engine->groups as $group => $v) {
        echo '<li><a href="#', $group, '_activity">', $group, ' activity</a></li>';
    }
    echo '</ol></div>';

    // sections
    foreach ($engine->groups as $group => $v) {
        // filter users
        $matching = array_filter($data, function ($r) use ($group) {
            return !!$r["user_has_$group"];
        });

        // print header
        echo "<h2 id='{$group}_activity'>{$group}s</h2>";
        if (!$matching) {
            echo "<div class='neutral'>No active {$group}s on this wiki.</div>";
            continue;
        }

        // print table
        $showLog = !!$engine->groups[$group];
        echo "<table class='pretty sortable' id='{$group}_metrics'><thead><tr><th>user</th><th>last edit</th>", ($showLog ? "<th>last log action</th>" : ""), "</tr></thead><tbody>";

        foreach ($matching as $row) {
            $name = $row["user_name"];
            $urlName = $backend->formatValue($name);
            $lastEdit = $row["last_edit"];
            $lastLog = $row["last_$group"];
            $domain = $engine->wiki->domain;

            echo "<tr>",
            "<td><a href='//$domain/wiki/User:$urlName' title='$urlName&#39;s user page'>$name</a> <small>[<a href='", $backend->url('/crossactivity/' . $urlName), "' title='scan this user&#39;s activity on all wikis'>all wikis</a>]</small></td>",
            $engine->getDateCellHtml($lastEdit),
            ($showLog ? $engine->getDateCellHtml($lastLog) : ''),
            "</tr>";
        }
        echo '</tbody></table>';
    }

    $backend->profiler->stop('analyze and output');
} while (0);

$backend->footer();
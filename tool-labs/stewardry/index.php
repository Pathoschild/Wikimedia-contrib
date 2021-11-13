<?php
require_once('../backend/modules/Backend.php');
require_once('framework/StewardryEngine.php');
$backend = Backend::Create('Stewardry', 'Estimates which users in a group are available based on their last edit or action.')
    ->link('/content/jquery.tablesorter.js', true)
    ->link('/stewardry/scripts.js', true)
    ->header();

##########
## Initialise
##########
$engine = new StewardryEngine($backend);
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


##########
## Process data
##########
do {
    ##########
    ## Validate
    ##########
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
    if ($engine->dbname == 'enwiki' && count($engine->groups) > 1)
        die('<div class="fail">Only one group (except sysop) can be selected for en.wikipedia.org because the result set is too large to process.</div>');

    ##########
    ## Fetch data
    ##########
    $backend->profiler->start('fetch data');
    $data = $engine->fetchMetrics();
    $backend->profiler->stop('fetch data');

    ##########
    ## Generate output
    ##########
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
        // filter & sort users
        $matching = array_filter($data, function ($r) use ($group) {
            return !!$r["user_has_$group"];
        });
        usort($matching, function($a, $b) {
            return max($b['last_edit'], $b["last_$group"]) <=> max($a['last_edit'], $a["last_$group"]);
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
            $urlName = $backend->formatWikiUrlTitle($name);
            $lastEdit = $row["last_edit"];
            $lastLog = $row["last_$group"];
            $domain = $engine->wiki->domain;

            echo "<tr>",
            "<td><a href='https://$domain/wiki/User:$urlName' title='$urlName&#39;s user page'>{$backend->formatText($name)}</a> <small>[<a href='", $backend->url('/crossactivity/' . $urlName), "' title='scan this user&#39;s activity on all wikis'>all wikis</a>]</small></td>",
            $engine->getDateCellHtml($lastEdit),
            ($showLog ? $engine->getDateCellHtml($lastLog) : ''),
            "</tr>";
        }
        echo '</tbody></table>';
    }

    $backend->profiler->stop('analyze and output');
} while (0);

$backend->footer();
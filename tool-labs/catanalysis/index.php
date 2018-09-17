<?php
set_time_limit(120); // set timout to two minutes
require_once('../backend/modules/Base.php');
require_once('../backend/modules/Backend.php');
require_once('../backend/modules/Form.php');
require_once('../backend/modules/IP.php');
$backend = Backend::create('Catanalysis', 'Analyzes edits to pages in the category tree rooted at the specified category (or pages rooted at a prefix). This is primarily intended for test project analysis by the Wikimedia Foundation <a href="//meta.wikimedia.org/wiki/Language_committee" title="language committee">language committee</a>.')
    ->link('/catanalysis/stylesheet.css')
    ->header();
spl_autoload_register(function ($className) {
    foreach (["framework/$className.php", "framework/models/$className.php"] as $path) {
        if (file_exists($path))
            include($path);
    }
});

##########
## Configuration
##########
/**
 * The maximum number of edits a user can have (per month) while still being counted as inactive.
 * @var int
 */
$maxEditsForInactivity = 10;

/**
 * The maximum number of users a test wiki can have (per month) while still being counted as inactive.
 */
$maxUsersForInactivity = 3;

##########
## Properties
##########
/* input */
$fullTitle = $backend->formatInitialCapital($backend->get('title'));
$database = $backend->get('wiki', $backend->get('db', 'incubatorwiki'));
$cat = !!$backend->get('cat', true);
$listpages = $backend->get('listpages');

/* normalise database */
if ($database && substr($database, -2) == '_p')
    $database = substr($database, 0, -2);

/* parse title */
$i = strpos($fullTitle, ':');
if ($i) {
    $namespace = substr($fullTitle, 0, $i);
    $title = substr($fullTitle, $i + 1);
} else {
    $namespace = null;
    $title = $fullTitle;
}

/* initialize */
$db = $backend->getDatabase();

##########
## Input form
##########
?>
    <form action="<?= $backend->url('/catanalysis') ?>" method="get">
        <fieldset>
            <p>Enter a category name to analyse members of, or a prefix to analyze subpages of (see <a
                        href="index.php?title=Wp/kab&cat=0&db=incubatorwiki" title="example">prefix</a> and <a
                        href="index.php?title=Hindi&cat=1&db=sourceswiki" title="example">category</a> examples).</p>

            <input type="text" id="title" name="title" value="<?= $backend->formatValue($fullTitle) ?>"/>
            (this is a <?= Form::select('cat', $cat, [1 => 'category', 0 => 'prefix']) ?> on <select name="wiki" id="wiki">
                <?php
                foreach ($db->getWikis() as $wiki) {
                    if (!$wiki->isClosed) {
                        $selected = $wiki->dbName == $database;
                        echo '<option value="', $wiki->dbName, '"', ($selected ? ' selected="yes" ' : ''), '>', $backend->formatText($wiki->domain), '</option>';
                    }
                }
                ?>
            </select>)<br/><br/>

            <?= Form::checkbox('listpages', $listpages) ?>
            <label for="listpages">List all pages and redirects (not recommended)</label>
            <br/>

            <input type="submit" value="analyze"/>
        </fieldset>
    </form>
<?php

do {
    ##########
    ## Validate
    ##########
    // missing data (break)
    if (!$title)
        break;

    // category mode (warn)
    if ($cat) {
        echo '<p class="neutral" style="border-color:#C66;">You have selected category mode, which can be skewed by incorrect categorization. Please review the list of pages generated below.</p>';
        $listpages = true;
    }
    if ($namespace) {
        echo '<p class="neutral" style="border-color:#C66;">You have specified the "', $backend->formatText($namespace), '" namespace in the prefix. The details below only reflect edits in that namespace.</p>';
    }


    ##########
    ## Collect revision metrics
    ##########
    $db->connect($database);
    $engine = new CatanalysisEngine();

    // build query
    $backend->profiler->start('build revisions query');
    $query = $cat
        ? $engine->getEditsByCategory($db, $title)
        : $engine->getEditsByPrefix($db, $namespace, $title);
    $backend->profiler->stop('build revisions query');

    // get metrics
    $backend->profiler->start('fetch revision metadata');
    $metrics = $engine->getEditMetrics($db, $query);
    $backend->profiler->stop('fetch revision metadata');

    // mark bots
    $backend->profiler->start('flag bots');
    $engine->flagBots($db, $metrics);
    $backend->profiler->stop('flag bots');

    unset($query);


    ##########
    ## Fetch domain
    ##########
    $backend->profiler->start('fetch domain');
    $db->connect('metawiki');
    $url = $db->query('SELECT url AS domain FROM meta_p.wiki WHERE dbname=? LIMIT 1', $database)->fetchValue();
    $db->dispose();
    $backend->profiler->stop('fetch domain');


    ##########
    ## Output table of contents
    ##########
    $backend->profiler->start('generate output');
    echo '<h2 id="Generated_statistics">Generated statistics</h2>';
    if ($metrics) {
        ?>
        <div id="toc">
            <b>Table of contents</b>
            <ol>
                <li>
                    <a href="#Lists">Lists</a>
                    <ol>
                        <li><a href="#list_editors">editors</a></li>
                        <?php
                        if ($listpages) {
                            ?>
                            <li><a href="#list_pages">pages</a></li>
                            <li><a href="#list_redirects">redirects</a></li>
                            <?php
                        }
                        ?>
                    </ol>
                </li>
                <li><a href="#Overview">Overview</a>
                    <ol>
                        <li><a href="#edits_per_month">edits per month</a></li>
                        <li><a href="#new_pages_per_month">new pages per month</a></li>
                        <li><a href="#bytes_added_per_month">bytes added per month</a></li>
                        <li><a href="#editors_per_month">editors per month</a></li>
                    </ol>
                </li>
                <li><a href="#distribution">Edit distribution per month</a>
                    <ol>
                        <?php
                        foreach ($metrics->months as $month)
                            echo '<li><a href="#distribution_', $month->name, '">', $month->name, '</a></li>';
                        unset($month);
                        ?>
                    </ol>
                </li>
            </ol>
        </div>
        <?php

        ##########
        ## Output lists
        ##########
        echo "<p>Bots, and users with less than $maxEditsForInactivity edits, are struck out or discounted.</p>";
        echo '<h3 id="Lists">Lists</h3>';

        /* user list */
        $users = $metrics->users;
        usort($users, function($a, $b) { return $b->edits - $a->edits; });
        echo '<h4 id="list_editors">editors</h4><ol>';
        foreach ($users as $user) {
            echo '<li';
            if ($user->edits < $maxEditsForInactivity || $user->isBot || $user->isAnonymous)
                echo ' class="struckout"';
            echo '>', $engine->getLinkHtml($url, 'user:' . $user->name, $user->name), ' (<small>', $user->edits, ' edits</small>)';

            if ($user->isBot)
                echo ' <small>[bot]</small>';
            echo '</li>';
        }
        echo '</ol>';
        unset($users);

        if ($listpages) {
            /* page list */
            echo '<h4 id="list_pages">pages</h4><ol>';
            foreach ($metrics->pages as $page)
                echo '<li', ($page->isRedirect ? ' class="redirect"' : ''), '>', $engine->getLinkHtml($url, $page->name), '</li>';
            echo '</ol>';
        }

        ##########
        ## Output overall statistics
        ##########
        echo '
            <h3 id="Overview">Overview</h3>
            There are:
            <ul>
                <li>', count($metrics->pages), ' pages (including categories, templates, talk pages, and redirects);</li>
                <li>', count($metrics->users), ' editors;</li>
                <li>', $metrics->edits, ' edits.</li>
            </ul>
            ';

        /* edits per month */
        echo "<h4 id='edits_per_month'>edits per month</h4><table>";
        foreach ($metrics->months as $month)
            echo $engine->getBarHtml($month->name, $month->edits, 10);
        echo "</table>";
        unset($month);

        /* new pages per month */
        echo "<h4 id='new_pages_per_month'>New pages per month</h4><table>";
        foreach ($metrics->months as $month)
            echo $engine->getBarHtml($month->name, $month->newPages, 10);
        echo "</table>";
        unset($month);

        /* content added per month */
        echo "<h4 id='bytes_added_per_month'>Bytes added per month</h4><table>";
        foreach ($metrics->months as $month)
            echo $engine->getBarHtml($month->name, $month->bytesAdded, 5000);
        echo '</table>';
        unset($month);

        /* editors per month */
        echo '<h4 id="editors_per_month">editors per month</h4>',
        '<table>';
        foreach ($metrics->months as $month) {
            // discount those with less than edit limit
            $users = 0;
            foreach ($month->users as $user) {
                if ($user->edits >= $maxEditsForInactivity && !$user->isBot && !$user->isAnonymous)
                    $users++;
            }
            echo $engine->getBarHtml($month->name, $users, 1);
        }
        echo '</table>';

        ##########
        ## Edit distribution per month
        ##########
        echo '<h3 id="distribution">Edit distribution per month</h3>';

        foreach ($metrics->months as $month) {
            echo '<h4 id="distribution_', $month->name, '">', $month->name, '</h4>',
            '<table>';

            $users = $month->users;
            usort($users, function($a, $b) { return $b->edits - $a->edits; });
            foreach ($users as $user) {
                $isActive = $user->edits > $maxEditsForInactivity && !$user->isBot && !$user->isAnonymous;
                echo $engine->getBarHtml($engine->getLinkHtml($url, 'user:' . $user->name, $user->name), $user->edits, 10, !$isActive);
            }
            unset($users);

            echo '</table>';
        }
    }
    $backend->profiler->stop('generate output');
} while (0);

$backend->footer();

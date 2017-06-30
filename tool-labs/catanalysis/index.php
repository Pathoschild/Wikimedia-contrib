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
    foreach (["models/$className.php"] as $path) {
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

/**
 * The tool engine.
 */
class Engine extends Base
{
    ##########
    ## Public methods
    ##########
    /**
     * Get the HTML for a bar to show in a bar graph.
     * @param string $label The bar label.
     * @param int $total The total value across all bars.
     * @param int $barvalue The value of this bar.
     * @param bool $strike Whether to format the label as struck out.
     * @return string
     */
    public function getBarHtml($label, $total, $barvalue, $strike = false)
    {
        $bars = floor($total / $barvalue);
        $out = '';

        $out .= '<tr><td';
        if ($strike)
            $out .= ' class="struckout"';
        $out .= '>' . $label . '</td><td><b>';
        for ($i = 0; $i < $bars; $i++)
            $out .= '|';
        $out .= '</b></td><td><small>';
        if ($total < 0)
            $out .= '<span style="color:#C00; font-weight:bold;">' . $total . '</span>';
        else
            $out .= $total;
        $out .= '</small></td></tr>';
        return $out;
    }

    /**
     * Get the HTML for a link.
     * @param string $url The base wiki URL.
     * @param string $target The link URL.
     * @param string $text The link text.
     * @return string
     */
    public function getLinkHtml($url, $target, $text = null)
    {
        $text = $this->formatText($text ? $text : $target);
        $target = $this->formatValue($target);
        return "<a href='$url/wiki/$target' title='$target'>$text</a>";
    }

    /**
     * Get the namespace name given the namespace ID.
     * @param int $id The namespace ID.
     * @return string
     */
    public function getNamespaceName($id)
    {
        switch ($id) {
            // built-in namespaces (https://www.mediawiki.org/wiki/Manual:Namespace#Built-in_namespaces)
            case 0:
                return '';
            case 1:
                return 'Talk';
            case 2:
                return 'User';
            case 3:
                return 'User talk';
            case 4:
                return 'Project';
            case 5:
                return 'Project talk';
            case 6:
                return 'Image';
            case 7:
                return 'Image talk';
            case 8:
                return 'MediaWiki';
            case 9:
                return 'MediaWiki talk';
            case 10:
                return 'Template';
            case 11:
                return 'Template talk';
            case 12:
                return 'Help';
            case 13:
                return 'Help talk';
            case 14:
                return 'Category';
            case 15:
                return 'Category talk';

            // Wikisource
            case 104:
                return 'Page';
            case 105:
                return 'Page talk';
            case 106:
                return 'Index';
            case 107:
                return 'Index talk';
            case 108:
                return 'Author';
            case 109:
                return 'Author talk';

            // special
            case 828:
                return 'Module';
            case 829:
                return 'Module talk';
            case 2300:
                return 'Gadget';
            case 2301:
                return 'Gadget talk';
            case 2302:
                return 'Gadget definition';
            case 2303:
                return 'Gadget definition talk';
            case 2600:
                return 'Topic';

            // fallback
            default:
                return '{{ns:' . $id . '}}';
        }
    }

    /**
     * Get a SQL query that fetches all the edits to pages with a common prefix.
     * @param Database $db The connected database instance.
     * @param string $namespace The namespace to search.
     * @param string $title The page prefix to search.
     * @return Database The Database instance to chain query methods.
     */
    public function getEditsByPrefix($db, $namespace, $title)
    {
        /* build initial query */
        $sql = 'SELECT page.page_namespace, page.page_title, page.page_is_redirect, page.page_is_new, revision.rev_minor_edit, revision.rev_user_text, revision.rev_timestamp, revision.rev_len, revision.rev_page FROM revision LEFT JOIN page ON page.page_id = revision.rev_page ';
        $values = [];

        /* add namespace */
        if ($namespace) {
            $sql .= 'JOIN toolserver.namespace ON page.page_namespace = toolserver.namespace.ns_id WHERE toolserver.namespace.ns_name = ? AND ';
            $values[] = $namespace;
        }
        else
            $sql .= 'WHERE ';

        /* add prefix */
        $sql .= ' (CONVERT(page_title USING binary)=CONVERT(? USING BINARY) OR CONVERT(page_title USING BINARY) LIKE CONVERT(? USING BINARY)) ORDER BY revision.rev_timestamp';
        $values[] = str_replace(' ', '_', $title);
        $values[] = str_replace(' ', '_', $title . '%');

        /* build query */
        return $db->query($sql, $values);
    }

    /**
     * Get a SQL query that fetches all the edits to pages in a category or its subcategories.
     * @param Database $db The connected database instance.
     * @param string $title The category title to search.
     * @return Database The Database instance to chain query methods.
     */
    public function getEditsByCategory($db, $title)
    {
        /* build initial query */
        $sql = 'SELECT page.page_namespace, page.page_title, page.page_is_redirect, page.page_is_new, revision.rev_minor_edit, revision.rev_user_text, revision.rev_timestamp, revision.rev_len, revision.rev_page FROM revision LEFT JOIN page ON page.page_id = revision.rev_page ';
        $values = [];

        /* fetch list of subcategories */
        $cats = [];
        $queue = [$title];
        while (count($queue)) {
            /* fetch subcategories of currently-known categories */
            $dbCatQuery = 'SELECT page_title FROM page JOIN categorylinks ON page_id=cl_from WHERE page_namespace=14 AND CONVERT(cl_to USING BINARY) IN (';
            $dbCatValues = [];
            while (count($queue)) {
                if (!in_array($queue[0], $cats)) {
                    $dbCatQuery .= 'CONVERT(? USING BINARY),';
                    $dbCatValues[] = str_replace(' ', '_', $queue[0]);
                    $cats[] = array_shift($queue);
                } else
                    array_shift($queue);
            }
            $dbCatQuery = rtrim($dbCatQuery, ',') . ')';

            /* queue subcategories */
            if (count($dbCatValues) == 0)
                continue;
            $subcats = $db->query($dbCatQuery, $dbCatValues)->fetchAllAssoc();
            foreach ($subcats as $subcat) {
                $queue[] = $subcat['page_title'];
            }
        }

        /* add to query */
        $sql .= 'JOIN categorylinks on page_id=cl_from WHERE CONVERT(cl_to USING BINARY) IN (';
        foreach ($cats as $cat) {
            $sql .= 'CONVERT(? USING BINARY),';
            $values[] = str_replace(' ', '_', $cat);
        }
        $sql = rtrim($sql, ', ') . ') ORDER BY revision.rev_timestamp';

        /* build query */
        return $db->query($sql, $values);
    }

    /**
     * Get whether the given username matches an anonymous user.
     * @param string $name The username.
     * @return boolean
     */
    public function isAnonymousUser($name)
    {
        $ip = new IPAddress($name);
        return $ip->isValid();
    }

    /**
     * Get metadata about matching revisions.
     * @param Database $db The connected database instance.
     * @param Database The revision query.
     * @return Metrics The revision metrics.
     */
    public function getEditMetrics($db, $revisionQuery) {
        $metrics = new Metrics();

        // get data
        while ($revision = $revisionQuery->fetchAssoc()) {
            // read row
            $row = [
                'namespace' => $revision['page_namespace'],
                'title' => $revision['page_title'],
                'user' => $revision['rev_user_text'],
                'timestamp' => $revision['rev_timestamp'],
                'isRedirect' => $revision['page_is_redirect'],
                'isMinor' => $revision['rev_minor_edit'],
                'pageid' => $revision['rev_page'],
                'size' => $revision['rev_len']
            ];
            $monthKey = preg_replace('/^(\d{4})(\d{2}).+$/', '$1-$2', $row['timestamp']);
            $isNew = !array_key_exists($row['pageid'], $metrics->pages);
            $username = $this->isAnonymousUser($row['user']) ? '(Anonymous)' : $row['user'];

            // init hashes if needed
            $month = $this->initArrayKey($metrics->months, $monthKey, function() { return new MonthMetrics(); });
            $user = $this->initArrayKey($metrics->users, $username, function() { return new UserData(); });
            $page = $this->initArrayKey($metrics->pages, $row['pageid'], function() { return new PageData(); });
            $monthUser = $this->initArrayKey($month->users, $username, function() { return new UserData(); });

            // update metrics
            $metrics->edits++;
            $month->name = $monthKey;
            $month->edits++;
            $month->bytesAdded += $row['size'] - $page->size;
            if ($isNew)
                $month->newPages++;

            // update user data
            $user->name = $username;
            $user->edits++;
            $monthUser->name = $username;
            $monthUser->edits++;

            // update page data
            $page->id = $row['pageid'];
            $page->namespace = $row['namespace'];
            if (!$page->name) {
                $page->name = str_replace('_', ' ', $row['title']);
                $namespaceName = $this->getNamespaceName($page->namespace);
                if($namespaceName)
                    $page->name = $namespaceName . ':' . $page->name;
            }
            $page->edits++;
            $page->size = $row['size'];
            $page->isRedirect = $row['isRedirect'];
        }
        unset($bytesAdded, $month, $monthKey, $prevSize, $query, $revision, $row);

        // collapse lookups into arrays & sort
        $metrics->months = array_values($metrics->months);
        $metrics->pages = array_values($metrics->pages);
        $metrics->users = array_values($metrics->users);

        usort($metrics->months, function($a, $b) { return strcmp($a->name, $b->name); });
        usort($metrics->pages, function($a, $b) { return strcmp($a->name, $b->name); });
        usort($metrics->users, function($a, $b) { return strcmp($a->name, $b->name); });

        foreach($metrics->months as &$month) {
            $month->users = array_values($month->users);
            usort($month->users, function($a, $b) { return strcmp($a->name, $b->name); });
        }

        return $metrics;
    }

    /**
     * Fetch bot flags and update the given metrics.
     * @param Database $db The connected database instance.
     * @param Metrics $metrics The revision metrics.
     * @return Metrics The revision metrics.
     */
    public function flagBots($db, &$metrics)
    {
        // get list of usernames
        $users = array_map(function($user) { return $user->name; }, $metrics->users);

        // get flags
        $bots = [];
        $query = $db->query('SELECT user_name FROM user INNER JOIN user_groups ON user_id = ug_user WHERE user_name IN (' . rtrim(str_repeat('?,', count($users)), ',') . ') AND ug_group = "bot"', $users);
        while ($user = $query->fetchValue())
            $bots[$user] = true;
        unset($user, $query);

        // flag bots in overall metrics
        foreach ($metrics->users as &$user)
            $user->isBot = array_key_exists($user->name, $bots) ? 1 : 0;
        unset($user);

        // flag bots in monthly metrics
        foreach ($metrics->months as &$month)
        {
            foreach ($month->users as &$user)
                $user->isBot = array_key_exists($user->name, $bots) ? 1 : 0;
        }
        unset($month, $user);

        // count non-bot edits
        foreach ($metrics->months as &$month) {
            foreach ($month->users as &$user) {
                if (!$user->isBot) {
                    $metrics->editsExcludingBots += $user->edits;
                    $month->editsExcludingBots += $user->edits;
                }
            }
        }
        unset($month, $user);

        return $metrics;
    }

    ##########
    ## Private methods
    ##########
    /**
     * Initialise an array key with the given default value if it isn't defined yet.
     * @param mixed[] $array The array whose key to set.
     * @param mixed $key The array key to set.
     * @param mixed $default The default value to set if the key isn't defined.
     * @return mixed Returns the value at that key.
     */
    private function initArrayKey(&$array, $key, $default)
    {
        if (!array_key_exists($key, $array)) {
            if (is_callable($default))
                $array[$key] = $default();
            else
                $array[$key] = $default;
        }
        return $array[$key];
    }
}

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
    $engine = new Engine();

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
            if ($user->edits < $maxEditsForInactivity || $user->isBot)
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
                if ($user->edits >= $maxEditsForInactivity && !$user->isBot)
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
                $isActive = $user->edits > $maxEditsForInactivity && !$user->isBot;
                echo $engine->getBarHtml($engine->getLinkHtml($url, 'user:' . $user->name, $user->name), $user->edits, 10, !$isActive);
            }
            unset($users);

            echo '</table>';
        }
    }
    $backend->profiler->stop('generate output');
} while (0);

$backend->footer();

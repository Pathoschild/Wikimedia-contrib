<?php
declare(strict_types=1);

require_once('../backend/modules/Backend.php');
$backend = Backend::create('User pages', 'Find your user pages on all Wikimedia wikis.')
    ->link('/userpages/scripts.js')
    ->link('/userpages/stylesheet.css')
    ->link('/content/undefer.js')
    ->header();

##########
## Get input
##########
$user = $backend->getString('user', allowBlank: false) ?? $backend->getRouteValue();
if ($user)
    $user = $backend->formatUsername($user);
$showAll = $backend->getBool('all') ?? false;
$showDetached = $backend->getBool('show_detached') ?? false;
$deferRun = !empty($user) && $backend->isDeferRequested();


##########
## Render form
##########
echo "
    <form action='{$backend->url('/userpages')}' method='get'>
        <label for='user'>User name:</label>
        <input type='text' name='user' id='user' value='{$backend->formatValue($user)}' />", ($user == 'Shanel' ? '&hearts;' : ''), "<br />

        <input type='checkbox' id='show_detached' name='show_detached' ", ($showDetached ? 'checked="checked" ' : ''), "/>
        <label for='show_detached'>Include wikis not connected to the global account (slow)</label><br />

        <input type='checkbox' id='all' name='all' ", ($showAll ? 'checked="checked" ' : ''), "/>
        <label for='all'>Show 'no pages here' entries</label><br />

        <input type='submit' value='Analyze »' />
    </form>
    ";

if ($deferRun) {
    echo "
        <div class='result-box'>
            {$backend->getDeferredHtml("Analyze »")}
        </div>
    ";
}
else if ($user) {
    echo "
        <div class='result-box'>
            See also
            <a href='", $backend->url('/stalktoy/' . urlencode($user)), "?defer=1' title='Global account details' data-undefer>global account details</a>, 
            <a href='", $backend->url('/crossactivity/' . urlencode($user)), "?defer=1' title='Crosswiki activity' data-undefer>recent activity</a>,
            <a href='https://meta.wikimedia.org/?title=Special:CentralAuth/", urlencode($user), "' title='Special:CentralAuth'>Special:CentralAuth</a>.
            <hr />
            Filters: page is
            <a href='#' class='selected filter' data-filter-key='misc' data-filters='.type-misc'>text</a>  
            <a href='#' class='selected filter' data-filter-key='css' data-filters='.type-css'>CSS</a> 
            <a href='#' class='selected filter' data-filter-key='js' data-filters='.type-js'>JS</a> 
            | namespace is 
            <a href='#' class='selected filter' data-filter-key='user' data-filters='[data-ns=\"2\"]'>user</a> 
            <a href='#' class='selected filter' data-filter-key='talk' data-filters='[data-ns=\"3\"]'>talk</a> 
            | include 
            <a href='#' class='selected filter' data-filter-key='top-pages' data-filters='[data-is-subpage=\"0\"]'>top pages</a> 
            <a href='#' class='selected filter' data-filter-key='subpages' data-filters='[data-is-subpage=\"1\"]'>subpages</a>
        ";
}

##########
## Process data
##########
do {
    if (!$user || $deferRun)
        break;

    ##########
    ## Get wikis to check
    ##########
    $db = $backend->getDatabase();
    if (!$db->connect('metawiki')) { // prints error on failure
        echo '
                <div class="error">Could not fetch the global account.</div>
            </div>
        ';
        break;
    }

    $wikis = $db->getWikis();
    $searchWikis = $wikis;

    if (!$showDetached) {
        $unifiedWikis = $db->getUnifiedWikis($user); // prints errors on failure
        if ($unifiedWikis === null) {
            echo '
                    <div class="error">Could not fetch the global account\'s unified wikis.</div>
                </div>
            ';
            break;
        }

        $searchWikis = array_intersect_key($wikis, array_flip($unifiedWikis));
        if (!$searchWikis) {
            echo "
                    <div class='neutral'>
                        There is no global account with this name, or it has been <a href='https://meta.wikimedia.org/wiki/Oversight' title='about hiding user names'>globally hidden</a>.<br />
                        You can <a href='{$backend->url('/userpages/' . urlencode($user))}?show_detached=1&amp;defer=1' data-undefer>search all wikis</a> if needed.
                    </div>
                </div>
            ";
            break;
        }
    }


    ##########
    ## Fetch pages
    ##########
    $sqlUser = str_replace(' ', '_', $user);
    $result = $db->queryManyWikis(
        array_keys($searchWikis),
        '
            SELECT
                {dbname} AS wiki,
                page_namespace,
                page_title,
                page_is_redirect,
                page_touched,
                page_len
            FROM {db}.page
            WHERE
                page_namespace IN (2, 3)
                AND (page_title = ? OR page_title LIKE CONCAT(?, "/%"))
        ',
        [$sqlUser, $sqlUser]
    );

    $pagesByWiki = [];
    foreach ($result->rows as $row)
        $pagesByWiki[$row['wiki']][] = $row;


    ##########
    ## Output data
    ##########
    if ($result->failedWikis) {
        $count = count($result->failedWikis);
        if ($count <= 10) {
            $domains = [];
            foreach ($result->failedWikis as $dbname)
                $domains[] = $backend->formatValue($searchWikis[$dbname]->domain ?? $dbname);
            $detail = implode(', ', $domains);
        }
        else
            $detail = "$count of " . count($searchWikis);

        echo "<div class='error'><strong>Warning:</strong> couldn't query some wikis ($detail), so the results below may be incomplete.</div>\n";
    }

    $any = false;
    foreach ($searchWikis as $dbname => $wiki) {
        /* get data */
        $domain = $wiki->domain;
        $pages = $pagesByWiki[$dbname] ?? [];
        if (!$pages && !$showAll)
            continue;

        /* show pages */
        echo "<h2>$domain</h2>";
        if (!$pages) {
            echo '<em>no pages here</em>';
            continue;
        }
        $any = true;

        echo '<ul class="page-list">';
        foreach ($pages as $page) {
            // metadata
            $namespaceNumber = $page['page_namespace'];
            $namespaceName = $namespaceNumber == 3 ? 'User talk' : 'User';
            $title = $namespaceName . ':' . $page['page_title'];
            $size = $page['page_len'];
            $isRedirect = $page['page_is_redirect'];
            $touched = new DateTime($page['page_touched']);
            $touched = $touched->format('Y-m-d');
            $isSubpage = strpos($title, '/') ? '1' : '0';

            // filter type
            $type = 'misc';
            if (substr($title, -3) == '.js')
                $type = 'js';
            elseif (substr($title, -4) == '.css')
                $type = 'css';

            // output
            echo "<li class='type-$type' data-redirect='$isRedirect' data-is-subpage='$isSubpage' data-type='$type' data-size='$size' data-ns='$namespaceNumber' data-title='", $backend->formatValue($page['page_title']), "'><a href='https://$domain/wiki/", $backend->formatWikiUrlTitle($title), "'>", $backend->formatValue($title), "</a> <small>(<span class='page-size'>$size bytes</span>, <span class='page-edited'>last <a href='https://www.mediawiki.org/wiki/Manual:Page_table#page_touched'>touched</a> $touched</span>)</small></li>";
        }
        echo '</ul>';
    }

    if (!$any)
        echo '<p><em>No user pages found.</em></p>';

    if (!$showDetached)
        echo "<p><small>Only wikis connected to the global account were searched. You can <a href='{$backend->url('/userpages/' . urlencode($user))}?show_detached=1&amp;defer=1' data-undefer>search all wikis</a> if needed.</small></p>";

    echo '</div>';
} while (0);

$backend->footer();
<?php
declare(strict_types=1);

require_once('../backend/modules/Backend.php');
require_once('../backend/modules/Form.php');
require_once('framework/GUserSearchEngine.php');
$backend = Backend::create('gUser search', 'Provides searching and filtering of global users on Wikimedia wikis.')
    ->link('/gusersearch/stylesheet.css')
    ->link('/gusersearch/javascript.js')
    ->link('/content/undefer.js')
    ->header();

#############################
## Instantiate script engine
#############################
$engine = new GUserSearchEngine($backend);
$backend->profiler->start('initialize');

/* get arguments */
$name = $backend->getString('name') ?? $backend->getRouteValue();
$useRegex = $backend->getBool('regex') ?? false;
$showLocked = $backend->getBool('show_locked') ?? false;
$caseInsensitive = $backend->getBool('icase') ?? false;
$deferRun = $backend->isDeferRequested();

/* add user name filter */
if ($name != null) {
    $engine->name = $name;
    $operator = ($useRegex ? GUserSearchEngine::OP_REGEXP : GUserSearchEngine::OP_LIKE);

    $searchField = 'gu_name';
    $searchValue = $name;
    if ($caseInsensitive) {
        $searchField = "UPPER(CONVERT($searchField USING utf8))";
        $searchValue = strtoupper($searchValue);
    }

    $engine->filter(GUserSearchEngine::T_GLOBALUSER, $searchField, $operator, $searchValue);
    $engine->describeFilter("username {$operator} {$name}");
}

/* add lock status filter */
if (!$showLocked) {
    $engine->filter(GUserSearchEngine::T_GLOBALUSER, 'gu_locked', GUserSearchEngine::OP_NOT_EQUAL, '1');
    $engine->describeFilter("NOT locked");
}

/* set limit */
$limit = $backend->getInt('limit');
if ($limit)
    $engine->setLimit($limit);
$limit = $engine->limit;

/* set offset */
$offset = $backend->getInt('offset');
if ($offset)
    $engine->setOffset($offset);
$offset = $engine->offset;

$engine->useRegex = $useRegex;
$engine->showLocked = $showLocked;
$engine->caseInsensitive = $caseInsensitive;

#############################
## Input form
#############################
$formUser = $backend->formatValue($name ?? '');

echo "
    <form action='{$backend->url('/gusersearch')}' method='get'>
        <input type='text' name='name' value='{$formUser}' />
        ", ($limit != GUserSearchEngine::DEFAULT_LIMIT ? Form::hidden('limit', $limit) : ""), "

        <input type='submit' value='Search »' /><br />
        <div style='padding-left:0.5em; border:1px solid gray; color:gray;'>
            ", Form::checkbox('show_locked', $showLocked), "
            <label for='show_locked'>Show locked accounts</label><br />

            ", Form::checkbox('regex', $useRegex, ['onClick' => 'script.toggleRegex(this.checked);']), "
            <label for='regex'>Use <a href='http://www.wellho.net/regex/mysql.html' title='MySQL regex reference'>regular expression</a> (much slower)</label><br />

            ", Form::checkbox('icase', $caseInsensitive), "
            <label for='icase'>Match any capitalization (much slower)</label><br />

            <p>
                <b>Search syntax:</b>
                <span id='tips-regex'", ($useRegex ? "" : " style='display:none;'"), ">
                    Regular expressions are much slower, but much more powerful. You will need to escape special characters like [.*^$]. See the <a href='http://www.wellho.net/regex/mysql.html' title='MySQL regex reference'>MySQL regex reference</a>.
                </span>
                <span id='tips-like'", ($useRegex ? " style='display:none;'" : ""), ">
                    Add % to your search string for multicharacter wildcards, and _ for a single-character wildcard. For example, '%Joe%' finds every username containing the word 'Joe').
                </span>
            </p>
            <p>Beware: search is <strong><em>much slower</em></strong> if the user name starts with a wildcard!</p>
        </div>
    </form>
    ";
$backend->profiler->stop('initialize');


#############################
## Perform search
#############################
$backend->profiler->start('query');

$count = 0;
if (!$deferRun) {
    $engine->query();
    $count = $engine->db->countRows();
}
$hasResults = (int)!$count;

$backend->profiler->stop('query');


#############################
## Show results
#############################
$backend->profiler->start('output');

##########
## Header + summary
##########
echo "
    <h2>Search results</h2>
    <p id='search-summary' class='search-results-", ($deferRun ? 'deferred' : $hasResults), "'>{$backend->formatText($engine->getFormattedSummary(!$deferRun))}.</p>
";

##########
## Deferred confirm prompt
##########
if ($deferRun) {
    if ($offset > 0) {
        // form lets user either preserve pagination on submit, or submit main form to start a new search
        echo "
            <div class='neutral' data-is-deferred='1'>
                <form action='{$backend->url('/gusersearch')}' method='get'>
                    Click <em>Submit</em> below to see the results.

                    ", ($name ?? '') !== '' ? Form::hidden('name', $backend->formatValue($name), ['id' => 'defer-name']) : '', "
                    ", $showLocked ? Form::hidden('show_locked', $showLocked, ['id' => 'defer-show_locked']) : '', "
                    ", $useRegex ? Form::hidden('regex', $useRegex, ['id' => 'defer-regex']) : '', "
                    ", $caseInsensitive ? Form::hidden('icase', $caseInsensitive, ['id' => 'defer-icase']) : '', "
                    ", ($limit != GUserSearchEngine::DEFAULT_LIMIT ? Form::hidden('limit', $limit, ['id' => 'defer-limit']) : ''), "
                    ", Form::hidden('offset', $offset, ['id' => 'defer-offset']), "

                    <input type='submit' value='Submit' />
                </form>
            </div>
        ";
    }
    else
        echo $backend->getDeferredHtml("Search »");
}

##########
## Results table
##########
if (!$deferRun) {
    if ($count) {
        /* pagination */
        echo "[",
        ($offset > 0 ? $engine->getPaginationLinkHtml($limit, $offset - $limit, "&larr;newer {$limit}") : "&larr;newer {$limit}"),
        " | ",
        ($engine->db->countRows() >= $limit ? $engine->getPaginationLinkHtml($limit, $offset + $limit, "older {$limit}&rarr;") : "older {$limit}&rarr;"),
        "] [show {$engine->getPaginationLinkHtml(50, $offset, 50)}, {$engine->getPaginationLinkHtml(250, $offset, 250)}, {$engine->getPaginationLinkHtml(500, $offset, 500)}]";

        /* table */
        echo "
            <table class='pretty' id='search-results'>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Unification date</th>
                    <th>Status</th>
                    <th>Global groups</th>
                    <th>Links</th>
                </tr>
            ";

        while ($row = $engine->db->fetchAssoc()) {
            /* get values */
            $inGroups = ($row['gu_groups'] ? '1' : '0');
            $isLocked = (int)$row['gu_locked'];
            $isOkay = !$isLocked ? 1 : 0;
            $linkTarget = urlencode($row['gu_name']);

            /* summarize status */
            $statusLabel = "";
            $statuses = [];
            if ($isLocked)
                array_push($statuses, 'locked');

            if (count($statuses) > 0)
                $statusLabel = implode(' | ', $statuses);

            /* output */
            echo "
                <tr class='user-okay-{$isOkay} user-locked-{$isLocked} user-in-groups-{$inGroups}'>
                    <td class='id'>{$backend->formatText($row['gu_id'])}</td>
                    <td class='name'><a href='" . $backend->url('/stalktoy/' . $linkTarget) . "?defer=1' title='about user' data-undefer>{$backend->formatText($row['gu_name'])}</a></td>
                    <td class='registration'>{$backend->formatText($row['gu_registration'])}</td>
                    <td class='status'>{$backend->formatText($statusLabel)}</td>
                    <td class='groups'>{$backend->formatText($row['gu_groups'])}</td>
                    <td class='linkies'><a href='https://meta.wikimedia.org/wiki/Special:CentralAuth?target={$linkTarget}' title='CentralAuth'>CentralAuth</a></td>
                </tr>";
        }
        echo "</table>";
    }

    if ($name && (($useRegex && !preg_match('/[+*.]/', $name)) || (!$useRegex && !preg_match('/[_%]/', $name))))
        echo "<p><strong><big>※</big></strong>You searched for an exact match; did you want partial matches? See <em>Search syntax</em> above.</p>";
}

##########
## Footer
##########
$backend->profiler->stop('output');
$backend->footer();

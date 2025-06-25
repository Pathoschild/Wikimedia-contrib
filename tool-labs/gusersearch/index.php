<?php
declare(strict_types=1);

require_once('../backend/modules/Backend.php');
require_once('../backend/modules/Form.php');
require_once('framework/GUserSearchEngine.php');
$backend = Backend::create('gUser search', 'Provides searching and filtering of global users on Wikimedia wikis.')
    ->link('/gusersearch/stylesheet.css')
    ->link('/gusersearch/javascript.js')
    ->header();

#############################
## Instantiate script engine
#############################
$engine = new GUserSearchEngine($backend);
$engine->minDate = $backend->get('date');
$backend->profiler->start('initialize');

/* get arguments */
$name = $backend->get('name', $backend->getRouteValue());
$useRegex = (bool)$backend->get('regex');
$showLocked = (bool)$backend->get('show_locked');
$caseInsensitive = (bool)$backend->get('icase');

/* add user name filter */
if ($name != null) {
    $engine->name = $name;
    $operator = ($useRegex ? GUserSearchEngine::OP_REGEXP : GUserSearchEngine::OP_LIKE);

    if ($caseInsensitive) {
        $engine->filter(GUserSearchEngine::T_GLOBALUSER, 'UPPER(CONVERT(gu_name USING utf8))', $operator, strtoupper($name));
        $engine->filter(GUserSearchEngine::T_LOCALWIKIS, 'UPPER(CONVERT(lu_name USING utf8))', $operator, strtoupper($name));
        $engine->describeFilter("username {$operator} {$name}");
    } else {
        $engine->filter(GUserSearchEngine::T_GLOBALUSER, 'gu_name', $operator, $name);
        $engine->filter(GUserSearchEngine::T_LOCALWIKIS, 'lu_name', $operator, $name);
        $engine->describeFilter("username {$operator} {$name}");
    }
}

/* add lock status filter */
if (!$showLocked) {
    $engine->filter(GUserSearchEngine::T_GLOBALUSER, 'gu_locked', GUserSearchEngine::OP_NOT_EQUAL, '1');
    $engine->describeFilter("NOT locked");
}

/* add date filter */
if ($engine->minDate) {
    $engine->describeFilter("registered after {$engine->minDate}");
}

/* set limit */
if ($x = $backend->get('limit'))
    $engine->setLimit(intval($x));
$limit = $engine->limit;

/* set offset */
if ($x = $backend->get('offset'))
    $engine->setOffset(intval($x));
$offset = $engine->offset;

$engine->useRegex = $useRegex;
$engine->showLocked = $showLocked;
$engine->caseInsensitive = $caseInsensitive;

#############################
## Input form
#############################
$formUser = $backend->formatValue(isset($name) ? $name : '');

echo "
    <form action='{$backend->url('/gusersearch')}' method='get'>
        <input type='text' name='name' value='{$formUser}' />
        ", (($limit != GUserSearchEngine::DEFAULT_LIMIT) ? "<input type='hidden' name='limit' value='{$limit}' />" : ""), "

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


#############################
## Perform search
#############################
$backend->profiler->stop('initialize');
$engine->query();
$backend->profiler->start('output');
$count = $engine->db->countRows();
$hasResults = (int)!$count;

echo "
    <h2>Search results</h2>
    <p id='search-summary' class='search-results-{$hasResults}'>{$backend->formatText($engine->getFormattedSummary())}.</p>
    ";

#############################
## Output
#############################
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
                <td class='name'><a href='" . $backend->url('/stalktoy/' . $linkTarget) . "' title='about user'>{$backend->formatText($row['gu_name'])}</a></td>
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

$backend->profiler->stop('output');
$backend->footer();

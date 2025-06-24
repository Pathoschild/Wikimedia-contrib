<?php
declare(strict_types=1);

require_once("../backend/modules/Backend.php");
require_once("../backend/models/LocalUser.php");
spl_autoload_register(function ($className) {
    foreach (["framework/$className.php", "framework/constants/$className.php", "framework/models/$className.php", "framework/rules/$className.php"] as $path) {
        if (file_exists($path))
            include($path);
    }
});

$backend = Backend::create('AccountEligibility', 'Analyzes a given user account to determine whether it\'s eligible to vote in the specified event.')
    ->link('/accounteligibility/stylesheet.css')
    ->link('/content/jquery.tablesorter.js')
    ->addScript('$(document).ready(function() { $("#local-accounts").tablesorter({sortList:[[1,1]]}); });')
    ->header();

############################
## Initialize
############################
$event = $backend->get('event') ?: $backend->getRouteValue();
$user = $backend->get('user') ?: $backend->getRouteValue(1) ?: '';
$wiki = $backend->get('wiki', null);
$backend->profiler->start('init engine');
$engine = new AccountEligibilityEngine($backend, $user, $event, $wiki);
$backend->profiler->stop('init engine');

############################
## Input form
############################
echo '
<form action="', $backend->url('/accounteligibility'), '" method="get">
    <label for="user">User:</label>
    <input type="text" name="user" id="user" value="', $backend->formatValue($engine->username), '" /> at 
    <select name="wiki" id="wiki">
        <option value="">auto-select wiki</option>', "\n";

foreach ($engine->db->getDomains() as $dbname => $domain) {
    if (!$engine->db->getLocked($dbname)) {
        $selected = ($dbname == $wiki);
        echo "<option value='$dbname' ", ($selected ? " selected" : ""), ">{$engine->formatText($domain)}</option>";
    }
}
echo '
    </select>
    <br />
    <label for="event">Event:</label>
    <select name="event" id="event">', "\n";

foreach ($engine->events as $id => $event)
    echo "<option value='{$id}'", ($id == $engine->event->id ? " selected " : ""), ($event->obsolete ? " class='is-obsolete'" : ""), ">{$event->year} &mdash; {$engine->formatText($event->name)}</option>";

echo "
        </select>
        <br />
        <input type='submit' value='Analyze »' />
    </form>
    ";


############################
## Timestamp constants
############################
$oneYear = 10000000000;
$oneMonth = 100000000;


############################
## Check requirements
############################
if ($engine->username)
{
    echo '<div class="result-box">';

    do {
        /* validate event */
        if (!$engine->event) {
            echo '<div class="error">There is no event matching the given ID.</div>';
            break;
        }

        /* print header */
        echo '<h3>Analysis', ($engine->username == 'Shanel' ? '♥' : ''), ' </h3>';

        /* validate selected wiki */
        if ($engine->wiki && !$engine->event->allowsDatabase($engine->wiki->dbName)) {
            $allowedDomains = array_map(fn($dbName) => $engine->wikis[$dbName]->domain, $engine->event->onlyDatabaseNames);

            echo
                '<div class="error">Account must be on ',
                count($allowedDomains) > 1 ? 'one of ' : '',
                implode(', ', $allowedDomains),
                '. Choose "auto-select wiki" above to select the correct wiki.</div>';
            break;
        }

        /* initialize wiki queue */
        $engine->profiler->start('init wiki queue');
        echo '<details open><summary>';
        if (!$engine->initWikiQueue($engine->event->onlyDatabaseNames, $engine->event->minEditsForAutoselect)) {
            if (!$engine->selectManually)
                $engine->msg('Selection failed, aborted.');
            break;
        }
        echo '</summary>';
        $engine->profiler->stop('init wiki queue');

        /* validate user exists */
        if (!$engine->user || !$engine->user->id) {
            echo '<div class="error">', $engine->formatText($engine->username), ' does not exist on ', $engine->formatText($engine->wiki->domain), '.</div>';
            break;
        }

        /* verify eligibility rules */
        $engine->profiler->start('verify requirements');
        $rules = new RuleManager($engine->event->rules);

        $engine->printWiki();
        do {
            foreach ($rules->accumulate($engine->db, $engine->wiki, $engine->user) as $result) {
                // print result
                switch ($result->result) {
                    case Result::FAIL:
                        $engine->msg("• {$result ->message}", "is-fail");
                        break;

                    case Result::ACCUMULATING:
                        $engine->msg("• {$result->message}", "is-warn");
                        break;

                    case Result::PASS:
                    case Result::SOFT_PASS:
                        $engine->msg("• {$result->message}", "is-pass");
                        break;

                    default:
                        throw new InvalidArgumentException("Unknown rule eligibility result '{$result->result}'");
                }

                // print warnings
                if ($result->warnings) {
                    foreach ($result->warnings as $warning)
                        $engine->msg("{$warning}", "is-subnote is-warn");
                }

                // print notes
                if ($result->notes) {
                    foreach ($result->notes as $note)
                        $engine->msg("{$note}", "is-subnote");
                }
            }
        } while (!$rules->final && $engine->getNext());
        $engine->eligible = $rules->result == Result::PASS || $rules->result == Result::SOFT_PASS;
        $engine->profiler->stop('verify requirements');
        echo '</details>';


        ############################
        ## Print result
        ############################
        if ($engine->event) {
            ########
            ## Script results
            ########
            $event = $engine->event;
            $action = isset($event->action) ? $event->action : 'vote';
            $class = $engine->eligible ? 'success' : 'fail';
            $name = $engine->username . ($engine->unified ? '' : '@' . $engine->wiki->domain);

            echo
                '<h3>Result</h3>',
                '<div class="', $class, '" id="result" data-is-eligible="', ($engine->eligible ? 1 : 0), '">',
                $engine->formatText($name), ' is ', ($engine->eligible ? '' : 'not '), 'eligible to ', $action, ' in the <a href="', $event->url, '" title="', $backend->formatValue($event->name), '">', $event->name, '</a> in ', $event->year, '. ',
                '</div>';

            ########
            ## Mention additional requirements
            ########
            if ($engine->eligible && !empty($engine->event->extraRequirements)) {
                echo '<div class="error" style="border-color:#CC0;"><strong>There are additional requirements</strong> that can\'t be checked by this script:<ul style="margin:0;">';
                foreach ($engine->event->extraRequirements as $req)
                    echo '<li>', $req, '</li>';
                echo '</ul></div>';
            } elseif (!$engine->eligible) {
                if (!empty($engine->event->exceptions)) {
                    echo '<div class="neutral"><strong>This account might still be eligible if it meets criteria which can\'t be checked by this script:</strong><ul style="margin:0;">';
                    foreach ($engine->event->exceptions as $exc)
                        echo '<li>', $exc, '</li>';
                    echo '</ul></div>';
                }
            }

            ########
            ## Add links for manual verification
            ########
            echo '<small>See also: <a href="', $backend->url('/stalktoy/' . urlencode($engine->username)), '" title="global account details">global account details</a></small>.';
        }

        /* exit loop */
        break;
    }
    while ($engine->user);

    echo '</div>';
}

/* globals, templates */
$backend->footer();

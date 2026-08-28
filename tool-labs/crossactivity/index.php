<?php
declare(strict_types=1);

require_once('../backend/modules/Backend.php');
require_once('framework/CrossactivityEngine.php');
$backend = Backend::create('CrossActivity', 'Measures a user\'s latest edit, bureaucrat, or sysop activity on all wikis.')
    ->link('/content/dataTables/jquery.dataTables.min.js')
    ->link('/content/dataTables/jquery.dataTables.plain.css')
    ->addScript('
        $(function() {
            $("#activity-table").dataTable({
                "bPaginate": false,
                "bAutoWidth": false,
                "aaSorting": [[2, "desc"]]
            });
        });
    ')
    ->header();

##########
## Get data
##########
$engine = new CrossactivityEngine();
$user = $backend->getString('user') ?? $backend->getRouteValue();
if ($user !== null)
    $user = $backend->formatUsername($user);
$showAll = $backend->getBool('show_all') ?? false;
$deferRun = !empty($user) && $backend->isDeferRequested();


##########
## Input form
##########
echo "
    <form action='{$backend->url('/crossactivity')}' method='get'>
        <label for='user'>User name:</label>
        <input type='text' name='user' id='user' value='{$backend->formatValue($user)}' />", ($user == 'Shanel' ? '&hearts;' : ''), "<br />
        <input type='checkbox' id='show_all' name='show_all'", ($showAll ? 'checked="checked" ' : ''), "/> <label for='show_all'>Show wikis with no activity</label><br />
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
else if (!empty($user)) {
    echo "
        <div class='result-box'>
            See also
            <a href='{$backend->url('/stalktoy/' . urlencode($user))}?defer=1' title='Global account details'>global account details</a>, 
            <a href='{$backend->url('/userpages/' . urlencode($user))}?defer=1' title='User pages'>user pages</a>,
            <a href='https://meta.wikimedia.org/?title=Special:CentralAuth/", urlencode($user), "' title='Special:CentralAuth'>Special:CentralAuth</a>.
        ";
}

##########
## Fetch & process data
##########
do {
    if (!$user || $deferRun)
        break;

    ##########
    ## Fetch wikis
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
        echo '
                <div class="neutral">There is no global account with this name, or it has been <a href="https://meta.wikimedia.org/wiki/Oversight" title="about hiding user names">globally hidden</a>.</div>
            </div>
        ';
        break;
    }

    ##########
    ## Fetch results & render
    ##########
    echo '<table class="pretty sortable" id="activity-table">
        <thead>
            <tr>
                <th>family</th>
                <th>wiki</th>
                <th>last edit</th>
                <th>last log action</th>
                <th>Local groups</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($searchWikis as $dbname => $wiki) {
        $domain = $wiki->domain;
        $family = $wiki->family;

        /* get data */
        $db->connect($dbname);
        $actor = $db->query('SELECT actor_id, actor_user FROM actor WHERE actor_name = ? LIMIT 1', [$user])->fetchAssoc();
        if (isset($actor['actor_user'])) {
            $actorID = $actor['actor_id'];
            $userID = $actor['actor_user'];

            $groups = $db->query('SELECT GROUP_CONCAT(ug_group SEPARATOR ", ") FROM user_groups WHERE ug_user = ?', [$userID])->fetchValue();
            $lastEdit = $db->query('SELECT DATE_FORMAT(MAX(rev_timestamp), "%Y-%m-%d %H:%i") FROM revision_userindex WHERE rev_actor = ?', [$actorID])->fetchValue();
            $lastLogAction = $db->query('SELECT DATE_FORMAT(MAX(log_timestamp), "%Y-%m-%d %H:%i") FROM logging_userindex WHERE log_actor = ?', [$actorID])->fetchValue();

            if ($showAll || !empty($lastEdit) || !empty($lastLogAction)) {
                echo "
                    <tr>
                        <td>$family</td>
                        <td>", $engine->getLinkHtml($domain, 'User:' . $user), "</td>",
                        $engine->getColoredCellHtml($lastEdit),
                        $engine->getColoredCellHtml($lastLogAction),
                        $engine->getGroupCellHtml($groups), "
                    </tr>
                    ";
            }
        }
        $db->dispose();
    }

    echo "
                </tbody>
            </table>
            <small>Only wikis linked to the global account are checked. You can <a href='{$backend->url('/stalktoy/' . urlencode($user))}?show_detached=1&amp;defer=1'>check for detached wikis</a> if needed.</small>
        </div>
    ";
} while (0);

$backend->footer();

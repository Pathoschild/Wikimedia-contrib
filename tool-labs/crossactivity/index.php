<?php
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
$user = $backend->get('user', $backend->getRouteValue());
if ($user != null)
    $user = $backend->formatUsername($user);
$userForm = $backend->formatValue($user);
$showAll = $backend->get('show_all', false);


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

if (!empty($user)) {
    echo "
        <div class='result-box'>
            See also
            <a href='{$backend->url('/stalktoy/' . urlencode($user))}' title='Global account details'>global account details</a>, 
            <a href='{$backend->url('/userpages/' . urlencode($user))}' title='User pages'>user pages</a>,
            <a href='//meta.wikimedia.org/?title=Special:CentralAuth/", urlencode($user), "' title='Special:CentralAuth'>Special:CentralAuth</a>.
        ";
}

/***************
 * Get & process data
 ***************/
do {
    if (!$user)
        break;

    /***************
     * Get list of wikis
     ***************/
    $db = $backend->getDatabase();
    $db->connect('metawiki');
    $wikis = $db->getWikis();

    /***************
     * Get data and Output
     ***************/
    echo '<table class="pretty sortable" id="activity-table">
        <thead>
            <tr>
                <th>family</th>
                <th>wiki</th>
                <th>last edit</th>
                <th>last log <small>(bureaucrat)</small></th>
                <th>last log <small>(sysop)</small></th>
                <th>Local groups</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($wikis as $wiki) {
        $dbname = $wiki->dbName;
        $domain = $wiki->domain;
        $family = $wiki->family;

        /* get data */
        $db->connect($dbname);
        $id = $db->query('SELECT user_id FROM user WHERE user_name=? LIMIT 1', [$user])->fetchValue();

        if ($id) {
            // groups
            $groups = $db->query('SELECT GROUP_CONCAT(ug_group SEPARATOR ", ") FROM user_groups WHERE ug_user=?', [$id])->fetchValue();

            // edits
            $lastEdit = $db->query('SELECT DATE_FORMAT(rev_timestamp, "%Y-%m-%d %H:%i") FROM revision_userindex WHERE rev_user=? ORDER BY rev_timestamp DESC LIMIT 1', [$id])->fetchValue();

            // log actions
            $lastBureaucratLog = $db->query('SELECT DATE_FORMAT(log_timestamp, "%Y-%m-%d %H:%i") FROM logging_userindex WHERE log_user=? AND log_type IN ("makebot", "renameuser", "rights") ORDER BY log_timestamp DESC LIMIT 1', [$id])->fetchValue();
            $lastAdminLog = $db->query('SELECT DATE_FORMAT(log_timestamp, "%Y-%m-%d %H:%i") FROM logging_userindex WHERE log_user=? AND log_type IN ("block", "delete", "protect") ORDER BY log_timestamp DESC LIMIT 1', [$id])->fetchValue();

            // output
            if ($showAll || !empty($lastEdit) || !empty($lastBureaucratLog) || !empty($lastAdminLog)) {
                echo "
                    <tr>
                        <td>$family</td>
                        <td>", $engine->getLinkHtml($domain, 'User:' . $user), "</td>",
                        $engine->getColoredCellHtml($lastEdit),
                        $engine->getColoredCellHtml($lastBureaucratLog),
                        $engine->getColoredCellHtml($lastAdminLog),
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
        </div>
        ";
} while (0);

$backend->footer();

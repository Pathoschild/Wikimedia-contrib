<?php
require_once('../backend/modules/Backend.php');
$backend = Backend::create('CrossActivity', 'Measures a user\'s latest edit, bureaucrat, or sysop activity on all wikis.')
	->link( '../backend/scripts/jquery.js' )
	->link( '../content/dataTables/jquery.dataTables.min.js' )
	->link( '../content/dataTables/jquery.dataTables.plain.css' )
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

/***************
 * Get data
 ***************/
$user = $backend->get('user', $backend->getRouteValue());
if($user != null)
	$user = $backend->FormatUsername($user);
$user_form = $backend->FormatFormValue($user);
$show_all = $backend->get('show_all', false);


/***************
 * Input form
 ***************/
echo '<form action="" method="get">
	<label for="user">User name:</label>
	<input type="text" name="user" id="user" value="', $backend->FormatFormValue($user), '" />', ($user == 'Shanel' ? '&hearts;' : ''), '<br />
	<input type="checkbox" id="show_all" name="show_all" ', ($show_all ? 'checked="checked" ' : ''), '/> <label
	for="show_all">Show wikis with no activity</label><br />
	<input type="submit" value="Analyze »" />
</form>';

if (!empty($user)) {
	echo '<div class="result-box">';
	echo 'Related tools:
		<ul>
			<li><a href="//toolserver.org/~luxo/contributions/contributions.php?user=', urlencode($user) ,'" title="Crosswiki edits">Crosswiki edits</a></li>
			<li><a href="//toolserver.org/~pathoschild/stalktoy/', urlencode($user), '" title="Global account details">Global account details</a></li>
			<li><a href="//meta.wikimedia.org/?title=Special:CentralAuth/', urlencode($user), '" title="Special:CentralAuth">Special:CentralAuth (stewards-only)</a></li>
		</ul>';

	/***************
	 * Functions
	 ***************/
	/* colour cell */
	$date_good = date('Ymd', strtotime('-1 week'));
	$date_okay = date('Ymd', strtotime('-3 week'));

	function color_cell($date) {
		global $date_good, $date_okay;
		if (!$date) {
			$color = "CCC";
		}
		else {
			$dateValue = substr(str_replace('-', '', $date), 0, 8);
			if ($dateValue >= $date_good)
				$color = "CFC";
			else if ($dateValue >= $date_okay)
				$color = "FFC";
			else
				$color = "FCC";
		}

		return '<td style="background-color:#' . $color . ';">' . $date . '</td>';
	}

	function list_groups($groups) {
		if (count($groups) == 0)
			return '<td style="background-color:#CCC;">&nbsp;</td>';
		return '<td>' . $groups . '</td>';
	}

	/* link domain */
	$domain_link_part = '?title=User:' . urlencode($user) . '" title="' . htmlspecialchars($user) . '\'s user page">';
	function link_domain($domain) {
		global $domain_link_part;
		return '<a href="//' . $domain . $domain_link_part . $domain . '</a>';
	}
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
	$db = $backend->GetDatabase();
	$db->Connect('metawiki_p');

	$wikis = $db->Query('SELECT dbname,domain,family,is_closed FROM toolserver.wiki WHERE is_closed=0 AND dbname!="sep11wiki_p"')->fetchAllAssoc();

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

	foreach($wikis as $wiki) {
		$dbname = $wiki['dbname'];
		$domain = $wiki['domain'];
		$family = $wiki['family'];

		/* get data */
		$db->Connect($dbname);
		$id = $db->Query('SELECT user_id FROM user WHERE user_name=? LIMIT 1', array($user))->fetchValue();

		if ($id) {
			// groups
			$groups = $db->Query('SELECT GROUP_CONCAT(ug_group SEPARATOR ", ") FROM user_groups WHERE ug_user=?', array($id))->fetchValue();

			// edits
			$last_edit = $db->Query('SELECT DATE_FORMAT(rev_timestamp, "%Y-%m-%dT%H:%i:%s") FROM revision WHERE rev_user=? ORDER BY rev_timestamp DESC LIMIT 1', array($id))->fetchValue();

			// log actions
			$last_log_bur = $db->Query('SELECT DATE_FORMAT(log_timestamp, "%Y-%m-%dT%H:%i:%s") FROM logging_ts_alternative WHERE log_user=? AND log_type IN ("makebot", "renameuser", "rights") ORDER BY log_timestamp DESC LIMIT 1', array($id))->fetchValue();
			$last_log_sys = $db->Query('SELECT DATE_FORMAT(log_timestamp, "%Y-%m-%dT%H:%i:%s") FROM logging_ts_alternative WHERE log_user=? AND log_type IN ("block", "delete", "protect") ORDER BY log_timestamp DESC LIMIT 1', array($id))->fetchValue();

			// output
			if ($show_all || !empty($last_edit) || !empty($last_log_bur) || !empty($last_log_sys)) {
				echo '<tr>',
					'<td>', $family, '</td>',
					'<td>', link_domain($domain), '</td>',
					color_cell($last_edit),
					color_cell($last_log_bur),
					color_cell($last_log_sys),
					list_groups($groups),
					'</tr>';
			}
		}
		$db->dispose();
	}
	echo '</tbody></table>';
	echo '</div>';
}
while (0);

$backend->footer();
?>

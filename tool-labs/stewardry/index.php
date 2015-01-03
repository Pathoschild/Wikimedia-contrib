<?php
require_once( '../backend/modules/Backend.php' );
$backend = Backend::Create('Stewardry', 'Estimates which users in a group are available based on their last edit or action.')
	->link( '/content/jquery.tablesorter.js', true )
	->link( '/stewardry/scripts.js', true )
	->header();

/***************
* Engine
***************/
class Engine {
	/***************
	** Properties
	***************/
	/*
	 * @var array The predefined user groups which can be analyzed through this tool.
	 */
	public $presetGroups = array(
		'sysop' => array('abusefilter', 'block', 'delete', 'protect'),
		'bureaucrat' => array('makebot', 'renameuser', 'rights'),
		'checkuser' => array(),
		'oversight' => array(),
		'bot' => array()
	);

	/**
	 * @var string The input dbname to analyze.
	 */
	public $dbname = null;
	
	/**
	 * @var string The selected groups.
	 */
	public $groups = array();

	/**
	 * @var Database The database handler from which to query data.
	 */
	public $db = null;


	/***************
	** Methods
	***************/
	/**
	 * Construct an instance.
	 * @param Backend $backend The backend framework.
	 */
	public function __construct($backend) {
		// set values
		$this->db = $backend->GetDatabase();

		// parse query
		$this->dbname = $backend->get('wiki') ?: $backend->getRouteValue();
		$this->wiki = $backend->db->wikis->GetWiki($this->dbname);
		foreach($this->presetGroups as $group => $logTypes) {
			if($backend->get($group))
				$this->groups[$group] = $logTypes;
		}

		// set defaults
		if(!$this->groups)
			$this->groups = array('sysop' => $this->presetGroups['sysop']);
	}

	/**
	 * Generate a SQL query which returns activity metrics for the selected groups.
	 */
	function fetch_metrics() {
		$names = array_keys($this->groups);
		$rights = $this->groups;

		// build SQL fragments
		$outerSelects = array();
		$innerSelects = array();
		foreach($names as $group) {
			$outerSelects[] = "user_has_$group";
			if($rights[$group])
				$outerSelects[] = "CASE WHEN user_has_$group<>0 THEN (SELECT log_timestamp FROM logging_userindex WHERE log_user=user_id AND log_type IN ('" . implode("','", $rights[$group]) . "') ORDER BY log_id DESC LIMIT 1) END AS last_$group";
			$innerSelects[] = "COUNT(CASE WHEN ug_group='$group' THEN 1 END) AS user_has_$group";
		}

		// execute SQL
		$this->db->Connect($this->wiki->name);
		$sql = "SELECT * FROM (SELECT user_name,(SELECT rev_timestamp FROM revision_userindex WHERE rev_user=user_id ORDER BY rev_timestamp DESC LIMIT 1) AS last_edit," . implode(",", $outerSelects) . " FROM (SELECT user_id,user_name," . implode(",", $innerSelects) . " FROM user INNER JOIN user_groups ON user_id = ug_user AND ug_group IN('" . implode("','", $names) . "') GROUP BY ug_user) AS t_users) AS t_metrics ORDER BY last_edit DESC";
		return $this->db->Query($sql)->fetchAllAssoc();

	}
}

/***************
** Handle request
***************/
$engine = new Engine($backend);
$data = array();


/***************
* Input form
***************/
?>
<form action="<?=$backend->url('/stewardry')?>" method="get">
	<label for="wiki">Wiki:</label>
	<select name="wiki">
		<?php foreach($backend->db->getDomains() as $dbname => $domain) { ?>
			<option value="<?=$dbname?>" <?=($dbname == $engine->wiki->name ? ' selected="selected"' : '')?>><?=$domain?></option>
		<?php } ?>
	</select><br />

	Groups to display (uncheck some for faster results):<br />
	<div style="margin-left:3em;">
		<?php foreach($engine->presetGroups as $group=>$rights) { ?>
			<input type="checkbox" id="<?=$group?>" name="<?=$group?>" value="1"<?=(isset($engine->groups[$group]) ? ' checked="checked"' : '')?> /> <label for="<?=$group?>"><?=$group?></label><br />
		<?php } ?>
	</div>
	<input type="submit" value="Analyze" />
</form>
<?php
/***************
** Functions
***************/
/* outputs a color-coded table cell for the given date */
function color_cell($last_date) {
	$date = $last_date ? preg_replace('/^(\d{8}).+$/','$1',$last_date) : 'never';
	$color = ($date == 'never' ? 'FCC'
		: ($date > date('Ymd', strtotime('-1 week')) ? 'CFC'
		: ($date > date('Ymd', strtotime('-3 week')) ? 'FFC'
		: 'FCC')));
	return "<td style='background:#$color;'>$date</td>";
}

/***************
* Get & process data
***************/
do {
	/***************
	* Error-check
	***************/
	// form not filled
	if(!$engine->dbname || !count($engine->groups))
		break;

	// invalid input
	if(!$engine->wiki) {
		print '<div class="fail">There is no wiki matching the selected database.</div>';
		break;
	}
	
	// disallowed queries
	if($domain=='en.wikipedia.org' && $engine->groups['sysop'])
		die('<div class="fail">Sysop statistics are disabled for en.wikipedia.org because the result set is too large to process.</div>');
	if($domain=='en.wikipedia.org' && ($engine->groups['bureaucrat']+$engine->groups['checkuser']+$engine->groups['oversight']+$engine->groups['bot'])>1)
		die('<div class="fail">Only one group (except sysop) can be selected for en.wikipedia.org because the result set is too large to process.</div>');



	/***************
	* Get data
	***************/
	$backend->profiler->start('fetch data');
	$data = $engine->fetch_metrics();
	$backend->profiler->stop('fetch data');
	

	/***************
	* Output
	***************/
	$backend->profiler->start('analyze and output');
	// table of contents
	echo '<h2>Generated data</h2>',
		 '<div id="toc"><b>Table of contents</b><ol>';
	foreach($engine->groups as $group=>$v) {
		echo '<li><a href="#', $group, '_activity">', $group, ' activity</a></li>';
	}
	echo '</ol></div>';

	// sections
	foreach($engine->groups as $group=>$v) {
		// filter users
		$matching = array_filter($data, function($r) use($group) { return !!$r["user_has_$group"]; });

		// print header
		echo "<h2 id='${group}_activity'>${group}s</h2>";
		if(!$matching) {
			echo "<div class='neutral'>No active ${group}s on this wiki.</div>";
			continue;
		}

		// print table
		$show_log = !!$engine->groups[$group];
		echo "<table class='pretty sortable' id='${group}_metrics'><thead><tr><th>user</th><th>last edit</th>", ($show_log ? "<th>last log action</th>" : ""), "</tr></thead><tbody>";

		foreach($matching as $row) {
			$name = $row["user_name"];
			$urlName = $backend->formatValue($name);
			$last_edit = $row["last_edit"];
			$last_log = $row["last_$group"];
			$domain = $engine->wiki->domain;

			echo "<tr>",
				"<td><a href='//$domain/wiki/User:$name' title='$name&#39;s user page'>$name</a> <small>[<a href='", $backend->url('/crossactivity/' . $urlName), "' title='scan this user&#39;s activity on all wikis'>all wikis</a>]</small></td>",
				 color_cell($last_edit),
				 ($show_log ? color_cell($last_log) : ''),
			 "</tr>";
		}
		echo '</tbody></table>';
	}

	$backend->profiler->stop('analyze and output');
} while(0);

$backend->footer();
?>

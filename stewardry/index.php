<?php
require_once( '../backend/modules/Backend.php' );
$backend = Backend::Create('Stewardry', 'Estimates which users in a role are available based on their last edit or action.')
	->header();

/***************
* Engine
***************/
class Engine {
	/***************
	** Properties
	***************/
	/*
	 * @var array The user roles which can be analyzed through this tool.
	 */
	public $defaultRoles = array('sysop' => true, 'bureaucrat' => true, 'oversight' => false, 'bot' => false);

	/**
	 * @var array The log types associated with a role, to determine the last role action.
	 */
	public $logTypes = array(
		'sysop' => array('block', 'delete', 'protect'),
		'bureaucrat' => array('makebot', 'renameuser', 'rights'),
		'steward' => array('gblblock', 'gblrights', 'globalauth')
	);

	/**
	 * @var string The input dbname to analyze.
	 */
	public $input = null;

	/**
	 * @var array The roles to analyze.
	 */
	public $roles = array();

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
		$this->input = $backend->get('wiki');
		$this->wiki = $backend->db->wikis->GetWiki($this->input . '_p');
		foreach($this->defaultRoles as $role => $d)
			$this->roles[$role] = $backend->get($role);

		// set defaults
		if(!in_array(true, $this->roles)) {
			foreach($this->defaultRoles as $role => $value)
				$this->roles[$role] = $value;
		}
	}
}

/***************
** Handle request
***************/
$engine = new Engine($backend);
$wiki = $engine->wiki;
$data = array();


/***************
* Input form
***************/
?>
<form action="" method="get">
	<label for="wiki">Wiki:</label>
	<select name="wiki">
		<?php foreach($backend->db->getDomains() as $dbname => $domain) { ?>
			<?php $dbname = substr($dbname, 0, -2); ?>
			<option value="<?=$dbname?>" <?=($dbname == $wiki->name ? ' selected="selected"' : '')?>><?=$domain?></option>
		<?php } ?>
	</select><br />

	Groups to display (uncheck some for faster results):<br />
	<div style="margin-left:3em;">
		<?php foreach($engine->roles as $role => $on) { ?>
			<input type="checkbox" id="<?=$role?>" name="<?=$role?>"<?=($on ? ' checked="checked"' : '')?> /> <label for="<?=$role?>"><?=$role?></label><br />
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
	$color = $date > date('Ymd', strtotime('-1 week')) ? 'CFC'
		: $date > date('Ymd', strtotime('-3 week')) ? 'FFC'
		: 'FCC';
	return "<td style='background:#$color;'>$date</td>";
}

/* outputs the result of a metric lookup for matching users. */
function add_namedate_table($label, $query_string) {
	global $wiki, $backend;
	
	$results = $backend->db->Query($query_string)->fetchAllAssoc();
	echo '<table class="pretty" style="float:left;"><tr><th>user</th><th>', $label, '</th></tr>';

	foreach($results as $row) {
		$name = $row['p_name'];
		$date = $row['p_date'];
		$domain = $wiki->domain;
		$urlName = $backend->formatValue($name);
		
		echo "<tr>",
			"<td><a href='//$domain/wiki/User:$name' title='$name&#39;s user page'>$name</a> <small>[<a href='//toolserver.org/~pathoschild/crossactivity/?user=$urlName' title='scan this user&#39;s activity on all wikis'>all wikis</a>]</small></td>",
			 color_cell($date),
			 "</tr>";
	}
	echo '</table>';
}

/***************
* Get & process data
***************/
do {
	/***************
	* Error-check
	***************/
	// form not filled
	if(!$engine->input || !count($engine->roles))
		break;

	// invalid input
	if(!$wiki) {
		print '<div class="fail">There is no wiki matching the selected database.</div>';
		break;
	}


	/***************
	* Get data
	***************/
	$backend->profiler->start('create temporary table');
	$backend->db->Connect($wiki->dbName);
	
	// generate unique table name
	list($msec, $sec) = explode(' ', microtime());
	$table = $sec . str_replace('0.','', $msec);
	unset($msec, $sec);
		
	/* disallowed queries */
	if($domain=='en.wikipedia.org' && $show['sysop'])
		die('<div class="fail">Sysop statistics are disabled for en.wikipedia.org because the result set is too large to process.</div>');
	if($domain=='en.wikipedia.org' && ($show['bureaucrat']+$show['checkuser']+$show['oversight']+$show['bot'])>1)
		die('<div class="fail">Only one group (except sysop) can be selected for en.wikipedia.org because the result set is too large to process.</div>');

	/* prepare database */
	$backend->db->Query('CREATE DATABASE IF NOT EXISTS u_pathoschild');
	$backend->db->Query("CREATE TABLE u_pathoschild.$table (p_id int(5) UNSIGNED, p_name varchar(255), p_group varchar(255), p_last_edit varchar(14), p_last_sysop varchar(14), p_last_bureaucrat varchar(14))");
	$backend->profiler->stop('create temporary table');
	
	
	/***************
	* Get data
	***************/
	$backend->profiler->start('fetch data into temporary table');

	// get user IDs and groups, skip groups we're not looking for
	$query = "INSERT INTO u_pathoschild.$table (p_id, p_group) SELECT ug_user, ug_group FROM user_groups WHERE";
	$values = array();
	foreach($engine->roles as $group => $boolean) {
		if($boolean) {
			$query .= ' ug_group=? OR';
			$values[] = $group;
		}
	}
	$query = substr_replace($query, '', -3, 3) . ' ORDER BY ug_user';
	$backend->db->query($query, $values);
	unset($query);
	
	// get user names	
	$backend->db->Query("UPDATE u_pathoschild.$table SET p_name=(SELECT user_name FROM user WHERE user_id=u_pathoschild.${table}.p_id)");
	
	// get last edits
	$backend->db->Query("UPDATE u_pathoschild.$table SET p_last_edit=(SELECT MAX(rev_timestamp) FROM revision WHERE rev_user=p_id)");
	
	// get date of last sysop action
	if($show['sysop'])
		$backend->db->Query("UPDATE u_pathoschild.$table SET p_last_sysop=(SELECT MAX(log_timestamp) FROM logging WHERE log_user=u_pathoschild.$table.p_id AND log_type IN ('block','delete','protect'))");

	// get date of last bureaucrat action
	if($show['bureaucrat'])
		$backend->db->Query("UPDATE u_pathoschild.$table SET p_last_bureaucrat=(SELECT MAX(log_timestamp) FROM logging WHERE log_user=u_pathoschild.$table.p_id AND log_type IN ('makebot','renameuser','rights'))");
	
	$backend->profiler->stop('fetch data into temporary table');

	/***************
	* Output
	***************/
	$backend->profiler->start('analyze and output');
	// table of contents
	echo '<h2>Generated data</h2>',
		 '<div id="toc"><b>Table of contents</b><ol>';
	foreach($engine->roles as $group=>$boolean) {
		if($boolean) {
			echo '<li><a href="#', $group, '_activity">', $group, ' activity</a></li>';
		}
	}
	echo '</ol></div>';

	// sections	
	foreach($engine->roles as $group=>$boolean) {
		if($boolean) {
			echo '<h2 id="', $group, '_activity">', $group, 's</h2>';
			
			if($backend->db->Query("SELECT COUNT(p_name) FROM u_pathoschild.$table WHERE p_group=?", array($group))->fetchValue() == 0) {
				echo '<div class="neutral">No active ' . $group . 's on this wiki.</div>';
			}
			else {
				// edits
				add_namedate_table('last edit', "SELECT p_name,p_last_edit AS p_date FROM u_pathoschild.$table WHERE p_group='$group' ORDER BY p_last_edit DESC");
				
				// log activity
				switch($group) {
					case 'sysop':
						add_namedate_table('last log event', "SELECT p_name,p_last_sysop AS p_date FROM u_pathoschild.$table WHERE p_group='sysop' ORDER BY p_last_sysop DESC");
						break;
					case 'bureaucrat':
						add_namedate_table('last log event', "SELECT p_name,p_last_bureaucrat AS p_date FROM u_pathoschild.$table WHERE p_group='bureaucrat' ORDER BY p_last_bureaucrat DESC");
				}
				echo '<br clear="all" />';
			}
		}
	}
	$backend->profiler->stop('analyze and output');
	
	$backend->profiler->start('drop temporary table');
	$backend->db->Query('DROP TABLE u_pathoschild.' . $table);
	$backend->profiler->stop('drop temporary table');
} while(0);


$backend->footer();
?>

<?php
/***************
* Configuration
***************/
$api_disallowed = array(
	'commons.wikimedia.org',
	'de.wikipedia.org',
	'en.wikipedia.org',
	'en.wiktionary.org',
	'fr.wikipedia.org',
	'fr.wiktionary.org'
);

/***************
* globals, templates
***************/
$locals = array (
	'title'       => 'stewardry',
	'description' => 'Provides useful statistics for stewards about the given project.',
	'files'       => array('index.php'),
	'path'        => &$globals['urls']['toolserver']
);
include('../backend/legacy/database.php');

// in API mode, don't display interface, and only return date of last action per-group
$api = $_GET['api'];


if(!$api)
	include('../backend/legacy/globals.php');

/***************
* Get data
***************/
$wiki = $_GET['wiki'];

// if anything is explicitly checked, only show that
if(isset($_GET['sysop']) || isset($_GET['bureaucrat']) || isset($_GET['checkuser']) || isset($_GET['oversight']) || isset($_GET['bot'])) {
	$show = Array(
		'bot'        => (($_GET['bot'])?1:0),
		'sysop'      => (($_GET['sysop'])?1:0),
		'bureaucrat' => (($_GET['bureaucrat'])?1:0),
		'checkuser'  => (($_GET['checkuser'])?1:0),
		'oversight'  => (($_GET['oversight'])?1:0)
	);
}
// if nothing set, use defaults
else {
	$show = Array(
		'bot'        => 0,
		'sysop'      => 1,
		'bureaucrat' => 1,
		'checkuser'  => 0,
		'oversight'  => 0
	);
}

// request summary
$reqSummary = '/* ' . addslashes('ip=' . $_SERVER['REMOTE_ADDR'] . ', request=' . print_r($_GET, true)) . '*/';

/***************
* Input form
***************/
if(!$api) {
?>
<form action="" method="get">
	<fieldset>
		<label for="wiki"><a href="//meta.wikimedia.org/wiki/Steward_handbook#Database_prefixes" title="documentation on database prefixes">database</a> or domain:</label>
		<input type="text" name="wiki" id="wiki" value="<?php echo htmlentities($_GET['wiki'], ENT_QUOTES); ?>" /><br />

		Groups to display (uncheck some for faster results):<br />
		<div style="margin-left:3em;">
			<input type="checkbox" id="sysop" name="sysop" <?php gCheckBox($show['sysop'], 1); ?> /> <label for="sysop">sysop</label><br />
			<input type="checkbox" id="bureaucrat" name="bureaucrat" <?php gCheckBox($show['bureaucrat'], 1); ?> /> <label for="bureaucrat">bureaucrat</label><br />
			<input type="checkbox" id="checkuser" name="checkuser" <?php gCheckBox($show['checkuser'], 0); ?> /> <label for="checkuser">checkuser</label><br />
			<input type="checkbox" id="oversight" name="oversight" <?php gCheckBox($show['oversight'], 0); ?> /> <label for="oversight">oversight</label><br />
			<input type="checkbox" id="bot" name="bot" <?php gCheckBox($show['bot'], 0); ?> /> <label for="bot">bot</label><br />
		</div>
		<?php gDebugOption(); ?><br />
		<input type="submit" value="Submit" id="submit" class="smallsubmit" />
	</fieldset>
</form>
<?php
}
else {
	?><api>
	<input>
		<wiki><?php echo htmlentities($wiki); ?></wiki>
	</input>
<?php
}

/***************
* Functions
***************/
/* outputs a color-coded table cell for the given date */
function color_cell($last_date) {
	// parse date
	if($last_date)
		$last_date = preg_replace('/^(\d{8}).+$/','$1',$last_date);

	$return = '<td style="background:#';
	if($last_date > date('Ymd', strtotime('-1 week')))
		$return .= 'CFC';
	elseif($last_date > date('Ymd', strtotime('-3 week')))
		$return .= 'FFC';
	else
		$return .= 'FCC';
	$return .= ';">' . (($last_date)?$last_date:'never') . '</td>';

	return $return;
}

/* outputs a table based on a query with two values: name, date. */
function add_namedate_table($label, $query_string) {
	global $domain;

	$query = db_query($query_string);
	echo '<table style="float:left;"><tr><th>user</th><th>', $label, '</th></tr>';

	while($data = mysql_fetch_row($query)) {
		echo '<tr>',
			 '<td><a href="//' . $domain . '/wiki/User:' . $data[0] . '" title="' . $data[0] . '\'s user page">' . $data[0] . '</a> <small>[<a href="//toolserver.org/~pathoschild/crossactivity/?user=' . $data[0] . '" title="scan this user\'s activity on all wikis">all wikis</a>]</small></td>',
			 color_cell($data[1]),
			 '</tr>';
	}
	echo '</table>';
}

/***************
* Get & process data
***************/
do {
	$domain;
	$database;

	/***************
	* Error-check
	***************/
	// form not filled
	if(!$wiki)
		break;
	
	// no groups selected
	if(!count($show))
		break;

	// invalid input
	if(preg_match('/[^a-z0-9\-_\.]/', $wiki)) {
		print '<div class="fail">The input contains invalid characters for a database prefix or domain name.</div>';
		break;
	}

	/***************
	* Parse database name or domain
	***************/
	// database name?
	if(!strstr($wiki, '.')) {
		dbconnect('metawiki-p');
		$prefix = preg_replace('/(?:[-_]p)?/', '', $prefix);
		$prefix = str_replace('_', '-', addslashes($wiki));
		$prefix = preg_replace( '/-p$/', '', $prefix ); 
		$domain = db_fetch_value('SELECT domain FROM toolserver.wiki WHERE dbname="' . str_replace('-', '_', $prefix) . '_p"', 'domain');
		
		mysql_close();
	}
	
	// domain name?
	if($domain == '') {
		dbconnect('metawiki-p');
		$domain = preg_replace('/^(?:https?:\/\/)?(?:www\.)?(.+?)(?:\.org.*)?$/', '$1.org', addslashes($wiki));
		$prefix = db_fetch_value('SELECT dbname FROM toolserver.wiki WHERE domain="' . $domain . '"', 'dbname');
		$prefix = str_replace('_', '-', $prefix);
		$prefix = preg_replace('/[_-]p$/', '', $prefix);
		mysql_close();
	
		if($domain=='') {
			echo '<div class="fail">Could not determine domain for ', htmlentities($prefix), '.</div>';
			break;
		}
	}
	
	/***************
	* Get data
	***************/
	dbconnect($prefix . '-p');
	
	// generate unique table name
	list($msec, $sec) = explode(' ', microtime());
	$time['start'] = (float)$sec + (float)$msec;
	$table = $sec . str_replace('0.','', $msec);
	unset($msec, $sec);
		
	/* human mode */
	if(!$api) {
		/* disallowed queries */
		if($domain=='en.wikipedia.org' && $show['sysop'])
			die('<div class="fail">Sysop statistics are disabled for en.wikipedia.org because the result set is too large to process.</div>');
		if($domain=='en.wikipedia.org' && ($show['bureaucrat']+$show['checkuser']+$show['oversight']+$show['bot'])>1)
			die('<div class="fail">Only one group (except sysop) can be selected for en.wikipedia.org because the result set is too large to process.</div>');
	
		/* prepare database */
		db_query('CREATE DATABASE IF NOT EXISTS u_pathoschild');
		db_query('CREATE TABLE u_pathoschild.' . $table . ' (p_id int(5) UNSIGNED, p_name varchar(255), p_group varchar(255), p_last_edit varchar(14), p_last_sysop varchar(14), p_last_bureaucrat varchar(14))');
		
		/***************
		* Get data
		***************/
		// replication lag warning
		echo replag_warning($domain, 86400);	// >= 1 day
		 
		// get user IDs and groups, skip groups we're not looking for
		$query = 'INSERT INTO u_pathoschild.' . $table . ' (p_id, p_group) SELECT ug_user, ug_group FROM user_groups WHERE';
		foreach($show as $group => $boolean) {
			if($boolean) {
				$query .= ' ug_group="' . $group . '" OR';
			}
		}
		$query = substr_replace($query, '', -3, 3) . ' ORDER BY ug_user';
		db_query($query);
		unset($query);
		
		// get user names	
		db_query( $reqSummary . 'UPDATE u_pathoschild.' . $table . ' SET p_name=(SELECT user_name FROM user WHERE user_id=u_pathoschild.' . $table . '.p_id)');
		
		// get last edits
		db_query($reqSummary . 'UPDATE u_pathoschild.' . $table . ' SET p_last_edit=(SELECT MAX(rev_timestamp) FROM revision WHERE rev_user=p_id)');
		
		// get date of last sysop action
		if($show['sysop'])
			db_query($reqSummary . 'UPDATE u_pathoschild.' . $table . ' SET p_last_sysop=(SELECT MAX(log_timestamp) FROM logging WHERE log_user=u_pathoschild.' . $table . '.p_id AND log_type IN ("block","delete","protect"))');

		// get date of last bureaucrat action
		if($show['bureaucrat'])
			db_query($reqSummary . 'UPDATE u_pathoschild.' . $table . ' SET p_last_bureaucrat=(SELECT MAX(log_timestamp) FROM logging WHERE log_user=u_pathoschild.' . $table . '.p_id AND log_type IN ("makebot","renameuser","rights"))');
		

		// debug
		list($msec, $sec) = explode(' ', microtime());
		$time['end'] = (float)$sec + (float)$msec;
		unset($msec, $sec);
		
		/***************
		* Output
		***************/
		// table of contents
		echo '<h2>Generated data</h2>',
			 '<div id="toc"><b>Table of contents</b><ol>';
		foreach($show as $group=>$boolean) {
			if($boolean) {
				echo '<li><a href="#', $group, '_activity">', $group, ' activity</a></li>';
			}
		}
		echo '</ol></div>';

		// sections	
		foreach($show as $group=>$boolean) {
			if($boolean) {
				echo '<h2 id="', $group, '_activity">', $group, 's</h2>';
				
				if(db_fetch_value('SELECT COUNT(p_name) FROM u_pathoschild.' . $table . ' WHERE p_group="' . $group . '"')==0) {
					echo '<div class="neutral">No active ' . $group . 's on this wiki.</div>';
				}
				else {
					// edits
					add_namedate_table('last edit', 'SELECT p_name,p_last_edit FROM u_pathoschild.' . $table . ' WHERE p_group="' . $group . '" ORDER BY p_last_edit DESC');
					
					// log activity
					switch($group) {
						case 'sysop':
							add_namedate_table('last log event', 'SELECT p_name,p_last_sysop FROM u_pathoschild.' . $table . ' WHERE p_group="sysop" ORDER BY p_last_sysop DESC');
							break;
						case 'bureaucrat':
							add_namedate_table('last log event', 'SELECT p_name,p_last_bureaucrat FROM u_pathoschild.' . $table . ' WHERE p_group="bureaucrat" ORDER BY p_last_bureaucrat DESC');
					}
					echo '<br clear="all" />';
				}
			}
		}
		echo '<small>Query completed in ', round($time['end']-$time['start'], 3), ' seconds.</small>';
		
		db_query('DROP TABLE u_pathoschild.' . $table);
	}
	
	/* API mode: date of last action in each category) */
	else {
		// validate
		if (in_array($domain, $api_disallowed))
			die('<div class="fail">The stewardry API is disabled for this wiki because the result set is too large to process.</div>');
		
		// get dates of latest actions
		$last_edit       = db_fetch_value('SELECT MAX(rev_timestamp) FROM revision');
		$last_checkuser  = db_fetch_value('SELECT MAX(rev_timestamp) FROM revision INNER JOIN user_groups on rev_user = ug_user AND ug_group="checkuser" ORDER BY rev_id DESC LIMIT 1');
		$last_sysop      = db_fetch_value('SELECT MAX(log_timestamp) FROM logging LEFT JOIN user_groups on log_user=ug_user WHERE log_type IN ("block","delete","protect") AND ug_group="sysop"');
		$last_bureaucrat = db_fetch_value('SELECT MAX(log_timestamp) FROM logging LEFT JOIN user_groups on log_user=ug_user WHERE log_type IN ("makebot","renameuser","rights") AND ug_group="bureaucrat"');

		// calculate differences from current date
		date_default_timezone_set('UTC');
		$now = new DateTime('now');
		$now = $now->format('U');
		
		function time_since($time) {
			static $MIN  = 60;
			static $HOUR = 3600;
			static $DAY  = 86400;
			
			global $now;
			$then = new DateTime($time);
			$secs = $now - $then->format('U');
			$since = '';
			
			if($secs >= $DAY) {
				$tmp  = ($secs - ($secs % $DAY)) / $DAY;
				$secs -= $tmp * $DAY;
				$since .= $tmp . ' days, ';
			}
			if($secs >= $HOUR) {
				$tmp  = ($secs - ($secs % $HOUR)) / $HOUR;
				$secs -= $tmp * $HOUR;
				$since .= $tmp . ' hours, ';
			}
			if($secs >= $MIN) {
				$tmp  = ($secs - ($secs % $MIN)) / $MIN;
				$secs -= $tmp * $MIN;
				$since .= $tmp . ' minutes, ';
			}
			if($secs > 0)
				$since .= $secs . ' seconds, ';
			
			$since = substr_replace($since, '', -2, 2);
			
			if($since)
				return $since . ' ago';
			else
				return 'never';
		}
		$since_edit = time_since($last_edit);
		$since_sysop = time_since($last_sysop);
		$since_bureaucrat = time_since($last_bureaucrat);
		$since_checkuser = time_since($last_checkuser);
		
		echo '	<dates>', "\n",
		     '		<edit>', $last_edit, '</edit>', "\n",
			 '		<sysop>', $last_sysop, '</sysop>', "\n",
			 '		<bureaucrat>', $last_bureaucrat, '</bureaucrat>', "\n",
			 '		<checkuser>', $last_checkuser, '</checkuser>', "\n",
			 '	</dates>', "\n",
			 '	<timesince>', "\n",
			 '		<edit>', $since_edit, '</edit>', "\n",
			 '		<sysop>', $since_sysop, '</sysop>', "\n",
			 '		<bureaucrat>', $since_bureaucrat, '</bureaucrat>', "\n",
			 '		<checkuser>', $since_checkuser, '</checkuser>', "\n",
			 '		<string>last edit: ', $since_edit, '; ',
			 'last sysop action: ', $since_sysop, '; ',
			 'last bureaucrat action: ', $since_bureaucrat, '; ',
			 'last checkuser edit: ', $since_checkuser,
			 '</string>', "\n",
			 '	</timesince>', "\n",
			 '</api>';
			 
	}
	
	/***************
	* Drop database
	***************/
	mysql_close();
} while(0);

if(!$api) {
	/***************
	* Debug
	***************/
	gDebug(get_defined_vars());

	/* globals, templates */
	makeFooter($license);
}
?>

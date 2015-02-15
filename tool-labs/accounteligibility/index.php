<?php
require_once('../backend/modules/Backend.php');
$backend = Backend::create('AccountEligibility', 'Analyzes a given user account to determine whether it\'s eligible to vote in the specified event.')
	->link('/accounteligibility/stylesheet.css')
	->link('/content/jquery.tablesorter.js')
	->addScript('$(document).ready(function() { $("#local-accounts").tablesorter({sortList:[[1,1]]}); });')
	->header();

############################
## Script engine
############################
class Script extends Base {
	############################
	## Configuration
	############################
	const DEFAULT_EVENT = 33;
	public $events = Array(
		35 => Array(
			'year' => 2015,
			'name' => 'steward elections',
			'url' => '//meta.wikimedia.org/wiki/Stewards/Elections_2015'
		),

		34 => Array(
			'year' => 2015,
			'name' => 'steward elections (candidates)',
			'url' => '//meta.wikimedia.org/wiki/Stewards/Elections_2015',
			'action' => '<strong>be a candidate</strong>',
			'more_reqs' => Array(
				'You must be 18 years old, and at the age of majority in your country.',
				'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="http://meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
				'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2015.'
			)
		),

		33 => Array(
			'year' => 2015,
			'name' => 'Commons Picture of the Year for 2014',
			'url' => '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2014'
		),

		32 => Array(
			'year' => 2014,
			'name' => 'Commons Picture of the Year for 2013',
			'url' => '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2013'
		),

		31 => Array(
			'year' => 2014,
			'name' => 'steward elections',
			'url' => '//meta.wikimedia.org/wiki/Stewards/Elections_2014'
		),

		30 => Array(
			'year' => 2014,
			'name' => 'steward elections (candidates)',
			'url' => '//meta.wikimedia.org/wiki/Stewards/Elections_2014',
			'action' => '<strong>be a candidate</strong>',
			'more_reqs' => Array(
				'You must be 18 years old, and at the age of majority in your country.',
				'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="http://meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
				'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2014.'
			)
		),

		29 => Array(
			'year' => 2013,
			'name' => 'steward elections',
			'url' => '//meta.wikimedia.org/wiki/Stewards/Elections_2013'
		),

		28 => Array(
			'year' => 2013,
			'name' => 'steward elections (candidates)',
			'url' => '//meta.wikimedia.org/wiki/Stewards/Elections_2013',
			'action' => '<strong>be a candidate</strong>',
			'more_reqs' => Array(
				'You must be 18 years old, and at the age of majority in your country.',
				'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="http://meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
				'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2013.'
			)
		),

		27 => Array(
			'year' => 2013,
			'name' => 'Commons Picture of the Year for 2012',
			'url' => '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2012'
		),

		26 => Array(
			'year' => 2012,
			'name' => 'enwiki arbcom elections (voters)',
			'url' => '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2012',
			'only_db' => 'enwiki_p'
		),

		25 => Array(
			'year' => 2012,
			'name' => 'enwiki arbcom elections (candidates)',
			'url' => '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2012',
			'only_db' => 'enwiki_p',
			'action' => '<strong>be a candidate</strong>',
			'more_reqs' => Array(
				'You must be in good standing and not subject to active blocks or site-bans.',
				'You must meet the Wikimedia Foundation\'s <a href="http://wikimediafoundation.org/w/index.php?title=Access_to_nonpublic_data_policy&oldid=47490" title="Access to nonpublic data policy">criteria for access to non-public data</a> and must identify with the Foundation if elected.',
				'You must have disclosed any alternate accounts in your election statement (legitimate accounts which have been declared to the Arbitration Committee before the close of nominations need not be publicly disclosed).'
			)
		),

		24 => Array(
			'year' => 2012,
			'name' => 'Commons Picture of the Year for 2011',
			'url' => '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2011'
		),

		23 => Array(
			'year' => 2012,
			'name' => 'steward elections',
			'url' => '//meta.wikimedia.org/wiki/Stewards/Elections_2012'
		),

		22 => Array(
			'year' => 2012,
			'name' => 'steward elections (candidates)',
			'url' => '//meta.wikimedia.org/wiki/Stewards/Elections_2012',
			'action' => '<strong>be a candidate</strong>',
			'more_reqs' => Array(
				'You must be 18 years old, and at the age of majority in your country.',
				'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
				'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2012.'
			)
		),

		21 => Array(
			'year' => 2011,
			'name' => 'enwiki arbcom elections',
			'url' => '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2011',
			'only_db' => 'enwiki_p'
		),

		20 => Array(
			'year' => 2011,
			'name' => 'enwiki arbcom elections (candidates)',
			'url' => '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2011',
			'only_db' => 'enwiki_p',
			'action' => '<strong>be a candidate</strong>',
			'more_reqs' => Array(
				'You must be in good standing and not subject to active blocks or site-bans.',
				'You must meet the Wikimedia Foundation\'s criteria for access to non-public data and must identify with the Foundation if elected.',
				'You must have disclosed any alternate accounts in your election statement (legitimate accounts which have been declared to the Arbitration Committee prior to the close of nominations need not be publicly disclosed).'
			)
		),

		19 => Array(
			'year' => 2011,
			'name' => '2011-09 steward elections',
			'url' => '//meta.wikimedia.org/wiki/Stewards/elections_2011-2'
		),

		18 => Array(
			'year' => 2011,
			'name' => '2011-09 steward elections (candidates)',
			'url' => '//meta.wikimedia.org/wiki/Stewards/elections_2011-2',
			'action' => '<strong>be a candidate</strong>',
			'more_reqs' => Array(
				'You must be 18 years old, and at the age of majority in your country.',
				'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
				'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 07 February 2011.'
			)
		),

		17 => Array(
			'year' => 2011,
			'name' => 'Board elections',
			'url' => '//meta.wikimedia.org/wiki/Board elections/2011',
			'more_reqs' => Array(
				'Your account must not be used by a bot.'
			),
			'exceptions' => Array(
				'You are a Wikimedia server administrator with shell access.',
				'You have MediaWiki commit access and made at least one commit between 15 May 2010 and 15 May 2011.',
				'You are a Wikimedia Foundation staff or contractor employed by Wikimedia between 15 February 2011 and 15 May 2011.',
				'You are a current or former member of the Wikimedia Board of Trustees or Advisory Board.'
			)
		),

		16 => Array(
			'year' => 2011,
			'name' => 'Commons Picture of the Year for 2010',
			'url' => '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2010'
		),

		15 => Array(
			'year' => 2011,
			'name' => 'steward confirmations',
			'url' => '//meta.wikimedia.org/wiki/Stewards/confirm/2011',
			'action' => 'comment'
		),

		14 => Array(
			'year' => 2011,
			'name' => '2011-01 steward elections',
			'url' => '//meta.wikimedia.org/wiki/Stewards/elections_2011'
		),

		13 => Array(
			'year' => 2011,
			'name' => '2011-01 steward elections (candidates)',
			'url' => '//meta.wikimedia.org/wiki/Stewards/elections_2011',
			'action' => '<strong>be a candidate</strong>',
			'more_reqs' => Array(
				'You must be 18 years old, and at the age of majority in your country.',
				'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
				'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 07 February 2011.'
			)
		),

		12 => Array(
			'year' => 2010,
			'name' => 'enwiki arbcom elections',
			'url' => '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2010',
			'only_db' => 'enwiki_p'
		),

		11 => Array(
			'year' => 2010,
			'name' => '2010-09 steward elections',
			'url' => '//meta.wikimedia.org/wiki/Stewards/elections_2010-2'
		),

		10 => Array(
			'year' => 2010,
			'name' => '2010-09 steward elections (candidates)',
			'url' => '//meta.wikimedia.org/wiki/Stewards/elections_2010-2',
			'action' => '<strong>be a candidate</strong>',
			'more_reqs' => Array(
				'You must be 18 years old, and at the age of majority in your country.',
				'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
				'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 01 February 2010.'
			)
		),

		9 => Array(
			'year' => 2010,
			'name' => 'Commons Picture of the Year for 2009',
			'url' => '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2009'
		),

		8 => Array(
			'year' => 2010,
			'name' => '2010-02 steward elections',
			'url' => '//meta.wikimedia.org/wiki/Stewards/elections_2010',
			'more_reqs' => Array(
				'Your account must not be primarily used for automated (bot) tasks.'
			)
		),

		7 => Array(
			'year' => 2010,
			'name' => '2010-02 steward elections (candidates)',
			'url' => '//meta.wikimedia.org/wiki/Stewards/elections_2010',
			'action' => '<strong>be a candidate</strong>',
			'more_reqs' => Array(
				'You must be 18 years old, and at the age of majority in your country.',
				'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a>.',
				'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 01 February 2010.'
			)
		),

		6 => Array(
			'year' => 2010,
			'name' => 'create global sysops vote',
			'url' => '//meta.wikimedia.org/wiki/Global_sysops/Vote'
		),

		5 => Array(
			'year' => 2009,
			'name' => 'enwiki arbcom elections',
			'url' => '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2009',
			'only_db' => 'enwiki_p'
		),

		4 => Array(
			'year' => 2009,
			'name' => 'Commons Picture of the Year for 2008',
			'url' => '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2008'
		),

		3 => Array(
			'year' => 2009,
			'name' => 'steward elections (candidates)',
			'url' => '//meta.wikimedia.org/wiki/Stewards/elections_2009',
			'action' => '<strong>be a candidate</strong>',
			'more_reqs' => Array(
				'You must be 18 years old, and at the age of majority in your country.',
				'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a>.',
				'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation.'
			)
		),

		2 => Array(
			'year' => 2009,
			'name' => 'steward elections',
			'url' => '//meta.wikimedia.org/wiki/Stewards/elections_2009'
		),

		1 => Array(
			'year' => 2008,
			'name' => 'enwiki arbcom elections',
			'url' => '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2008',
			'only_db' => 'enwiki_p'
		),

		0 => Array(
			'year' => 2008,
			'name' => 'Board elections',
			'url' => '//meta.wikimedia.org/wiki/Board elections/2008'
		)
	);

	############################
	## Properties
	############################
	/**
	 * The underlying database manager.
	 * @var Toolserver
	 */
	public $db = NULL;
	public $profiler = NULL;
	protected $backend = NULL;
	private $_userName = NULL;
	protected $eventID = NULL;

	/**
	 * Whether the user must select a wiki manually, because there is no matching global account.
	 */
	public $selectManually = false;

	public $wiki = NULL;
	public $domain = NULL;
	public $user = Array();
	public $event = Array();

	public $wikis = Array(); // dbn => domain for all known wikis
	public $users = Array(); // dbn => user data; cache for get_user()
	public $queue = Array(); // dbn => edit count (edit count used to sort)
	public $queue_top = -1; // index of next item in the wiki queue

	public $eligible = true;
	public $unified = false; // is script iterating over unified wikis?
	public $cache_key = null;


	############################
	## Constructor
	############################
	public function __construct($backend, $user, $event, $wiki) {
		$this->backend = $backend;
		$this->db = $backend->GetDatabase();
		$this->profiler = $backend->profiler;

		/* set user */
		$this->_userName = $backend->formatUsername($user);

		/* set event */
		$this->eventID = isset($event) ? $event : self::DEFAULT_EVENT;
		$this->event = $this->events[$this->eventID];
		$this->event['id'] = $this->eventID;

		/* preparse event list */
		$years = array();
		$currentYear = new DateTime('now', new DateTimeZone('utc'));
		$currentYear = $currentYear->format('Y');

		foreach ($this->events as $id => $event) {
			// normalise
			$event['id'] = $id;
			$event['obsolete'] = $event['year'] < $currentYear;
			if(!$event['year'])
				$event['year'] = 0;

			// add by year
			if (!array_key_exists($event['year'], $years))
				$years[$event['year']] = array();
			$years[$event['year']][] = $event;
		}
		$this->eventsByYear = $years;

		/* get wikis */
		$this->wikis = array();
		foreach($this->db->getDomains() as $dbname => $domain) {
			$this->wikis[$dbname] = array(
				'dbname' => $dbname,
				'code'   => substr($dbname, 0, -2),
				'domain' => $domain
			);
		}

		/* connect database */
		if (!$wiki)
			$wiki = NULL;
		$this->Connect($wiki);

		/* initialize cache */
		$this->cache_key = "accounteligibility-user={$user}|event={$event}|wiki={$wiki}";
	}


	############################
	## State methods
	############################
	########
	## Are there no more wikis? :(
	########
	public function IsQueueEmpty() {
		return $this->queue_top >= 0;
	}

	########
	## Set active wiki & fetch user data
	########
	public function get_next($echo = true) {
		if (!$this->connect_next()) {
			return false;
		}

		$this->get_user();
		if ($echo) {
			$this->printWiki();
		}
		return true;
	}


	########
	## Set active wiki
	########
	public function Connect($dbname) {
		/* reset variables */
		$this->user = array('name' => $this->backend->formatUsername($this->_userName));

		/* connect & fetch user details */
		if ($dbname) {
			$this->wiki = $this->wikis[$dbname];
			$this->db->Connect($dbname);
		}
	}


	########
	## Remove next wiki from queue and set it as active wiki
	########
	public function connect_next() {
		while ($this->queue_top >= 0) {
			/* skip private wiki (not listed in toolserver.wiki) */
			$dbname = $this->queue[$this->queue_top--];
			if (!isset($this->wikis[$dbname])) {
				continue;
			}

			/* connect */
			$this->wiki = $this->wikis[$dbname];
			$this->Connect($dbname);
			return true;
		}
		return false;
	}


	########
	## Set default wiki if wiki not selected
	########
	public function default_wiki($defaultDbname = NULL) {
		########
		## Set selected wiki
		########
		if ($this->wiki) {
			$this->queue = Array($this->wiki['dbname']);
			$this->queue_top = 0;
			$this->msg('Selected ' . $this->wiki['domain'] . '.', 'is-metadata');
		}

		########
		## Set single wiki
		########
		elseif ($defaultDbname != NULL) {
			$this->queue = Array($defaultDbname);
			$this->queue_top = 0;
			$this->msg('Auto-selected ' . $defaultDbname . '.', 'is-metadata');
		}

		########
		## Queue unified wikis
		########
		else {
			/* fetch unified wikis */
			$this->profiler->start('fetch unified wikis');
			$unifiedDbnames = $this->db->getUnifiedWikis($this->user['name']);
			if (!$unifiedDbnames) {
				$this->selectManually = true;
				$encoded = urlencode($this->user['name']);
				echo '<div id="result" class="neutral" data-is-error="1">', $this->formatText($this->user['name']), ' has no global account, so we cannot auto-select an eligible wiki. Please select a wiki (see <a href="', $backend->url('/stalktoy/' . $encoded), '" title="global details about this user">global details about this user</a>).</div>';
				return false;
			}
			$this->profiler->stop('fetch unified wikis');


			/* fetch user edit count for each wiki & sort by edit count */
			$this->profiler->start('fetch edit counts');
			foreach ($unifiedDbnames as $unifiedDbname) {
				if (!isset($this->wikis[$unifiedDbname]))
					continue; // skip private wikis (not listed in meta_p.wiki)
				$this->db->Connect($unifiedDbname);
				$this->queue[$unifiedDbname] = $this->db->Query('SELECT user_editcount FROM user WHERE user_name = ? LIMIT 1', array($this->user['name']))->fetchColumn();
			}
			$this->profiler->stop('fetch edit counts');
			asort($this->queue);

			/* ignore accounts with 0 edits */
			function filter($count) {
				return $count != 0;
			}
			$this->queue = array_filter($this->queue, 'filter');

			/* initialize queue */
			$this->queue = array_keys($this->queue);
			$this->queue_top = count($this->queue) - 1;
			$this->unified = true;

			/* report queue */
			$this->msg('Auto-selected ' . count($this->queue) . ' unified accounts with at least one edit.', 'is-metadata');
		}

		########
		## Connect & return
		########
		return $this->get_next(false /*don't output name@wiki yet*/);
	}


	############################
	## Data methods
	############################
	########
	## Is user account global?
	########
	public function is_global() {
		if (!isset($this->user['global'])) {
			$this->user['global'] = $this->unified || $this->get_home_wiki();
		}
		return $this->user['global'];
	}

	########
	## Has account on specified wiki?
	########
	public function has_account($wiki) {
		if ($this->wiki['dbname'] == $wiki || in_array($wiki, $this->queue))
			return true;
		else {
			$this->db->Connect('metawiki');
			$on_meta = $this->db->Query('SELECT user_id FROM user WHERE user_name = ? LIMIT 1', array($this->user['name']))->fetchColumn();
			$this->db->ConnectPrevious();
			return $on_meta;
		}
	}

	########
	## Get home wiki
	########
	public function get_home_wiki() {
		return $this->db->getHomeWiki($this->user['name']);
	}


	########
	## Get information about the user on the current wiki
	########
	public function get_user() {
		$dbname = $this->wiki['dbname'];

		if (!isset($this->users[$dbname])) {
			$this->users[$dbname] = $this->db->getUserDetails($dbname, $this->user['name']);
			$this->users[$dbname]['name'] = $this->_userName;
		}

		$this->user = $this->users[$dbname];
		return $this->user;
	}


	########
	## User has a role?
	########
	public function has_role($role) {
		if ($role != 'bot' && $role != 'sysop')
			throw new Exception('Unrecognized role "' . $role . '" not found in whitelist.');
		return (bool)$this->db->Query('SELECT COUNT(ug_user) FROM user_groups WHERE ug_user=? AND ug_group=? LIMIT 1', array($this->user['id'], $role))->fetchColumn();
	}

	public function get_role_longest_duration($role, $endDate) {
		// SQL to determine the current groups after each log entry
		// (depending on how it was stored on that particular day)
		$sql = '
			SELECT
				log_title,
				log_timestamp,
				log_params,
				log_comment'/*,
				CASE
					WHEN log_params <> "" THEN
						CASE WHEN INSTR("\n", log_params) >= 0
							THEN SUBSTR(log_params, INSTR(log_params, "\n") + 1)
							ELSE log_params
						END
					ELSE log_comment
				END AS "log_resulting_groups"*/.'
			FROM logging_logindex
			WHERE
				log_type = "rights"
				AND log_title';
		$logName = str_replace(' ', '_', $this->user['name']);

		// fetch local logs
		$this->db->Query($sql . ' = ?', array($logName));
		$local = $this->db->fetchAllAssoc();

		// merge with Meta logs
		if(!array_key_exists($role, $this->_metaRoleAgeCache)) {
			$this->db->Connect('metawiki');
			$this->db->Query($sql . ' LIKE ?', array($logName . '@%'));
			$_metaRoleAgeCache[$role] = $this->db->fetchAllAssoc();
			$this->db->ConnectPrevious();
		}
		$local = array_merge($local, $_metaRoleAgeCache[$role]);

		// parse log entries
		$logs = array();
		foreach($local as $row) {
			// alias fields
			$title = $row['log_title'];
			$date = $row['log_timestamp'];
			$params = $row['log_params'];
			$comment = $row['log_comment'];

			// filter logs for wrong wiki / deadline
			if($title != $logName && $title != $logName . '@' . $this->wiki['code'])
				continue;
			if($date > $endDate)
				continue;

			// parse format (changed over the years)
			if(($i = strpos($params, "\n")) !== false) // params: old\nnew
				$groups = substr($params, $i + 1);
			else if($params != '')                     // ...or params: new
				$groups = $params;
			else                                       // ...or comment: +new +new OR =
				$groups = $comment;

			// append to timeline
			$logs[$date] = $groups;
		}
		if(count($logs) == 0)
			return false;
		ksort($logs);

		// parse ranges
		$ranges = array();
		$i = -1;
		$wasInRole = $nowInRole = false;
		foreach ($logs as $timestamp => $roles)
		{
			$nowInRole = (strpos($roles, $role) !== false);

			// start range
			if(!$wasInRole && $nowInRole) {
				++$i;
				$ranges[$i] = array($timestamp, $endDate);
			}

			// end range
			if($wasInRole && !$nowInRole)
				$ranges[$i][1] = $timestamp;

			// update trackers
			$wasInRole = $nowInRole;
		}
		if(count($ranges) == 0)
			return false;

		// determine widest range
		$maxDuration = 0;
		$longest = 0;
		foreach($ranges as $i => $range) {
			$duration = $range[1] - $range[0];
			if($duration > $maxDuration) {
				$maxDuration = $duration;
				$longest = $i;
			}
		}

		// calculate range length
		$start = DateTime::createFromFormat('YmdHis', $ranges[$i][0]);
		$end   = DateTime::createFromFormat('YmdHis', $ranges[$i][1]);
		$diff = $start->diff($end);
		$months = $diff->days / (365.25 / 12);
		return round($months, 2);
	}
	private $_metaRoleAgeCache = Array();

	########
	## Edit counts
	########
	public function edit_count($start = NULL, $end = NULL) {
		/* all edits */
		if (!$start && !$end)
			return $this->user['edit_count'];

		/* within date range */
		$sql = 'SELECT COUNT(rev_id) FROM revision_userindex WHERE rev_user=? AND rev_timestamp ';
		if ($start && $end)
			$this->db->Query($sql . 'BETWEEN ? AND ?', Array($this->user['id'], $start, $end));
		elseif ($start)
			$this->db->Query($sql . '>= ?', Array($this->user['id'], $start));
		elseif ($end)
			$this->db->Query($sql . '<= ?', Array($this->user['id'], $end));

		return $this->db->fetchColumn();
	}

	########
	## Currently blocked?
	########
	public function currently_blocked() {
		$this->db->Query('SELECT COUNT(ipb_expiry) FROM ipblocks WHERE ipb_user=? LIMIT 1', array($this->user['id']));
		return (bool)$this->db->fetchColumn();
	}


	########
	## Indefinitely blocked?
	########
	public function indef_blocked() {
		$this->db->Query('SELECT COUNT(ipb_expiry) FROM ipblocks WHERE ipb_user=? AND ipb_expiry="infinity" LIMIT 1', array($this->user['id']));
		return (bool)$this->db->fetchColumn();
	}


	############################
	## Output methods
	############################
	########
	## Print message
	########
	function msg($message, $classes = NULL) {
		// normalize classes
		$classes = $classes
			? trim($classes)
			: 'is-note';

		// output
		echo '<div class="', $classes, '">', $message, '</div>';
	}


	########
	## Print 'name@wiki...' header
	########
	function printWiki() {
		$name = $this->user['name'];
		$domain = $this->wiki['domain'];
		$this->msg('On <a href="//' . $domain . '/wiki/User:' . $name . '" title="' . $name . '\'s user page on ' . $domain . '">' . $domain . '</a>:', 'is-wiki');
	}


	########
	## Print conditional message, and return condition's boolean
	## If false, sets $this->eligible = false.
	########
	function condition($bool, $msg_eligible, $msg_not, $class_eligible = '', $class_not = '') {
		if ($bool) {
			$this->msg("• {$msg_eligible}", "is-pass {$class_eligible}");
		}
		else {
			$this->msg("• {$msg_not}", "is-fail {$class_not}");
			$this->eligible = false;
		}
		return $bool;
	}

	########
	## conditional wrappers
	########
	public function is_at_least($value, $min, $msg_eligible, $msg_not) {
		return $this->condition($value != null && $value >= $min, $msg_eligible, $msg_not);
	}

	public function is_at_most($value, $max, $msg_eligible, $msg_not) {
		return $this->condition($value != null && $value <= $max, $msg_eligible, $msg_not);
	}
}


############################
## Initialize
############################
$event = $backend->get('event') ?: $backend->getRouteValue() ?: Script::DEFAULT_EVENT;
$user = $backend->get('user') ?: $backend->getRouteValue(2) ?: '';
$wiki = $backend->get('wiki', NULL);
$script = new Script($backend, $user, $event, $wiki);


############################
## Input form
############################
echo '
<form action="', $backend->url('/accounteligibility'), '" method="get">
	<label for="user">User:</label>
	<input type="text" name="user" id="user" value="', $backend->formatValue($script->user['name']), '" /> at 
	<select name="wiki" id="wiki">
		<option value="">auto-select wiki</option>', "\n";

foreach ($script->wikis as $dbname => $details) {
	if (!$script->db->getLocked($dbname)) {
		$selected = ($dbname == $wiki);
		echo '<option value="', $dbname, '"', ($selected ? ' selected="yes" ' : '') , '>', $script->formatText($details['domain']), '</option>';
	}
}
echo '
	</select>
	<br />
	<label for="event">Event:</label>
	<select name="event" id="event">', "\n";

foreach ($script->eventsByYear as $year => $events) {
	foreach ($events as $event) {
		echo '
			<option value="', $event['id'], '" ',
			($event['id'] == $script->event['id'] ? ' selected="yes" ' : ''),
			($event['obsolete'] ? ' class="is-obsolete"' : ''),
			'>', $event['year'], ' &mdash; ', $script->formatText($event['name']), '</option>';
	}
	echo '</optgroup>';
}
echo '
	</select>
	<br />
	<input type="submit" value="Analyze »" />
</form>';


############################
## Timestamp constants
############################
$ONE_YEAR = 10000000000;
$ONE_MONTH = 100000000;


############################
## Check requirements
############################
if($script->user['name'])
	echo '<div class="result-box">';

while ($script->user['name'] && !$cached) {
	if (!isset($script->event)) {
		echo '<div class="error">There is no event matching the given ID.</div>';
		break;
	}

	echo '<h3>Analysis', ($script->user['name'] == 'Shanel' ? '♥' : ''),' </h3>';

	/***************
	 * Validate or default wiki
	 ***************/
	/* incorrect wiki specified */
	if ($script->wiki && isset($script->event['only_db']) && $script->wiki['dbname'] != $script->event['only_db']) {
		echo '<div class="error">Account must be on ', $script->wikis[$script->event['only_db']]['domain'], '. Choose "auto-select wiki" above to select the correct wiki.</div>';
		break;
	}

	/* initialize queue wikis */
	if(!isset($script->event['only_db']))
		$script->event['only_db'] = NULL;
	if (!$script->default_wiki($script->event['only_db'])) {
		if(!$script->selectManually)
			$script->msg('Selection failed, aborted.');
		break;
	}

	/* validate user exists */
	if (!isset($script->user['id'])) {
		echo '<div class="error">', $script->formatText($script->user['name']), ' does not exist on ', $script->formatText($script->wiki['domain']), '.</div>';
		break;
	}

	/***************
	 * Verify requirements
	 ***************/
	switch ($script->event['id']) {

		############################
		## 2015 steward elections
		############################
		case 35:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (optional)...",
				"",
				"is-warn"
			);
			$script->eligible = true;

			########
			## Check local requirements
			########
			$script->printWiki();

			/* set messages for global accounts */
			if ($script->unified) {
				$edits_fail_append = "However, edits will be combined with other unified wikis.";
				$edits_fail_attrs = "is-warn";
			}
			else {
				$edits_fail_append = '';
				$edits_fail_attrs = '';
			}

			/* check requirements */
			$prior_edits = 0;
			$recent_edits = 0;
			$combining = false;
			$marked_bot = false;
			do {
				########
				## Should not be a bot
				########
				$is_bot = $script->has_role('bot');
				$script->condition(
					!$is_bot,
					"no bot flag...",
					"has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
					"",
					"is-warn"
				);
				$script->eligible = true;
				if ($is_bot && !$marked_bot) {
					$script->event['append_eligible'] = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
					$marked_bot = true;
				}

				########
				## >=600 edits before 01 November 2014
				########
				$edits = $script->edit_count(NULL, 20141101000000);
				$prior_edits += $edits;
				$script->condition(
					$edits >= 600,
					"has 600 edits before 01 November 2014 (has {$edits})...",
					"does not have 600 edits before 01 November 2014 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}

				########
				## >=50 edits between 2014-Aug-01 and 2015-Jan-31
				########
				$edits = $script->edit_count(20140801000000, 20150131000000);
				$recent_edits += $edits;
				$script->condition(
					$edits >= 50,
					"has 50 edits between 01 August 2014 and 31 January 2015 (has {$edits})...",
					"does not have 50 edits between 01 August 2014 and 31 January 2015 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}


				########
				## Exit conditions
				########
				$script->eligible = $prior_edits >= 600 && $recent_edits >= 50;

				/* unified met requirements */
				if ($script->unified && !$script->IsQueueEmpty()) {
					if ($script->eligible) {
						break;
					}
					$combining = true;
				}
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2015 steward elections (candidates)
		############################
		case 34:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}


			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Check local requirements
			########
			/* check local requirements */
			$minDurationMet = false;
			$script->printWiki();
			do {
				$script->eligible = true;

				########
				## Registered for six months (i.e. <= 2014-Aug-08)
				########
				$script->condition(
					$script->user['registration_raw'] < 20140808000000,
					"has an account registered before 08 August 2014 (registered {$script->user['registration']})...",
					"does not have an account registered before 08 August 2014 (registered {$script->user['registration']})."
				);

				########
				## Flagged as a sysop for three months (as of 2015-Feb-08)
				########
				if (!$minDurationMet) {
					/* check flag duration */
					$months = $script->get_role_longest_duration('sysop', 20150208000000);
					$minDurationMet = $months >= 3;
					$script->condition(
						$minDurationMet,
						'was flagged as an administrator for a continuous period of at least three months before 08 February 2015 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
						'was not flagged as an administrator for a continuous period of at least three months before 08 February 2015 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
					);

					/* edge case: if the user was registered before 2005, they might have been flagged before flag changes were logged */
					if(!$minDurationMet && (!$script->user['registration_raw'] || $script->user['registration_raw'] < 20050000000000)) {
						// output warning
						$script->msg('<small>' . $script->user['name'] . ' registered here before 2005, so he might have been flagged before the rights log was created.</small>', 'is-warn is-subnote');

						// add note
						$script->event['warn_ineligible'] = '<strong>This result might be inaccurate.</strong> ' . $script->user['name'] . ' registered on some wikis before the rights log was created in 2005. You may need to investigate manually.';
					}
					else if($minDurationMet)
						$script->event['warn_ineligible'] = NULL;

					/* link to log for double-checking */
					$script->msg('<small>(See <a href="//' . $script->wiki['domain'] . '/wiki/Special:Log/rights?page=User:' . $script->user['name'] . '" title="local rights log">local</a> & <a href="//meta.wikimedia.org/wiki/Special:Log/rights?page=User:' . $script->user['name'] . '@' . $script->wiki['code'] . '" title="crosswiki rights log">crosswiki</a> rights logs.)</small>', 'is-subnote');
				}
			}
			while (!$script->eligible && $script->get_next());
			break;

		############################
		## 2015 Commons Picture of the Year 2014
		############################
		case 33:
			$script->printWiki();
			$age_okay = false;
			$edits_okay = false;
			do {
				$script->eligible = true;

				########
				## registered < 2014-Jan-01
				########
				if(!$age_okay) {
					$age_okay = $script->condition(
						$date_okay = ($script->user['registration_raw'] < 20150101000000),
						"has an account registered before 01 January 2015 (registered {$script->user['registration']})...",
						"does not have an account registered before 01 January 2015 (registered {$script->user['registration']})."
					);
				}

				########
				## >= 75 edits before 2014-Jan-01
				########
				if(!$edits_okay) {
					$edits = $script->edit_count(NULL, 20150101000000);
					$edits_okay = $script->condition(
						$edits_okay = ($edits >= 75),
						"has at least 75 edits before 01 January 2015 (has {$edits})...",
						"does not have at least 75 edits before 01 January 2015 (has {$edits})."
					);
				}

				$script->eligible = ($age_okay && $edits_okay);
			}
			while (!$script->eligible && $script->get_next());
			break;
		
		############################
		## 2014 Commons Picture of the Year 2013
		############################
		case 32:
			$script->printWiki();
			$age_okay = false;
			$edits_okay = false;
			do {
				$script->eligible = true;

				########
				## registered < 2014-Jan-01
				########
				if(!$age_okay) {
					$age_okay = $script->condition(
						$date_okay = ($script->user['registration_raw'] < 20140101000000),
						"has an account registered before 01 January 2014 (registered {$script->user['registration']})...",
						"does not have an account registered before 01 January 2014 (registered {$script->user['registration']})."
					);
				}

				########
				## > 75 edits before 2014-Jan-01
				########
				if(!$edits_okay) {
					$edits = $script->edit_count(NULL, 20140101000000);
					$edits_okay = $script->condition(
						$edits_okay = ($edits > 75),
						"has more than 75 edits before 01 January 2014 (has {$edits})...",
						"does not have more than 75 edits before 01 January 2014 (has {$edits})."
					);
				}

				$script->eligible = ($age_okay && $edits_okay);
			}
			while (!$script->eligible && $script->get_next());
			break;

		############################
		## 2014 steward elections
		############################
		case 31:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (optional)...",
				"",
				"is-warn"
			);
			$script->eligible = true;

			########
			## Check local requirements
			########
			$script->printWiki();

			/* set messages for global accounts */
			if ($script->unified) {
				$edits_fail_append = "However, edits will be combined with other unified wikis.";
				$edits_fail_attrs = "is-warn";
			}
			else {
				$edits_fail_append = '';
				$edits_fail_attrs = '';
			}

			/* check requirements */
			$prior_edits = 0;
			$recent_edits = 0;
			$combining = false;
			$marked_bot = false;
			do {
				########
				## Should not be a bot
				########
				$is_bot = $script->has_role('bot');
				$script->condition(
					!$is_bot,
					"no bot flag...",
					"has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
					"",
					"is-warn"
				);
				$script->eligible = true;
				if ($is_bot && !$marked_bot) {
					$script->event['append_eligible'] = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
					$marked_bot = true;
				}

				########
				## >=600 edits before 01 November 2013
				########
				$edits = $script->edit_count(NULL, 20131101000000);
				$prior_edits += $edits;
				$script->condition(
					$edits >= 600,
					"has 600 edits before 01 November 2013 (has {$edits})...",
					"does not have 600 edits before 01 November 2013 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}

				########
				## >=50 edits between 2013-Aug-01 and 2014-Jan-31
				########
				$edits = $script->edit_count(20130801000000, 20140131000000);
				$recent_edits += $edits;
				$script->condition(
					$edits >= 50,
					"has 50 edits between 01 August 2013 and 31 January 2014 (has {$edits})...",
					"does not have 50 edits between 01 August 2013 and 31 January 2014 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}


				########
				## Exit conditions
				########
				$script->eligible = $prior_edits >= 600 && $recent_edits >= 50;

				/* unified met requirements */
				if ($script->unified && !$script->IsQueueEmpty()) {
					if ($script->eligible) {
						break;
					}
					$combining = true;
				}
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2014 steward elections (candidates)
		############################
		case 30:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}


			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Check local requirements
			########
			/* check local requirements */
			$minDurationMet = false;
			$script->printWiki();
			do {
				$script->eligible = true;

				########
				## Registered for six months (i.e. <= 2013-Aug-08)
				########
				$script->condition(
					$script->user['registration_raw'] < 20130808000000,
					"has an account registered before 08 August 2013 (registered {$script->user['registration']})...",
					"does not have an account registered before 08 August 2013 (registered {$script->user['registration']})."
				);

				########
				## Flagged as a sysop for three months (as of 2014-Feb-08)
				########
				if (!$minDurationMet) {
					/* check flag duration */
					$months = $script->get_role_longest_duration('sysop', 20140208000000);
					$minDurationMet = $months >= 3;
					$script->condition(
						$minDurationMet,
						'was flagged as an administrator for a continuous period of at least three months before 08 February 2014 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
						'was not flagged as an administrator for a continuous period of at least three months before 08 February 2014 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
					);

					/* edge case: if the user was registered before 2005, they might have been flagged before flag changes were logged */
					if(!$minDurationMet && (!$script->user['registration_raw'] || $script->user['registration_raw'] < 20050000000000)) {
						// output warning
						$script->msg('<small>' . $script->user['name'] . ' registered here before 2005, so he might have been flagged before the rights log was created.</small>', 'is-warn is-subnote');

						// add note
						$script->event['warn_ineligible'] = '<strong>This result might be inaccurate.</strong> ' . $script->user['name'] . ' registered on some wikis before the rights log was created in 2005. You may need to investigate manually.';
					}
					else if($minDurationMet)
						$script->event['warn_ineligible'] = NULL;

					/* link to log for double-checking */
					$script->msg('<small>(See <a href="//' . $script->wiki['domain'] . '/wiki/Special:Log/rights?page=User:' . $script->user['name'] . '" title="local rights log">local</a> & <a href="//meta.wikimedia.org/wiki/Special:Log/rights?page=User:' . $script->user['name'] . '@' . $script->wiki['code'] . '" title="crosswiki rights log">crosswiki</a> rights logs.)</small>', 'is-subnote');
				}
			}
			while (!$script->eligible && $script->get_next());
			break;

		############################
		## 2013 steward elections
		############################
		case 29:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (optional)...",
				"",
				"is-warn"
			);
			$script->eligible = true;

			########
			## Check local requirements
			########
			$script->printWiki();

			/* set messages for global accounts */
			if ($script->unified) {
				$edits_fail_append = "However, edits will be combined with other unified wikis.";
				$edits_fail_attrs = "is-warn";
			}
			else {
				$edits_fail_append = '';
				$edits_fail_attrs = '';
			}

			/* check requirements */
			$prior_edits = 0;
			$recent_edits = 0;
			$combining = false;
			$marked_bot = false;
			do {
				########
				## Should not be a bot
				########
				$is_bot = $script->has_role('bot');
				$script->condition(
					!$is_bot,
					"no bot flag...",
					"has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
					"",
					"is-warn"
				);
				$script->eligible = true;
				if ($is_bot && !$marked_bot) {
					$script->event['append_eligible'] = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
					$marked_bot = true;
				}

				########
				## >=600 edits before 01 November 2012
				########
				$edits = $script->edit_count(NULL, 20121101000000);
				$prior_edits += $edits;
				$script->condition(
					$edits >= 600,
					"has 600 edits before 01 November 2012 (has {$edits})...",
					"does not have 600 edits before 01 November 2012 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}

				########
				## >=50 edits between 2012-Aug-01 and 2013-Jan-31
				########
				$edits = $script->edit_count(20120801000000, 20130131000000);
				$recent_edits += $edits;
				$script->condition(
					$edits >= 50,
					"has 50 edits between 01 August 2012 and 31 January 2013 (has {$edits})...",
					"does not have 50 edits between 01 August 2012 and 31 January 2013 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}


				########
				## Exit conditions
				########
				$script->eligible = $prior_edits >= 600 && $recent_edits >= 50;

				/* unified met requirements */
				if ($script->unified && !$script->IsQueueEmpty()) {
					if ($script->eligible) {
						break;
					}
					$combining = true;
				}
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2013 steward elections (candidates)
		############################
		case 28:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}


			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Check local requirements
			########
			/* check local requirements */
			$minDurationMet = false;
			$script->printWiki();
			do {
				$script->eligible = true;

				########
				## Registered for six months (i.e. <= 2012-Aug-08)
				########
				$script->condition(
					$script->user['registration_raw'] < 20120808000000,
					"has an account registered before 08 August 2012 (registered {$script->user['registration']})...",
					"does not have an account registered before 08 August 2012 (registered {$script->user['registration']})."
				);

				########
				## Flagged as a sysop for three months (as of 2013-Feb-08)
				########
				if (!$minDurationMet) {
					/* check flag duration */
					$months = $script->get_role_longest_duration('sysop', 20130208000000);
					$minDurationMet = $months >= 3;
					$script->condition(
						$minDurationMet,
						'was flagged as an administrator for a continuous period of at least three months before 08 February 2013 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
						'was not flagged as an administrator for a continuous period of at least three months before 08 February 2013 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
					);

					/* edge case: if the user was registered before 2005, they might have been flagged before flag changes were logged */
					if(!$minDurationMet && (!$script->user['registration_raw'] || $script->user['registration_raw'] < 20050000000000)) {
						// output warning
						$script->msg('<small>' . $script->user['name'] . ' registered here before 2005, so he might have been flagged before the rights log was created.</small>', 'is-warn is-subnote');

						// add note
						$script->event['warn_ineligible'] = '<strong>This result might be inaccurate.</strong> ' . $script->user['name'] . ' registered on some wikis before the rights log was created in 2005. You may need to investigate manually.';
					}
					else if($minDurationMet)
						$script->event['warn_ineligible'] = NULL;

					/* link to log for double-checking */
					$script->msg('<small>(See <a href="//' . $script->wiki['domain'] . '/wiki/Special:Log/rights?page=User:' . $script->user['name'] . '" title="local rights log">local</a> & <a href="//meta.wikimedia.org/wiki/Special:Log/rights?page=User:' . $script->user['name'] . '@' . $script->wiki['code'] . '" title="crosswiki rights log">crosswiki</a> rights logs.)</small>', 'is-subnote');
				}
			}
			while (!$script->eligible && $script->get_next());
			break;

		############################
		## 2013 Commons Picture of the Year 2012
		############################
		case 27:
			$script->printWiki();
			$age_okay = false;
			$edits_okay = false;
			do {
				$script->eligible = true;

				########
				## registered < 2013-Jan-01
				########
				if(!$age_okay) {
					$age_okay = $script->condition(
						$date_okay = ($script->user['registration_raw'] < 20130101000000),
						"has an account registered before 01 January 2013 (registered {$script->user['registration']})...",
						"does not have an account registered before 01 January 2013 (registered {$script->user['registration']})."
					);
				}

				########
				## > 75 edits before 2013-Jan-01
				########
				if(!$edits_okay) {
					$edits = $script->edit_count(NULL, 20130101000000);
					$edits_okay = $script->condition(
						$edits_okay = ($edits >= 75),
						"has more than 75 edits before 01 January 2013 (has {$edits})...",
						"does not have more than 75 edits before 01 January 2013 (has {$edits})."
					);
				}

				$script->eligible = ($age_okay && $edits_okay);
			}
			while (!$script->eligible && $script->get_next());
			break;

		############################
		## 2012 enwiki arbcom elections (voters)
		############################
		case 26:
			$script->printWiki();

			########
			## registered < 2012-Oct-28
			########
			$script->condition(
				($script->user['registration_raw'] < 20121028000000),
				"has an account registered before 28 October 2012 (registered {$script->user['registration']})...",
				"does not have an account registered before 28 October 2012 (registered {$script->user['registration']})."
			);

			########
			## >=150 main-NS edits before 2012-Nov-01
			########
			/* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
			$script->db->Query(
				'SELECT data.count FROM ('
				. 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
				. 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
				. ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
				. ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
				Array($script->user['id'], 20121102000000)
			);
			$edits = $script->db->fetchColumn();
			$script->condition(
				$edits >= 150,
				"has 150 main-namespace edits on or before 01 November 2012 (has {$edits})...",
				"does not have 150 main-namespace edits on or before 01 November 2012 (has {$edits})."
			);

			########
			## Not currently blocked
			########
			$script->condition(
				!$script->currently_blocked(),
				"not currently blocked...",
				"must not be blocked during at least part of election (verify <a href='//en.wikipedia.org/wiki/Special:Log/block?user=" . urlencode($script->user['name']) . "' title='block log'>block log</a>)."
			);
			break;

		############################
		## 2012 enwiki arbcom elections (candidates)
		############################
		case 25:
			$script->printWiki();

			########
			## >=500 main-NS edits before 2012-Nov-02
			########
			/* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
			$script->db->Query(
				'SELECT data.count FROM ('
				. 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
				. 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
				. ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
				. ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
				Array($script->user['id'], 20121102000000)
			);
			$edits = $script->db->fetchColumn();
			$script->condition(
				$edits >= 500,
				"has 500 main-namespace edits on or before 01 November 2012 (has {$edits})...",
				"does not have 500 main-namespace edits on or before 01 November 2012 (has {$edits})."
			);

			########
			## Not currently blocked
			########
			$script->condition(
				!$script->currently_blocked(),
				"not currently blocked...",
				"must not be currently blocked (verify <a href='//en.wikipedia.org/wiki/Special:Log/block?user=" . urlencode($script->user['name']) . "' title='block log'>block log</a>)."
			);
			break;

		############################
		## 2012 Commons Picture of the Year 2011
		############################
		case 24:
			$script->printWiki();
			$age_okay = false;
			$edits_okay = false;
			do {
				$script->eligible = true;

				########
				## registered < 2012-Apr-01
				########
				if(!$age_okay) {
					$age_okay = $script->condition(
						$date_okay = ($script->user['registration_raw'] < 20120401000000),
						"has an account registered before 01 April 2012 (registered {$script->user['registration']})...",
						"does not have an account registered before 01 April 2012 (registered {$script->user['registration']})."
					);
				}

				########
				## > 75 edits before 2012-Apr-01
				########
				if(!$edits_okay) {
					$edits = $script->edit_count(NULL, 20120401000000);
					$edits_okay = $script->condition(
						$edits_okay = ($edits >= 75),
						"has more than 75 edits before 01 April 2012 (has {$edits})...",
						"does not have more than 75 edits before 01 April 2012 (has {$edits})."
					);
				}

				$script->eligible = ($age_okay && $edits_okay);
			}
			while (!$script->eligible && $script->get_next());
			break;

		############################
		## 2012 steward elections
		############################
		case 23:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (<strong>optional</strong>)...",
				"",
				"is-warn"
			);
			$script->eligible = true;

			########
			## Check local requirements
			########
			$script->printWiki();

			/* set messages for global accounts */
			if ($script->unified) {
				$edits_fail_append = "However, edits will be combined with other unified wikis.";
				$edits_fail_attrs = "is-warn";
			}
			else {
				$edits_fail_append = '';
				$edits_fail_attrs = '';
			}

			/* check requirements */
			$prior_edits = 0;
			$recent_edits = 0;
			$combining = false;
			$marked_bot = false;
			do {
				########
				## Should not be a bot
				########
				$is_bot = $script->has_role('bot');
				$script->condition(
					!$is_bot,
					"no bot flag...",
					"has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
					"",
					"is-warn"
				);
				$script->eligible = true;
				if ($is_bot && !$marked_bot) {
					$script->event['append_eligible'] = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
					$marked_bot = true;
				}

				########
				## >=600 edits before 01 November 2011
				########
				$edits = $script->edit_count(NULL, 20111101000000);
				$prior_edits += $edits;
				$script->condition(
					$edits >= 600,
					"has 600 edits before 01 November 2011 (has {$edits})...",
					"does not have 600 edits before 01 November 2011 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}

				########
				## >=50 edits between 2011-Aug-01 and 2012-Jan-31
				########
				$edits = $script->edit_count(20110801000000, 20120131000000);
				$recent_edits += $edits;
				$script->condition(
					$edits >= 50,
					"has 50 edits between 01 August 2011 and 31 January 2012 (has {$edits})...",
					"does not have 50 edits between 01 August 2011 and 31 January 2012 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}


				########
				## Exit conditions
				########
				$script->eligible = $prior_edits >= 600 && $recent_edits >= 50;

				/* unified met requirements */
				if ($script->unified && !$script->IsQueueEmpty()) {
					if ($script->eligible) {
						break;
					}
					$combining = true;
				}
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2012 steward elections (candidates)
		############################
		case 22:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}


			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Check local requirements
			########
			/* check local requirements */
			$minDurationMet = false;
			$script->printWiki();
			do {
				$script->eligible = true;

				########
				## Registered before 2011-July-10
				########
				$script->condition(
					$script->user['registration_raw'] < 20110710000000,
					"has an account registered before 10 July 2011 (registered {$script->user['registration']})...",
					"does not have an account registered before 10 July 2011 (registered {$script->user['registration']})."
				);

				########
				## Must have been a sysop for three months (as of 29 January 2012)
				########
				if (!$minDurationMet) {
					/* check flag duration */
					$months = $script->get_role_longest_duration('sysop', 20120129000000);
					$minDurationMet = $months >= 3;
					$script->condition(
						$minDurationMet,
						'was flagged as an administrator for a continuous period of at least three months before 29 January 2012 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
						'was not flagged as an administrator for a continuous period of at least three months before 29 January 2012 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
					);

					/* edge case: if the user was registered before 2005, they might have been flagged before flag changes were logged */
					if(!$minDurationMet && (!$script->user['registration_raw'] || $script->user['registration_raw'] < 20050000000000)) {
						// output warning
						$script->msg('<small>' . $script->user['name'] . ' registered here before 2005, so he might have been flagged before the rights log was created.</small>', 'is-warn is-subnote');

						// add note
						$script->event['warn_ineligible'] = '<strong>This result might be inaccurate.</strong> ' . $script->user['name'] . ' registered on some wikis before the rights log was created in 2005. You may need to investigate manually.';
					}
					else if($minDurationMet)
						$script->event['warn_ineligible'] = NULL;

					/* link to log for double-checking */
					$script->msg('<small>(See <a href="//' . $script->wiki['domain'] . '/wiki/Special:Log/rights?page=User:' . $script->user['name'] . '" title="local rights log">local</a> & <a href="//meta.wikimedia.org/wiki/Special:Log/rights?page=User:' . $script->user['name'] . '@' . $script->wiki['code'] . '" title="crosswiki rights log">crosswiki</a> rights logs.)</small>', 'is-subnote');
				}
			}
			while (!$script->eligible && $script->get_next());
			break;

		############################
		## 2011 enwiki arbcom elections
		############################
		case 20: // candidates
		case 21: // voters (same checked rules)
			$script->printWiki();
			
			########
			## >=150 main-NS edits before 2011-Nov-01
			########
			/* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
			$script->db->Query(
				'SELECT data.count FROM ('
				. 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
				. 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
				. ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
				. ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
				Array($script->user['id'], 20111101000000)
			);
			$edits = $script->db->fetchColumn();
			$script->condition(
				$edits >= 150,
				"has 150 main-namespace edits on or before 01 November 2011 (has {$edits})...",
				"does not have 150 main-namespace edits on or before 01 November 2011 (has {$edits})."
			);
			
			########
			## Not currently blocked
			########
			$script->condition(
				!$script->currently_blocked(),
				"not currently blocked...",
				"must not be blocked during at least part of election (verify <a href='//en.wikipedia.org/wiki/Special:Log/block?user=" . urlencode($script->user['name']) . "' title='block log'>block log</a>)."
			);
			break;
		
		############################
		## 2011-09 steward elections
		############################
		case 19:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (<strong>optional</strong>)...",
				"",
				"is-warn"
			);
			$script->eligible = true;

			########
			## Check local requirements
			########
			$script->printWiki();

			/* set messages for global accounts */
			if ($script->unified) {
				$edits_fail_append = "However, edits will be combined with other unified wikis.";
				$edits_fail_attrs = "is-warn";
			}
			else {
				$edits_fail_append = '';
				$edits_fail_attrs = '';
			}

			/* check requirements */
			$prior_edits = 0;
			$recent_edits = 0;
			$combining = false;
			$marked_bot = false;
			do {
				########
				## Should not be a bot
				########
				$is_bot = $script->has_role('bot');
				$script->condition(
					!$is_bot,
					"no bot flag...",
					"has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
					"",
					"is-warn"
				);
				$script->eligible = true;
				if ($is_bot && !$marked_bot) {
					$script->event['append_eligible'] = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
					$marked_bot = true;
				}

				########
				## >=600 edits before 15 June 2011
				########
				$edits = $script->edit_count(NULL, 20110614000000);
				$prior_edits += $edits;
				$script->condition(
					$edits >= 600,
					"has 600 edits before 15 June 2011 (has {$edits})...",
					"does not have 600 edits before 15 June 2011 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}

				########
				## >=50 edits between 2011-Mar-15 and 2011-Sep-14
				########
				$edits = $script->edit_count(20110315000000, 20110914000000);
				$recent_edits += $edits;
				$script->condition(
					$edits >= 50,
					"has 50 edits between 15 March 2011 and 14 September 2011 (has {$edits})...",
					"does not have 50 edits between 15 March 2011 and 14 September 2011 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}


				########
				## Exit conditions
				########
				$script->eligible = $prior_edits >= 600 && $recent_edits >= 50;

				/* unified met requirements */
				if ($script->unified && !$script->IsQueueEmpty()) {
					if ($script->eligible) {
						break;
					}
					$combining = true;
				}
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2011 steward elections (candidates)
		############################
		case 18:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}


			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>..."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Check local requirements
			########
			/* check local requirements */
			$minDurationMet = false;
			$script->printWiki();
			do {
				$script->eligible = true;

				########
				## Registered before 2010-Mar-29
				########
				$script->condition(
					$script->user['registration_raw'] < 20110314000000,
					"has an account registered before 14 March 2011 (registered {$script->user['registration']})...",
					"does not have an account registered before 14 March 2011 (registered {$script->user['registration']})."
				);

				########
				## Must have been a sysop for three months
				########
				if (!$minDurationMet) {
					$months = $script->get_role_longest_duration('sysop', 20110913000000);
					$minDurationMet = $months >= 3;
					$script->condition(
						$minDurationMet,
						'was flagged as an administrator for a continuous period of at least three months before 13 September 2011 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
						'was not flagged as an administrator for a continuous period of at least three months before 13 September 2011 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
					);
				}
			}
			while (!$script->eligible && $script->get_next());
			break;

	
		############################
		## 2011 Board elections
		############################
		case 17:
			$indefBlockMessage = "indefinitely blocked: account is still eligible if only blocked on one wiki.";
			$indefBlockMessageMultiple = "indefinitely blocked on more than one wiki.";
			$indefBlockClass = "is-warn";
			
			$indefBlockCount = 0;
			$editCount = 0;
			$editCountRecent = 0;
			
			$script->printWiki();
			
			do {
				$script->eligible = true;
				
				########
				## Not a bot
				########
				$script->condition(
					!$script->has_role('bot'),
					"no bot flag...",
					"has a bot flag: this account might not be eligible (refer to the requirements).",
					"",
					"is-warn"
				);

				########
				## Not indefinitely blocked on more than one wiki
				########
				$isBlocked = $script->indef_blocked();
				$script->condition(
					!$isBlocked,
					"not indefinitely blocked...",
					$indefBlockMessage,
					"",
					$indefBlockClass
				);
				if ($isBlocked) {
					$indefBlockCount++;
					$indefBlockClass = "";
					$indefBlockMessage = $indefBlockMessageMultiple;
				}

				########
				## >=300 edits before 2011-04-15
				########
				$edits = $script->edit_count(NULL, 20110415000000);
				$script->condition(
					$edits >= 300,
					"has 300 edits before 15 April 2011 (has {$edits})...",
					"does not have 300 edits before 15 April 2011 (has {$edits}); edits can be combined across wikis.",
					"",
					"is-warn"
				);
				$editCount += $edits;

				########
				## >=20 edits between 2010-Nov-15 and 2011-May-15
				########
				$edits = $script->edit_count(20101115000000, 20110516000000);
				$script->condition(
					$edits >= 20,
					"has 20 edits between 15 November 2010 and 15 May 2011 (has {$edits})...",
					"does not have 20 edits between 15 November 2010 and 15 May 2011 (has {$edits}); edits can be combined across wikis.",
					"",
					"is-warn"
				);
				$editCountRecent += $edits;

				########
				## Exit conditions
				########
				$script->eligible = ($indefBlockCount <= 1 && $editCount >= 300 && $editCountRecent >= 20);

				/* no other accounts can be eligible */
				if ($script->user['editcount'] < 300) {
					$script->eligible = false;
					break;
				}
			}
			while (!$script->eligible && $script->get_next());
			
			break;


		############################
		## 2011 Commons Picture of the Year 2010
		############################
		case 16:
			$script->printWiki();
			$age_okay = false;
			$edits_okay = false;
			do {
				$script->eligible = true;
			
				########
				## registered < 2011-Jan-01
				########
				if(!$age_okay) {
					$age_okay = $script->condition(
						$date_okay = ($script->user['registration_raw'] < 20110101000000),
						"has an account registered before 01 January 2011 (registered {$script->user['registration']})...",
						"does not have an account registered before 01 January 2011 (registered {$script->user['registration']})."
					);
				}

				########
				## >= 200 edits before 2011-Jan-01
				########
				if(!$edits_okay) {
					$edits = $script->edit_count(NULL, 20110101000000);
					$edits_okay = $script->condition(
						$edits_okay = ($edits >= 200),
						"has 200 edits before 01 January 2011 (has {$edits})...",
						"does not have 200 edits before 01 January 2011 (has {$edits})."
					);
				}
				
				$script->eligible = ($age_okay && $edits_okay);
			}
			while (!$script->eligible && $script->get_next());
			break;
	
		############################
		## 2011 steward confirmations
		############################
		case 15:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Check local requirements
			########
			$script->printWiki();
			do {
				########
				## >=1 edits before 01 February 2011
				########
				$edits = $script->edit_count(NULL, 20110201000000);
				$script->condition(
					$edits >= 1,
					"has one edit before 01 February 2011 (has {$edits})...",
					"does not have one edit before 01 February 2011 (has {$edits})."
				);
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2011 steward elections
		############################
		case 14:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (<strong>optional</strong>)...",
				"",
				"is-warn"
			);
			$script->eligible = true;

			########
			## Check local requirements
			########
			$script->printWiki();

			/* set messages for global accounts */
			if ($script->unified) {
				$edits_fail_append = "However, edits will be combined with other unified wikis.";
				$edits_fail_attrs = "is-warn";
			}
			else {
				$edits_fail_append = '';
				$edits_fail_attrs = '';
			}

			/* check requirements */
			$prior_edits = 0;
			$recent_edits = 0;
			$combining = false;
			$marked_bot = false;
			do {
				########
				## Should not be a bot
				########
				$is_bot = $script->has_role('bot');
				$script->condition(
					!$is_bot,
					"no bot flag...",
					"has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
					"",
					"is-warn"
				);
				$script->eligible = true;
				if ($is_bot && !$marked_bot) {
					$script->event['append_eligible'] = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
					$marked_bot = true;
				}

				########
				## >=600 edits before 01 November 2010
				########
				$edits = $script->edit_count(NULL, 20101101000000);
				$prior_edits += $edits;
				$script->condition(
					$edits >= 600,
					"has 600 edits before 01 November 2010 (has {$edits})...",
					"does not have 600 edits before 01 November 2010 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}

				########
				## >=50 edits between 2010-Aug-01 and 2010-Jan-31
				########
				$edits = $script->edit_count(20100800000000, 20110200000000);
				$recent_edits += $edits;
				$script->condition(
					$edits >= 50,
					"has 50 edits between 01 August 2010 and 31 January 2011 (has {$edits})...",
					"does not have 50 edits between 01 August 2010 and 31 January 2011 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}


				########
				## Exit conditions
				########
				$script->eligible = $prior_edits >= 600 && $recent_edits >= 50;

				/* unified met requirements */
				if ($script->unified && !$script->IsQueueEmpty()) {
					if ($script->eligible) {
						break;
					}
					$combining = true;
				}
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2011 steward elections (candidates)
		############################
		case 13:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}


			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>..."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Check local requirements
			########
			/* check local requirements */
			$minDurationMet = false;
			$script->printWiki();
			do {
				$script->eligible = true;

				########
				## Registered before 2010-Mar-29
				########
				$script->condition(
					$script->user['registration_raw'] < 20100829000000,
					"has an account registered before 29 August 2010 (registered {$script->user['registration']})...",
					"does not have an account registered before 29 August 2010 (registered {$script->user['registration']})."
				);

				########
				## Must have been a sysop for three months
				########
				if (!$minDurationMet) {
					$months = $script->get_role_longest_duration('sysop', 20110129000000);
					$minDurationMet = $months >= 3;
					$script->condition(
						$minDurationMet,
						'was flagged as an administrator for a continuous period of at least three months before 29 January 2011 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
						'was not flagged as an administrator for a continuous period of at least three months before 29 January 2011 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
					);
				}
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2010 enwiki arbcom elections
		############################
		case 12:
			$script->printWiki();

			########
			## >=150 main-NS edits before 2010-Nov-02
			########
			/* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
			$script->db->Query(
				'SELECT data.count FROM ('
				. 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
				. 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
				. ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
				. ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
				Array($script->user['id'], 20101102000000)
			);
			$edits = $script->db->fetchColumn();

			$script->condition(
				$edits >= 150,
				"has 150 main-namespace edits on or before 01 November 2010 (has {$edits})...",
				"does not have 150 main-namespace edits on or before 01 November 2010 (has {$edits})."
			);
			

			########
			## Not currently blocked
			########
			$script->condition(
				!$script->currently_blocked(),
				"not currently blocked...",
				"must not be blocked during at least part of election (verify <a href='//en.wikipedia.org/wiki/Special:Log/block?user=" . urlencode($script->user['name']) . "' title='block log'>block log</a>)."
			);
			break;


		############################
		## 2010 steward elections, September
		############################
		case 11:
			$start_date = 20100901000000;

			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (<strong>optional</strong>)...",
				"",
				"is-warn"
			);


			########
			## Check local requirements
			########
			$script->printWiki();

			/* set messages for global accounts */
			if ($script->unified) {
				$edits_fail_append = "However, edits will be combined with other unified wikis.";
				$edits_fail_attrs = "is-warn";
			}
			else {
				$edits_fail_append = '';
				$edits_fail_attrs = '';
			}

			/* check requirements */
			$prior_edits = 0;
			$recent_edits = 0;
			$combining = false;
			$marked_bot = false;
			do {
				########
				## Should not be a bot
				########
				$script->condition(
					!$script->has_role('bot'),
					"no bot flag...",
					"has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
					"",
					"is-warn"
				);
				$script->eligible = true;
				if ($script->has_role('bot') && !$marked_bot) {
					$script->event['append_eligible'] = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
					$marked_bot = true;
				}


				########
				## >=600 edits before 01 June 2010
				########
				$edits = $script->edit_count(NULL, 20100601000000);
				$prior_edits += $edits;
				$script->condition(
					$edits >= 600,
					"has 600 edits before 01 June 2010 (has {$edits})...",
					"does not have 600 edits before 01 June 2010 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}

				########
				## >=50 edits between 2010-Mar-01 and 2010-Aug-31
				########
				$edits = $script->edit_count(20100300000000, 20100900000000);
				$recent_edits += $edits;
				$script->condition(
					$edits >= 50,
					"has 50 edits between 01 March 2010 and 31 August 2010 (has {$edits})...",
					"does not have 50 edits between 01 March 2010 and 31 August 2010 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}

				########
				## Exit conditions
				########
				$script->eligible = $prior_edits >= 600 && $recent_edits >= 50;

				/* unified met requirements */
				if ($script->unified && !$script->IsQueueEmpty()) {
					if ($script->eligible) {
						break;
					}
					$combining = true;
				}
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2010 steward elections, September (candidates)
		############################
		case 10:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}


			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>..."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Check local requirements
			########
			$script->printWiki();
			do {
				$script->eligible = true;

				########
				## Registered before 2010-Mar-29
				########
				$script->eligible = true;
				$script->condition(
					$script->user['registration_raw'] < 20100329000000,
					"has an account registered before 29 March 2010 (registered {$script->user['registration']})...",
					"does not have an account registered before 29 March 2010 (registered $script->user['registration'])."
				);
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2010 Commons Picture of the Year 2009
		############################
		case 9:
			$date_okay = false;
			$edits_okay = false;

			$script->printWiki();
			do {
				########
				## registered < 2010-Jan-01
				########
				if (!$date_okay) {
					$script->condition(
						$date_okay = ($script->user['registration_raw'] < 20100101000000),
						"has an account registered before 01 January 2010 (registered {$script->user['registration']})...",
						"does not have an account registered before 01 January 2010 (registered {$script->user['registration']})."
					);
				}

				########
				## >= 200 edits before 2010-Jan-16
				########
				if (!$edits_okay) {
					$edits = $script->edit_count(NULL, 20100116000000);
					$script->condition(
						$edits_okay = ($edits >= 200),
						"has 200 edits before 16 January 2010 (has {$edits})...",
						"does not have 200 edits before 16 January 2010 (has {$edits})."
					);
				}
			}
			while ((!$date_okay || !$edits_okay) && $script->get_next());
			$script->eligible = ($date_okay && $edits_okay);
			break;


		############################
		## 2010 steward elections, February
		############################
		case 8:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Has a global account (optional)
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (<strong>optional</strong>)...",
				"",
				"is-warn"
			);


			########
			## Check local requirements
			########
			$script->printWiki();

			/* set messages for global accounts */
			if ($script->unified) {
				$edits_fail_append = "However, edits will be combined with other unified wikis.";
				$edits_fail_attrs = "is-warn";
			}
			else {
				$edits_fail_append = '';
				$edits_fail_attrs = '';
			}

			/* check requirements */
			$prior_edits = 0;
			$recent_edits = 0;
			$combining = false;
			do {
				########
				## Should not be a bot
				########
				$script->condition(
					!$script->has_role('bot'),
					"no bot flag...",
					"has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
					"",
					"is-warn"
				);
				$script->eligible = true;
				if ($script->has_role('bot')) {
					$script->event['append_eligible'] = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
				}


				########
				## >=600 edits before 2009-Nov-01
				########
				$edits = $script->edit_count(NULL, 20091101000000);
				$prior_edits += $edits;
				$script->condition(
					$edits >= 600,
					"has 600 edits before 01 November 2009 (has {$edits})...",
					"does not have 600 edits before 01 November 2009 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}

				########
				## >=50 edits between 2009-Aug-01 and 2010-Jan-31
				########
				$edits = $script->edit_count(20090801000000, 20100200000000);
				$recent_edits += $edits;
				$script->condition(
					$edits >= 50,
					"has 50 edits between 01 August 2009 and 31 January 2010 (has {$edits})...",
					"does not have 50 edits between 01 August 2009 and 31 January 2010 (has {$edits}). {$edits_fail_append}",
					"",
					$edits_fail_attrs
				);
				if (!$script->eligible) {
					if (!$script->unified) {
						continue;
					}
					$combining = true;
				}

				########
				## Exit conditions
				########
				$script->eligible = $prior_edits >= 600 && $recent_edits >= 50;

				/* unified met requirements */
				if ($script->unified && !$script->IsQueueEmpty()) {
					if ($script->eligible) {
						break;
					}
					$combining = true;
				}
			}
			while (!$script->eligible && $script->get_next());


			########
			## Add requirement for non-unified accounts
			########
			if ($script->eligible && !$script->unified) {
				$script->event['more_reqs'][] = "If you do not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>, your user page on Meta must link to your main user page, and your main user page must link your Meta user page.";
			}


			########
			## Print message about combined edits
			########
			if ($script->eligible) {
				if ($script->unified && $combining) {
					$script->msg("<br />Met edit requirements.");
				}
			}
			else {
				if ($script->unified && $combining) {
					$script->msg("<br />Combined edits did not meet edit requirements.");
				}
				elseif ($script->is_global())
				{
					$script->event['append_ineligible'] = "<br /><strong>But wait!</strong> This is a global account, which is eligible to combine edits across wikis to meet the requirements. Select 'auto-select wiki' above to combine edits.";
				}
			}
			break;


		############################
		## 2010 steward elections, February (candidates)
		############################
		case 7:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}


			########
			## Has a global account
			########
			$script->condition(
				$script->is_global(),
				"has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
				"does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>..."
			);
			if (!$script->eligible) {
				break;
			}

			########
			## Check local requirements
			########
			$script->printWiki();
			do {
				$script->eligible = true;

				########
				## Registered before 2009-Oct-28
				########
				$script->eligible = true;
				$script->condition(
					$script->user['registration_raw'] < 20091029000000,
					"has an account registered before 29 October 2009 (registered {$script->user['registration']})...",
					"does not have an account registered before 29 October 2009 (registered $script->user['registration'])."
				);
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2010 global sysops vote
		############################
		case 6:
			$age_okay = false;
			$edits_okay = false;

			$script->printWiki();
			do {
				########
				## Registered (on any wiki) before 2009-Oct
				########
				if (!$age_okay) {
					$age_okay = $script->condition(
						$script->user['registration_raw'] <= 20091001000000,
						"has an account registered before 01 October 2009, on any wiki (registered {$script->user['registration']})...",
						"does not have an account registered before 01 October 2009, on any wiki (registered {$script->user['registration']})."
					);
				}


				########
				## >=150 edits (on any wiki) before start of vote (2010-Jan-01)
				########
				if (!$edits_okay) {
					$edits = $script->edit_count(NULL, 20100101000000);
					$edits_okay = $script->condition(
						$edits >= 150,
						"has 150 edits before 01 January 2010, on any wiki (has {$edits})...",
						"does not have 150 edits before 01 January 2010, on any wiki (has {$edits})."
					);

					if (!$edits_okay && $script->user['editcount'] < 150) {
						break;
					} // no other wiki can qualify (sorted by edit count)
				}
			}
			while ((!$age_okay || !$edits_okay) && $script->get_next());

			$script->eligible = $age_okay && $edits_okay;
			break;


		############################
		## 2009 enwiki arbcom elections
		############################
		case 5:
			$script->printWiki();

			########
			## >=150 main-NS edits before 2009-Nov-02
			########
			/* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
			$script->db->Query(
				'SELECT data.count FROM ('
				. 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
				. 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
				. ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
				. ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
				Array($script->user['id'], 20091102000000)
			);
			$edits = $script->db->fetchColumn();

			$script->condition(
				$edits >= 150,
				"has 150 main-namespace edits on or before 01 November 2009 (has {$edits}).",
				"does not have 150 main-namespace edits on or before 01 November 2009 (has {$edits})."
			);
			break;


		############################
		## 2009 Commons Picture of the Year 2008
		############################
		case 4:
			$script->printWiki();
			do {
				$script->eligible = true;

				########
				## registered < 2009-Jan-01
				########
				$script->condition(
					$script->user['registration_raw'] < 20090101000000,
					"has an account registered before 01 January 2009 (registered {$script->user['registration']})...",
					"does not have an account registered before 01 January 2009 (registered {$script->user['registration']})."
				);
				if (!$script->eligible) {
					continue;
				}

				########
				## >= 200 edits before 2009-Feb-12
				########
				$edits = $script->edit_count(NULL, 20090212000000);
				$script->condition(
					$edits >= 200,
					"has 200 edits before 12 February 2009 (has {$edits})...",
					"does not have 200 edits before 12 February 2009 (has {$edits})."
				);
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2009 steward elections (candidates)
		############################
		case 3:
			########
			## Has an account on Meta
			########
			$script->msg('Global requirements:', 'is-wiki');
			$script->condition(
				$script->has_account('metawiki_p'),
				"has an account on Meta...",
				"does not have an account on Meta."
			);
			if (!$script->eligible) {
				break;
			}


			########
			## Registered before 2008-Nov-01
			########
			$script->printWiki();
			do {
				$script->eligible = true;
				$script->condition(
					$script->user['registration_raw'] <= 20081101000000,
					"has an account registered before 01 November 2008 (registered {$script->user['registration']})...",
					"does not have an account registered before 01 November 2008 (registered $script->user['registration'])."
				);
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2009 steward elections
		############################
		case 2:
			########
			## Must not be blocked on Meta
			########
			$script->db->Connect('metawiki');
			$script->db->Query(
				'SELECT COUNT(ipb_expiry) FROM metawiki_p.ipblocks WHERE ipb_user=(SELECT user_id FROM metawiki_p.user WHERE user_name=? LIMIT 1) AND ipb_expiry="infinity" LIMIT 1',
				array($script->user['name'])
			);
			$script->condition(
				!$script->db->fetchColumn(),
				'is not blocked on Meta...',
				'is blocked on Meta.'
			);
			if (!$script->eligible) {
				break;
			}
			$script->db->ConnectPrevious();


			########
			## Check local requirements
			########
			$script->printWiki();
			do {
				$script->eligible = true;

				########
				## Must not be a bot
				########
				$script->condition(
					!$script->has_role('bot'),
					"no bot flag...",
					"has a bot flag."
				);
				if (!$script->eligible) {
					continue;
				}

				########
				## Registered before 20090101
				########
				$script->condition(
					$script->user['registration_raw'] <= 20090101000000,
					"has an account registered before 01 January 2009 (registered {$script->user['registration']})...",
					"does not have an account registered before 01 January 2009 (registered {$script->user['registration']})."
				);
				if (!$script->eligible) {
					continue;
				}

				########
				## >=600 edits before 2008-Nov-01
				########
				$edits = $script->edit_count(NULL, 20081101000000);
				$script->condition(
					$edits >= 600,
					"has 600 edits before 01 November 2008 (has {$edits})...",
					"does not have 600 edits before 01 November 2008 (has {$edits})."
				);
				if (!$script->eligible) {
					continue;
				}

				########
				## >=50 edits between 2008-Aug-01 and 2009-Jan-31
				########
				$edits = $script->edit_count(20080801000000, 20090131000000);
				$script->condition(
					$edits >= 50,
					"has 50 edits between 01 August 2008 and 31 January 2009 (has {$edits})...",
					"does not have 50 edits between 01 August 2008 and 31 January 2009 (has {$edits})."
				);

				/* exit if no other account can qualify */
				if ($script->user['editcount'] < 600) {
					break;
				}
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## 2008 enwiki arbcom elections
		############################
		case 1:
			$script->printWiki();

			########
			## >=150 main-NS edits before 2008-Nov-02
			########
			/* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
			$script->db->Query(
				'SELECT data.count FROM ('
				. 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
				. 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
				. ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
				. ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
				Array($script->user['id'], 20081102000000)
			);

			$edits = $script->db->fetchColumn();
			$script->condition(
				$edits >= 150,
				"has 150 main-namespace edits on or before 01 November 2008 (has {$edits}).",
				"does not have 150 main-namespace edits on or before 01 November 2008 (has {$edits})."
			);

			break;


		############################
		## 2008 Board elections
		############################
		case 0:
			$script->printWiki();
			do {
				$script->eligible = true;

				########
				## Not indefinitely blocked
				########
				$script->condition(
					!$script->indef_blocked(),
					"not indefinitely blocked...",
					"indefinitely blocked."
				);
				if (!$script->eligible) {
					continue;
				}

				########
				## Not a bot
				########
				$script->condition(
					!$script->has_role('bot'),
					"no bot flag...",
					"has a bot flag."
				);
				if (!$script->eligible) {
					continue;
				}

				########
				## >=600 edits before 2008-Mar
				########
				$edits = $script->edit_count(NULL, 20080301000000);
				$script->condition(
					$edits >= 600,
					"has 600 edits before 01 March 2008 (has {$edits})...",
					"does not have 600 edits before 01 March 2008 (has {$edits})."
				);
				if (!$script->eligible) {
					continue;
				}

				########
				## >=50 edits between 2008-Jan and 2008-Jun
				########
				$edits = $script->edit_count(20080101000000, 20080529000000);
				$script->condition(
					$edits >= 50,
					"has 50 edits between 01 January 2008 and 29 May 2008 (has {$edits})...",
					"does not have 50 edits between 01 January 2008 and 29 May 2008 (has {$edits})."
				);

				########
				## Exit conditions
				########
				/* eligible */
				if ($script->eligible) {
					break;
				}

				/* no other accounts can be eligible */
				if ($script->user['editcount'] < 600) {
					$script->eligible = false;
					break;
				}
			}
			while (!$script->eligible && $script->get_next());
			break;


		############################
		## No such event
		############################
		default:
			echo '<div class="fail">No such event.</div>';
	}


	############################
	## Print result
	############################
	if ($script->event) {
		########
		## Script results
		########
		$event = $script->event;
		$action = isset($event['action']) ? $event['action'] : 'vote';
		$class = $script->eligible ? 'success' : 'fail';
		$name = $script->user['name'] . ($script->unified ? '' : '@' . $script->wiki['domain']);

		echo
			'<h3>Result</h3>',
			'<div class="', $class, '" id="result" data-is-eligible="', ($script->eligible ? 1 : 0), '">',
			$script->formatText($name), ' is ', ($script->eligible ? '' : 'not '), 'eligible to ', $action, ' in the <a href="', $event['url'], '" title="', $backend->formatValue($event['name']), '">', $event['name'], '</a> in ', $event['year'], '. ';
		if ($script->eligible && isset($script->event['append_eligible']))
			echo $script->event['append_eligible'];
		elseif (!$script->eligible && isset($script->event['append_ineligible']))
			echo $script->event['append_ineligible'];
		echo '</div>';
		echo '<small>See also: <a href="', $backend->url('/stalktoy/' . urlencode($script->user['name'])), '" title="global account details">global account details</a></small>.';


		########
		## Mention additional requirements
		########
		if ($script->eligible && isset($script->event['more_reqs'])) {
			echo '<div class="error" style="border-color:#CC0;"><strong>There are additional requirements</strong> that cannot be checked by this script:<ul style="margin:0;">';
			foreach ($script->event['more_reqs'] as $req)
				echo '<li>', $req, '</li>';
			echo '</ul></div>';
		}
		elseif(!$script->eligible) {
			if(isset($script->event['exceptions'])) {
				echo '<div class="neutral"><strong>This account might be eligible if it fits rule exceptions that cannot be checked by this script:<ul style="margin:0;">';
				foreach ($script->event['exceptions'] as $exc)
					echo '<li>{$exc}</li>';
				echo '</ul></div>';
			}
			if(isset($script->event['warn_ineligible']))
				echo '<div class="error" style="border-color:#CC0;">', $script->event['warn_ineligible'], '</div>';
		}
	}

	/* exit loop */
	break;
}

if($script->user['name'])
	echo '</div>';


/* globals, templates */
$backend->footer();
?>

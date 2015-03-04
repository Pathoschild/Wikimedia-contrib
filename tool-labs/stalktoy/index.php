<?php
require_once( '../backend/modules/Backend.php' );
require_once( '../backend/modules/IP.php' );
require_once( '../backend/modules/Form.php' );
require_once( 'Stalktoy.php');
$backend = Backend::create('Stalk toy', 'View global details about a user across all Wikimedia wikis. You can provide an account name (like <a href="/meta/stalktoy/Pathoschild" title="view result for Pathoschild"><tt>Pathoschild</tt></a>), an IPv4 address (like <a href="/meta/stalktoy/127.0.0.1" title="view result for 127.0.0.1"><tt>127.0.0.1</tt></a>), an IPv6 address (like <a href="/meta/stalktoy/2001:db8:1234::" title="view result for 2001:db8:1234::"><tt>2001:db8:1234::</tt></a>), or a CIDR block (like <a href="/meta/stalktoy/212.75.0.1/16" title="view result for 212.75.0.1/16"><tt>212.75.0.1/16</tt></a> or <a href="/meta/stalktoy/2600:3C00::/48" title="view result for 2600:3C00::/48"><tt>2600:3C00::/48</tt></a>).')
	->link( '/stalktoy/stylesheet.css' )
	->link( '/content/jquery.tablesorter.js' )
	->link( 'https://www.google.com/jsapi', 'js' )
	->link( '/stalktoy/scripts.js' )
	->header();

/**
 * Implements logic for the Stalktoy tool.
 */
class StalktoyScript extends Base {
	#############################
	## Properties
	#############################
	public $target;
	public $target_wiki_url;
	public $target_url;

	/**
	 * A lookup hash of wiki data.
	 * @var Wiki[]
	 */
	public $wikis;

	/**
	 * A lookup hash of wiki domains.
	 * @var string[]
	 */
	public $domains;

	public $wiki;
	public $show_all_wikis = false;       // in account mode, display wikis the user isn't on?
	public $show_groups_per_wiki = false; // in account mode, list relevant global groups for each wiki
	public $db;


	#############################
	## Public methods
	#############################
	/**
	 * Construct an instance.
	 * @param {Backend} $backend The toolserver backend framework.
	 * @param {string} $target The username or IP address to analyze.
	 */
	public function __construct( $backend, $target ) {
		if(!$target)
			return;

		/* instantiate objects */
		$this->db = $backend->GetDatabase( Toolserver::ERROR_PRINT );
		$this->db->Connect('metawiki');

		/* store target (name, address, or range) */
		$this->target = $this->formatUsername( $target );
		$this->target_url  = urlencode( $this->target );
		$this->target_wiki_url = str_replace( '+', '_', $this->target_url );

		/* fetch wikis */
		$this->domains = $this->db->getDomains();
		$this->wikis = $this->db->getWikis();
	}

	/**
	 * Whether there is a username or IP address to analyze.
	 */
	public function isValid() {
		return !!$this->target;
	}

	/**
	 * Set the current wiki to analyze.
	 * @param {string} $wiki The database name of the wiki to analyze.
	 */
	public function setWiki( $wiki ) {
		$this->wiki = $wiki;
		$this->db->Connect( $wiki );
		$this->local = Array();
	}

	/**
	 * Get details about a global account.
	 * @param string $target The username for which to fetch details.
	 */
	public function getGlobal($target) {
		// fetch details
		$row = $this->db->Query(
			'SELECT gu_id, gu_name, DATE_FORMAT(gu_registration, "%Y-%m-%d %H:%i") AS gu_timestamp, gu_locked, gu_hidden, GROUP_CONCAT(gug_group SEPARATOR ",") AS gu_groups, lu_wiki FROM centralauth_p.globaluser LEFT JOIN centralauth_p.global_user_groups ON gu_id = gug_user LEFT JOIN centralauth_p.localuser ON lu_name = ? AND lu_attached_method IN ("primary", "new") WHERE gu_name = ? LIMIT 1',
			array( $target, $target )
		)->fetchAssoc();

		// create model
		$account = new Stalktoy\GlobalAccount();
		$account->exists = isset($row['gu_id']);
		if($account->exists) {
			$account->id = $row['gu_id'];
			$account->name = $row['gu_name'];
			$account->isHidden = $row['gu_hidden'];
			$account->isLocked = $row['gu_locked'];
			$account->registered = $row['gu_timestamp'];
			$account->groups = ($row['gu_groups'] ? explode(',', $row['gu_groups']) : array());
			$account->homeWiki = $row['lu_wiki'];
			$account->wikis = $this->db->getUnifiedWikis($this->target);
			$account->wikiHash = array_flip($account->wikis);
		}
		return $account;
	}

	/**
	 * Get the user's global groups that apply for each wiki.
	 * @param int $id The user's global account ID.
	 * @param string[] $wikis The database names of the wikis on which the user's account is unified.
	 * @returns array An array of groups in the form array(dbname => string[]).
	 */
	public function getGlobalGroupsByWiki($id, $wikis) {
		// fetch details
		$rows = $this->db->Query(
			'SELECT gug_group, ws_type, ws_wikis FROM centralauth_p.global_user_groups LEFT JOIN centralauth_p.global_group_restrictions ON gug_group = ggr_group LEFT JOIN centralauth_p.wikiset ON ggr_set = ws_id WHERE gug_user = ?',
			array( $id )
		)->fetchAllAssoc();

		// extract groups for each wiki
		$groups = array();
		foreach($wikis as $wiki)
			$groups[$wiki] = array();
		foreach($rows as $row) {
			// prettify name
			$group = str_replace('_', ' ', $row['gug_group']);

			// parse opt-in or opt-out list
			$optList = array();
			if($row['ws_wikis'] != NULL) {
				$list = explode(',', $row['ws_wikis']);
				foreach($list as $wiki)
					$optList[] = $wiki;
			}

			// apply groups
			switch($row['ws_type']) {
				// all wikis
				case NULL:
					foreach($wikis as $wiki)
						$groups[$wiki][] = $group;
					break;

				// some wikis
				case 'optin':
					foreach($optList as $wiki) 
						$groups[$wiki][] = $group;
					break;

				// all except some wikis
				case 'optout':
					$optout = array_flip($optList);
					foreach($wikis as $wiki) {
						if(!isset($optout[$wiki]))
							$groups[$wiki][] = $group;
					}
					break;
			}
		}
		return $groups;
	}

	/**
	 * Get global details about an IP address or range.
	 * @param Database $db The database from which to fetch details.
	 * @param string $user The IP address or range for which to fetch details.
	 */
	public function getGlobalIP($db, $target) {
		$ip = new Stalktoy\GlobalIP();

		// fetch IP address
		$ip->ip = new IPAddress($target);
		if(!$ip->ip->isValid())
			return $ip;

		// fetch global blocks
		$ip->globalBlocks = array();
		$start = $ip->ip->getEncoded(IPAddress::START);
		$end = $ip->ip->getEncoded(IPAddress::END);
		$query = $this->db->Query(
			'SELECT gb_address, gb_by, gb_reason, DATE_FORMAT(gb_timestamp, "%Y-%b-%d") AS timestamp, gb_anon_only, DATE_FORMAT(gb_expiry, "%Y-%b-%d") AS expiry FROM centralauth_p.globalblocks WHERE (gb_range_start <= ? AND gb_range_end >= ?) OR (gb_range_start >= ? AND gb_range_end <= ?) ORDER BY gb_timestamp',
			array($start, $end, $start, $end)
		)->fetchAllAssoc();

		foreach( $query as $row ) {
			$block = new Stalktoy\Block();
			$block->by = $row['gb_by'];
			$block->target = $row['gb_address'];
			$block->timestamp = $row['timestamp'];
			$block->expiry = $row['expiry'];
			$block->reason = $row['gb_reason'];
			$block->anonOnly = $row['gb_anon_only'];
			$block->isHidden = false;
			$ip->globalBlocks[] = $block;
		}

		return $ip;
	}

	/**
	 * Get details about a local account.
	 * @param Database $db The database from which to fetch details.
	 * @param string $user The name of the user for which to fetch local details.
	 * @param Wiki $wiki The wiki on which the account is being fetched.
	 */
	public function getLocal($db, $userName, $isUnified, $wiki) {
		// fetch details
		$row = $db->Query(
			'SELECT user_id, user_registration, DATE_FORMAT(user_registration, "%Y-%m-%d %H:%i") as registration, user_editcount, GROUP_CONCAT(ug_group SEPARATOR ", ") AS user_groups, ipb_by_text, ipb_reason, DATE_FORMAT(ipb_timestamp, "%Y-%m-%d %H:%i") AS ipb_timestamp, ipb_deleted, COALESCE(DATE_FORMAT(ipb_expiry, "%Y-%m-%d %H:%i"), ipb_expiry) AS ipb_expiry FROM user LEFT JOIN user_groups ON user_id = ug_user LEFT JOIN ipblocks ON user_id = ipb_user WHERE user_name = ? LIMIT 1',
			array( $userName )
		)->fetchAssoc();

		// build model
		$account = new Stalktoy\LocalAccount();
		$account->exists = isset($row['user_id']);
		$account->wiki = $wiki;
		if($account->exists) {
			// account details
			$account->id = $row['user_id'];
			$account->registered = $row['registration'];
			$account->registeredRaw = $row['user_registration'];
			$account->editCount = $row['user_editcount'];
			$account->groups = $row['user_groups'];
			$account->isUnified = $isUnified;

			// handle edge cases with older accounts
			if( !$account->registeredRaw ) {
				$date = $db->getRegistrationDate( $account->id );
				$account->registered = $date['formatted'];
				$account->registeredRaw = $date['raw'];
			}

			// block details
			$account->isBlocked = isset($row['ipb_timestamp']);
			if($account->isBlocked) {
				$account->block = new Stalktoy\Block();
				$account->block->by = $row['ipb_by_text'];
				$account->block->target = $userName;
				$account->block->reason = $row['ipb_reason'];
				$account->block->timestamp = $row['ipb_timestamp'];
				$account->block->isHidden = $row['ipb_deleted'];
				$account->block->expiry = $row['ipb_expiry'];
			}
		}

		return $account;
	}

	########
	## Get hash of local IP blocks
	########
	/**
	 * Get a list of local blocks against editing by this IP address.
	 * @return Stalktoy\Block[];
	 */
	public function getLocalIPBlocks($ip) {
		// get blocks
		$start = $ip->ip->getEncoded(IPAddress::START);
		$end = $ip->ip->getEncoded(IPAddress::END);
		$query = $this->db->Query(
			'SELECT ipb_by_text, ipb_address, ipb_reason, DATE_FORMAT(ipb_timestamp, "%Y-%b-%d") AS timestamp, DATE_FORMAT(ipb_expiry, "%Y-%b-%d") AS expiry, ipb_anon_only FROM ipblocks WHERE (ipb_range_start <= ? AND ipb_range_end >= ?) OR (ipb_range_start >= ? AND ipb_range_end <= ?)',
			array( $start, $end, $start, $end )
		)->fetchAllAssoc();

		// build model
		$blocks = Array();
		foreach( $query as $row ) {
			$block = new Stalktoy\Block();
			$block->by = $row['ipb_by_text'];
			$block->target = $row['ipb_address'];
			$block->reason = $row['ipb_reason'];
			$block->timestamp = $row['timestamp'];
			$block->expiry = $row['expiry'];
			$block->anonOnly = $row['ipb_anon_only'];
			$block->isHidden = false;
			$blocks[] = $block;
		}

		return $blocks;
	}


	#############################
	## Output functions
	#############################
	########
	## Link if domain known
	########
	function link( $domain, $title, $text = NULL ) {
		if( $text === NULL )
			$text = $title;

		if( !$domain )
			return $text;
		else
			return "<a href='//{$domain}/wiki/$title' title='$title'>$text</a>";
	}

	########
	## Parse wikilinks in reason
	########
	function parse_reason( $text, $domain ) {
		if( !preg_match_all('/\[\[([^\]]+)\]\]/', $text, $links) )
			return $text;

		foreach( $links[1] as $i => $link ) {
			$pieces = explode( '|', $link );
			$link_target = $pieces[0];
			$link_text   = isset($pieces[1]) ? $pieces[1] : $link_target;

			$text = str_replace( $links[0][$i], "<a href='//{$domain}/wiki/{$link_target}' title='{$link_text}'>{$link_text}</a>", $text );
		}

		return $text;
	}
}


#############################
## Model
#############################
/**
 * Represents strongly-typed details about a global user.
 */
class StalktoyUserModel {
	/* global details */
	public $userName = 3;
}

#############################
## Instantiate script engine
############################# 
$backend->profiler->start('initialize');
$target_form = '';

# parse target
# stalktoy is an edge case for route values: an IP range like '127.0.0.1/16' should be treated as one value despite the path separator.
$target = $backend->get('target');
if($target == NULL) {
	$target = $backend->getRouteValue();
	if($target != NULL && $backend->getRouteValue(2) != NULL)
		$target .= '/' . $backend->getRouteValue(2);
}

# initialise
$script = new StalktoyScript( $backend, $target );
$script->show_all_wikis = $backend->get('show_all_wikis', false);
$script->show_groups_per_wiki = $backend->get('global_groups_per_wiki', false);
$deletedGlobalGroups = array('Cabal');

$backend->profiler->stop('initialize');

#############################
## Input form
#############################
$target_form = '';
if( $script->isValid() )
	$target_form = $backend->formatValue($script->target);
echo '
	<p>Who shall we stalk?</p>
	<form action="', $backend->url('/stalktoy/'), '" method="get">
		<div>
			<input type="text" name="target" value="', $target_form, '" />
			<input type="submit" value="Analyze »" /> <br />

			', Form::Checkbox( 'show_all_wikis', $script->show_all_wikis ), '
			<label for="show_all_wikis">Show wikis where account is not registered.</label><br />
			', Form::Checkbox( 'global_groups_per_wiki', $script->show_groups_per_wiki ), '
			<label for="global_groups_per_wiki">Show relevant global groups for each wiki.</label><br />
		</div>
	</form>';

#############################
## Process data (IP / CIDR)
#############################
$ip = $script->getGlobalIP($script->db, $script->target);
if( $script->isValid() && $ip->ip->isValid() ) {
	########
	## Fetch data
	########
	/* global data */
	$backend->profiler->start('fetch global');
	$global = Array(
		'wikis'        => $script->wikis,
		'ip'           => $ip,
		'pretty_range' => $ip->ip->getFriendly(IPAddress::START) . ' &mdash; ' . $ip->ip->getFriendly(IPAddress::END)
	);
	$backend->profiler->stop('fetch global');

	/* local data */
	$backend->profiler->start('fetch local');
	foreach( $global['wikis'] as $wiki => $wikiData ) {
		$script->setWiki( $wiki );
		$localBlocks[$wiki] = $script->getLocalIPBlocks($ip);
	}
	$backend->profiler->stop('fetch local');


	########
	## Output
	########
	$backend->profiler->start('output');
	echo '<div class="result-box">
	<h3>', ($ip->ip->isIPv4() ? 'IPv4' : 'IPv6'), ($ip->ip->isRange() ? ' range' : ' address'), '</h3>',
	($ip->ip->isRange() ? '<b>' . $global['pretty_range'] . '</b><br />' : '');
	if( $global['ip']->globalBlocks ) {
		echo '
			<fieldset>
				<legend>Global blocks</legend>
				<ul>';
		foreach( $global['ip']->globalBlocks as $block ) {
			$by_url = urlencode( $block->by );
			$reason = $script->parse_reason( $block->reason, 'meta.wikimedia.org' );
			echo '
					<li>', $block->timestamp, ' &mdash; ', $block->expiry, ':',
				' <b>', $block->target, '</b> globally blocked by',
				' <a href="//meta.wikimedia.org/wiki/user:', $by_url, '">', $block->by, '</a>',
				' (<small>', $reason, '</small>)</li>';
		}
		echo '
				</ul>
			</fieldset>';
	} else
		echo '<em>No global blocks.</em><br />';


	########
	## Steward tools
	########
	echo '
		<div>
			Related toys:
			<a href="http://www.sixxs.net/tools/whois/?handle=', urlencode($ip->ip->getFriendly()), '" title="whois query">whois</a>,
			<a href="//meta.wikimedia.org/wiki/Special:GlobalBlock?wpAddress=', $script->target_wiki_url, '" title="Special:GlobalBlock">global block</a>.
		</div>';


	########
	## Local results
	########
	/* print header */
	echo '
		<h4>Local blocks</h4>
		<table class="pretty sortable" id="local-ips">
			<thead>
				<tr>
					<th>wiki</th>
					<th>blocked</th>
				</tr>
			</thead>
			<tbody>';

		/* print each row */
		foreach( $global['wikis'] as $wiki => $wikiData ) {
			$domain = $wikiData->domain;
			$blocked   = (int)(bool)$localBlocks[$wiki];
			$link_wiki = $script->link( $domain, 'user:' . $script->target_wiki_url, $domain );

			echo '
				<tr data-open="', (int)!$wikiData->isClosed, '" data-blocked="', (int)$blocked, '">
					<td class="wiki">', $link_wiki, '</td>
					<td class="blocks">';
			if( $localBlocks[$wiki] ) {
				foreach( $localBlocks[$wiki] as $block ) {
					$reason = $script->parse_reason( $block->reason, $domain );
					echo '<span class="is-block-start">', $block->timestamp, '</span> &mdash; <span class="is-block-end">', $block->expiry, '</span>: <b>', $block->target, '</b> blocked by <span class="is-block-admin">', $block->by, '</span> (<span class="is-block-reason">', $reason, '</span>)<br />';
				}
			}
			echo "
					</td>
				</tr>";
		}

		/* print footer */
		echo "
			</tbody>
		</table></div>\n";
	$backend->profiler->stop('output');
}

#############################
## Process data (user)
#############################
else if( $script->isValid() && $script->target ) {
	#######
	## Fetch data
	########
	/* global details */
	$backend->profiler->start('fetch global account');
	$account = $script->getGlobal($script->target);
	if( $account->exists ) {
		$stats = Array(
			'wikis'      => 0,
			'edit_count' => 0,
			'most_edits' => -1,
			'most_edits_domain' => NULL,
			'oldest' => NULL,
			'oldest_raw' => 999999999999999,
			'oldest_domain' => NULL
		);
	}
	else {
		$stats = Array(
			'most_edits'        => -1,
			'most_edits_domain' => NULL
		);
	}
	$backend->profiler->stop('fetch global account');

	/* global groups */
	$globalGroupsByWiki = array();
	if($account->exists && $account->groups) {
		$backend->profiler->start('fetch global groups by wiki');
		$globalGroupsByWiki = $script->getGlobalGroupsByWiki($account->id, $account->wikis);
		$backend->profiler->stop('fetch global groups by wiki');
	}

	/* local details */
	$backend->profiler->start('fetch local accounts');
	$local = array();
	foreach( $script->wikis as $wiki => $wikiData ) {
		$domain = $wikiData->domain;
		$script->setWiki( $wiki );
		$localAccount = $script->getLocal($script->db, $script->target, isset($account->wikiHash[$wiki]), $wikiData);

		if($localAccount->exists || $script->show_all_wikis)
			$local[$wiki] = $localAccount;

		if($localAccount->exists) {
			/* statistics used even when no global account */
			if( $localAccount->editCount > $stats['most_edits'] ) {
				$stats['most_edits'] = $localAccount->editCount;
				$stats['most_edits_domain'] = $domain;
			}

			/* statistics shown only for global account */
			if( $account->exists && $localAccount->exists ) {
				$stats['wikis']++;
				$stats['edit_count'] += $localAccount->editCount;
				if( $localAccount->registeredRaw && $localAccount->registeredRaw < $stats['oldest_raw'] ) {
					$stats['oldest'] = $localAccount->registered;
					$stats['oldest_raw'] = $localAccount->registeredRaw;
					$stats['oldest_domain'] = $domain;
				}
			}
		}
	}
	$backend->profiler->stop('fetch local accounts');

	/* best guess for pre-2005 oldest account */
	if( $account->exists ) {
		if( !$stats['oldest'] || !$local[$account->homeWiki]->registeredRaw ) {
			$homeWiki = $local[$account->homeWiki];
			$stats['oldest'] = $homeWiki->registered;
			$stats['oldest_raw'] = $homeWiki->registeredRaw;
			$stats['oldest_domain'] = $homeWiki->wiki->domain;
		}
	}


	#######
	## Output global details
	########
	$backend->profiler->start('output');
	echo "
		<div class='result-box'>
		<h3>Global account</h3>\n";
	echo '<div class="is-global-details"',
		' data-is-global="', ($account->exists ? '1' : '0'), '"';
	if($account->exists) {
		echo ' data-home-wiki="', $backend->formatValue($account->homeWiki), '"',
		' data-status="', ($account->isLocked && $account->isHidden ? 'locked, hidden' : ($account->isLocked ? 'locked' : ($account->isHidden ? 'hidden' : 'okay'))), '"',
		' data-id="', $account->id, '"',
		' data-registered="', $account->registered, '"',
		' data-groups="', $backend->formatValue(implode(', ', $account->groups)), '"';
	}
	echo '>';
	if( $account->exists ) {
		$globalGroups = '&mdash;';
		if($account->groups) {
			$globalGroups = array();
			foreach($account->groups as $group) {
				$globalGroups[] = in_array($group, $deletedGlobalGroups)
					? $backend->formatValue($group)
					:  '<a href="' . $backend->url('/globalgroups') . '#' . $backend->formatAnchor($group) . '" title="View global group details">' . $backend->formatValue(str_replace('_', ' ', $group)) . '</a>';
			}
			$globalGroups = implode(', ', $globalGroups);
		}

		echo "
			<table class='plain'>
				<tr>
					<td>Home wiki:</td>";
		if( $account->homeWiki )
			echo "<td><b><a href='//{$script->wikis[$account->homeWiki]->domain}/wiki/user:{$script->target_wiki_url}' title='home wiki'>{$script->wikis[$account->homeWiki]->domain}</a></b></td>";
		else
			echo "<td><b>unknown</b> <small>(The main account may be <a href='//meta.wikimedia.org/wiki/Oversight' title='about hiding user names'>hidden</a> or renamed, or the data <a href='//wiki.toolserver.org/view/Replication_lag' title='about replication lag'>might not be replicated yet</a>.)</small></td>";
		echo "
				</tr>
				<tr>
					<td>Status:</td>
					<td>";
		if( $account->isLocked || $account->isHidden ) {
			if( $account->isLocked )
				echo "<span class='bad'>Locked</span> ";
			if( $account->isHidden )
				echo "<span class='bad'>Hidden</span>";
		}
		else if( $script->target == 'Shanel' )
			echo "<span class='good'>&nbsp;&hearts;&nbsp;</span>";
		else
			echo "<span class='good'>okay</span>";
		echo '
				</tr>
				<td>Registered:</td>
					<td><b>', $account->registered, ' <span class="account-id">(account #', $account->id, ')</span></b></td>
				</tr>
				<tr>
						<td>Groups:</td>
					<td><b>', $globalGroups, '</b></td>
				</tr>
				<tr>
					<td style="vertical-align:top;">Statistics:</td>
					<td>
						', $stats['edit_count'], ' edits on ', $stats['wikis'], ' wikis.<br />
						Most edits on <a href="//', $stats['most_edits_domain'], '/wiki/Special:Contributions/', $script->target_wiki_url, '">', $stats['most_edits_domain'], '</a> (', $stats['most_edits'], ').<br />
						Oldest account on <a href="//', $stats['oldest_domain'], '/wiki/user:', $script->target_wiki_url, '">', $stats['oldest_domain'], '</a> (', ( $stats['oldest'] ? $stats['oldest'] : '2005 or earlier, so probably inaccurate; registration date was not stored until late 2005' ), ').
						<div id="account-visualizations"><br clear="all" /></div>
					</td>
				</tr>
			</table>
			See also
			<a href="', $backend->url('/crossactivity/' . $script->target_url), '" title="recent activity">recent activity</a>,
			<a href="', $backend->url('/userpages/' . $script->target_url), '" title="user pages">user pages</a>,
			<a href="//meta.wikimedia.org/wiki/Special:CentralAuth/', $script->target_wiki_url, '" title="Special:CentralAuth">global user manager</a>.
			';
	}
	else
		echo '<div class="neutral">There is no global account with this name, or it has been <a href="//meta.wikimedia.org/wiki/Oversight" title="about hiding user names">globally hidden</a>.</div>';
	echo '</div>';


	########
	## Output local wikis
	########
	echo "<h3>Local accounts</h3>\n";

	if( count($local) ) {
		/* precompile */
		$label_unified_strs = Array( 'local', 'unified' );

		/* output */
		echo '
			<table class="pretty sortable" id="local-accounts">
	 			<thead>
					<tr>
	 					<th>wiki</th>
	 					<th>edits</th>
	 					<th>registered</th>
	 					<th>groups</th>',
						($script->show_groups_per_wiki && $globalGroupsByWiki ? '<th>global groups</th>' : ''),
						'<th><a href="//meta.wikimedia.org/wiki/Help:Unified_login" title="about unified login">unified login</a></th>
						<th>block</th>
	 				</tr>
				</thead>
				<tbody>';

		foreach( $local as $dbname => $user ) {
			########
			## Prepare strings
			########
			$wiki = $user->wiki;
			$link_wiki  = $script->link($wiki->domain, 'User:' . $script->target_wiki_url, $wiki->domain);

			/* user exists */
			if( $user->exists ) {
				$link_edits = $script->link( $wiki->domain, 'Special:Contributions/' . $script->target_wiki_url, '&nbsp;' . $user->editCount . '&nbsp;' );
				$has_groups = (int)(bool)$user->groups;
				$is_blocked = (int)$user->isBlocked;
				$is_hidden  = (int)($is_blocked && $user->block->isHidden);
				$is_unified = (int)$user->isUnified;
				$label_unified = $label_unified_strs[$is_unified];
				$hasGlobalGroups = $globalGroupsByWiki && isset($globalGroupsByWiki[$wiki->dbName]) && $globalGroupsByWiki[$wiki->dbName];;
				$globalGroups = $hasGlobalGroups
					? implode(', ', $globalGroupsByWiki[$wiki->dbName])
					: '&nbsp;';

				if( $user->isBlocked ) {
					$reason = $script->parse_reason($user->block->reason, $wiki->domain );
					$block_summary = "<span class=\"is-block-start\">{$user->block->timestamp}</span> &mdash; <span class=\"is-block-end\">{$user->block->expiry}</span>: blocked by <span class=\"is-block-admin\">{$user->block->by}</span> (<span class=\"is-block-reason\">{$reason}</span>)";
				}
				else
					$block_summary = '&nbsp;';
			}

			/* user doesn't exist */
			else {
				$link_edits = '&nbsp;';
				$has_groups = 0;
				$is_blocked = 0;
				$is_hidden  = 0;
				$is_unified = 0;
				$label_unified = 'no such user';
				$block_summary = '&nbsp;';
				$globalGroups = '&nbsp;';
			}

			########
			## Output
			########
			$family = $wiki->family;
			if($wiki->name == 'sourceswiki')
				$family = 'wikisource';
			echo '
				<tr data-wiki="', $wiki->name, '" data-domain="', $wiki->domain, '" data-lang="', ($wiki->isMultilingual ? 'multilingual' : $wiki->lang) ,'" data-family="', $family, '" data-open="', (int)!$wiki->isClosed, '" data-exists="', (int)(bool)$user->exists, '" data-edits="', $user->editCount, '" data-groups="', (int)$has_groups, '" data-global-groups="', (int)($hasGlobalGroups), '" data-registered="', $user->registered, '" data-unified="', (int)$user->isUnified, '" data-blocked="', (int)$user->isBlocked, '">
					<td class="wiki">', $link_wiki, '</td>
					<td class="edit-count">', $link_edits, '</td>
					<td class="timestamp">', $user->registered, '</td>
					<td class="groups">', $user->groups, '</td>',
					($script->show_groups_per_wiki && $globalGroupsByWiki ? '<td class="global-groups">' . $globalGroups . '</td>' : ''),
					'<td class="unification">', $label_unified, '</td>
					<td class="blocks">', $block_summary, '</td>
				</tr>', "\n";
		}
		echo '</tbody></table></div>';
	}
	else
		echo "<div class='error'>There are no local accounts with this name.</div>\n";
	$backend->profiler->stop('output');
}

$backend->footer();

<?php
require_once( '../backend/modules/Backend.php' );
require_once( '../backend/modules/IP.php' );
require_once( '../backend/modules/Form.php' );
$backend = Backend::create('Stalk toy', 'Provides comprehensive information about the given user, IP address, or CIDR range on all Wikimedia wikis.')
	->link( 'stylesheet.css' )
	->link('../backend/content/jquery.tablesorter.js')
	->addScript('
		$(document).ready(function() { 
			$(\'#local-ips, #local-accounts\').tablesorter({sortList:[[1,1]]});
		});
	')
	->header();

#############################
## Script methods
#############################
class Stalktoy extends Base {
	#############################
	## Properties
	#############################
	public $target;
	public $target_wiki_url;
	public $target_url;
	public $global_blocks;
	
	public $wikis;
	public $wiki;
	public $show_all_wikis = false;    // in account mode, display wikis the user isn't on?
	public $show_closed_wikis = false; // also list wikis that are locked or closed?
	
	public $ip;
	public $db;

	private $ip_hex_start;
	private $ip_hex_end;
	private $global_id;
	private $global_timestamp;
	private $global_locked;
	private $global_hidden;
	private $global_home_wiki = NULL;
	private $global_groups    = NULL;
	private $global_wikis     = NULL;
	private $local = NULL;
	

	#############################
	## Constructor
	#############################
	public function __construct( $backend, $target ) {
		/* instantiate objects */
		$this->ip = new IP( $target );
		$this->db = $backend->GetDatabase( Toolserver::ERROR_PRINT );
		$this->db->Connect('metawiki_p');

		/* store target (name, address, or range) */
		$this->target = $this->FormatUsername( $target );
		$this->target_url  = urlencode( $this->target );
		$this->target_wiki_url = str_replace( '+', '_', $this->target_url );
		
		
		/* fetch wikis */
		$this->wikis = $this->db->getDomains();
		
		/* precompile data */
		if( $this->ip->valid() ) {
			$this->ip_hex_start = $this->ip->hex();
			$this->ip_hex_end   = $this->ip->hex( IP::END );
		}
	}


	#############################
	## State methods
	#############################
	public function set_wiki( $wiki ) {
		$this->wiki = $wiki;
		$this->db->Connect( $wiki );
		$this->local = Array();
	}
	
	
	#############################
	## Global data methods
	#############################
	########
	## Get global ID
	########
	public function gu_id() {
		if( !$this->global_id )
			$this->get_gu_details();
		return $this->global_id;
	}
	

	########
	## Get global account unification timestamp
	########
	public function gu_timestamp() {
		if( !$this->global_timestamp )
			$this->get_gu_details();
		return $this->global_timestamp;
	}
	

	########
	## Get globally locked?
	########
	public function gu_locked() {
		if( !$this->global_id )
			$this->get_gu_details();
		return $this->global_locked;

	}


	########
	## Get globally hidden?
	########
	public function gu_hidden() {
		if( !$this->global_id )
			$this->get_gu_details();
		return $this->global_hidden;
	}


	########
	## Get globally details
	########
	public function get_gu_details() {
		$row = $this->db->Query(
			'SELECT gu_id, DATE_FORMAT(gu_registration, \'%Y-%m-%d %H:%i\') AS gu_timestamp, gu_locked, gu_hidden FROM centralauth_p.globaluser WHERE gu_name = ? LIMIT 1',
			array( $this->target )
		)->fetchAssoc();
		
		$this->global_id        = $row['gu_id'];
		$this->global_timestamp = $row['gu_timestamp'];
		$this->global_locked    = $row['gu_locked'];
		$this->global_hidden    = $row['gu_hidden'];
	}


	########
	## Global account's home wiki 
	########
	public function gu_home_wiki() {
		if( !$this->global_home_wiki )
			$this->global_home_wiki = $this->db->getHomeWiki( $this->target );
		return $this->global_home_wiki;
	}


	########
	## Global account's unified wikis
	########
	public function gu_wikis() {
		if( $this->global_wikis == NULL )
			$this->global_wikis = $this->db->getUnifiedWikis( $this->target );
		return $this->global_wikis;		
	}


	########
	## Global account groups
	########
	public function gu_groups() {
		if( $this->global_groups == NULL ) {
			if( !$this->global_id )
				$this->get_gu_details();
			
			$this->db->Connect( 'metawiki_p' );
			$this->db->Query(
				'SELECT GROUP_CONCAT(gug_group SEPARATOR \', \') AS gug_groups FROM centralauth_p.global_user_groups WHERE gug_user = ?',
				array( $this->global_id )
			);
			$this->db->ConnectPrevious();
			
			$row = $this->db->fetchAssoc();
			$this->global_groups = $row['gug_groups'];
		}
		
		return $this->global_groups;
	}
	

	########
	## Get hash of global blocks
	########
	public function get_global_blocks() {
		if( !$this->global_blocks ) {
			$this->db->Connect( 'metawiki_p' );
			
			$this->global_blocks = Array();
			$query = $this->db->Query(
				'SELECT gb_address, gb_by, gb_reason, DATE_FORMAT(gb_timestamp, \'%Y-%b-%d\') AS timestamp, gb_anon_only, DATE_FORMAT(gb_expiry, \'%Y-%b-%d\') AS expiry FROM centralauth_p.globalblocks WHERE (gb_range_start <= ? AND gb_range_end >= ?) OR (gb_range_start >= ? AND gb_range_end <= ?) ORDER BY gb_timestamp',
				array( $this->ip_hex_start, $this->ip_hex_end, $this->ip_hex_start, $this->ip_hex_end )
			)->fetchAllAssoc();
			
			foreach( $query as $row ) {
				$this->global_blocks[] = Array(
					'address'   => $row['gb_address'],
					'by'        => $row['gb_by'],
					'reason'    => $row['gb_reason'],
					'timestamp' => $row['timestamp'],
					'anon_only' => $row['gb_anon_only'],
					'expiry'    => $row['expiry']
				);
			}
			
			$this->db->ConnectPrevious();
		}

		return $this->global_blocks;
	}
	
	
	#############################
	## Local data methods
	#############################
	########
	## Get user details
	########
	private function get_lu_details() {
		if( isset($this->local['fetched']) )
			return;
		
		$row = $this->db->Query(
			'SELECT user_id, user_registration, DATE_FORMAT(user_registration, \'%Y-%m-%d %H:%i\') as registration, user_editcount FROM user WHERE user_name = ? LIMIT 1',
			array( $this->target )
		)->fetchAssoc();
		
		$this->local = Array(
			'id'            => $row['user_id'],
			'timestamp_raw' => $row['user_registration'],
			'timestamp'     => $row['registration'],
			'edit_count'    => $row['user_editcount'],
			'fetched'       => true
		);
		
		/* if needed, use more complex date algorithm */
		if( $this->local['id'] && !$this->local['timestamp_raw'] ) {
			$date = $this->db->getRegistrationDate( $this->wiki, $this->local['id'] );
			$this->local['timestamp_raw'] = $date['raw'];
			$this->local['timestamp']     = $date['formatted'];
		}
	}
	
	
	########
	## Get ID
	########
	public function lu_id() {
		$this->get_lu_details();
		return $this->local['id'];
	}


	########
	## Get timestamp
	########
	public function lu_timestamp( $raw = false ) {
		$this->get_lu_details();
		if( $raw )
			return $this->local['timestamp_raw'];
		return $this->local['timestamp'];
	}
	
	
	########
	## Get edit count
	########
	public function lu_edit_count() {
		$this->get_lu_details();
		return $this->local['edit_count'];
	}

	########
	## Get hash of local user groups
	########
	public function lu_groups() {
		$this->get_lu_details();
		if( !isset($this->local['groups']) ) {
			$this->local['groups'] = $this->db->Query(
				'SELECT GROUP_CONCAT(ug_group SEPARATOR \', \') FROM user_groups WHERE ug_user = ?',
				array( $this->local['id'] )
			)->fetchValue();
		}
		return $this->local['groups'];
	}
	
	
	#######
	## Get hash of local user block
	#######
	public function lu_block() {
		$this->get_lu_details();
	
		$this->db->Query(
			'SELECT ipb_by_text, ipb_reason, DATE_FORMAT(ipb_timestamp, \'%Y-%m-%d %H:%i\') AS ipb_timestamp, ipb_deleted, COALESCE(DATE_FORMAT(ipb_expiry, \'%Y-%m-%d %H:%i\'), ipb_expiry) AS ipb_expiry FROM ipblocks WHERE ipb_user = ? LIMIT 1',
			array( $this->local['id'] )
		);
		
		$row = $this->db->fetchAssoc();
		$block = Array(
			'by'        => $row['ipb_by_text'],
			'reason'    => $row['ipb_reason'],
			'timestamp' => $row['ipb_timestamp'],
			'deleted'   => $row['ipb_deleted'],
			'expiry'    => $row['ipb_expiry']
		);
		
		if( $block['by'] )
			return $block;
		return NULL;
	}


	########
	## Get hash of local IP blocks
	########
	public function get_ip_blocks() {
		$query = $this->db->Query(
			'SELECT ipb_by_text, ipb_address, ipb_reason, DATE_FORMAT(ipb_timestamp, \'%Y-%b-%d\') AS timestamp, ipb_deleted, DATE_FORMAT(ipb_expiry, \'%Y-%b-%d\') AS expiry FROM ipblocks WHERE (ipb_range_start <= ? AND ipb_range_end >= ?) OR (ipb_range_start >= ? AND ipb_range_end <= ?)',
			array( $this->ip_hex_start, $this->ip_hex_end, $this->ip_hex_start, $this->ip_hex_end )
		)->fetchAllAssoc();

		$blocks = Array();
		foreach( $query as $row ) {
			$blocks[] = array(
				'by'        => $row['ipb_by_text'],
				'address'   => $row['ipb_address'],
				'reason'    => $row['ipb_reason'],
				'timestamp' => $row['timestamp'],
				'deleted'   => $row['ipb_deleted'],
				'expiry'    => $row['expiry']
			);
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
$backend->TimerStart('initialize');
$script = NULL;
$target_form = '';

if( isset($_GET['target']) )
	$script = new Stalktoy( $backend, $_GET['target'] );

if( isset($_GET['show_all_wikis']) && $_GET['show_all_wikis'] )
	$script->show_all_wikis = true;

if( isset($_GET['closed']) && $_GET['closed'] )
	$script->show_closed_wikis = true;

$backend->TimerStop('initialize');

#############################
## Input form
#############################
$target_form = '';
if( $script )
	$target_form = $backend->FormatFormValue($script->target);
echo '
	<p>Who shall we stalk?</p>
	<form action="" method="get">
		<div>
			<input type="text" name="target" value="', $target_form, '" />
			<input type="submit" value="Analyze »" /> <br />
		
			', Form::Checkbox( 'show_all_wikis', $script && $script->show_all_wikis ), '
			<label for="show_all_wikis">Show wikis where account is not registered.</label><br />
			', Form::Checkbox( 'closed', $script && $script->show_closed_wikis ), '
			<label for="closed">Show closed wikis.</label>
		</div>
	</form>';

#############################
## No input
#############################
if( !$script ) {}

#############################
## Process data (IP / CIDR)
#############################
elseif( $script->ip->valid() ) {
	########
	## Fetch data
	########
	/* global data */
	$backend->TimerStart('fetch global');
	$global = Array(
		'wikis'        => $script->wikis,
		'blocks'       => $script->get_global_blocks(),
		'pretty_range' => $script->ip->pretty_range()
	);
	$backend->TimerStop('fetch global');

	/* local data */
	$backend->TimerStart('fetch local');
	foreach( $global['wikis'] as $wiki => $domain ) {
		$script->set_wiki( $wiki );
		
		$closed = $script->db->getLocked($wiki);
		if( !$script->show_closed_wikis && $closed ) {
			unset( $global['wikis'][$wiki] );
			continue;
		}
		
		$local[$wiki] = Array(
			'blocks'   => $script->get_ip_blocks(),
			'editable' => (int)!$closed
		);
	}
	$backend->TimerStop('fetch local');


	########
	## Output
	########
	$backend->TimerStart('output');
	echo '<div class="result-box">
	<h3>Global details</h3>
	<b>', $global['pretty_range'], '</b><br />';
	if( $global['blocks'] ) {
		echo '
			<fieldset>
				<legend>Global blocks</legend>
				<ul>';
		foreach( $global['blocks'] as $block ) {
			$by_url = urlencode( $block['by'] );
			$reason = $script->parse_reason( $block['reason'], 'meta.wikimedia.org' );
			echo '
					<li>', $block['timestamp'], ' &mdash; ', $block['expiry'], ':',
				' <b>', $block['address'], '</b> globally blocked by',
				' <a href="//meta.wikimedia.org/wiki/user:', $by_url, '">', $block['by'], '</a>',
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
			<a href="//meta.wikimedia.org/wiki/Special:GlobalBlock?wpAddress=', $script->target_wiki_url, '" title="Special:GlobalBlock">global block</a>,
			<a href="//toolserver.org/~luxo/contributions/contributions.php?user=', $script->target_url, '&blocks=true" title="list edits">list edits</a>,
			<a href="http://domaintools.com/', $script->ip->dec(), '" title="whois query">whois</a>.
		</div>';


	########
	## Local results
	########
	/* print header */
	echo '
		<h3>Local IPs</h3>
		<table class="pretty" id="local-ips">
			<thead>
				<tr>
					<th>wiki</th>
					<th>blocked</th>
				</tr>
			</thead>
			<tbody>';
	
		/* print each row */
		foreach( $global['wikis'] as $wiki => $domain ) {
			$blocked   = (int)(bool)$local[$wiki]['blocks'];
			$link_wiki = $script->link( $domain, 'user:' . $script->target_wiki_url, preg_replace( '/_p$/', '', $wiki) );
		
			echo '
				<tr class="wiki-open-', $local[$wiki]['editable'], ' ip-blocked-', $blocked, '">
					<td class="wiki">', $link_wiki, '</td>
					<td class="blocks">';
			if( $local[$wiki]['blocks'] ) {
				foreach( $local[$wiki]['blocks'] as $block ) {
					$reason = $script->parse_reason( $block['reason'], $domain );
					echo '<span class="is-block-start">', $block['timestamp'], '</span> &mdash; <span class="is-block-end">', $block['expiry'], '</span>: <b>', $block['address'], '</b> blocked by <span class="is-block-admin">', $block['by'], '</span> (<span class="is-block-reason">', $reason, '</span>)<br />';
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
	$backend->TimerStop('output');
}

#############################
## Process data (user)
#############################
else if( $script->target ) {
	#######
	## Fetch data
	########
	/* global details */
	$backend->TimerStart('fetch global');
	if( $script->gu_id() ) {
		$global = Array(
			'id'        => $script->gu_id(),
			'timestamp' => $script->gu_timestamp(),
			'groups'    => $script->gu_groups(),
			'locked'    => $script->gu_locked(),
			'hidden'    => $script->gu_hidden(),
			'home_wiki' => $script->gu_home_wiki(),
			'unified'   => array_flip( $script->gu_wikis() ),
			'stats'     => Array(
				'wikis'      => 0,
				'edit_count' => 0,
				'most_edits' => -1,
				'most_edits_domain' => NULL,
				'oldest' => NULL,
				'oldest_raw' => 999999999999999,
				'oldest_domain' => NULL
			)
		);
	}
	else
		$global = Array(
			'id' => NULL,
			'stats' => Array(
				'most_edits'        => -1,
				'most_edits_domain' => NULL
			)
		);
	$backend->TimerStop('fetch global');
	
	/* local details */
	$backend->TimerStart('fetch local');
	$local = Array();
	foreach( $script->wikis as $wiki => $domain ) {
		$script->set_wiki( $wiki );
		
		$closed = $script->db->getLocked($wiki);		
		if( !$script->show_closed_wikis && $closed )
			continue;
		
		/* no such local user */
		if( !$script->lu_id() ) {
			if( $script->show_all_wikis ) {
				$local[$wiki] = Array(
					'exists'        => 0,
					'id'            => NULL,
					'timestamp'     => NULL,
					'timestamp_raw' => NULL,
					'edit_count'    => NULL,
					'block'         => NULL,
					'groups'        => NULL,
					'domain'        => $script->wikis[$wiki],
					'editable'      => (int)!$closed
				);
			}
			continue;
		}
		
		/* local details */
		$local[$wiki] = Array(
			'exists'        => 1,
			'id'            => $script->lu_id(),
			'timestamp'     => $script->lu_timestamp(),
			'timestamp_raw' => $script->lu_timestamp( true ),
			'edit_count'    => $script->lu_edit_count(),
			'block'         => $script->lu_block(),
			'groups'        => $script->lu_groups(),
			'unified'       => (int)($global['id'] && isset( $global['unified'][$wiki] )),
			'domain'        => $script->wikis[$wiki],
			'editable'      => (int)!$script->db->getLocked($wiki)
		);
		

		/* statistics used even when no global account */
		if( $local[$wiki]['edit_count'] > $global['stats']['most_edits'] ) {
			$global['stats']['most_edits'] = $local[$wiki]['edit_count'];
			$global['stats']['most_edits_domain'] = $domain;
		}
		
		/* statistics shown only for global account */
		if( $global['id'] ) {
			$global['stats']['wikis']++;
			$global['stats']['edit_count'] += $local[$wiki]['edit_count'];
			if( $local[$wiki]['timestamp_raw'] < $global['stats']['oldest_raw'] ) {
				$global['stats']['oldest'] = $local[$wiki]['timestamp'];
				$global['stats']['oldest_raw'] = $local[$wiki]['timestamp_raw'];
				$global['stats']['oldest_domain'] = $local[$wiki]['domain'];
			}
		}
	}
	$backend->TimerStop('fetch local');

	$backend->TimerStart('adjust stats');
	/* best guess for pre-2005 oldest account */
	if( $global['id'] )
		if( !$global['stats']['oldest'] && !$local[$global['home_wiki']]['timestamp_raw'] )
			$global['stats']['oldest_domain'] = $local[$global['home_wiki']]['domain'];
	
	
	/* zero-padding for sorting */
	$backend->TimerStop('adjust stats');

		
	#######
	## Output global details
	########
	$backend->TimerStart('output');
	echo "
		<div class='result-box'>
		<h3>Global account</h3>\n";
	echo '<div class="is-global-details"',
		' data-is-global="', ($global['id'] ? '1' : '0'), '"',
		' data-username="', htmlentities($script->target), '"';
	if($global['id']) {
		echo ' data-home-wiki="', htmlentities($global['home_wiki']), '"',
		// quick hack below, please avert your eyes.
		' data-status="', ($global['locked'] && $global['hidden'] ? 'locked, hidden' : ($global['locked'] ? 'locked' : ($global['hidden'] ? 'hidden' : 'okay'))), '"',
		' data-id="', $global['id'], '"',
		' data-registered="', $global['timestamp'], '"',
		' data-groups="', htmlentities($global['groups']), '"';
	}
	echo '>';
	if( $global['id'] ) {
		echo "
			<table class='plain'>
				<tr>
					<td>User name:</td>
					<td><b>{$script->target}</b></td>
				</tr>
				<tr>
					<td>Home wiki:</td>";
		if( $global['home_wiki'] )
			echo "					
					<td><b><a href='//{$script->wikis[$global['home_wiki']]}/wiki/user:{$script->target_wiki_url}' title='home wiki'>{$script->wikis[$global['home_wiki']]}</a></b></td>";
		else
			echo "
					<td><b>unknown</b> <small>(The main account may be <a href='//meta.wikimedia.org/wiki/Oversight' title='about hiding user names'>hidden</a> or renamed, or the data <a href='//wiki.toolserver.org/view/Replication_lag' title='about replication lag'>might not be replicated yet</a>.)</small></td>";
		echo "
				</tr>
				<tr>
					<td>Status:</td>
					<td>";
		if( $global['locked'] || $global['hidden'] ) {
			if( $global['locked'] )
				echo "<span class='bad'>Locked</span> ";
				if( $global['hidden'] )
			echo "<span class='bad'>Hidden</span>";
		}
		else if( $script->target == 'Shanel' )
			echo "<span class='good'>&nbsp;&hearts;&nbsp;</span>";
		else
			echo "<span class='good'>okay</span>";
		echo "
				</tr>
				<tr>
					<td>User ID:</td>
					<td><b>{$global['id']}</b></td>
				</tr>
				<td>Registered:</td>
					<td><b>{$global['timestamp']}</b></td>
				</tr>
				<tr>
						<td>Groups:</td>
					<td><b>{$global['groups']}</b></td>
				</tr>
				<tr>
					<td>Other toys:</td>
					<td>
						<a href='//meta.wikimedia.org/wiki/Special:CentralAuth/{$script->target_wiki_url}' title='Special:CentralAuth'>CentralAuth</a>,
						<a href='//toolserver.org/~luxo/contributions/contributions.php?user={$script->target_url}&blocks=true' title='list edits'>list edits</a>
					</td>
				</tr>
				<tr>
					<td style='vertical-align:top;'>Global statistics:</td>
					<td>
						{$global['stats']['edit_count']} edits on {$global['stats']['wikis']} wikis.<br />
						Most edits on <a href='//{$global['stats']['most_edits_domain']}/wiki/Special:Contributions/{$script->target_wiki_url}'>{$global['stats']['most_edits_domain']}</a> ({$global['stats']['most_edits']}).<br />
						Oldest account on <a href='//{$global['stats']['oldest_domain']}/wiki/user:{$script->target_wiki_url}'>{$global['stats']['oldest_domain']}</a> (", ( $global['stats']['oldest'] ? $global['stats']['oldest'] : '2005 or earlier, so probably inaccurate; registration date was not stored until late 2005' ), ").
					</td>
				</tr>
			</table>\n";
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
		echo "
			<table class='pretty sortable' id='local-accounts'>
	 			<thead>
					<tr>
	 					<th>wiki</th>
	 					<th>edits</th>
	 					<th>registration date</th>
	 					<th>groups</th>
						<th><a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about unified login'>unified login</a></th>
						<th>block</th>
	 				</tr>
				</thead>
				<tbody>\n";
		 			
		foreach( $local as $wiki => $data ) {
			########
			## Prepare strings
			########
			$link_wiki  = $script->link( $data['domain'], "User:{$script->target_wiki_url}", preg_replace('/_p$/', '', $wiki) );

			/* user exists */
			if( $data['exists'] ) {
				$link_edits = $script->link( $data['domain'], "Special:Contributions/{$script->target_wiki_url}", "&nbsp;{$data['edit_count']}&nbsp;" );
				$has_groups = (int)(bool)$data['groups'];
				$is_blocked = (int)(bool)$data['block'];
				$is_hidden  = (int)($is_blocked && $data['block']['deleted']);
				$is_unified = $data['unified'];
				$label_unified = $label_unified_strs[$is_unified];
			
				if( $data['block'] ) {
					$reason = $script->parse_reason( $data['block']['reason'], $data['domain'] );
					$block_summary = "<span class=\"is-block-start\">{$data['block']['timestamp']}</span> &mdash; <span class=\"is-block-end\">{$data['block']['expiry']}</span>: blocked by <span class=\"is-block-admin\">{$data['block']['by']}</span> (<span class=\"is-block-reason\">{$reason}</span>)";
				}
				else
					$block_summary = '';
			}
			
			/* user doesn't exist */
			else {
				$link_edits = '&nbsp;';
				$has_groups = '_';
				$is_blocked = '_';
				$is_hidden  = '_';
				$is_unified = '_';
				$label_unified = 'no such user';
				$block_summary = '&nbsp;';
			}
			
			########
			## Output
			########
			echo '
					<tr class="is-wiki wiki-open-', $data['editable'], ' user-exists-', $data['exists'], ' user-in-groups-', $has_groups, ' user-unified-', $is_unified, ' user-blocked-', $is_blocked, '"',
					'data-wiki="', preg_replace('/_p$/', '', $wiki), '" data-wiki-domain="', $data['domain'], '" data-is-open="', $data['editable'], '" data-user-exists="', $data['exists'], '" data-user-edits="', $data['edit_count'], '" data-user-groups="', htmlentities($data['groups']), '" data-registered="', $data['timestamp'], '" data-is-unified="', $is_unified, '" data-is-blocked="', $is_blocked, '"',
					($is_blocked ? 'data-block-start="' . $data['block']['timestamp'] . '" data-block-end="' . $data['block']['expiry'] . '" data-block-admin="' . htmlentities($data['block']['by']) . '" data-block-reason="' . htmlentities($reason) . '"' : ''),
					'>
						<td class="wiki"><span class="row_wiki">', $link_wiki, '</span></td>
						<td class="edit-count">', $link_edits, '</td>
						<td class="timestamp">', $data['timestamp'], '</td>
						<td class="groups">', $data['groups'], '</td>
						<td class="unification">', $label_unified, '</td>
						<td class="blocks">', $block_summary, '</td>
					</tr>', "\n";
		}
		echo "</tbody>
		</table></div>";
	}
	else
		echo "<div class='error'>There are no local accounts with this name.</div>\n";
	$backend->TimerStop('output');
}

$backend->footer();

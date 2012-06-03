<?php
require_once( '../backend/modules/Backend.php' );
require_once( '../backend/modules/Form.php' );
require_once( './sekret_key.php' );
$backend = new Backend(Array(
	'title'  => 'gUser search',
	'blurb'  => 'Provides searching and filtering of global users on Wikimedia wikis.',
	'source' => Array( 'index.php' )
));
$backend->link( Array('stylesheet.r2.css', 'javascript.js', '../globals/sorttable.js') );
$backend->header();

#############################
## Script methods
#############################
class Script extends Base {
	#############################
	## Properties
	#############################
	// constants
	const T_GLOBALUSER   = 'globaluser';
	const T_GLOBALGROUPS = 'global_user_groups';
	const T_LOCALWIKIS   = 'localuser';
	const OP_REGEXP = 'REGEXP';
	const OP_LIKE   = 'LIKE';
	const OP_EQUAL  = '=';
	const OP_NOT_EQUAL = '!=';
	const MIN_LIMIT = 1;
	const MAX_LIMIT = 5000;
	const DEFAULT_LIMIT = 50;

	// properties
	public $name = ''; // only used for pagination
	public $use_regex; // only used for pagination
	public $show_locked; // only used for pagination
	public $show_hidden; // only used for pagination

	// values
	protected $filters = Array();
	protected $filter_descriptions = Array();
	private $disposed = false;

	public $limit  = 50;
	public $offset = 0;
	public $query  = "";
	public $values = Array();

	#############################
	## Initialization & configuration
	#############################
	###############
	## Constructor
	###############
	public function __construct() {}


	###############
	## Add a filter
	###############
	public function filter( $table, $field, $operator, $value ) {
		$this->filters[$table][$field] = Array($operator, $value);
	}


	###############
	## Add a filter description (for human-readability summary)
	###############
	public function describeFilter( $text ) {
		array_push( $this->filter_descriptions, htmlentities($text) );
	}


	###############
	## Set row limit
	###############
	public function setLimit( $limit ) {
		$limit = (int)$limit;
		/* validate */
		if( $limit < self::MIN_LIMIT )
			$limit = self::MIN_LIMIT;
		else if( $limit > self::MAX_LIMIT )
			$limit = self::MAX_LIMIT;

		$this->limit = $limit;
	}


	###############
	## Set row offset
	###############
	public function setOffset( $offset ) {
		$offset = (int)$offset;
		if( $offset < 0 )
			$offset = 0;

		$this->offset = $offset;
	}


	#############################
	## Getters
	#############################
	###############
	## Get value of a filter
	###############
	public function getFilterValue( $table, $field ) {
		if( isset($this->filters[$table][$field]) )
			return $this->filters[$table][$field][1];
		return NULL;
	}


	#############################
	## HTML methods
	#############################
	###############
	## Get human-readable description of search options
	###############
	public function getFormattedSearchOptions() {
		$count = $this->db->countRows();
		$output = '';

		if( $count ) {
			$output .= ( $count < $this->limit ? "Found all " : "Found latest " );
			$output .= $this->db->countRows() . " global accounts where ";
		}
		else
			$output .= "Found <b>no global accounts</b> matching ";

		return $output . '[' . implode('] and [', $this->filter_descriptions) . ']';
	}

	###############
	## Generate pagination link
	###############
	public function paginationLink( $limit, $offset, $label = NULL  ) {
		$link = "<a href='?name=" . urlencode($this->name);
		if( $limit != self::DEFAULT_LIMIT )
			$link .= "&limit={$limit}";
		if( $offset > 0 )
			$link .= "&offset={$offset}";
		if( $this->use_regex )
			$link .= "&regex=1";
		if( $this->show_locked )
			$link .= "&show_locked=1";
		if( $this->show_hidden )
			$link .= "&show_hidden=1";
		$link .= "' title='{$label}'>{$label}</a>";

		return $link;
	}


	#############################
	## Querying
	#############################
	###############
	## Build partial query
	###############
	protected function buildQuery(/* variadic */) {
		$args = func_get_args();
		foreach( $args as $arg )
			$this->query .= $arg;
	}

	###############
	## Return WHERE string, and add variables to bind values
	###############
	protected function addWhere( $table ) {
		if( !isset($this->filters[$table]) || !count($this->filters[$table]) )
			return "";

		$output = "WHERE ";
		foreach( $this->filters[$table] as $field => $opts ) {
			$output .= "{$field} {$opts[0]} ? AND ";
			array_push( $this->values, $opts[1] );
		}
		$output = substr( $output, 0, -4 );
		return $output;
	}


	###############
	## Prepare and execute query
	###############
	public function Query() {
		global $backend;
		
		/*********
		** connect to DB
		*********/
		$this->TimerStart( 'instantiate Toolserver' );

		$this->db = $backend->GetDatabase( Toolserver::ERROR_PRINT );
		$this->db->Connect( 'metawiki_p' );

		$this->TimerStop( 'instantiate Toolserver' );


		/*********
		** build query
		*********/
		$this->TimerStart('build query');

		/* global user */
		$global_users  = self::T_GLOBALUSER;
		$global_groups = self::T_GLOBALGROUPS;
		$local_wikis   = self::T_LOCALWIKIS;

		$this->buildQuery("
			SELECT t_user.*, t_groups.gu_groups
			FROM (
				SELECT gu_id, gu_name, DATE_FORMAT(gu_registration, '%Y-%b-%d %H:%i') AS gu_registration, gu_locked, gu_hidden
				FROM centralauth_p.{$global_users}
				", $this->addWhere($global_users), "
				ORDER BY gu_id DESC
				LIMIT {$this->limit}
				OFFSET {$this->offset}
			) AS t_user
			LEFT JOIN (
				SELECT gug_user, GROUP_CONCAT(gug_group SEPARATOR ', ') AS gu_groups
				FROM centralauth_p.{$global_groups}
				GROUP BY gug_user
				", $this->addWhere($global_groups), "
			) AS t_groups
			ON gu_id = gug_user
		");

		/*********
		** Fetch and dispose
		*********/
		$this->TimerStop('build query');
		$this->TimerStart('do search');

		$this->db->Query( $this->query, $this->values );

		$this->TimerStop('do search');
		$this->db->Dispose();
	}


	#############################
	## Destructor
	#############################
	public function Dispose() {
		if( $this->disposed )
			return;
		$this->disposed = true;
		$this->db->Dispose();
	}
	public function __destruct() {
		$this->db = NULL;
	}
}


#############################
## Instantiate script engine
#############################
$script = new Script();
$script->TimerStart('initialize');

/* get arguments */
$name = (isset($_GET['name'])) ? $_GET['name'] : '';
$use_regex = isset($_GET['regex']) && $_GET['regex'];
$show_locked = isset($_GET['show_locked']) ? (bool)$_GET['show_locked'] : false;
$show_hidden = isset($_GET['show_hidden']) ? (bool)$_GET['show_hidden'] : false;
$case_insensitive = isset($_GET['icase']) ? (bool)$_GET['icase'] : false;
$has_sekret = isset($_GET['sekret']) && $_GET['sekret'] == $sekret_key;

/* add user name filter */
if( $name != '' ) {
	$script->name = $name;
	$operator = ( $use_regex ? Script::OP_REGEXP : Script::OP_LIKE );

	if( $case_insensitive ) {
		$script->filter( Script::T_GLOBALUSER, 'UPPER(CONVERT(gu_name USING utf8))', $operator, strtoupper($name) );
		$script->filter( Script::T_LOCALWIKIS, 'UPPER(CONVERT(lu_name USING utf8))', $operator, strtoupper($name) );
		$script->describeFilter( "username {$operator} {$name}" );
	}
	else {
		$script->filter( Script::T_GLOBALUSER, 'gu_name', $operator, $name );
		$script->filter( Script::T_LOCALWIKIS, 'lu_name', $operator, $name );
		$script->describeFilter( "username {$operator} {$name}" );
	}
}

/* add lock status filter */
if( !$show_locked ) {
	$script->filter( Script::T_GLOBALUSER, 'gu_locked', Script::OP_NOT_EQUAL, '1' );
	$script->describeFilter( "NOT locked" );
}

/* add hide status filter */
if( !$show_hidden ) {
	$script->filter( Script::T_GLOBALUSER, 'gu_hidden', Script::OP_NOT_EQUAL, 'lists' );
	$script->filter( Script::T_GLOBALUSER, '`gu_hidden`', Script::OP_NOT_EQUAL, 'suppressed' );
	$script->describeFilter( "NOT hidden" );
}

/* set limit */
if( isset($_GET['limit']) )
	$script->setLimit( $_GET['limit'] );
$limit = $script->limit;

/* set offset */
if( isset($_GET['offset']) )
	$script->setOffset( $_GET['offset'] );
$offset = $script->offset;

$script->use_regex = $use_regex;
$script->show_locked = $show_locked;
$script->show_hidden = $show_hidden;

#############################
## Input form
#############################
$f_username = $backend->FormatFormValue( isset($name) ? $name : '' );

echo "
	<form action='' method='get'>
		<input type='text' name='name' value='{$f_username}' />
		",
		( ($limit != Script::DEFAULT_LIMIT) ? "<input type='hidden' name='limit' value='{$limit}' />" : ""),
		"
		<input type='submit' value='Submit' /> <br />
		<div style='padding-left:0.5em; border:1px solid gray; color:gray;'>
			", Form::Checkbox( 'show_locked', $show_locked ), "
			<label for='show_locked'>Show locked accounts</label><br />

			", Form::Checkbox( 'show_hidden', $show_hidden ), "
			<label for='show_hidden'>Show hidden accounts</label><br />

			", Form::Checkbox( 'regex', $use_regex, Array('onClick' => 'script.toggleRegex(this.checked);') ), "
			<label for='regex'>Use <a href='http://www.wellho.net/regex/mysql.html' title='MySQL regex reference'>regular expression</a> (much slower)</label><br />

			", Form::Checkbox( 'icase', $case_insensitive ), "
			<label for='icase'>Match any capitalization (much slower)</label><br />

			<p>
				<b>Search syntax:</b>
				<span id='tips-regex'", ($use_regex ? "" : " style='display:none;'"), ">
					Regular expressions are much slower, but much more powerful. You will need to escape special characters like [.*^$]. See the <a href='http://www.wellho.net/regex/mysql.html' title='MySQL regex reference'>MySQL regex reference</a>.
				</span>
				<span id='tips-like'", ( $use_regex ? " style='display:none;'" : ""), ">
					Add % to your search string for multicharacter wildcards, and _ for a single-character wildcard. For example, '%Joe%' finds every username containing the word 'Joe').
				</span>
			</p>
			<p>Beware: search is <strong><em>much slower</em></strong> if the user name starts with a wildcard!</p>
		</div>
	</form>\n";


#############################
## Perform search
#############################
$script->TimerStop('initialize');
$script->Query();
$count = $script->db->countRows();
$has_results = (int)!(bool)$count;

echo "
	<h2>Search results</h2>";
if($has_sekret) {
	echo '<div class="success">You\'re using a secret key that lets you view hidden user names. Please do not share the URL (with the secret key) with non-stewards, to avoid abuse.</div>';
}
echo "
	<p id='search-summary' class='search-results-{$has_results}'>{$script->getFormattedSearchOptions()}.</p>";

#############################
## Output
#############################
if( $count ) {
	/* pagination */
	echo "[",
	     (( $offset > 0 ) ? $script->paginationLink( $limit, $offset - $limit, "&larr;newer {$limit}" ) : "&larr;newer {$limit}" ),
	     " | ",
	     (( $script->db->countRows() >= $limit ) ? $script->paginationLink( $limit, $offset + $limit, "older {$limit}&rarr;" ) : "older {$limit}&rarr;" ),
	     "] [show ",
	     $script->paginationLink( 50, $offset, 50 ),
	     ", ",
	     $script->paginationLink( 250, $offset, 250 ),
	     ", ",
	     $script->paginationLink( 500, $offset, 500 ),
	     "]";

	/* table */
	echo "
		<table class='pretty sortable' id='search-results'>
			<tr>
				<th>ID</th>
				<th>Name</th>
				<th>Unification date</th>
				<th>Status</th>
				<!--<th>Local wikis</th>-->
				<th>Global groups</th>
				<th>Links</th>
			</tr>\n";

	$any_oversighted = false;
	while( $row = $script->db->fetchAssoc() ) {
		/* get values */
		$in_groups = ( $row['gu_groups'] ? '1' : '0' );
		$is_locked = (int)$row['gu_locked'];
		$is_hidden = ($row['gu_hidden'] == "lists" ? 1 : 0);
		$is_oversighted = ($row['gu_hidden'] == "suppressed" ? 1 : 0);
		$is_okay = (!$is_locked && !$is_hidden && !$is_oversighted ? 1 : 0);
		$lnk_target = urlencode($row['gu_name']);
		
		$name_hidden = ($is_hidden || $is_oversighted) && !$has_sekret;
		if($name_hidden)
			$any_oversighted = true;

		/* summarize status */
		$lbl_status = "";
		$statuses = array();
		if( $is_locked )
			array_push( $statuses, 'locked' );
		if( $is_hidden )
			array_push( $statuses, 'hidden' );
		if( $is_oversighted )
			array_push( $statuses, 'oversighted' );

		if( count($statuses) > 0 )
			$lbl_status = implode( ' | ', $statuses );

		/* output */
		echo "
			<tr class='user-okay-{$is_okay} user-locked-{$is_locked} user-hidden-{$is_hidden} user-oversighted-{$is_oversighted} user-in-groups-{$in_groups}'>
				<td class='id'>{$row['gu_id']}</td>
				<td class='name'>",
			($name_hidden
				? str_pad("", mb_strlen($row['gu_name'], 'utf-8'), "*")
				: "<a href='//toolserver.org/~pathoschild/stalktoy?target={$lnk_target}' title='about user'>{$row['gu_name']}</a>"
			), "</td>
				<td class='registration'>{$row['gu_registration']}</td>
				<td class='status'>{$lbl_status}</td>
				<td class='groups'>{$row['gu_groups']}</td>
				<td class='linkies'>
				",
				($name_hidden
					? "&mdash;"
					: "
						<a href='//toolserver.org/~luxo/contributions/contributions.php?user={$lnk_target}&blocks=true' title='List edits'>edits</a>
						| <a href='//meta.wikimedia.org/wiki/Special:CentralAuth?target={$lnk_target}' title='CentralAuth'>CentralAuth</a>
					"
				),
				"</td>
			</tr>\n";
	}
	echo "
		</table>\n";
}

if( $name && (($use_regex && !preg_match('/[+*.]/', $name)) || (!$use_regex && !preg_match('/[_%]/', $name))) )
	echo "<p><strong><big>※</big></strong>You searched for an exact match; did you want partial matches? See <em>Search syntax</em> above.</p>";
if( isset($any_oversighted) && $any_oversighted )
	echo "<p><strong><big>※</big></strong>Hidden or oversighted names are censored for privacy reasons.</p>";

echo "<!--
query = {$script->query}<br />
values = ", print_r($script->values, true),
"-->\n";

$backend->footer();

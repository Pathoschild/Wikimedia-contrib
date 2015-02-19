<?php
require_once('../backend/modules/Backend.php');
$backend = Backend::create('User pages', 'Find your user pages on all Wikimedia wikis.')
	->link('/userpages/scripts.js')
	->link('/userpages/stylesheet.css')
	->header();

/***************
 * Get data
 ***************/
$user = $backend->get('user', $backend->getRouteValue());
if($user)
	$user = $backend->formatUsername($user);
$user_form = $backend->formatValue($user);
$show_all = $backend->get('all', false);


/***************
 * Input form
 ***************/
echo '<form action="', $backend->url('/userpages'), '" method="get">
	<label for="user">User name:</label>
	<input type="text" name="user" id="user" value="', $backend->formatValue($user), '" />', ($user == 'Shanel' ? '&hearts;' : ''), '<br />
	<input type="checkbox" id="all" name="all" ', ($show_all ? 'checked="checked" ' : ''), '/> <label
	for="all">Show wikis with no user pages</label><br />
	<input type="submit" value="Analyze »" />
</form>';

if (!empty($user)) {
	echo '<div class="result-box">';
	echo 'See also <a href="', $backend->url('/stalktoy/' . urlencode($user)), '" title="Global account details">global account details</a>, <a href="', $backend->url('/crossactivity/' . urlencode($user)), '" title="Crosswiki activity">recent activity</a>, <a href="//meta.wikimedia.org/?title=Special:CentralAuth/', urlencode($user), '" title="Special:CentralAuth">Special:CentralAuth</a>.';
}
if ($user) {
	echo '<hr />Filters: <a href="#" class="selected filter" data-filter="misc">wikitext</a> <a href="#" class="selected filter" data-filter="css">CSS</a> <a href="#" class="selected filter" data-filter="js">JS</a>';
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
	$db->Connect('metawiki');
	$wikis = $db->getWikis();

	/***************
	 * Output data
	 ***************/
	$any = false;
	foreach($wikis as $wiki) {
		$dbname = $wiki->dbName;
		$domain = $wiki->domain;
		$family = $wiki->family;

		/* get data */
		$db->Connect($dbname);
		$sql_user = str_replace(' ', '_', $user);
		$pages = $db->Query('SELECT page_namespace, page_title, page_restrictions, page_counter, page_is_redirect, page_touched, page_len FROM page WHERE page_namespace IN (2,3) AND (page_title = ? OR page_title LIKE CONCAT(?, "/%"))', array($sql_user, $sql_user))->fetchAllAssoc();
		if(!$pages && !$show_all)
			continue;

		/* show pages */
		echo '<h2>', $domain, '</h2>';
		if(!$pages) {
			echo '<em>no pages here</em>';
			continue;
		}
		$any = true;

		echo '<ul class="page-list">';
		foreach($pages as $page) {
			// metadata
			$ns = $page['page_namespace'] == 3 ? 'User talk' : 'User';
			$title = $ns . ':' . $page['page_title'];
			$size = $page['page_len'];
			$is_redirect = $page['page_is_redirect'];
			$touched = new DateTime($page['page_touched']);
			$touched = $touched->format('Y-m-d');

			// filter type
			$type = 'misc';
			if(substr($title, -3) == '.js')
				$type = 'js';
			elseif(substr($title, -4) == '.css')
				$type = 'css';

			// output
			echo "<li class='redirect type-$type size-$size' data-redirect='$is_redirect' data-type='$type' data-size='$size' data-ns='$ns' data-title='", $backend->formatValue($page['page_title']), "'><a href='//$domain/wiki/", $backend->formatValue($title), "'>", $backend->formatValue($title), "</a> <small>(<span class='page-size'>$size bytes</span>, <span class='page-edited'>last <a href='https://www.mediawiki.org/wiki/Manual:Page_table#page_touched'>touched</a> $touched</span>)</small></li>";
		}
		echo '</ul>';
	}

	if(!$any)
		echo '<em>No user pages on any Wikimedia wikis.</em>';
}
while (0);

$backend->footer();
?>

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
	echo '<hr />',
		'Filters: page is ',
		'<a href="#" class="selected filter" data-filter-key="misc" data-filters=".type-misc">text</a> ',
		'<a href="#" class="selected filter" data-filter-key="css" data-filters=".type-css">CSS</a> ',
		'<a href="#" class="selected filter" data-filter-key="js" data-filters=".type-js">JS</a> ',
		'| namespace is ',
		'<a href="#" class="selected filter" data-filter-key="user" data-filters="[data-ns=\'2\']">user</a> ',
		'<a href="#" class="selected filter" data-filter-key="talk" data-filters="[data-ns=\'3\']">talk</a> ',
		'| include ',
		'<a href="#" class="selected filter" data-filter-key="top-pages" data-filters="[data-is-subpage=\'0\']">top pages</a> ',
		'<a href="#" class="selected filter" data-filter-key="subpages" data-filters="[data-is-subpage=\'1\']">subpages</a>';
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
		$pages = $db->Query('SELECT page_namespace, page_title, page_is_redirect, page_touched, page_len FROM page WHERE page_namespace IN (2,3) AND (page_title = ? OR page_title LIKE CONCAT(?, "/%"))', array($sql_user, $sql_user))->fetchAllAssoc();
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
			$namespaceNumber = $page['page_namespace'];
			$namespaceName = $namespaceNumber == 3 ? 'User talk' : 'User';
			$title = $namespaceName . ':' . $page['page_title'];
			$size = $page['page_len'];
			$is_redirect = $page['page_is_redirect'];
			$touched = new DateTime($page['page_touched']);
			$touched = $touched->format('Y-m-d');
			$is_subpage = strpos($title, '/') ? '1' : '0';

			// filter type
			$type = 'misc';
			if(substr($title, -3) == '.js')
				$type = 'js';
			elseif(substr($title, -4) == '.css')
				$type = 'css';

			// output
			echo "<li class='type-$type' data-redirect='$is_redirect' data-is-subpage='$is_subpage' data-type='$type' data-size='$size' data-ns='$namespaceNumber' data-title='", $backend->formatValue($page['page_title']), "'><a href='//$domain/wiki/", $backend->formatValue($title), "'>", $backend->formatValue($title), "</a> <small>(<span class='page-size'>$size bytes</span>, <span class='page-edited'>last <a href='https://www.mediawiki.org/wiki/Manual:Page_table#page_touched'>touched</a> $touched</span>)</small></li>";
		}
		echo '</ul>';
	}

	if(!$any)
		echo '<em>No user pages on any Wikimedia wikis.</em>';
}
while (0);

$backend->footer();
?>

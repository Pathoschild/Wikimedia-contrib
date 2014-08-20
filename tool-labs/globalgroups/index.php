<?php
require_once('../backend/modules/Backend.php');
$backend = Backend::create('GlobalGroups', 'A review of extra permissions assigned to <a href="//meta.wikimedia.org/wiki/Steward_handbook#Globally_and_wiki_sets" title="global groups">global groups</a> on Wikimedia Foundation wikis.')
	->link('/globalgroups/stylesheet.css')
	->header();

/*########
## Flag descriptions
########*/
// taken from MediaWiki localization files
// TODO: rethink how this is done
$flagBlurbs = Array(
	// MediaWiki svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/languages/messages/MessagesEn.php?view=co
	'right-read'                  => 'Read pages',
	'right-edit'                  => 'Edit pages',
	'right-createpage'            => 'Create pages (which are not discussion pages)',
	'right-createtalk'            => 'Create discussion pages',
	'right-createaccount'         => 'Create new user accounts',
	'right-minoredit'             => 'Mark edits as minor',
	'right-move'                  => 'Move pages',
	'right-move-subpages'         => 'Move pages with their subpages',
	'right-move-rootuserpages'    => 'Move root user pages',
	'right-movefile'              => 'Move files',
	'right-suppressredirect'      => 'Not create redirects from source pages when moving pages',
	'right-upload'                => 'Upload files',
	'right-reupload'              => 'Overwrite existing files',
	'right-reupload-own'          => 'Overwrite existing files uploaded by oneself',
	'right-reupload-shared'       => 'Override files on the shared media repository locally',
	'right-upload_by_url'         => 'Upload files from a URL',
	'right-purge'                 => 'Purge the site cache for a page without confirmation',
	'right-autoconfirmed'         => 'Edit semi-protected pages',
	'right-bot'                   => 'Be treated as an automated process',
	'right-nominornewtalk'        => 'Not have minor edits to discussion pages trigger the new messages prompt',
	'right-apihighlimits'         => 'Use higher limits in API queries',
	'right-writeapi'              => 'Use of the write API',
	'right-delete'                => 'Delete pages',
	'right-bigdelete'             => 'Delete pages with large histories',
	'right-deleterevision'        => 'Delete and undelete specific revisions of pages',
	'right-deletedhistory'        => 'View deleted history entries, without their associated text',
	'right-deletedtext'           => 'View deleted text and changes between deleted revisions',
	'right-browsearchive'         => 'Search deleted pages',
	'right-undelete'              => 'Undelete a page',
	'right-suppressrevision'      => 'Review and restore revisions hidden from administrators',
	'right-suppressionlog'        => 'View private logs',
	'right-block'                 => 'Block other users from editing',
	'right-blockemail'            => 'Block a user from sending e-mail',
	'right-hideuser'              => 'Block a username, hiding it from the public',
	'right-ipblock-exempt'        => 'Bypass IP blocks, auto-blocks and range blocks',
	'right-proxyunbannable'       => 'Bypass automatic blocks of proxies',
	'right-unblockself'           => 'Unblock themselves',
	'right-protect'               => 'Change protection levels and edit protected pages',
	'right-editprotected'         => 'Edit protected pages (without cascading protection)',
	'right-editinterface'         => 'Edit the user interface',
	'right-editusercssjs'         => "Edit other users' CSS and JavaScript files",
	'right-editusercss'           => "Edit other users' CSS files",
	'right-edituserjs'            => "Edit other users' JavaScript files",
	'right-rollback'              => 'Quickly rollback the edits of the last user who edited a particular page',
	'right-markbotedits'          => 'Mark rolled-back edits as bot edits',
	'right-noratelimit'           => 'Not be affected by rate limits',
	'right-import'                => 'Import pages from other wikis',
	'right-importupload'          => 'Import pages from a file upload',
	'right-patrol'                => "Mark others' edits as patrolled",
	'right-autopatrol'            => "Have one's own edits automatically marked as patrolled",
	'right-patrolmarks'           => 'View recent changes patrol marks',
	'right-unwatchedpages'        => 'View a list of unwatched pages',
	'right-mergehistory'          => 'Merge the history of pages',
	'right-userrights'            => 'Edit all user rights',
	'right-userrights-interwiki'  => 'Edit user rights of users on other wikis',
	'right-siteadmin'             => 'Lock and unlock the database',
	'right-override-export-depth' => 'Export pages including linked pages up to a depth of 5',
	'right-sendemail'             => 'Send e-mail to other users',
	'right-passwordreset'         => 'View password reset e-mails',
	
	// AbuseFilter extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/AbuseFilter/AbuseFilter.i18n.php?view=co
	'right-abusefilter-modify' => 'Modify abuse filters',
	'right-abusefilter-view' => 'View abuse filters',
	'right-abusefilter-log' => 'View the abuse log',
	'right-abusefilter-log-detail' => 'View detailed abuse log entries',
	'right-abusefilter-private' => 'View private data in the abuse log',
	'right-abusefilter-modify-restricted' => 'Modify abuse filters with restricted actions',
	'right-abusefilter-revert' => 'Revert all changes by a given abuse filter',
	'right-abusefilter-view-private' => 'View abuse filters marked as private',
	'right-abusefilter-hide-log' => 'Hide entries in the abuse log',
	'right-abusefilter-hidden-log' => 'View hidden abuse log entries',
	
	// AntiSpoof extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/AntiSpoof/AntiSpoof.i18n.php?view=co
	'right-override-antispoof' => 'Override the spoofing checks',
	
	// ArticleFeedback extension: https://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/ArticleFeedbackv5/ArticleFeedbackv5.i18n.php?view=co
	'right-aftv5-hide-feedback' => 'Hide feedback',
	'right-aftv5-delete-feedback' => 'Delete feedback',
	'right-aftv5-see-deleted-feedback' => 'View deleted feedback',
	'right-aftv5-see-hidden-feedback' => 'View hidden feedback',
	
	// CentralAuth extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/CentralAuth/CentralAuth.i18n.php?view=co
	'right-globalgroupmembership'   => 'Edit membership to global groups',
	'right-centralauth-autoaccount' => 'Automatically login with global account',
	'right-centralauth-unmerge'     => 'Unmerge global account',
	'right-centralauth-lock'        => 'Lock or hide global account',
	'right-centralauth-oversight'   => 'Suppress global account',
	'right-centralauth-merge'       => 'Merge their account',
	'right-globalgrouppermissions'  => 'Manage global groups',
	
	// CentralNotice extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/CentralNotice/CentralNotice.i18n.php?view=co
	'right-centralnotice-admin' => 'Manage central notices',
	
	// Checkuser extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/CheckUser/CheckUser.i18n.php?view=co
	'right-checkuser'            => "Check user's IP addresses and other information",
	'right-checkuser-log'        => 'View the checkuser log',
	
	// ConfirmEdit extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/ConfirmEdit/ConfirmEdit.i18n.php?view=co
	'right-skipcaptcha'          => 'Perform CAPTCHA-triggering actions without having to go through the CAPTCHA',
	
	// GlobalBlock extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/GlobalBlocking/GlobalBlocking.i18n.php?view=co
	'right-globalblock' => 'Make global blocks',
	'right-globalunblock' => 'Remove global blocks',
	'right-globalblock-whitelist' => 'Disable global blocks locally',
	'right-globalblock-exempt' => 'Bypass global blocks',
	
	// HideRevision (Oversight) extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/Oversight/HideRevision.i18n.php?view=co
	'right-oversight'        => 'View a previously hidden revision with Extension:Oversight',
	'right-hiderevision'     => 'Hide revisions from administrators with Extension:Oversight',
	
	// MoodBar extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/MoodBar/MoodBar.i18n.php?view=co
	'right-moodbar-view' => 'View and export MoodBar feedback',
	'right-moodbar-admin' => 'Alter visibility on the feedback dashboard',
	
	// Nuke extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/Nuke/Nuke.i18n.php?view=co
	'right-nuke'         => 'Mass delete pages',
	
	// RenameUser extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/Renameuser/Renameuser.i18n.php?view=co
	'right-renameuser'      => 'Rename users',
	
	// TitleBlacklist extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/TitleBlacklist/TitleBlacklist.i18n.php?view=co
	'right-tboverride'                => 'Override the title blacklist',
	'right-tboverride-account'        => 'Override the username blacklist',
	
	// TorBlock extension: http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/TorBlock/TorBlock.i18n.php?view=co
	'right-torunblocked' => 'Bypass automatic blocks of tor exit nodes',
	
	// obsolete permissions
	'right-centralnotice-translate' => '(obsolete)',
	'right-centralauth-admin' => '(obsolete)',
	'right-moodbar-admin' => '(obsolete)',
	'right-prefstats' => '(obsolete)',
	'right-uboverride' => '(obsolete)'
);

/*########
## Query group details
########*/
$db = $backend->GetDatabase('metawiki');
$db->Connect('metawiki');

$groups = array();
foreach($db->Query('SELECT DISTINCT ggp_group FROM centralauth_p.global_group_permissions')->fetchAllAssoc() as $groupRow) {
	/* group name */
	$groupKey = $groupRow['ggp_group'];
	$group = array(
		'key' => $groupKey,
		'name' => $backend->formatInitialCapital(str_replace('_', ' ', $groupKey)),
		'anchor' => $backend->formatAnchor($groupKey),
		'rights' => array(),
		'members' => 0,
		'wikis' => 0,
		'wikiset' => array()
	);
	
	/* rights */
	foreach($db->Query('SELECT ggp_permission FROM centralauth_p.global_group_permissions WHERE ggp_group = ?', array($groupKey))->fetchAllAssoc() as $rightsRow)
		$group['rights'][] = $rightsRow['ggp_permission'];
	sort($group['rights']);
	if(!count($group['rights']))
		continue; // groups with no rights are deleted (but still in the database)
	
	/* member count */
	$group['members'] = $db->Query('SELECT COUNT(*) FROM centralauth_p.global_user_groups LEFT JOIN centralauth_p.globaluser ON gug_user = gu_id WHERE gug_group = ? AND gu_id IS NOT NULL', array($groupKey))->fetchValue();
	
	/* wikis */
	$wikiset = $db->Query('SELECT ws_id, ws_name, ws_type, ws_wikis FROM centralauth_p.wikiset WHERE ws_id = (SELECT ggr_set FROM centralauth_p.global_group_restrictions WHERE ggr_group = ?)', array($groupKey))->fetchAssoc();
	if($wikiset) {
		$group['wikiset'] = array(
			'id' => $wikiset['ws_id'],
			'name' => $wikiset['ws_name'],
			'type' => $wikiset['ws_type'],
			'wikis' => $wikiset['ws_wikis'],
			'count' => substr_count($wikiset['ws_wikis'], ',')
		);
	}
	
	/* store values */
	$groups[$groupKey] = $group;
}

/*########
## Sort
########*/
$sort = $backend->get('sort', 'name');
switch($sort) {
	case 'members':
		function groupSort($a, $b) {
			$countA = $a['members'];
			$countB = $b['members'];
			if($countA == $countB)
				return 0;
			if($countA < $countB)
				return 1;
			return -1;
		}
		break;

	case 'permissions':
		function groupSort($a, $b) {
			$countA = count($a['rights']);
			$countB = count($b['rights']);
			if($countA == $countB)
				return 0;
			if($countA < $countB)
				return 1;
			return -1;
		}
		break;

	default:
		function groupSort($a, $b) {
			return strcasecmp($a['name'], $b['name']);
		}
}

//echo '<pre>', print_r($groups, true), '</pre>';
uasort($groups, 'groupSort');

/*########
## Output
########*/
/* TOC */
echo '<div id="side-toc">',
	'<b>Table of contents</b>',
	'<ol>';
foreach($groups as $group)
	echo '<li><a href="#', $group['anchor'], '" title="', $backend->formatValue($group['name']), '">', $backend->formatText($group['name']), '</a></li>';
echo '</ol></div>';

/* sort */
echo '<p>Sort by ';
$sortLabels = array();
foreach(array('name' => 'name', 'permissions' => 'number of permissions', 'members' => 'number of members') as $key => $display) {
	$sortLabels[] = ($sort == $key
		? '<span>' . $display . '</span>'
		: '<a href="?sort=' . $key . '" title="Sort by ' . $display . '">' . $display . '</a>'
	);
}
echo implode(', ', $sortLabels) . '.</p>';

/* results */
foreach($groups as $group) {
	echo '<h3 id="', $group['anchor'], '">', $backend->formatText($group['name']), '</h3>',
		'<div class="group">',
		'<a href="//meta.wikimedia.org/wiki/Special:GlobalUsers?group=', urlencode($group['key']), '" title="list of users in this group">', $group['members'], ' account', ($group['members'] != 1 ? 's' : ''), '</a> on ';
	if($group['wikiset']) {
		echo '<a href="//meta.wikimedia.org/wiki/Special:WikiSets/', $group['wikiset']['id'], '">';
		if($group['wikiset']['type'] == 'optout')
			echo 'all except ';
		echo $group['wikiset']['count'], ' wiki', ($group['wikiset']['count'] != 1 ? 's' : ''), '</a>';
	}
	else
		echo 'all wikis';
	echo ' ', ($group['members'] != 1 ? 'have' : 'has'), ' these permissions:';

	/* output rights */
	echo '<table class="group-rights">';
	foreach($group['rights'] as $rkey => $right) {
		$blurb = isset($flagBlurbs['right-' . $right]) ? $flagBlurbs['right-' . $right] : '';
		echo '<tr><td class="group-rights-name">', $backend->formatText($right), '</td><td class="group-rights-blurb">', $blurb, '</td></tr>';
	}
	echo '</table></div>';
}

$backend->footer();
?>

<?php
require_once('../backend/modules/Backend.php');
$backend = Backend::create('GlobalGroups', 'A review of extra permissions assigned to <a href="//meta.wikimedia.org/wiki/Steward_handbook#Globally_and_wiki_sets" title="global groups">global groups</a> on Wikimedia Foundation wikis.')
    ->link('/globalgroups/stylesheet.css')
    ->header();

##########
## Flag descriptions
##########
// taken from MediaWiki localization files
// TODO: rethink how this is done
$flagBlurbs = [
    // MediaWiki core: https://github.com/wikimedia/mediawiki/blob/REL1_29/languages/i18n/en.json
    "right-read" => "Read pages",
    "right-edit" => "Edit pages",
    "right-createpage" => "Create pages (which are not discussion pages)",
    "right-createtalk" => "Create discussion pages",
    "right-createaccount" => "Create new user accounts",
    "right-autocreateaccount" => "Automatically log in with an external user account",
    "right-minoredit" => "Mark edits as minor",
    "right-move" => "Move pages",
    "right-move-subpages" => "Move pages with their subpages",
    "right-move-rootuserpages" => "Move root user pages",
    "right-move-categorypages" => "Move category pages",
    "right-movefile" => "Move files",
    "right-suppressredirect" => "Not create redirects from source pages when moving pages",
    "right-upload" => "Upload files",
    "right-reupload" => "Overwrite existing files",
    "right-reupload-own" => "Overwrite existing files uploaded by oneself",
    "right-reupload-shared" => "Override files on the shared media repository locally",
    "right-upload_by_url" => "Upload files from a URL",
    "right-purge" => "Purge the site cache for a page without confirmation",
    "right-autoconfirmed" => "Not be affected by IP-based rate limits",
    "right-bot" => "Be treated as an automated process",
    "right-nominornewtalk" => "Not have minor edits to discussion pages trigger the new messages prompt",
    "right-apihighlimits" => "Use higher limits in API queries",
    "right-writeapi" => "Use of the write API",
    "right-delete" => "Delete pages",
    "right-bigdelete" => "Delete pages with large histories",
    "right-deletelogentry" => "Delete and undelete specific log entries",
    "right-deleterevision" => "Delete and undelete specific revisions of pages",
    "right-deletedhistory" => "View deleted history entries, without their associated text",
    "right-deletedtext" => "View deleted text and changes between deleted revisions",
    "right-browsearchive" => "Search deleted pages",
    "right-undelete" => "Undelete a page",
    "right-suppressrevision" => "View, hide and unhide specific revisions of pages from any user",
    "right-viewsuppressed" => "View revisions hidden from any user",
    "right-suppressionlog" => "View private logs",
    "right-block" => "Block other users from editing",
    "right-blockemail" => "Block a user from sending email",
    "right-hideuser" => "Block a username, hiding it from the public",
    "right-ipblock-exempt" => "Bypass IP blocks, auto-blocks and range blocks",
    "right-unblockself" => "Unblock oneself",
    "right-protect" => "Change protection levels and edit cascade-protected pages",
    "right-editprotected" => "Edit pages protected as \"{{int:protect-level-sysop}}\"",
    "right-editsemiprotected" => "Edit pages protected as \"{{int:protect-level-autoconfirmed}}\"",
    "right-editcontentmodel" => "Edit the content model of a page",
    "right-editinterface" => "Edit the user interface",
    "right-editusercss" => "Edit other users' CSS files",
    "right-edituserjs" => "Edit other users' JavaScript files",
    "right-editmyusercss" => "Edit your own user CSS files",
    "right-editmyuserjs" => "Edit your own user JavaScript files",
    "right-viewmywatchlist" => "View your own watchlist",
    "right-editmywatchlist" => "Edit your own watchlist. Note some actions will still add pages even without this right.",
    "right-viewmyprivateinfo" => "View your own private data (e.g. email address, real name)",
    "right-editmyprivateinfo" => "Edit your own private data (e.g. email address, real name)",
    "right-editmyoptions" => "Edit your own preferences",
    "right-rollback" => "Quickly rollback the edits of the last user who edited a particular page",
    "right-markbotedits" => "Mark rolled-back edits as bot edits",
    "right-noratelimit" => "Not be affected by rate limits",
    "right-import" => "Import pages from other wikis",
    "right-importupload" => "Import pages from a file upload",
    "right-patrol" => "Mark others' edits as patrolled",
    "right-autopatrol" => "Have one's own edits automatically marked as patrolled",
    "right-patrolmarks" => "View recent changes patrol marks",
    "right-unwatchedpages" => "View a list of unwatched pages",
    "right-mergehistory" => "Merge the history of pages",
    "right-userrights" => "Edit all user rights",
    "right-userrights-interwiki" => "Edit user rights of users on other wikis",
    "right-siteadmin" => "Lock and unlock the database",
    "right-override-export-depth" => "Export pages including linked pages up to a depth of 5",
    "right-sendemail" => "Send email to other users",
    "right-managechangetags" => "Create and (de)activate [[Special:Tags|tags]]",
    "right-applychangetags" => "Apply [[Special:Tags|tags]] along with one's changes",
    "right-changetags" => "Add and remove arbitrary [[Special:Tags|tags]] on individual revisions and log entries",
    "right-deletechangetags" => "Delete [[Special:Tags|tags]] from the database",

    // AbuseFilter extension: https://github.com/wikimedia/mediawiki-extensions-AbuseFilter/blob/REL1_28/i18n/en.json
    "right-abusefilter-modify" => "Modify abuse filters",
    "right-abusefilter-view" => "View abuse filters",
    "right-abusefilter-log" => "View the abuse log",
    "right-abusefilter-log-detail" => "View detailed abuse log entries",
    "right-abusefilter-private" => "View private data in the abuse log",
    "right-abusefilter-modify-restricted" => "Modify abuse filters with restricted actions",
    "right-abusefilter-revert" => "Revert all changes by a given abuse filter",
    "right-abusefilter-view-private" => "View abuse filters marked as private",
    "right-abusefilter-log-private" => "View log entries of abuse filters marked as private",
    "right-abusefilter-hide-log" => "Hide entries in the abuse log",
    "right-abusefilter-hidden-log" => "View hidden abuse log entries",
    "right-abusefilter-modify-global" => "Create or modify global abuse filters",

    // AntiSpoof extension: https://github.com/wikimedia/mediawiki-extensions-AntiSpoof/blob/REL1_28/i18n/en.json
    "right-override-antispoof" => "Override the spoofing checks",

    // CentralAuth extension: https://github.com/wikimedia/mediawiki-extensions-CentralAuth/blob/REL1_28/i18n/en.json
    "right-globalgroupmembership" => "Edit membership to global groups",
    "right-centralauth-unmerge" => "Unmerge global account",
    "right-centralauth-lock" => "Lock or unlock global account",
    "right-centralauth-oversight" => "Suppress or hide global account",
    "right-centralauth-merge" => "Merge their account",
    "right-globalgrouppermissions" => "Manage global groups",

    // CentralNotice extension: https://github.com/wikimedia/mediawiki-extensions-CentralNotice/blob/REL1_28/i18n/en.json
    "right-centralnotice-admin" => "Manage central notices",

    // Checkuser extension: https://github.com/wikimedia/mediawiki-extensions-CheckUser/blob/REL1_28/i18n/en.json
    "right-checkuser" => "Check user's IP addresses and other information",
    "right-checkuser-log" => "View the checkuser log",

    // ConfirmEdit extension: https://github.com/wikimedia/mediawiki-extensions-ConfirmEdit/blob/REL1_28/i18n/en.json
    "right-skipcaptcha" => "Perform CAPTCHA-triggering actions without having to go through the CAPTCHA",

    // FlaggedRevs extension: https://github.com/wikimedia/mediawiki-extensions-FlaggedRevs/blob/REL1_28/i18n/flaggedrevs/en.json
    "right-autoreview" => "Have one's own edits automatically marked as \"checked\"",
	"right-autoreviewrestore" => "Auto-review on rollback",
	"right-movestable" => "Move pages with stable versions",
	"right-review" => "Mark revisions as being \"checked\"",
	"right-stablesettings" => "Configure how the stable version is selected and displayed",
	"right-validate" => "Mark revisions as being \"quality\"",
	"right-unreviewedpages" => "View the [[Special:UnreviewedPages|list of unreviewed pages]]",

    // Flow extension: https://github.com/wikimedia/mediawiki-extensions-Flow/blob/REL1_28/i18n/en.json
    "right-flow-create-board" => "Create Flow boards in any location",
	"right-flow-hide" => "Hide Flow topics and posts",
	"right-flow-lock" => "Mark Flow topics as resolved",
	"right-flow-delete" => "Delete Flow topics and posts",
	"right-flow-edit-post" => "Edit Flow posts by other users",
	"right-flow-suppress" => "Suppress Flow revisions",

    // GlobalBlocking extension: https://github.com/wikimedia/mediawiki-extensions-GlobalBlocking/blob/REL1_28/i18n/en.json
    "right-globalblock" => "Make and remove global blocks",
    "right-globalblock-whitelist" => "Disable global blocks locally",
    "right-globalblock-exempt" => "Bypass global blocks",

    // Nuke extension: https://github.com/wikimedia/mediawiki-extensions-Nuke/blob/REL1_28/i18n/en.json
    "right-nuke" => "Mass delete pages",

    // OATHAuth extension: https://github.com/wikimedia/mediawiki-extensions-OATHAuth/blob/REL1_28/i18n/en.json
    "right-oathauth-enable" => "Enable two-factor authentication",
    "right-oathauth-api-all" => "Query and validate OATH information for self and others",

    // OAuth extension: https://github.com/wikimedia/mediawiki-extensions-OAuth/blob/REL1_28/i18n/en.json
    "right-mwoauthproposeconsumer" => "Propose new OAuth consumers",
	"right-mwoauthupdateownconsumer" => "Update OAuth consumers you control",
	"right-mwoauthmanageconsumer" => "Manage OAuth consumers",
	"right-mwoauthsuppress" => "Suppress OAuth consumers",
	"right-mwoauthviewsuppressed" => "View suppressed OAuth consumers",
	"right-mwoauthviewprivate" => "View private OAuth data",
	"right-mwoauthmanagemygrants" => "Manage OAuth grants",

    // RenameUser extension: https://github.com/wikimedia/mediawiki-extensions-RenameUser/blob/REL1_28/i18n/en.json
    "right-renameuser" => "Rename users",

    // SpamBlacklist extension: https://github.com/wikimedia/mediawiki-extensions-SpamBlacklist/blob/REL1_28/i18n/en.json
    "right-spamblacklistlog" => "View the spam blacklist log",

    // TimedMediaHandler extension: https://github.com/wikimedia/mediawiki-extensions-TimedMediaHandler/blob/REL1_28/i18n/en.json
    "right-transcode-reset" => "Reset failed or transcoded videos so they are inserted into the job queue again",
	"right-transcode-status" => "View [[Special:TimedMediaHandler|information about the current transcode activity]]",

    // TitleBlacklist extension: https://github.com/wikimedia/mediawiki-extensions-TitleBlacklist/blob/REL1_28/i18n/en.json
    "right-tboverride" => "Override the title or username blacklist",
    "right-tboverride-account" => "Override the username blacklist",
    "right-titleblacklistlog" => "View title blacklist log",

    // TorBlock extension: https://github.com/wikimedia/mediawiki-extensions-TorBlock/blob/REL1_28/i18n/en.json
    "right-torunblocked" => "Bypass automatic blocks of Tor exit nodes",

    // Translate extension: https://github.com/wikimedia/mediawiki-extensions-Translate/blob/REL1_28/i18n/pagetranslation/en.json
    "right-pagetranslation" => "Mark versions of pages for translation",

    // WikimediaMessages extension: https://github.com/wikimedia/mediawiki-extensions-WikimediaMessages/blob/REL1_28/i18n/wikimedia/en.json
    "right-superprotect" => "Change super protection levels",
    "right-templateeditor" => "Edit protected templates",
    "right-extendedconfirmed" => "Edit restricted pages",
    "right-editeditorprotected" => "Edit pages protected as \"{{int:protect-level-editeditorprotected}}\"",
    "right-editextendedsemiprotected" => "Edit pages protected as \"{{int:protect-level-editextendedsemiprotected}}\"",
    "right-viewdeletedfile" => "View files and pages in the {{ns:file}} and {{ns:file_talk}} namespaces that are deleted"
];


##########
## Query group details
##########
$db = $backend->getDatabase();
$db->connect('metawiki');

$groups = [];
foreach ($db->query('SELECT DISTINCT ggp_group FROM centralauth_p.global_group_permissions')->fetchAllAssoc() as $groupRow) {
    /* group name */
    $groupKey = $groupRow['ggp_group'];
    $group = [
        'key' => $groupKey,
        'name' => $backend->formatInitialCapital(str_replace('_', ' ', $groupKey)),
        'anchor' => $backend->formatAnchor($groupKey),
        'rights' => [],
        'members' => 0,
        'wikis' => 0,
        'wikiset' => []
    ];

    /* rights */
    foreach ($db->query('SELECT ggp_permission FROM centralauth_p.global_group_permissions WHERE ggp_group = ?', [$groupKey])->fetchAllAssoc() as $rightsRow)
        $group['rights'][] = $rightsRow['ggp_permission'];
    sort($group['rights']);
    if (!count($group['rights']))
        continue; // groups with no rights are deleted (but still in the database)

    /* member count */
    $group['members'] = $db->query('SELECT COUNT(*) FROM centralauth_p.global_user_groups LEFT JOIN centralauth_p.globaluser ON gug_user = gu_id WHERE gug_group = ? AND gu_id IS NOT NULL', [$groupKey])->fetchValue();

    /* wikis */
    $wikiset = $db->query('SELECT ws_id, ws_name, ws_type, ws_wikis FROM centralauth_p.wikiset WHERE ws_id = (SELECT ggr_set FROM centralauth_p.global_group_restrictions WHERE ggr_group = ?)', [$groupKey])->fetchAssoc();
    if ($wikiset) {
        $group['wikiset'] = [
            'id' => $wikiset['ws_id'],
            'name' => $wikiset['ws_name'],
            'type' => $wikiset['ws_type'],
            'wikis' => $wikiset['ws_wikis'],
            'count' => substr_count($wikiset['ws_wikis'], ',')
        ];
    }

    /* store values */
    $groups[$groupKey] = $group;
}


##########
## Sort
##########
$sort = $backend->get('sort', 'name');

/**
 * Compare two groups for sorting based on the $sort value.
 * @param array $a The left group.
 * @param array $b The right group.
 * @return int
 */
function groupSort($a, $b)
{
    global $sort;
    switch ($sort) {
        case 'members':
            $countA = $a['members'];
            $countB = $b['members'];
            if ($countA == $countB)
                return 0;
            if ($countA < $countB)
                return 1;
            return -1;

        case 'permissions':
            $countA = count($a['rights']);
            $countB = count($b['rights']);
            if ($countA == $countB)
                return 0;
            if ($countA < $countB)
                return 1;
            return -1;

        default:
            return strcasecmp($a['name'], $b['name']);
    }
}

uasort($groups, 'groupSort');


#########
## Output
#########
/* TOC */
echo "
    <div id='side-toc'>
        <b>Table of contents</b>
        <ol>
    ";
foreach ($groups as $group)
    echo "<li><a href='#{$group['anchor']}' title='{$backend->formatValue($group['name'])}'>{$backend->formatText($group['name'])}</a></li>";
echo "</ol></div>";

/* sort */
echo "<p>Sort by ";
$sortLabels = [];
foreach (['name' => 'name', 'permissions' => 'number of permissions', 'members' => 'number of members'] as $key => $display) {
    $sortLabels[] = ($sort == $key
        ? "<span>$display</span>"
        : "<a href='?sort=$key' title='Sort by $display'>$display</a>"
    );
}
echo implode(', ', $sortLabels), ".</p>";

/* results */
foreach ($groups as $group) {
    echo "
        <h3 id='{$group['anchor']}'>{$backend->formatText($group['name'])}</h3>
        <div class='group'>
            <a href='//meta.wikimedia.org/wiki/Special:GlobalUsers?group=", urlencode($group['key']), "' title='list of users in this group'>{$group['members']} account", ($group['members'] != 1 ? 's' : ''), "</a> on ";
    if ($group['wikiset']) {
        echo '<a href="//meta.wikimedia.org/wiki/Special:WikiSets/', $group['wikiset']['id'], '">';
        if ($group['wikiset']['type'] == 'optout')
            echo 'all except ';
        echo $group['wikiset']['count'], ' wiki', ($group['wikiset']['count'] != 1 ? 's' : ''), '</a>';
    } else
        echo 'all wikis';
    echo ' ', ($group['members'] != 1 ? 'have' : 'has'), ' these permissions:';

    /* output rights */
    echo '<table class="group-rights">';
    foreach ($group['rights'] as $rkey => $right) {
        $blurb = isset($flagBlurbs['right-' . $right]) ? $flagBlurbs['right-' . $right] : '';
        echo '<tr><td class="group-rights-name">', $backend->formatText($right), '</td><td class="group-rights-blurb">', $blurb, '</td></tr>';
    }
    echo '</table></div>';
}

$backend->footer();

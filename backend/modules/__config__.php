<?php
#################################################
## Site configuration and constants
#################################################
$gconfig = Array();


#############################
## Error-reporting
#############################
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);


#############################
## URLs and navigation
#############################
$gconfig['root_url']  = '//toolserver.org/~pathoschild/';
$gconfig['style_url'] = $gconfig['root_url'] . 'backend/content/';
$gconfig['sql_config_file'] = '/home/' . get_current_user() . '/.my.cnf';
$other_root = 'http://pathos.ca/tools/';

$gconfig['tools'] = array(
	'Wikimedia' => array(
		array( 'accountEligibility', 'analyze an account to determine whether it is eligible to vote in a given event.' ),
		array( 'catanalysis', 'analyze edits to pages in a category tree or with a prefix over time.' ),
		array( 'crossBlock', 'lists block status on all Wikimedia wikis, with links to prefilled (un)block forms.' ),
		array( 'crossActivity', 'measures a user\'s latest edit, bureaucrat, or sysop activity on all wikis.' ),
		array( 'globalGroups', 'lists rights with descriptions for each global group.' ),
		array( 'gUserSearch', 'searches and filters global account creations' ),
		array( 'stalktoy', 'provides comprehensive global information about the given user, IP address, or CIDR range.' ),
		array( 'stewardry', 'analyze user activity by group on a Wikimedia wiki.' ),
		array( 'magicredirect', 'redirects to an arbitrary URL with tokens based on user and wiki filled in.' )
	),
	'strings' => array(
		array( 'regextoy', 'perform regex search and replace' ),
		array( 'etymology reader','convert etymology shorthand into legible text.', $other_root ),
		array( 'line numbers','add line numbers to text for proofreading.', $other_root ),
		array( 'poem formatting','convert HTML and wikiML formatting to &lt;poem&gt; formatting.', $other_root )
	),
	'lists' => array(
		array( 'set operations', 'perform set operations on two lists.', $other_root ),
		array( 'sort', 'sort an input list.', $other_root ),
		array( 'increment','increment numbers and repeat text with $new and $last magic words.', $other_root ),
		array( 'timecount','calculate time elapsed in multiple time ranges.', $other_root )
	),
	'databases' => array(
		array( 'iso639db','search ISO 639 codes.', $other_root )
	),
	'other' => array(
		array( 'pgkbot','IRC-based wiki monitoring bot', $other_root ),
		array( 'wikilink action table','create links to pre-filled delete or move forms from wikilinks.', $other_root ),
	)
);

#############################
## Footer
#############################
/* default licensing */
$gconfig['license'] = 'This toy is open-source and released under the <a href="//creativecommons.org/licenses/by/3.0/" title="Creative Commons Attribution 3.0 license">CC-BY 3.0 license</a> (except the <a href="//commons.wikimedia.org/wiki/File:Gear_3.svg" title="gear_3">gear logo</a>).';

/* benchmark precision */
$gconfig['profile_time_precision'] = 3;
$gconfig['profile_perc_precision'] = 2;
?>

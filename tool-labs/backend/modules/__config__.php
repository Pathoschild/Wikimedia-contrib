<?php
#################################################
## Constants
#################################################
##########
## Paths
##########
/**
 * The name of the current user running this script.
 * @var string
 */
DEFINE('SCRIPT_USER', preg_replace('/^\/data\/project\/([^\/]+).*$/', '$1', $_SERVER['DOCUMENT_ROOT']));

/**
 * The directory to which to write non-public data like logs and cache files.
 * @var string
 */
DEFINE('DATA_PATH', '/data/project/' . SCRIPT_USER . '/');

/**
 * The directory to which to write log files.
 * @var string
 */
DEFINE('REPLICA_CNF_PATH', DATA_PATH . '/replica.my.cnf');

/**
 * The directory to which to write log files.
 * @var string
 */
DEFINE('LOG_PATH', DATA_PATH . '/logs/');

/**
 * The directory to which to write cache files.
 * @var string
 */
DEFINE('CACHE_PATH', DATA_PATH . '/cache/');

/**
 * The web-accessible directory containing the backend modules.
 * @var string
 */
DEFINE('BACKEND_PATH', __DIR__);

##########
## Database
##########
/**
 * A database server hostname to always use when connecting to a database. This is intended to simplify SSH tunnelling,
 * but it reduces the effectiveness of load balancing. This should only be set during local development.
 */
DEFINE('FORCE_DB_HOST', null);


#################################################
## Site configuration
#################################################
$settings = [];

#############################
## Error-reporting
#############################
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);
$settings['debug'] = false;


#############################
## URLs and navigation
#############################
$settings['root_url']  = 'https://' . SCRIPT_USER . '.toolforge.org';
$settings['tools'] = [
    'Wikimedia' => [
        ['https://meta.toolforge.org/accounteligibility', 'analyze an account to determine whether it is eligible to vote in a given event.', 'Account eligibility'],
        ['https://meta.toolforge.org/catanalysis', 'analyze edits to pages in a category tree or with a prefix over time.', 'Category analysis'],
        ['https://meta2.toolforge.org/crossactivity', 'measures a user\'s latest edit, bureaucrat, or sysop activity on all wikis.', 'Crosswiki activity'],
        ['https://meta.toolforge.org/globalgroups', 'lists rights with descriptions for each global group.', 'Global groups'],
        ['https://meta.toolforge.org/gusersearch', 'searches and filters global account creations', 'Global user search'],
        ['https://meta.toolforge.org/magicredirect', 'redirects to an arbitrary URL with tokens based on user and wiki filled in.', 'Magic redirect'],
        ['https://meta.toolforge.org/stalktoy', 'provides comprehensive global information about the given user, IP address, or CIDR range.', 'Stalk toy'],
        ['https://meta.toolforge.org/stewardry', 'analyze user activity by group on a Wikimedia wiki.', 'Stewardry'],
        ['https://meta.toolforge.org/userpages', 'find your user pages on all wikis.', 'User pages']
    ],
    'generic' => [
        ['https://meta.toolforge.org/iso639db', 'search ISO 639 codes.', 'ISO-639 database'],
        ['https://meta.toolforge.org/pgkbot', 'IRC-based wiki monitoring bot', 'pgkbot']
    ]
];

#############################
## Footer
#############################
/* default licensing */
$settings['license'] = 'This tool is open-source and released under the <a href="https://github.com/Pathoschild/Wikimedia-contrib/blob/master/LICENSE.txt" title="MIT license">MIT license</a> (except the <a href="https://commons.wikimedia.org/wiki/File:Gear_3.svg" title="gear_3">gear image</a>).';

/* benchmark precision */
$settings['profile_time_precision'] = 3;
$settings['profile_perc_precision'] = 2;

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
$settings['root_url']  = SCRIPT_USER == 'pathoschild-contrib'
    ? ('https://tools.wmflabs.org/' . SCRIPT_USER . '/tools-edge')
    : ('https://tools.wmflabs.org/' . SCRIPT_USER);

$settings['tools'] = [
    'Wikimedia' => [
        ['/meta/accounteligibility', 'analyze an account to determine whether it is eligible to vote in a given event.', 'Account eligibility'],
        ['/meta/catanalysis', 'analyze edits to pages in a category tree or with a prefix over time.', 'Category analysis'],
        ['/crossactivity', 'measures a user\'s latest edit, bureaucrat, or sysop activity on all wikis.', 'Crosswiki activity'],
        ['/meta/globalgroups', 'lists rights with descriptions for each global group.', 'Global groups'],
        ['/meta/gusersearch', 'searches and filters global account creations', 'Global user search'],
        ['/meta/magicredirect', 'redirects to an arbitrary URL with tokens based on user and wiki filled in.', 'Magic redirect'],
        ['/meta/stalktoy', 'provides comprehensive global information about the given user, IP address, or CIDR range.', 'Stalk toy'],
        ['/meta/stewardry', 'analyze user activity by group on a Wikimedia wiki.', 'Stewardry'],
        ['/meta/userpages', 'find your user pages on all wikis.', 'User pages']
    ],
    'generic' => [
        ['/iso639db', 'search ISO 639 codes.', 'ISO-639 database'],
        ['/pgkbot', 'IRC-based wiki monitoring bot', 'pgkbot']
    ]
];

#############################
## Footer
#############################
/* default licensing */
$settings['license'] = 'This tool is open-source and released under the <a href="https://github.com/Pathoschild/Wikimedia-contrib/blob/master/LICENSE.txt" title="MIT license">MIT license</a> (except the <a href="https://commons.wikimedia.org/wiki/File:Gear_3.svg" title="gear_3">gear logo</a>).';

/* benchmark precision */
$settings['profile_time_precision'] = 3;
$settings['profile_perc_precision'] = 2;

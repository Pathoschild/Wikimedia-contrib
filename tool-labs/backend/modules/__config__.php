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
/**
 * The absolute URL to a Toolforge account, with a placeholder for the account name.
 */
$settings['tool_url_format'] = 'https://%s.toolforge.org';

/**
 * The absolute URL to the Toolforge account hosting the current tool.
 */
$settings['root_url']  = sprintf($settings['tool_url_format'], SCRIPT_USER);

/**
 * The shared accounts which host multiple tools, indexed by tool name.
 */
$settings['shared_accounts'] = [
    'accounteligibility' => 'meta',
    'catanalysis'        => 'meta',
    'crossactivity'      => 'meta2',
    'globalgroups'       => 'meta',
    'gusersearch'        => 'meta',
    'magicredirect'      => 'meta',
    'stalktoy'           => 'meta3',
    'stewardry'          => 'meta',
    'userpages'          => 'meta'
];

/**
 * The tools to display in the navbar.
 *
 * Each entry is in the form `toolName => [display name, description]`, where 'toolName' is the canonical name matching
 * its folder.
 */
$settings['tools'] = [
    'accounteligibility' => ['Account eligibility', 'analyze an account to determine whether it is eligible to vote in a given event.'],
    'catanalysis' => ['Category analysis', 'analyze edits to pages in a category tree or with a prefix over time.'],
    'crossactivity' => ['Crosswiki activity','measures a user\'s latest edit, bureaucrat, or sysop activity on all wikis.'],
    'globalgroups' => ['Global groups', 'lists rights with descriptions for each global group.'],
    'gusersearch' => ['Global user search', 'searches and filters global account creations'],
    'magicredirect' => ['Magic redirect', 'redirects to an arbitrary URL with tokens based on user and wiki filled in.'],
    'stalktoy' => ['Stalk toy', 'provides comprehensive global information about the given user, IP address, or CIDR range.'],
    'stewardry' => ['Stewardry', 'analyze user activity by group on a Wikimedia wiki.'],
    'userpages' => ['User pages', 'find your user pages on all wikis.']
];

#############################
## Footer
#############################
/* default licensing */
$settings['license'] = 'This tool is open-source and released under the <a href="https://github.com/Pathoschild/Wikimedia-contrib/blob/master/LICENSE.txt" title="MIT license">MIT license</a> (except the <a href="https://commons.wikimedia.org/wiki/File:Gear_3.svg" title="gear_3">gear image</a>).';

/* benchmark precision */
$settings['profile_time_precision'] = 3;
$settings['profile_perc_precision'] = 2;

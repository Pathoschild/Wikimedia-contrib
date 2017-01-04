<?php
require_once('../backend/modules/Backend.php');
require_once('../backend/models/LocalUser.php');
require_once('Event.php');

$backend = Backend::create('AccountEligibility', 'Analyzes a given user account to determine whether it\'s eligible to vote in the specified event.')
    ->link('/accounteligibility/stylesheet.css')
    ->link('/content/jquery.tablesorter.js')
    ->addScript('$(document).ready(function() { $("#local-accounts").tablesorter({sortList:[[1,1]]}); });')
    ->header();

############################
## Script engine
############################
/**
 * Provides account eligibility methods and event data.
 */
class Script extends Base
{
    ##########
    ## Configuration
    ##########
    /**
     * The default event ID to preselect.
     * @var int
     */
    const DEFAULT_EVENT = 39;

    /**
     * The event data.
     * @var Event[]
     */
    public $events = [];

    ##########
    ## Properties
    ##########
    /**
     * The underlying database manager.
     * @var Toolserver
     */
    public $db;

    /**
     * Provides basic performance profiling.
     * @var Profiler
     */
    public $profiler;

    /**
     * Provides a wrapper used by page scripts to generate HTML, interact with the database, and so forth.
     * @var Backend
     */
    private $backend;

    /**
     * The target username to analyse.
     * @var string
     */
    private $username;

    /**
     * The selected event ID.
     * @var int|null
     */
    private $eventID;

    /**
     * Whether the user must select a wiki manually, because there is no matching global account.
     */
    public $selectManually = false;

    /**
     * The selected wiki.
     * @var Wiki
     */
    public $wiki;

    /**
     * The current local user account.
     * @var LocalUser
     */
    public $user;

    /**
     * The selected event.
     * @var array
     */
    public $event;

    /**
     * The available wikis.
     * @var Wiki[]
     */
    public $wikis = Array();

    /**
     * The user's local accounts as a database name => local account lookup.
     * @var LocalUser[]
     */
    public $users = [];

    /**
     * The list of database names to analyse.
     * @var array
     */
    public $queue = [];

    /**
     * The index of the next item in the wiki queue.
     * @var int
     */
    public $nextQueueIndex = -1;

    /**
     * Whether the user has met all the rules.
     * @var bool
     */
    public $eligible = true;

    /**
     * Whether the user has a unified global account.
     * @var bool
     */
    public $unified = false;

    /**
     * The user's role assignment/removal logs on Meta as a role => array hash.
     * @var array
     */
    private $metaRoleDurationCache = [];


    ############################
    ## Constructor
    ############################
    /**
     * Construct an instance.
     * @param Backend $backend Provides a wrapper used by page scripts to generate HTML, interact with the database, and so forth.
     * @param string $user The username to analyse.
     * @param int $eventID The event ID to analyse.
     * @param string $dbname The wiki database name to analyse.
     */
    public function __construct($backend, $user, $eventID, $dbname)
    {
        parent::__construct();

        /* configure */
        $events = [
            Event::make(39, 2016, 'Commons Picture of the Year for 2015', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2015'),
            Event::make(38, 2016, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2016'),
            Event::make(37, 2016, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2016')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be 18 years old, and at the age of majority in your country.',
                    'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="//meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
                    'You must <a href="https://meta.wikimedia.org/wiki/Special:MyLanguage/Access_to_nonpublic_information_policy" title="Access to nonpublic information policy">sign the confidentiality agreement</a>.'
                ]),
            Event::make(36, 2015, 'Wikimedia Foundation elections', '//meta.wikimedia.org/wiki/Wikimedia_Foundation_elections_2015')
                ->withExtraRequirements(['Your account must not be used by a bot.'])
                ->withExceptions([
                    'You are a Wikimedia server administrator with shell access.',
                    'You have commit access and have made at least one merged commit in git to Wikimedia Foundation utilized repos between 15 October 2014 and 15 April 2015.',
                    'You are a current Wikimedia Foundation staff member or contractor employed by the Foundation as of 15 April 2015.',
                    'You are a current or former member of the Wikimedia Board of Trustees, Advisory Board or Funds Dissemination Committee.'
                ])
                ->withMinEditsForAutoselect(300),
            Event::make(35, 2015, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2015'),
            Event::make(34, 2015, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2015')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be 18 years old, and at the age of majority in your country.',
                    'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="//meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
                    'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2015.'
                ]),
            Event::make(33, 2015, 'Commons Picture of the Year for 2014', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2014'),
            Event::make(32, 2014, 'Commons Picture of the Year for 2013', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2013'),
            Event::make(31, 2014, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2014'),
            Event::make(30, 2014, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2014')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be 18 years old, and at the age of majority in your country.',
                    'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="//meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
                    'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2014.'
                ]),
            Event::make(29, 2013, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2013'),
            Event::make(28, 2013, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2013')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be 18 years old, and at the age of majority in your country.',
                    'You must agree to abide by the policies governing <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">steward access</a>, <a href="//meta.wikimedia.org/wiki/CheckUser_policy" title="checkuser policy">checkuser access</a>, <a href="//meta.wikimedia.org/wiki/Oversight_policy" title="oversight policy">oversight access</a>, and <a href="//wikimediafoundation.org/wiki/privacy_policy" title="privacy policy">privacy</a>.',
                    'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2013.'
                ]),
            Event::make(27, 2013, 'Commons Picture of the Year for 2012', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2012'),
            Event::make(26, 2012, 'enwiki arbcom elections (voters)', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2012')
                ->withOnlyDB('enwiki_p'),
            Event::make(25, 2012, 'enwiki arbcom elections (candidates)', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2012')
                ->withOnlyDB('enwiki_p')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be in good standing and not subject to active blocks or site-bans.',
                    'You must meet the Wikimedia Foundation\'s <a href="//wikimediafoundation.org/w/index.php?title=Access_to_nonpublic_data_policy&oldid=47490" title="Access to nonpublic data policy">criteria for access to non-public data</a> and must identify with the Foundation if elected.',
                    'You must have disclosed any alternate accounts in your election statement (legitimate accounts which have been declared to the Arbitration Committee before the close of nominations need not be publicly disclosed).'
                ]),
            Event::make(24, 2012, 'Commons Picture of the Year for 2011', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2011'),
            Event::make(23, 2012, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/Elections_2012'),
            Event::make(22, 2012, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/Elections_2012')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be 18 years old, and at the age of majority in your country.',
                    'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
                    'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 08 February 2012.'
                ]),
            Event::make(21, 2011, 'enwiki arbcom elections', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2011')
                ->withOnlyDB('enwiki_p'),
            Event::make(20, 2011, 'enwiki arbcom elections (candidates)', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2011')
                ->withOnlyDB('enwiki_p')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be in good standing and not subject to active blocks or site-bans.',
                    'You must meet the Wikimedia Foundation\'s criteria for access to non-public data and must identify with the Foundation if elected.',
                    'You must have disclosed any alternate accounts in your election statement (legitimate accounts which have been declared to the Arbitration Committee prior to the close of nominations need not be publicly disclosed).'
                ]),
            Event::make(19, 2011, '2011-09 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2011-2'),
            Event::make(18, 2011, '2011-09 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2011-2')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be 18 years old, and at the age of majority in your country.',
                    'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
                    'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 07 February 2011.'
                ]),
            Event::make(17, 2011, 'Board elections', '//meta.wikimedia.org/wiki/Board elections/2011')
                ->withMinEditsForAutoselect(300)
                ->withExtraRequirements(['Your account must not be used by a bot.'])
                ->withExceptions([
                    'You are a Wikimedia server administrator with shell access.',
                    'You have MediaWiki commit access and made at least one commit between 15 May 2010 and 15 May 2011.',
                    'You are a Wikimedia Foundation staff or contractor employed by Wikimedia between 15 February 2011 and 15 May 2011.',
                    'You are a current or former member of the Wikimedia Board of Trustees or Advisory Board.'
                ]),
            Event::make(16, 2011, 'Commons Picture of the Year for 2010', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2010'),
            Event::make(15, 2011, 'steward confirmations', '//meta.wikimedia.org/wiki/Stewards/confirm/2011')
                ->withAction('comment'),
            Event::make(14, 2011, '2011-01 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2011'),
            Event::make(13, 2011, '2011-01 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2011')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be 18 years old, and at the age of majority in your country.',
                    'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
                    'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 07 February 2011.'
                ]),
            Event::make(12, 2010, 'enwiki arbcom elections', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2010')
                ->withOnlyDB('enwiki_p'),
            Event::make(11, 2010, '2010-09 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2010-2'),
            Event::make(10, 2010, '2010-09 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2010-2')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be 18 years old, and at the age of majority in your country.',
                    'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a> and <a href="//wikimediafoundation.org/wiki/Template:Policy" title="Wikimedia Foundation policies">Foundation policies</a>.',
                    'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 01 February 2010.'
                ]),
            Event::make(9, 2010, 'Commons Picture of the Year for 2009', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2009'),
            Event::make(8, 2010, '2010-02 steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2010')
                ->withExtraRequirements(['Your account must not be primarily used for automated (bot) tasks.']),
            Event::make(7, 2010, '2010-02 steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2010')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be 18 years old, and at the age of majority in your country.',
                    'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a>.',
                    'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation before 01 February 2010.'
                ]),
            Event::make(6, 2010, 'create global sysops vote', '//meta.wikimedia.org/wiki/Global_sysops/Vote'),
            Event::make(5, 2009, 'enwiki arbcom elections', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2009')
                ->withonlyDB('enwiki_p'),
            Event::make(4, 2009, 'Commons Picture of the Year for 2008', '//commons.wikimedia.org/wiki/Commons:Picture_of_the_Year/2008'),
            Event::make(3, 2009, 'steward elections (candidates)', '//meta.wikimedia.org/wiki/Stewards/elections_2009')
                ->withAction('<strong>be a candidate</strong>')
                ->withExtraRequirements([
                    'You must be 18 years old, and at the age of majority in your country.',
                    'You must agree to abide by the <a href="//meta.wikimedia.org/wiki/Stewards_policy" title="Steward policy">Steward policy</a>.',
                    'You must <a href="//meta.wikimedia.org/wiki/Steward_handbook/email_templates" title="instructions for providing ID">provide your full name and proof of identity</a> to the Wikimedia Foundation.'
                ]),
            Event::make(2, 2009, 'steward elections', '//meta.wikimedia.org/wiki/Stewards/elections_2009')
                ->withMinEditsForAutoselect(600),
            Event::make(1, 2008, 'enwiki arbcom elections', '//en.wikipedia.org/wiki/Wikipedia:Arbitration_Committee_Elections_December_2008')
                ->withOnlyDB('enwiki_p'),
            Event::make(0, 2008, 'Board elections', '//meta.wikimedia.org/wiki/Board elections/2008')
                ->withMinEditsForAutoselect(600)
        ];
        foreach ($events as $event)
            $this->events[$event->id] = $event;

        /* set instances */
        $this->backend = $backend;
        $this->db = $backend->GetDatabase();
        $this->profiler = $backend->profiler;

        /* set user */
        $this->username = $backend->formatUsername($user);

        /* set event */
        $this->eventID = isset($eventID) ? $eventID : self::DEFAULT_EVENT;
        $this->event = $this->events[$this->eventID];

        /* get wikis */
        $this->wikis = $this->db->getWikis();

        /* connect database */
        if (!$dbname)
            $dbname = null;
        $this->connect($dbname);
    }

    /**
     * Get whether there are no more wikis to process.
     * @return bool
     */
    public function isQueueEmpty()
    {
        return $this->nextQueueIndex >= 0;
    }

    /**
     * Load the next wiki in the queue.
     * @param bool $echo Whether to write the wiki name to the output.
     * @return bool Whether a wiki was successfully loaded from the queue.
     */
    public function getNext($echo = true)
    {
        if (!$this->connectNext())
            return false;

        $this->getUser();
        if ($echo)
            $this->printWiki();
        return true;
    }

    /**
     * Load the specified wiki.
     * @param string $dbname The database name to load.
     */
    public function connect($dbname)
    {
        /* reset variables */
        $this->user = new LocalUser(null, $this->backend->formatUsername($this->username), null, null, null);

        /* connect & fetch user details */
        if ($dbname) {
            $this->wiki = $this->wikis[$dbname];
            $this->db->connect($dbname);
        }
    }

    /**
     * Load the next wiki in the queue.
     * @return bool Whether a wiki was successfully loaded from the queue.
     */
    public function connectNext()
    {
        while ($this->nextQueueIndex >= 0) {
            /* skip private wiki (not listed in meta_p.wiki) */
            $dbname = $this->queue[$this->nextQueueIndex--];
            if (!isset($this->wikis[$dbname])) {
                continue;
            }

            /* connect */
            $this->wiki = $this->wikis[$dbname];
            $this->connect($dbname);
            return true;
        }
        return false;
    }

    /**
     * Load the queue of wikis to analyse.
     * @param string|null $default The default wiki.
     * @param int $minEdits The minimum number of edits.
     * @return bool Whether at least one wiki was successfully loaded.
     */
    public function initWikiQueue($default = null, $minEdits = 1)
    {
        ########
        ## Set selected wiki
        ########
        if ($this->wiki) {
            $this->queue = [$this->wiki->dbName];
            $this->nextQueueIndex = 0;
            $this->msg("Selected {$this->wiki->domain}.", 'is-metadata');
        }

        ########
        ## Set single wiki
        ########
        elseif ($default) {
            $this->queue = [$default];
            $this->nextQueueIndex = 0;
            $this->msg("Auto-selected $default.", 'is-metadata');
        }

        ########
        ## Queue unified wikis
        ########
        else {
            /* fetch unified wikis */
            $this->profiler->start('fetch unified wikis');
            $unifiedDbnames = $this->db->getUnifiedWikis($this->user->name);
            if (!$unifiedDbnames) {
                $this->selectManually = true;
                $encoded = urlencode($this->user->name);
                echo '<div id="result" class="neutral" data-is-error="1">', $this->formatText($this->user->name), ' has no global account, so we cannot auto-select an eligible wiki. Please select a wiki (see <a href="', $this->backend->url('/stalktoy/' . $encoded), '" title="global details about this user">global details about this user</a>).</div>';
                return false;
            }
            $this->profiler->stop('fetch unified wikis');

            /* fetch user edit count for each wiki & sort by edit count */
            $this->profiler->start('fetch edit counts');
            foreach ($unifiedDbnames as $unifiedDbname) {
                if (!isset($this->wikis[$unifiedDbname]))
                    continue; // skip private wikis (not listed in meta_p.wiki)
                $this->db->connect($unifiedDbname);
                $this->queue[$unifiedDbname] = $this->db->query('SELECT user_editcount FROM user WHERE user_name = ? LIMIT 1', array($this->user->name))->fetchColumn();
            }
            $this->profiler->stop('fetch edit counts');
            asort($this->queue);

            /**
             * Get whether an edit count meets the minimum edit count needed.
             * @param int $count The edit count to check.
             * @return bool
             */
            function filter($count)
            {
                global $minEdits;
                return $count >= $minEdits;
            }

            $this->queue = array_filter($this->queue, 'filter');

            /* initialize queue */
            $this->queue = array_keys($this->queue);
            $this->nextQueueIndex = count($this->queue) - 1;
            $this->unified = true;

            /* report queue */
            $this->msg('Auto-selected ' . count($this->queue) . ' unified accounts with at least ' . $minEdits . ' edit' . ($minEdits != 1 ? 's' : '') . '.', 'is-metadata');
        }

        ########
        ## Connect & return
        ########
        return $this->getNext(false /*don't output name@wiki yet*/);
    }

    #####
    ## Data methods
    #####
    /**
     * Get whether the user has a global account.
     * @return bool
     */
    public function isGlobal()
    {
        if (!isset($this->user->global))
            $this->user->global = $this->unified || $this->getHomeWiki();
        return $this->user->global;
    }

    /**
     * Get whether the user has a local account on the specified wiki.
     * @param string $wiki The wiki database name to check.
     * @return bool
     */
    public function hasAccount($wiki)
    {
        if ($this->wiki->dbName == $wiki || in_array($wiki, $this->queue))
            return true;
        else {
            $this->db->connect('metawiki');
            $onMeta = $this->db->query('SELECT user_id FROM user WHERE user_name = ? LIMIT 1', array($this->user->name))->fetchColumn();
            $this->db->connectPrevious();
            return $onMeta;
        }
    }

    /**
     * Get the user's home wiki.
     * @return string|null
     */
    public function getHomeWiki()
    {
        return $this->db->getHomeWiki($this->user->name);
    }

    /**
     * Get the user's local account information for the current wiki.
     * @return LocalUser
     */
    public function getUser()
    {
        $dbname = $this->wiki->dbName;

        if (!isset($this->users[$dbname]))
            $this->users[$dbname] = $this->db->getUserDetails($dbname, $this->user->name);

        $this->user = $this->users[$dbname];
        return $this->user;
    }

    /**
     * Get whether the user has the specified role.
     * @param string $role The name of the user role.
     * @return bool
     * @throws Exception The specified role is not whitelisted for use.
     */
    public function hasRole($role)
    {
        if ($role != 'bot' && $role != 'sysop')
            throw new Exception('Unrecognized role "' . $role . '" not found in whitelist.');
        return (bool)$this->db->query('SELECT COUNT(ug_user) FROM user_groups WHERE ug_user=? AND ug_group=? LIMIT 1', array($this->user->id, $role))->fetchColumn();
    }

    /**
     * Get the longest duration (in days) that the user had the specified role on the current wiki.
     * @param string $role The role to check.
     * @param $endDate
     * @return bool|float
     */
    public function getRoleLongestDuration($role, $endDate)
    {
        // SQL to determine the current groups after each log entry
        // (depending on how it was stored on that particular day)
        $sql = '
			SELECT
				log_title,
				log_timestamp,
				log_params,
				log_comment'/*,
				CASE
					WHEN log_params <> "" THEN
						CASE WHEN INSTR("\n", log_params) >= 0
							THEN SUBSTR(log_params, INSTR(log_params, "\n") + 1)
							ELSE log_params
						END
					ELSE log_comment
				END AS "log_resulting_groups"*/ . '
			FROM logging_logindex
			WHERE
				log_type = "rights"
				AND log_title';
        $logName = str_replace(' ', '_', $this->user->name);

        // fetch local logs
        $this->db->query($sql . ' = ?', array($logName));
        $local = $this->db->fetchAllAssoc();

        // merge with Meta logs
        if (!array_key_exists($role, $this->metaRoleDurationCache)) {
            $this->db->connect('metawiki');
            $this->db->query($sql . ' LIKE ?', array($logName . '@%'));
            $this->metaRoleDurationCache[$role] = $this->db->fetchAllAssoc();
            $this->db->connectPrevious();
        }

        $local = array_merge($local, $this->metaRoleDurationCache[$role]);

        // parse log entries
        $logs = array();
        foreach ($local as $row) {
            // alias fields
            $title = $row['log_title'];
            $date = $row['log_timestamp'];
            $params = $row['log_params'];
            $comment = $row['log_comment'];

            // filter logs for wrong wiki / deadline
            if ($title != $logName && $title != "{$logName}@{$this->wiki->dbName}")
                continue;
            if ($date > $endDate)
                continue;

            // parse format (changed over the years)
            if (($i = strpos($params, "\n")) !== false) // params: old\nnew
                $groups = substr($params, $i + 1);
            else if ($params != '')                     // ...or params: new
                $groups = $params;
            else                                       // ...or comment: +new +new OR =
                $groups = $comment;

            // append to timeline
            $logs[$date] = $groups;
        }
        if (count($logs) == 0)
            return false;
        ksort($logs);

        // parse ranges
        $ranges = array();
        $i = -1;
        $wasInRole = $nowInRole = false;
        foreach ($logs as $timestamp => $roles) {
            $nowInRole = (strpos($roles, $role) !== false);

            // start range
            if (!$wasInRole && $nowInRole) {
                ++$i;
                $ranges[$i] = array($timestamp, $endDate);
            }

            // end range
            if ($wasInRole && !$nowInRole)
                $ranges[$i][1] = $timestamp;

            // update trackers
            $wasInRole = $nowInRole;
        }
        if (count($ranges) == 0)
            return false;

        // determine widest range
        $maxDuration = 0;
        foreach ($ranges as $i => $range) {
            $duration = $range[1] - $range[0];
            if ($duration > $maxDuration) {
                $maxDuration = $duration;
            }
        }

        // calculate range length
        $start = DateTime::createFromFormat('YmdHis', $ranges[$i][0]);
        $end = DateTime::createFromFormat('YmdHis', $ranges[$i][1]);
        $diff = $start->diff($end);
        $months = $diff->days / (365.25 / 12);
        return round($months, 2);
    }

    /**
     * Get the number of edits the user has on the current wiki.
     * @param int|null $start The minimum date for which to count edits.
     * @param int|null $end The maximum date for which to count edits.
     * @return int
     */
    public function editCount($start = null, $end = null)
    {
        /* all edits */
        if (!$start && !$end)
            return $this->user->edits;

        /* within date range */
        $sql = 'SELECT COUNT(rev_id) FROM revision_userindex WHERE rev_user=? AND rev_timestamp ';
        if ($start && $end)
            $this->db->query($sql . 'BETWEEN ? AND ?', Array($this->user->id, $start, $end));
        elseif ($start)
            $this->db->query($sql . '>= ?', Array($this->user->id, $start));
        elseif ($end)
            $this->db->query($sql . '<= ?', Array($this->user->id, $end));

        return $this->db->fetchColumn();
    }

    /**
     * Get whether the user is blocked on the current wiki.
     * @return bool
     */
    public function isBlocked()
    {
        $this->db->query('SELECT COUNT(ipb_expiry) FROM ipblocks WHERE ipb_user=? LIMIT 1', array($this->user->id));
        return (bool)$this->db->fetchColumn();
    }

    /**
     * Get whether the user is blocked indefinitely on the current wiki.
     * @return bool
     */
    public function isIndefBlocked()
    {
        $this->db->query('SELECT COUNT(ipb_expiry) FROM ipblocks WHERE ipb_user=? AND ipb_expiry="infinity" LIMIT 1', array($this->user->id));
        return (bool)$this->db->fetchColumn();
    }

    #####
    ## Output methods
    #####
    /**
     * Write a message to the output.
     * @param string $message The message to print.
     * @param string $classes The CSS classes to add to the output line.
     */
    function msg($message, $classes = null)
    {
        // normalize classes
        $classes = $classes
            ? trim($classes)
            : 'is-note';

        // output
        echo '<div class="', $classes, '">', $message, '</div>';
    }

    /**
     * Print a 'name@wiki...' header for the current wiki.
     */
    function printWiki()
    {
        $name = $this->user->name;
        $domain = $this->wiki->domain;
        $this->msg('On <a href="//' . $domain . '/wiki/User:' . $name . '" title="' . $name . '\'s user page on ' . $domain . '">' . $domain . '</a>:', 'is-wiki');
    }

    /**
     * Print a conditional message, set $this->eligible to false if the condition fails, and return the condition value.
     * @param bool $bool The condition to check.
     * @param string $msgEligible The message to print if the condition passed.
     * @param string $msgFailed The message to print if the condition failed.
     * @param string $classEligible The CSS class with which to format the message if the condition passed.
     * @param string $classFailed The CSS class with which to format the message if the condition failed.
     * @return bool
     */
    function condition($bool, $msgEligible, $msgFailed, $classEligible = '', $classFailed = '')
    {
        if ($bool) {
            $this->msg("• $msgEligible", "is-pass $classEligible");
        } else {
            $this->msg("• $msgFailed", "is-fail $classFailed");
            $this->eligible = false;
        }
        return $bool;
    }
}


############################
## Initialize
############################
$event = $backend->get('event') ?: $backend->getRouteValue() ?: Script::DEFAULT_EVENT;
$user = $backend->get('user') ?: $backend->getRouteValue(2) ?: '';
$wiki = $backend->get('wiki', null);
$script = new Script($backend, $user, $event, $wiki);


############################
## Input form
############################
echo '
<form action="', $backend->url('/accounteligibility'), '" method="get">
	<label for="user">User:</label>
	<input type="text" name="user" id="user" value="', $backend->formatValue($script->user->name), '" /> at 
	<select name="wiki" id="wiki">
		<option value="">auto-select wiki</option>', "\n";

foreach ($script->db->getDomains() as $dbname => $domain) {
    if (!$script->db->getLocked($dbname)) {
        $selected = ($dbname == $wiki);
        echo "<option value='$dbname' ", ($selected ? " selected='yes'" : ""), ">{$script->formatText($domain)}</option>";
    }
}
echo '
	</select>
	<br />
	<label for="event">Event:</label>
	<select name="event" id="event">', "\n";

foreach ($script->events as $id => $event) {
    echo "
        <option
            value='{$id}'
            ", ($id == $script->event->id ? " selected='yes' " : ""), "
            ", ($event->obsolete ? " class='is-obsolete'" : ""), "
        >{$event->year} &mdash; {$script->formatText($event->name)}</option>
        ";
}
echo "
        </select>
        <br />
        <input type='submit' value='Analyze »' />
    </form>
    ";


############################
## Timestamp constants
############################
$oneYear = 10000000000;
$oneMonth = 100000000;


############################
## Check requirements
############################
if ($script->user->name)
    echo '<div class="result-box">';

while ($script->user->name) {
    if (!$script->event) {
        echo '<div class="error">There is no event matching the given ID.</div>';
        break;
    }

    echo '<h3>Analysis', ($script->user->name == 'Shanel' ? '♥' : ''), ' </h3>';

    /***************
     * Validate or default wiki
     ***************/
    /* incorrect wiki specified */
    if ($script->wiki && $script->event->onlyDB && $script->wiki->dbName != $script->event->onlyDB) {
        echo '<div class="error">Account must be on ', $script->wikis[$script->event->onlyDB]->domain, '. Choose "auto-select wiki" above to select the correct wiki.</div>';
        break;
    }

    /* initialize wiki queue */
    if (!$script->initWikiQueue($script->event->onlyDB, $script->event->minEditsForAutoselect)) {
        if (!$script->selectManually)
            $script->msg('Selection failed, aborted.');
        break;
    }

    /* validate user exists */
    if (!$script->user->id) {
        echo '<div class="error">', $script->formatText($script->user->name), ' does not exist on ', $script->formatText($script->wiki->domain), '.</div>';
        break;
    }

    /***************
     * Verify requirements
     ***************/
    $script->profiler->start('verify requirements');
    switch ($script->event->id) {
        ############################
        ## 2016 Commons Picture of the Year 2015
        ############################
        case 39:
            $script->printWiki();
            $ageOkay = false;
            $editsOkay = false;
            do {
                $script->eligible = true;

                ########
                ## registered < 2016-Jan-01
                ########
                if (!$ageOkay) {
                    $ageOkay = $script->condition(
                        $dateOkay = ($script->user->registered < 20160101000000),
                        "has an account registered before 01 January 2016 (registered {$script->user->registeredStr})...",
                        "does not have an account registered before 01 January 2016 (registered {$script->user->registeredStr})."
                    );
                }

                ########
                ## >= 75 edits before 2016-Jan-01
                ########
                if (!$editsOkay) {
                    $edits = $script->editCount(null, 20160101000000);
                    $editsOkay = $script->condition(
                        $editsOkay = ($edits >= 75),
                        "has at least 75 edits before 01 January 2016 (has {$edits})...",
                        "does not have at least 75 edits before 01 January 2016 (has {$edits})."
                    );
                }

                $script->eligible = ($ageOkay && $editsOkay);
            } while (!$script->eligible && $script->getNext());
            break;

        ############################
        ## 2016 steward elections
        ############################
        case 38:
            $script->printWiki();

            /* check requirements */
            $priorEdits = 0;
            $recentEdits = 0;
            $combining = false;
            $markedBot = false;
            do {
                ########
                ## Should not be a bot
                ########
                $isBot = $script->hasRole('bot');
                $script->condition(
                    !$isBot,
                    "no bot flag...",
                    "has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
                    "",
                    "is-warn"
                );
                $script->eligible = true;
                if ($isBot && !$markedBot) {
                    $script->event->append_eligible = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
                    $markedBot = true;
                }

                ########
                ## >=600 edits before 01 November 2015
                ########
                $edits = $script->editCount(null, 20151101000000);
                $priorEdits += $edits;
                $script->condition(
                    $edits >= 600,
                    "has 600 edits before 01 November 2015 (has {$edits})...",
                    "does not have 600 edits before 01 November 2015 (has {$edits}). However, edits will be combined with other unified wikis.",
                    "",
                    "is-warn"
                );
                if (!$script->eligible)
                    $combining = true;

                ########
                ## >=50 edits between 2015-Aug-01 and 2016-Jan-31
                ########
                $edits = $script->editCount(20150801000000, 20160131000000);
                $recentEdits += $edits;
                $script->condition(
                    $edits >= 50,
                    "has 50 edits between 01 August 2015 and 31 January 2016 (has {$edits})...",
                    "does not have 50 edits between 01 August 2015 and 31 January 2016 (has {$edits}). However, edits will be combined with other unified wikis.",
                    "",
                    "is-warn"
                );
                if (!$script->eligible)
                    $combining = true;


                ########
                ## Exit conditions
                ########
                $script->eligible = $priorEdits >= 600 && $recentEdits >= 50;

                /* unified met requirements */
                if (!$script->isQueueEmpty()) {
                    if ($script->eligible)
                        break;
                    $combining = true;
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2016 steward elections (candidates)
        ############################
        case 37:
            /* check local requirements */
            $minDurationMet = false;
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## Registered for six months (as of 2015-Feb-08)
                ########
                $script->condition(
                    $script->user->registered < 20150808000000,
                    "has an account registered before 08 August 2015 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 08 August 2015 (registered {$script->user->registeredStr})."
                );

                ########
                ## Flagged as a sysop for three months (as of 2015-Feb-08)
                ########
                if (!$minDurationMet) {
                    /* check flag duration */
                    $months = $script->getRoleLongestDuration('sysop', 20160208000000);
                    $minDurationMet = $months >= 3;
                    $script->condition(
                        $minDurationMet,
                        'was flagged as an administrator for a continuous period of at least three months before 08 February 2016 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
                        'was not flagged as an administrator for a continuous period of at least three months before 08 February 2016 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
                    );

                    /* edge case: if the user was registered before 2005, they might have been flagged before flag changes were logged */
                    if (!$minDurationMet && (!$script->user->registered || $script->user->registered < 20050000000000)) {
                        // output warning
                        $script->msg('<small>' . $script->user->name . ' registered here before 2005, so he might have been flagged before the rights log was created.</small>', 'is-warn is-subnote');

                        // add note
                        $script->event->warn_ineligible = '<strong>This result might be inaccurate.</strong> ' . $script->user->name . ' registered on some wikis before the rights log was created in 2005. You may need to investigate manually.';
                    } else if ($minDurationMet)
                        $script->event->warn_ineligible = null;

                    /* link to log for double-checking */
                    $script->msg('<small>(See <a href="//' . $script->wiki->domain . '/wiki/Special:Log/rights?page=User:' . $script->user->name . '" title="local rights log">local</a> & <a href="//meta.wikimedia.org/wiki/Special:Log/rights?page=User:' . $script->user->name . '@' . $script->wiki->dbName . '" title="crosswiki rights log">crosswiki</a> rights logs.)</small>', 'is-subnote');
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2015 WMF elections
        ############################
        case 36:
            $blockMessage = "Blocked (account is still eligible if only blocked on one wiki).";
            $blockMessageMultiple = "Blocked on more than one wiki.";
            $blockClass = "is-warn";

            $blockCount = 0;
            $editCount = 0;
            $editCountRecent = 0;

            $script->printWiki();

            do {
                $script->eligible = true;


                ########
                ## Not  blocked on more than one wiki
                ########
                $isBlocked = $script->isBlocked();
                $script->condition(
                    !$isBlocked,
                    "not blocked...",
                    $blockMessage,
                    "",
                    $blockClass
                );
                if ($isBlocked) {
                    $blockCount++;
                    $blockClass = "";
                    $blockMessage = $blockMessageMultiple;
                }

                ########
                ## Not a bot
                ########
                $script->condition(
                    !$script->hasRole('bot'),
                    "no bot flag...",
                    "has a bot flag: this account is not eligible.",
                    "",
                    "is-fail"
                );


                ########
                ## >=300 edits before 2015-04-15
                ########
                $edits = $script->editCount(null, 20150415235959);
                $script->condition(
                    $edits >= 300,
                    "has 300 edits before 15 April 2015 (has {$edits})...",
                    "does not have 300 edits before 15 April 2015 (has {$edits}); edits can be combined across wikis.",
                    "",
                    "is-warn"
                );
                $editCount += $edits;

                ########
                ## >=20 edits between 2014-10-15 and 2015-04-15
                ########
                $edits = $script->editCount(20141015000000, 20150415235959);
                $script->condition(
                    $edits >= 20,
                    "has 20 edits between 15 October 2014 and 15 April 2015 (has {$edits})...",
                    "does not have 20 edits between 15 October 2014 and 15 April 2015 (has {$edits}); edits can be combined across wikis.",
                    "",
                    "is-warn"
                );
                $editCountRecent += $edits;

                ########
                ## Exit conditions
                ########
                $script->eligible = ($blockCount <= 1 && $editCount >= 300 && $editCountRecent >= 20);
            } while (!$script->eligible && $script->getNext());

            break;

        ############################
        ## 2015 steward elections
        ############################
        case 35:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (optional)...",
                "",
                "is-warn"
            );
            $script->eligible = true;

            ########
            ## Check local requirements
            ########
            $script->printWiki();

            /* set messages for global accounts */
            if ($script->unified) {
                $editsFailAppend = "However, edits will be combined with other unified wikis.";
                $editsFailAttrs = "is-warn";
            } else {
                $editsFailAppend = '';
                $editsFailAttrs = '';
            }

            /* check requirements */
            $priorEdits = 0;
            $recentEdits = 0;
            $combining = false;
            $markedBot = false;
            do {
                ########
                ## Should not be a bot
                ########
                $isBot = $script->hasRole('bot');
                $script->condition(
                    !$isBot,
                    "no bot flag...",
                    "has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
                    "",
                    "is-warn"
                );
                $script->eligible = true;
                if ($isBot && !$markedBot) {
                    $script->event->append_eligible = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
                    $markedBot = true;
                }

                ########
                ## >=600 edits before 01 November 2014
                ########
                $edits = $script->editCount(null, 20141101000000);
                $priorEdits += $edits;
                $script->condition(
                    $edits >= 600,
                    "has 600 edits before 01 November 2014 (has {$edits})...",
                    "does not have 600 edits before 01 November 2014 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }

                ########
                ## >=50 edits between 2014-Aug-01 and 2015-Jan-31
                ########
                $edits = $script->editCount(20140801000000, 20150131000000);
                $recentEdits += $edits;
                $script->condition(
                    $edits >= 50,
                    "has 50 edits between 01 August 2014 and 31 January 2015 (has {$edits})...",
                    "does not have 50 edits between 01 August 2014 and 31 January 2015 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }


                ########
                ## Exit conditions
                ########
                $script->eligible = $priorEdits >= 600 && $recentEdits >= 50;

                /* unified met requirements */
                if ($script->unified && !$script->isQueueEmpty()) {
                    if ($script->eligible) {
                        break;
                    }
                    $combining = true;
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2015 steward elections (candidates)
        ############################
        case 34:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }


            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Check local requirements
            ########
            /* check local requirements */
            $minDurationMet = false;
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## Registered for six months (i.e. <= 2014-Aug-08)
                ########
                $script->condition(
                    $script->user->registered < 20140808000000,
                    "has an account registered before 08 August 2014 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 08 August 2014 (registered {$script->user->registeredStr})."
                );

                ########
                ## Flagged as a sysop for three months (as of 2015-Feb-08)
                ########
                if (!$minDurationMet) {
                    /* check flag duration */
                    $months = $script->getRoleLongestDuration('sysop', 20150208000000);
                    $minDurationMet = $months >= 3;
                    $script->condition(
                        $minDurationMet,
                        'was flagged as an administrator for a continuous period of at least three months before 08 February 2015 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
                        'was not flagged as an administrator for a continuous period of at least three months before 08 February 2015 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
                    );

                    /* edge case: if the user was registered before 2005, they might have been flagged before flag changes were logged */
                    if (!$minDurationMet && (!$script->user->registered || $script->user->registered < 20050000000000)) {
                        // output warning
                        $script->msg('<small>' . $script->user->name . ' registered here before 2005, so he might have been flagged before the rights log was created.</small>', 'is-warn is-subnote');

                        // add note
                        $script->event->warn_ineligible = '<strong>This result might be inaccurate.</strong> ' . $script->user->name . ' registered on some wikis before the rights log was created in 2005. You may need to investigate manually.';
                    } else if ($minDurationMet)
                        $script->event->warn_ineligible = null;

                    /* link to log for double-checking */
                    $script->msg('<small>(See <a href="//' . $script->wiki->domain . '/wiki/Special:Log/rights?page=User:' . $script->user->name . '" title="local rights log">local</a> & <a href="//meta.wikimedia.org/wiki/Special:Log/rights?page=User:' . $script->user->name . '@' . $script->wiki->dbName . '" title="crosswiki rights log">crosswiki</a> rights logs.)</small>', 'is-subnote');
                }
            } while (!$script->eligible && $script->getNext());
            break;

        ############################
        ## 2015 Commons Picture of the Year 2014
        ############################
        case 33:
            $script->printWiki();
            $ageOkay = false;
            $editsOkay = false;
            do {
                $script->eligible = true;

                ########
                ## registered < 2014-Jan-01
                ########
                if (!$ageOkay) {
                    $ageOkay = $script->condition(
                        $dateOkay = ($script->user->registered < 20150101000000),
                        "has an account registered before 01 January 2015 (registered {$script->user->registeredStr})...",
                        "does not have an account registered before 01 January 2015 (registered {$script->user->registeredStr})."
                    );
                }

                ########
                ## >= 75 edits before 2014-Jan-01
                ########
                if (!$editsOkay) {
                    $edits = $script->editCount(null, 20150101000000);
                    $editsOkay = $script->condition(
                        $editsOkay = ($edits >= 75),
                        "has at least 75 edits before 01 January 2015 (has {$edits})...",
                        "does not have at least 75 edits before 01 January 2015 (has {$edits})."
                    );
                }

                $script->eligible = ($ageOkay && $editsOkay);
            } while (!$script->eligible && $script->getNext());
            break;

        ############################
        ## 2014 Commons Picture of the Year 2013
        ############################
        case 32:
            $script->printWiki();
            $ageOkay = false;
            $editsOkay = false;
            do {
                $script->eligible = true;

                ########
                ## registered < 2014-Jan-01
                ########
                if (!$ageOkay) {
                    $ageOkay = $script->condition(
                        $dateOkay = ($script->user->registered < 20140101000000),
                        "has an account registered before 01 January 2014 (registered {$script->user->registeredStr})...",
                        "does not have an account registered before 01 January 2014 (registered {$script->user->registeredStr})."
                    );
                }

                ########
                ## > 75 edits before 2014-Jan-01
                ########
                if (!$editsOkay) {
                    $edits = $script->editCount(null, 20140101000000);
                    $editsOkay = $script->condition(
                        $editsOkay = ($edits > 75),
                        "has more than 75 edits before 01 January 2014 (has {$edits})...",
                        "does not have more than 75 edits before 01 January 2014 (has {$edits})."
                    );
                }

                $script->eligible = ($ageOkay && $editsOkay);
            } while (!$script->eligible && $script->getNext());
            break;

        ############################
        ## 2014 steward elections
        ############################
        case 31:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (optional)...",
                "",
                "is-warn"
            );
            $script->eligible = true;

            ########
            ## Check local requirements
            ########
            $script->printWiki();

            /* set messages for global accounts */
            if ($script->unified) {
                $editsFailAppend = "However, edits will be combined with other unified wikis.";
                $editsFailAttrs = "is-warn";
            } else {
                $editsFailAppend = '';
                $editsFailAttrs = '';
            }

            /* check requirements */
            $priorEdits = 0;
            $recentEdits = 0;
            $combining = false;
            $markedBot = false;
            do {
                ########
                ## Should not be a bot
                ########
                $isBot = $script->hasRole('bot');
                $script->condition(
                    !$isBot,
                    "no bot flag...",
                    "has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
                    "",
                    "is-warn"
                );
                $script->eligible = true;
                if ($isBot && !$markedBot) {
                    $script->event->append_eligible = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
                    $markedBot = true;
                }

                ########
                ## >=600 edits before 01 November 2013
                ########
                $edits = $script->editCount(null, 20131101000000);
                $priorEdits += $edits;
                $script->condition(
                    $edits >= 600,
                    "has 600 edits before 01 November 2013 (has {$edits})...",
                    "does not have 600 edits before 01 November 2013 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }

                ########
                ## >=50 edits between 2013-Aug-01 and 2014-Jan-31
                ########
                $edits = $script->editCount(20130801000000, 20140131000000);
                $recentEdits += $edits;
                $script->condition(
                    $edits >= 50,
                    "has 50 edits between 01 August 2013 and 31 January 2014 (has {$edits})...",
                    "does not have 50 edits between 01 August 2013 and 31 January 2014 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }


                ########
                ## Exit conditions
                ########
                $script->eligible = $priorEdits >= 600 && $recentEdits >= 50;

                /* unified met requirements */
                if ($script->unified && !$script->isQueueEmpty()) {
                    if ($script->eligible) {
                        break;
                    }
                    $combining = true;
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2014 steward elections (candidates)
        ############################
        case 30:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }


            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Check local requirements
            ########
            /* check local requirements */
            $minDurationMet = false;
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## Registered for six months (i.e. <= 2013-Aug-08)
                ########
                $script->condition(
                    $script->user->registered < 20130808000000,
                    "has an account registered before 08 August 2013 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 08 August 2013 (registered {$script->user->registeredStr})."
                );

                ########
                ## Flagged as a sysop for three months (as of 2014-Feb-08)
                ########
                if (!$minDurationMet) {
                    /* check flag duration */
                    $months = $script->getRoleLongestDuration('sysop', 20140208000000);
                    $minDurationMet = $months >= 3;
                    $script->condition(
                        $minDurationMet,
                        'was flagged as an administrator for a continuous period of at least three months before 08 February 2014 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
                        'was not flagged as an administrator for a continuous period of at least three months before 08 February 2014 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
                    );

                    /* edge case: if the user was registered before 2005, they might have been flagged before flag changes were logged */
                    if (!$minDurationMet && (!$script->user->registered || $script->user->registered < 20050000000000)) {
                        // output warning
                        $script->msg('<small>' . $script->user->name . ' registered here before 2005, so he might have been flagged before the rights log was created.</small>', 'is-warn is-subnote');

                        // add note
                        $script->event->warn_ineligible = '<strong>This result might be inaccurate.</strong> ' . $script->user->name . ' registered on some wikis before the rights log was created in 2005. You may need to investigate manually.';
                    } else if ($minDurationMet)
                        $script->event->warn_ineligible = null;

                    /* link to log for double-checking */
                    $script->msg('<small>(See <a href="//' . $script->wiki->domain . '/wiki/Special:Log/rights?page=User:' . $script->user->name . '" title="local rights log">local</a> & <a href="//meta.wikimedia.org/wiki/Special:Log/rights?page=User:' . $script->user->name . '@' . $script->wiki->dbName . '" title="crosswiki rights log">crosswiki</a> rights logs.)</small>', 'is-subnote');
                }
            } while (!$script->eligible && $script->getNext());
            break;

        ############################
        ## 2013 steward elections
        ############################
        case 29:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (optional)...",
                "",
                "is-warn"
            );
            $script->eligible = true;

            ########
            ## Check local requirements
            ########
            $script->printWiki();

            /* set messages for global accounts */
            if ($script->unified) {
                $editsFailAppend = "However, edits will be combined with other unified wikis.";
                $editsFailAttrs = "is-warn";
            } else {
                $editsFailAppend = '';
                $editsFailAttrs = '';
            }

            /* check requirements */
            $priorEdits = 0;
            $recentEdits = 0;
            $combining = false;
            $markedBot = false;
            do {
                ########
                ## Should not be a bot
                ########
                $isBot = $script->hasRole('bot');
                $script->condition(
                    !$isBot,
                    "no bot flag...",
                    "has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
                    "",
                    "is-warn"
                );
                $script->eligible = true;
                if ($isBot && !$markedBot) {
                    $script->event->append_eligible = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
                    $markedBot = true;
                }

                ########
                ## >=600 edits before 01 November 2012
                ########
                $edits = $script->editCount(null, 20121101000000);
                $priorEdits += $edits;
                $script->condition(
                    $edits >= 600,
                    "has 600 edits before 01 November 2012 (has {$edits})...",
                    "does not have 600 edits before 01 November 2012 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }

                ########
                ## >=50 edits between 2012-Aug-01 and 2013-Jan-31
                ########
                $edits = $script->editCount(20120801000000, 20130131000000);
                $recentEdits += $edits;
                $script->condition(
                    $edits >= 50,
                    "has 50 edits between 01 August 2012 and 31 January 2013 (has {$edits})...",
                    "does not have 50 edits between 01 August 2012 and 31 January 2013 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }


                ########
                ## Exit conditions
                ########
                $script->eligible = $priorEdits >= 600 && $recentEdits >= 50;

                /* unified met requirements */
                if ($script->unified && !$script->isQueueEmpty()) {
                    if ($script->eligible) {
                        break;
                    }
                    $combining = true;
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2013 steward elections (candidates)
        ############################
        case 28:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }


            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Check local requirements
            ########
            /* check local requirements */
            $minDurationMet = false;
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## Registered for six months (i.e. <= 2012-Aug-08)
                ########
                $script->condition(
                    $script->user->registered < 20120808000000,
                    "has an account registered before 08 August 2012 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 08 August 2012 (registered {$script->user->registeredStr})."
                );

                ########
                ## Flagged as a sysop for three months (as of 2013-Feb-08)
                ########
                if (!$minDurationMet) {
                    /* check flag duration */
                    $months = $script->getRoleLongestDuration('sysop', 20130208000000);
                    $minDurationMet = $months >= 3;
                    $script->condition(
                        $minDurationMet,
                        'was flagged as an administrator for a continuous period of at least three months before 08 February 2013 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
                        'was not flagged as an administrator for a continuous period of at least three months before 08 February 2013 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
                    );

                    /* edge case: if the user was registered before 2005, they might have been flagged before flag changes were logged */
                    if (!$minDurationMet && (!$script->user->registered || $script->user->registered < 20050000000000)) {
                        // output warning
                        $script->msg('<small>' . $script->user->name . ' registered here before 2005, so he might have been flagged before the rights log was created.</small>', 'is-warn is-subnote');

                        // add note
                        $script->event->warn_ineligible = '<strong>This result might be inaccurate.</strong> ' . $script->user->name . ' registered on some wikis before the rights log was created in 2005. You may need to investigate manually.';
                    } else if ($minDurationMet)
                        $script->event->warn_ineligible = null;

                    /* link to log for double-checking */
                    $script->msg('<small>(See <a href="//' . $script->wiki->domain . '/wiki/Special:Log/rights?page=User:' . $script->user->name . '" title="local rights log">local</a> & <a href="//meta.wikimedia.org/wiki/Special:Log/rights?page=User:' . $script->user->name . '@' . $script->wiki->dbName . '" title="crosswiki rights log">crosswiki</a> rights logs.)</small>', 'is-subnote');
                }
            } while (!$script->eligible && $script->getNext());
            break;

        ############################
        ## 2013 Commons Picture of the Year 2012
        ############################
        case 27:
            $script->printWiki();
            $ageOkay = false;
            $editsOkay = false;
            do {
                $script->eligible = true;

                ########
                ## registered < 2013-Jan-01
                ########
                if (!$ageOkay) {
                    $ageOkay = $script->condition(
                        $dateOkay = ($script->user->registered < 20130101000000),
                        "has an account registered before 01 January 2013 (registered {$script->user->registeredStr})...",
                        "does not have an account registered before 01 January 2013 (registered {$script->user->registeredStr})."
                    );
                }

                ########
                ## > 75 edits before 2013-Jan-01
                ########
                if (!$editsOkay) {
                    $edits = $script->editCount(null, 20130101000000);
                    $editsOkay = $script->condition(
                        $editsOkay = ($edits >= 75),
                        "has more than 75 edits before 01 January 2013 (has {$edits})...",
                        "does not have more than 75 edits before 01 January 2013 (has {$edits})."
                    );
                }

                $script->eligible = ($ageOkay && $editsOkay);
            } while (!$script->eligible && $script->getNext());
            break;

        ############################
        ## 2012 enwiki arbcom elections (voters)
        ############################
        case 26:
            $script->printWiki();

            ########
            ## registered < 2012-Oct-28
            ########
            $script->condition(
                ($script->user->registered < 20121028000000),
                "has an account registered before 28 October 2012 (registered {$script->user->registeredStr})...",
                "does not have an account registered before 28 October 2012 (registered {$script->user->registeredStr})."
            );

            ########
            ## >=150 main-NS edits before 2012-Nov-01
            ########
            /* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
            $script->db->query(
                'SELECT data.count FROM ('
                . 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
                . 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
                . ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
                . ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
                [$script->user->id, 20121102000000]
            );
            $edits = $script->db->fetchColumn();
            $script->condition(
                $edits >= 150,
                "has 150 main-namespace edits on or before 01 November 2012 (has {$edits})...",
                "does not have 150 main-namespace edits on or before 01 November 2012 (has {$edits})."
            );

            ########
            ## Not currently blocked
            ########
            $script->condition(
                !$script->isBlocked(),
                "not currently blocked...",
                "must not be blocked during at least part of election (verify <a href='//en.wikipedia.org/wiki/Special:Log/block?user=" . urlencode($script->user->name) . "' title='block log'>block log</a>)."
            );
            break;

        ############################
        ## 2012 enwiki arbcom elections (candidates)
        ############################
        case 25:
            $script->printWiki();

            ########
            ## >=500 main-NS edits before 2012-Nov-02
            ########
            /* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
            $script->db->query(
                'SELECT data.count FROM ('
                . 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
                . 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
                . ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
                . ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
                [$script->user->id, 20121102000000]
            );
            $edits = $script->db->fetchColumn();
            $script->condition(
                $edits >= 500,
                "has 500 main-namespace edits on or before 01 November 2012 (has {$edits})...",
                "does not have 500 main-namespace edits on or before 01 November 2012 (has {$edits})."
            );

            ########
            ## Not currently blocked
            ########
            $script->condition(
                !$script->isBlocked(),
                "not currently blocked...",
                "must not be currently blocked (verify <a href='//en.wikipedia.org/wiki/Special:Log/block?user=" . urlencode($script->user->name) . "' title='block log'>block log</a>)."
            );
            break;

        ############################
        ## 2012 Commons Picture of the Year 2011
        ############################
        case 24:
            $script->printWiki();
            $ageOkay = false;
            $editsOkay = false;
            do {
                $script->eligible = true;

                ########
                ## registered < 2012-Apr-01
                ########
                if (!$ageOkay) {
                    $ageOkay = $script->condition(
                        $dateOkay = ($script->user->registered < 20120401000000),
                        "has an account registered before 01 April 2012 (registered {$script->user->registeredStr})...",
                        "does not have an account registered before 01 April 2012 (registered {$script->user->registeredStr})."
                    );
                }

                ########
                ## > 75 edits before 2012-Apr-01
                ########
                if (!$editsOkay) {
                    $edits = $script->editCount(null, 20120401000000);
                    $editsOkay = $script->condition(
                        $editsOkay = ($edits >= 75),
                        "has more than 75 edits before 01 April 2012 (has {$edits})...",
                        "does not have more than 75 edits before 01 April 2012 (has {$edits})."
                    );
                }

                $script->eligible = ($ageOkay && $editsOkay);
            } while (!$script->eligible && $script->getNext());
            break;

        ############################
        ## 2012 steward elections
        ############################
        case 23:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (<strong>optional</strong>)...",
                "",
                "is-warn"
            );
            $script->eligible = true;

            ########
            ## Check local requirements
            ########
            $script->printWiki();

            /* set messages for global accounts */
            if ($script->unified) {
                $editsFailAppend = "However, edits will be combined with other unified wikis.";
                $editsFailAttrs = "is-warn";
            } else {
                $editsFailAppend = '';
                $editsFailAttrs = '';
            }

            /* check requirements */
            $priorEdits = 0;
            $recentEdits = 0;
            $combining = false;
            $markedBot = false;
            do {
                ########
                ## Should not be a bot
                ########
                $isBot = $script->hasRole('bot');
                $script->condition(
                    !$isBot,
                    "no bot flag...",
                    "has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
                    "",
                    "is-warn"
                );
                $script->eligible = true;
                if ($isBot && !$markedBot) {
                    $script->event->append_eligible = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
                    $markedBot = true;
                }

                ########
                ## >=600 edits before 01 November 2011
                ########
                $edits = $script->editCount(null, 20111101000000);
                $priorEdits += $edits;
                $script->condition(
                    $edits >= 600,
                    "has 600 edits before 01 November 2011 (has {$edits})...",
                    "does not have 600 edits before 01 November 2011 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }

                ########
                ## >=50 edits between 2011-Aug-01 and 2012-Jan-31
                ########
                $edits = $script->editCount(20110801000000, 20120131000000);
                $recentEdits += $edits;
                $script->condition(
                    $edits >= 50,
                    "has 50 edits between 01 August 2011 and 31 January 2012 (has {$edits})...",
                    "does not have 50 edits between 01 August 2011 and 31 January 2012 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }


                ########
                ## Exit conditions
                ########
                $script->eligible = $priorEdits >= 600 && $recentEdits >= 50;

                /* unified met requirements */
                if ($script->unified && !$script->isQueueEmpty()) {
                    if ($script->eligible) {
                        break;
                    }
                    $combining = true;
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2012 steward elections (candidates)
        ############################
        case 22:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }


            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Check local requirements
            ########
            /* check local requirements */
            $minDurationMet = false;
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## Registered before 2011-July-10
                ########
                $script->condition(
                    $script->user->registered < 20110710000000,
                    "has an account registered before 10 July 2011 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 10 July 2011 (registered {$script->user->registeredStr})."
                );

                ########
                ## Must have been a sysop for three months (as of 29 January 2012)
                ########
                if (!$minDurationMet) {
                    /* check flag duration */
                    $months = $script->getRoleLongestDuration('sysop', 20120129000000);
                    $minDurationMet = $months >= 3;
                    $script->condition(
                        $minDurationMet,
                        'was flagged as an administrator for a continuous period of at least three months before 29 January 2012 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
                        'was not flagged as an administrator for a continuous period of at least three months before 29 January 2012 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
                    );

                    /* edge case: if the user was registered before 2005, they might have been flagged before flag changes were logged */
                    if (!$minDurationMet && (!$script->user->registered || $script->user->registered < 20050000000000)) {
                        // output warning
                        $script->msg('<small>' . $script->user->name . ' registered here before 2005, so he might have been flagged before the rights log was created.</small>', 'is-warn is-subnote');

                        // add note
                        $script->event->warn_ineligible = '<strong>This result might be inaccurate.</strong> ' . $script->user->name . ' registered on some wikis before the rights log was created in 2005. You may need to investigate manually.';
                    } else if ($minDurationMet)
                        $script->event->warn_ineligible = null;

                    /* link to log for double-checking */
                    $script->msg('<small>(See <a href="//' . $script->wiki->domain . '/wiki/Special:Log/rights?page=User:' . $script->user->name . '" title="local rights log">local</a> & <a href="//meta.wikimedia.org/wiki/Special:Log/rights?page=User:' . $script->user->name . '@' . $script->wiki->dbName . '" title="crosswiki rights log">crosswiki</a> rights logs.)</small>', 'is-subnote');
                }
            } while (!$script->eligible && $script->getNext());
            break;

        ############################
        ## 2011 enwiki arbcom elections
        ############################
        case 20: // candidates
        case 21: // voters (same checked rules)
            $script->printWiki();

            ########
            ## >=150 main-NS edits before 2011-Nov-01
            ########
            /* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
            $script->db->query(
                'SELECT data.count FROM ('
                . 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
                . 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
                . ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
                . ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
                [$script->user->id, 20111101000000]
            );
            $edits = $script->db->fetchColumn();
            $script->condition(
                $edits >= 150,
                "has 150 main-namespace edits on or before 01 November 2011 (has {$edits})...",
                "does not have 150 main-namespace edits on or before 01 November 2011 (has {$edits})."
            );

            ########
            ## Not currently blocked
            ########
            $script->condition(
                !$script->isBlocked(),
                "not currently blocked...",
                "must not be blocked during at least part of election (verify <a href='//en.wikipedia.org/wiki/Special:Log/block?user=" . urlencode($script->user->name) . "' title='block log'>block log</a>)."
            );
            break;

        ############################
        ## 2011-09 steward elections
        ############################
        case 19:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (<strong>optional</strong>)...",
                "",
                "is-warn"
            );
            $script->eligible = true;

            ########
            ## Check local requirements
            ########
            $script->printWiki();

            /* set messages for global accounts */
            if ($script->unified) {
                $editsFailAppend = "However, edits will be combined with other unified wikis.";
                $editsFailAttrs = "is-warn";
            } else {
                $editsFailAppend = '';
                $editsFailAttrs = '';
            }

            /* check requirements */
            $priorEdits = 0;
            $recentEdits = 0;
            $combining = false;
            $markedBot = false;
            do {
                ########
                ## Should not be a bot
                ########
                $isBot = $script->hasRole('bot');
                $script->condition(
                    !$isBot,
                    "no bot flag...",
                    "has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
                    "",
                    "is-warn"
                );
                $script->eligible = true;
                if ($isBot && !$markedBot) {
                    $script->event->append_eligible = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
                    $markedBot = true;
                }

                ########
                ## >=600 edits before 15 June 2011
                ########
                $edits = $script->editCount(null, 20110614000000);
                $priorEdits += $edits;
                $script->condition(
                    $edits >= 600,
                    "has 600 edits before 15 June 2011 (has {$edits})...",
                    "does not have 600 edits before 15 June 2011 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }

                ########
                ## >=50 edits between 2011-Mar-15 and 2011-Sep-14
                ########
                $edits = $script->editCount(20110315000000, 20110914000000);
                $recentEdits += $edits;
                $script->condition(
                    $edits >= 50,
                    "has 50 edits between 15 March 2011 and 14 September 2011 (has {$edits})...",
                    "does not have 50 edits between 15 March 2011 and 14 September 2011 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }


                ########
                ## Exit conditions
                ########
                $script->eligible = $priorEdits >= 600 && $recentEdits >= 50;

                /* unified met requirements */
                if ($script->unified && !$script->isQueueEmpty()) {
                    if ($script->eligible) {
                        break;
                    }
                    $combining = true;
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2011 steward elections (candidates)
        ############################
        case 18:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }


            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>..."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Check local requirements
            ########
            /* check local requirements */
            $minDurationMet = false;
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## Registered before 2010-Mar-29
                ########
                $script->condition(
                    $script->user->registered < 20110314000000,
                    "has an account registered before 14 March 2011 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 14 March 2011 (registered {$script->user->registeredStr})."
                );

                ########
                ## Must have been a sysop for three months
                ########
                if (!$minDurationMet) {
                    $months = $script->getRoleLongestDuration('sysop', 20110913000000);
                    $minDurationMet = $months >= 3;
                    $script->condition(
                        $minDurationMet,
                        'was flagged as an administrator for a continuous period of at least three months before 13 September 2011 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
                        'was not flagged as an administrator for a continuous period of at least three months before 13 September 2011 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
                    );
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2011 Board elections
        ############################
        case 17:
            $indefBlockMessage = "indefinitely blocked: account is still eligible if only blocked on one wiki.";
            $indefBlockMessageMultiple = "indefinitely blocked on more than one wiki.";
            $indefBlockClass = "is-warn";

            $indefBlockCount = 0;
            $editCount = 0;
            $editCountRecent = 0;

            $script->printWiki();

            do {
                $script->eligible = true;

                ########
                ## Not a bot
                ########
                $script->condition(
                    !$script->hasRole('bot'),
                    "no bot flag...",
                    "has a bot flag: this account might not be eligible (refer to the requirements).",
                    "",
                    "is-warn"
                );

                ########
                ## Not indefinitely blocked on more than one wiki
                ########
                $isBlocked = $script->isIndefBlocked();
                $script->condition(
                    !$isBlocked,
                    "not indefinitely blocked...",
                    $indefBlockMessage,
                    "",
                    $indefBlockClass
                );
                if ($isBlocked) {
                    $indefBlockCount++;
                    $indefBlockClass = "";
                    $indefBlockMessage = $indefBlockMessageMultiple;
                }

                ########
                ## >=300 edits before 2011-04-15
                ########
                $edits = $script->editCount(null, 20110415000000);
                $script->condition(
                    $edits >= 300,
                    "has 300 edits before 15 April 2011 (has {$edits})...",
                    "does not have 300 edits before 15 April 2011 (has {$edits}); edits can be combined across wikis.",
                    "",
                    "is-warn"
                );
                $editCount += $edits;

                ########
                ## >=20 edits between 2010-Nov-15 and 2011-May-15
                ########
                $edits = $script->editCount(20101115000000, 20110516000000);
                $script->condition(
                    $edits >= 20,
                    "has 20 edits between 15 November 2010 and 15 May 2011 (has {$edits})...",
                    "does not have 20 edits between 15 November 2010 and 15 May 2011 (has {$edits}); edits can be combined across wikis.",
                    "",
                    "is-warn"
                );
                $editCountRecent += $edits;

                ########
                ## Exit conditions
                ########
                $script->eligible = ($indefBlockCount <= 1 && $editCount >= 300 && $editCountRecent >= 20);
            } while (!$script->eligible && $script->getNext());

            break;


        ############################
        ## 2011 Commons Picture of the Year 2010
        ############################
        case 16:
            $script->printWiki();
            $ageOkay = false;
            $editsOkay = false;
            do {
                $script->eligible = true;

                ########
                ## registered < 2011-Jan-01
                ########
                if (!$ageOkay) {
                    $ageOkay = $script->condition(
                        $dateOkay = ($script->user->registered < 20110101000000),
                        "has an account registered before 01 January 2011 (registered {$script->user->registeredStr})...",
                        "does not have an account registered before 01 January 2011 (registered {$script->user->registeredStr})."
                    );
                }

                ########
                ## >= 200 edits before 2011-Jan-01
                ########
                if (!$editsOkay) {
                    $edits = $script->editCount(null, 20110101000000);
                    $editsOkay = $script->condition(
                        $editsOkay = ($edits >= 200),
                        "has 200 edits before 01 January 2011 (has {$edits})...",
                        "does not have 200 edits before 01 January 2011 (has {$edits})."
                    );
                }

                $script->eligible = ($ageOkay && $editsOkay);
            } while (!$script->eligible && $script->getNext());
            break;

        ############################
        ## 2011 steward confirmations
        ############################
        case 15:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Check local requirements
            ########
            $script->printWiki();
            do {
                ########
                ## >=1 edits before 01 February 2011
                ########
                $edits = $script->editCount(null, 20110201000000);
                $script->condition(
                    $edits >= 1,
                    "has one edit before 01 February 2011 (has {$edits})...",
                    "does not have one edit before 01 February 2011 (has {$edits})."
                );
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2011 steward elections
        ############################
        case 14:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (<strong>optional</strong>)...",
                "",
                "is-warn"
            );
            $script->eligible = true;

            ########
            ## Check local requirements
            ########
            $script->printWiki();

            /* set messages for global accounts */
            if ($script->unified) {
                $editsFailAppend = "However, edits will be combined with other unified wikis.";
                $editsFailAttrs = "is-warn";
            } else {
                $editsFailAppend = '';
                $editsFailAttrs = '';
            }

            /* check requirements */
            $priorEdits = 0;
            $recentEdits = 0;
            $combining = false;
            $markedBot = false;
            do {
                ########
                ## Should not be a bot
                ########
                $isBot = $script->hasRole('bot');
                $script->condition(
                    !$isBot,
                    "no bot flag...",
                    "has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
                    "",
                    "is-warn"
                );
                $script->eligible = true;
                if ($isBot && !$markedBot) {
                    $script->event->append_eligible = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
                    $markedBot = true;
                }

                ########
                ## >=600 edits before 01 November 2010
                ########
                $edits = $script->editCount(null, 20101101000000);
                $priorEdits += $edits;
                $script->condition(
                    $edits >= 600,
                    "has 600 edits before 01 November 2010 (has {$edits})...",
                    "does not have 600 edits before 01 November 2010 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }

                ########
                ## >=50 edits between 2010-Aug-01 and 2010-Jan-31
                ########
                $edits = $script->editCount(20100800000000, 20110200000000);
                $recentEdits += $edits;
                $script->condition(
                    $edits >= 50,
                    "has 50 edits between 01 August 2010 and 31 January 2011 (has {$edits})...",
                    "does not have 50 edits between 01 August 2010 and 31 January 2011 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }


                ########
                ## Exit conditions
                ########
                $script->eligible = $priorEdits >= 600 && $recentEdits >= 50;

                /* unified met requirements */
                if ($script->unified && !$script->isQueueEmpty()) {
                    if ($script->eligible) {
                        break;
                    }
                    $combining = true;
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2011 steward elections (candidates)
        ############################
        case 13:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }


            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>..."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Check local requirements
            ########
            /* check local requirements */
            $minDurationMet = false;
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## Registered before 2010-Mar-29
                ########
                $script->condition(
                    $script->user->registered < 20100829000000,
                    "has an account registered before 29 August 2010 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 29 August 2010 (registered {$script->user->registeredStr})."
                );

                ########
                ## Must have been a sysop for three months
                ########
                if (!$minDurationMet) {
                    $months = $script->getRoleLongestDuration('sysop', 20110129000000);
                    $minDurationMet = $months >= 3;
                    $script->condition(
                        $minDurationMet,
                        'was flagged as an administrator for a continuous period of at least three months before 29 January 2011 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ')...',
                        'was not flagged as an administrator for a continuous period of at least three months before 29 January 2011 (' . ($months > 0 ? 'longest flag duration is ' . $months . ' months' : 'never flagged') . ').'
                    );
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2010 enwiki arbcom elections
        ############################
        case 12:
            $script->printWiki();

            ########
            ## >=150 main-NS edits before 2010-Nov-02
            ########
            /* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
            $script->db->query(
                'SELECT data.count FROM ('
                . 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
                . 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
                . ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
                . ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
                [$script->user->id, 20101102000000]
            );
            $edits = $script->db->fetchColumn();

            $script->condition(
                $edits >= 150,
                "has 150 main-namespace edits on or before 01 November 2010 (has {$edits})...",
                "does not have 150 main-namespace edits on or before 01 November 2010 (has {$edits})."
            );


            ########
            ## Not currently blocked
            ########
            $script->condition(
                !$script->isBlocked(),
                "not currently blocked...",
                "must not be blocked during at least part of election (verify <a href='//en.wikipedia.org/wiki/Special:Log/block?user=" . urlencode($script->user->name) . "' title='block log'>block log</a>)."
            );
            break;


        ############################
        ## 2010 steward elections, September
        ############################
        case 11:
            $startDate = 20100901000000;

            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (<strong>optional</strong>)...",
                "",
                "is-warn"
            );


            ########
            ## Check local requirements
            ########
            $script->printWiki();

            /* set messages for global accounts */
            if ($script->unified) {
                $editsFailAppend = "However, edits will be combined with other unified wikis.";
                $editsFailAttrs = "is-warn";
            } else {
                $editsFailAppend = '';
                $editsFailAttrs = '';
            }

            /* check requirements */
            $priorEdits = 0;
            $recentEdits = 0;
            $combining = false;
            $markedBot = false;
            do {
                ########
                ## Should not be a bot
                ########
                $script->condition(
                    !$script->hasRole('bot'),
                    "no bot flag...",
                    "has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
                    "",
                    "is-warn"
                );
                $script->eligible = true;
                if ($script->hasRole('bot') && !$markedBot) {
                    $script->event->append_eligible = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
                    $markedBot = true;
                }


                ########
                ## >=600 edits before 01 June 2010
                ########
                $edits = $script->editCount(null, 20100601000000);
                $priorEdits += $edits;
                $script->condition(
                    $edits >= 600,
                    "has 600 edits before 01 June 2010 (has {$edits})...",
                    "does not have 600 edits before 01 June 2010 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }

                ########
                ## >=50 edits between 2010-Mar-01 and 2010-Aug-31
                ########
                $edits = $script->editCount(20100300000000, 20100900000000);
                $recentEdits += $edits;
                $script->condition(
                    $edits >= 50,
                    "has 50 edits between 01 March 2010 and 31 August 2010 (has {$edits})...",
                    "does not have 50 edits between 01 March 2010 and 31 August 2010 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }

                ########
                ## Exit conditions
                ########
                $script->eligible = $priorEdits >= 600 && $recentEdits >= 50;

                /* unified met requirements */
                if ($script->unified && !$script->isQueueEmpty()) {
                    if ($script->eligible) {
                        break;
                    }
                    $combining = true;
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2010 steward elections, September (candidates)
        ############################
        case 10:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }


            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>..."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Check local requirements
            ########
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## Registered before 2010-Mar-29
                ########
                $script->eligible = true;
                $script->condition(
                    $script->user->registered < 20100329000000,
                    "has an account registered before 29 March 2010 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 29 March 2010 (registered $script->user->registeredStr)."
                );
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2010 Commons Picture of the Year 2009
        ############################
        case 9:
            $dateOkay = false;
            $editsOkay = false;

            $script->printWiki();
            do {
                ########
                ## registered < 2010-Jan-01
                ########
                if (!$dateOkay) {
                    $script->condition(
                        $dateOkay = ($script->user->registered < 20100101000000),
                        "has an account registered before 01 January 2010 (registered {$script->user->registeredStr})...",
                        "does not have an account registered before 01 January 2010 (registered {$script->user->registeredStr})."
                    );
                }

                ########
                ## >= 200 edits before 2010-Jan-16
                ########
                if (!$editsOkay) {
                    $edits = $script->editCount(null, 20100116000000);
                    $script->condition(
                        $editsOkay = ($edits >= 200),
                        "has 200 edits before 16 January 2010 (has {$edits})...",
                        "does not have 200 edits before 16 January 2010 (has {$edits})."
                    );
                }
            } while ((!$dateOkay || !$editsOkay) && $script->getNext());
            $script->eligible = ($dateOkay && $editsOkay);
            break;


        ############################
        ## 2010 steward elections, February
        ############################
        case 8:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Has a global account (optional)
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a> (<strong>optional</strong>)...",
                "",
                "is-warn"
            );


            ########
            ## Check local requirements
            ########
            $script->printWiki();

            /* set messages for global accounts */
            if ($script->unified) {
                $editsFailAppend = "However, edits will be combined with other unified wikis.";
                $editsFailAttrs = "is-warn";
            } else {
                $editsFailAppend = '';
                $editsFailAttrs = '';
            }

            /* check requirements */
            $priorEdits = 0;
            $recentEdits = 0;
            $combining = false;
            do {
                ########
                ## Should not be a bot
                ########
                $script->condition(
                    !$script->hasRole('bot'),
                    "no bot flag...",
                    "has a bot flag &mdash; the global account must not be primarily automated (bot), but I can't check this so won't mark ineligible.",
                    "",
                    "is-warn"
                );
                $script->eligible = true;
                if ($script->hasRole('bot')) {
                    $script->event->append_eligible = "<br /><strong>Note:</strong> this account is marked as a bot on some wikis. If it is primarily an automated account (bot), it is <em>not</em> eligible.";
                }


                ########
                ## >=600 edits before 2009-Nov-01
                ########
                $edits = $script->editCount(null, 20091101000000);
                $priorEdits += $edits;
                $script->condition(
                    $edits >= 600,
                    "has 600 edits before 01 November 2009 (has {$edits})...",
                    "does not have 600 edits before 01 November 2009 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }

                ########
                ## >=50 edits between 2009-Aug-01 and 2010-Jan-31
                ########
                $edits = $script->editCount(20090801000000, 20100200000000);
                $recentEdits += $edits;
                $script->condition(
                    $edits >= 50,
                    "has 50 edits between 01 August 2009 and 31 January 2010 (has {$edits})...",
                    "does not have 50 edits between 01 August 2009 and 31 January 2010 (has {$edits}). {$editsFailAppend}",
                    "",
                    $editsFailAttrs
                );
                if (!$script->eligible) {
                    if (!$script->unified) {
                        continue;
                    }
                    $combining = true;
                }

                ########
                ## Exit conditions
                ########
                $script->eligible = $priorEdits >= 600 && $recentEdits >= 50;

                /* unified met requirements */
                if ($script->unified && !$script->isQueueEmpty()) {
                    if ($script->eligible) {
                        break;
                    }
                    $combining = true;
                }
            } while (!$script->eligible && $script->getNext());


            ########
            ## Add requirement for non-unified accounts
            ########
            if ($script->eligible && !$script->unified) {
                $script->event->extraRequirements[] = "If you do not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>, your user page on Meta must link to your main user page, and your main user page must link your Meta user page.";
            }


            ########
            ## Print message about combined edits
            ########
            if ($script->eligible) {
                if ($script->unified && $combining) {
                    $script->msg("<br />Met edit requirements.");
                }
            } else {
                if ($script->unified && $combining) {
                    $script->msg("<br />Combined edits did not meet edit requirements.");
                } elseif ($script->isGlobal()) {
                    $script->event->append_ineligible = "<br /><strong>But wait!</strong> This is a global account, which is eligible to combine edits across wikis to meet the requirements. Select 'auto-select wiki' above to combine edits.";
                }
            }
            break;


        ############################
        ## 2010 steward elections, February (candidates)
        ############################
        case 7:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }


            ########
            ## Has a global account
            ########
            $script->condition(
                $script->isGlobal(),
                "has a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>...",
                "does not have a <a href='//meta.wikimedia.org/wiki/Help:Unified_login' title='about global accounts'>global account</a>..."
            );
            if (!$script->eligible) {
                break;
            }

            ########
            ## Check local requirements
            ########
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## Registered before 2009-Oct-28
                ########
                $script->eligible = true;
                $script->condition(
                    $script->user->registered < 20091029000000,
                    "has an account registered before 29 October 2009 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 29 October 2009 (registered $script->user->registeredStr)."
                );
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2010 global sysops vote
        ############################
        case 6:
            $ageOkay = false;
            $editsOkay = false;

            $script->printWiki();
            do {
                ########
                ## Registered (on any wiki) before 2009-Oct
                ########
                if (!$ageOkay) {
                    $ageOkay = $script->condition(
                        $script->user->registered <= 20091001000000,
                        "has an account registered before 01 October 2009, on any wiki (registered {$script->user->registeredStr})...",
                        "does not have an account registered before 01 October 2009, on any wiki (registered {$script->user->registeredStr})."
                    );
                }


                ########
                ## >=150 edits (on any wiki) before start of vote (2010-Jan-01)
                ########
                if (!$editsOkay) {
                    $edits = $script->editCount(null, 20100101000000);
                    $editsOkay = $script->condition(
                        $edits >= 150,
                        "has 150 edits before 01 January 2010, on any wiki (has {$edits})...",
                        "does not have 150 edits before 01 January 2010, on any wiki (has {$edits})."
                    );

                    if (!$editsOkay && $script->user->edits < 150) {
                        break;
                    } // no other wiki can qualify (sorted by edit count)
                }
            } while ((!$ageOkay || !$editsOkay) && $script->getNext());

            $script->eligible = $ageOkay && $editsOkay;
            break;


        ############################
        ## 2009 enwiki arbcom elections
        ############################
        case 5:
            $script->printWiki();

            ########
            ## >=150 main-NS edits before 2009-Nov-02
            ########
            /* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
            $script->db->query(
                'SELECT data.count FROM ('
                . 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
                . 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
                . ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
                . ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
                [$script->user->id, 20091102000000]
            );
            $edits = $script->db->fetchColumn();

            $script->condition(
                $edits >= 150,
                "has 150 main-namespace edits on or before 01 November 2009 (has {$edits}).",
                "does not have 150 main-namespace edits on or before 01 November 2009 (has {$edits})."
            );
            break;


        ############################
        ## 2009 Commons Picture of the Year 2008
        ############################
        case 4:
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## registered < 2009-Jan-01
                ########
                $script->condition(
                    $script->user->registered < 20090101000000,
                    "has an account registered before 01 January 2009 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 01 January 2009 (registered {$script->user->registeredStr})."
                );
                if (!$script->eligible) {
                    continue;
                }

                ########
                ## >= 200 edits before 2009-Feb-12
                ########
                $edits = $script->editCount(null, 20090212000000);
                $script->condition(
                    $edits >= 200,
                    "has 200 edits before 12 February 2009 (has {$edits})...",
                    "does not have 200 edits before 12 February 2009 (has {$edits})."
                );
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2009 steward elections (candidates)
        ############################
        case 3:
            ########
            ## Has an account on Meta
            ########
            $script->msg('Global requirements:', 'is-wiki');
            $script->condition(
                $script->hasAccount('metawiki_p'),
                "has an account on Meta...",
                "does not have an account on Meta."
            );
            if (!$script->eligible) {
                break;
            }


            ########
            ## Registered before 2008-Nov-01
            ########
            $script->printWiki();
            do {
                $script->eligible = true;
                $script->condition(
                    $script->user->registered <= 20081101000000,
                    "has an account registered before 01 November 2008 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 01 November 2008 (registered $script->user->registeredStr)."
                );
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2009 steward elections
        ############################
        case 2:
            ########
            ## Must not be blocked on Meta
            ########
            $script->db->connect('metawiki');
            $script->db->query(
                'SELECT COUNT(ipb_expiry) FROM metawiki_p.ipblocks WHERE ipb_user=(SELECT user_id FROM metawiki_p.user WHERE user_name=? LIMIT 1) AND ipb_expiry="infinity" LIMIT 1',
                [$script->user->name]
            );
            $script->condition(
                !$script->db->fetchColumn(),
                'is not blocked on Meta...',
                'is blocked on Meta.'
            );
            if (!$script->eligible) {
                break;
            }
            $script->db->connectPrevious();


            ########
            ## Check local requirements
            ########
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## Must not be a bot
                ########
                $script->condition(
                    !$script->hasRole('bot'),
                    "no bot flag...",
                    "has a bot flag."
                );
                if (!$script->eligible) {
                    continue;
                }

                ########
                ## Registered before 20090101
                ########
                $script->condition(
                    $script->user->registered <= 20090101000000,
                    "has an account registered before 01 January 2009 (registered {$script->user->registeredStr})...",
                    "does not have an account registered before 01 January 2009 (registered {$script->user->registeredStr})."
                );
                if (!$script->eligible) {
                    continue;
                }

                ########
                ## >=600 edits before 2008-Nov-01
                ########
                $edits = $script->editCount(null, 20081101000000);
                $script->condition(
                    $edits >= 600,
                    "has 600 edits before 01 November 2008 (has {$edits})...",
                    "does not have 600 edits before 01 November 2008 (has {$edits})."
                );
                if (!$script->eligible) {
                    continue;
                }

                ########
                ## >=50 edits between 2008-Aug-01 and 2009-Jan-31
                ########
                $edits = $script->editCount(20080801000000, 20090131000000);
                $script->condition(
                    $edits >= 50,
                    "has 50 edits between 01 August 2008 and 31 January 2009 (has {$edits})...",
                    "does not have 50 edits between 01 August 2008 and 31 January 2009 (has {$edits})."
                );
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## 2008 enwiki arbcom elections
        ############################
        case 1:
            $script->printWiki();

            ########
            ## >=150 main-NS edits before 2008-Nov-02
            ########
            /* SQL derived from query written by [[en:user:Cobi]], from < //toolserver.org/~sql/sqlbot.txt > */
            $script->db->query(
                'SELECT data.count FROM ('
                . 'SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ('
                . 'SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp<? GROUP BY rev_page'
                . ') AS rev WHERE rev.rev_page=page_id AND page_namespace=0'
                . ') AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname="enwiki_p"',
                [$script->user->id, 20081102000000]
            );

            $edits = $script->db->fetchColumn();
            $script->condition(
                $edits >= 150,
                "has 150 main-namespace edits on or before 01 November 2008 (has {$edits}).",
                "does not have 150 main-namespace edits on or before 01 November 2008 (has {$edits})."
            );

            break;


        ############################
        ## 2008 Board elections
        ############################
        case 0:
            $script->printWiki();
            do {
                $script->eligible = true;

                ########
                ## Not indefinitely blocked
                ########
                $script->condition(
                    !$script->isIndefBlocked(),
                    "not indefinitely blocked...",
                    "indefinitely blocked."
                );
                if (!$script->eligible) {
                    continue;
                }

                ########
                ## Not a bot
                ########
                $script->condition(
                    !$script->hasRole('bot'),
                    "no bot flag...",
                    "has a bot flag."
                );
                if (!$script->eligible) {
                    continue;
                }

                ########
                ## >=600 edits before 2008-Mar
                ########
                $edits = $script->editCount(null, 20080301000000);
                $script->condition(
                    $edits >= 600,
                    "has 600 edits before 01 March 2008 (has {$edits})...",
                    "does not have 600 edits before 01 March 2008 (has {$edits})."
                );
                if (!$script->eligible) {
                    continue;
                }

                ########
                ## >=50 edits between 2008-Jan and 2008-Jun
                ########
                $edits = $script->editCount(20080101000000, 20080529000000);
                $script->condition(
                    $edits >= 50,
                    "has 50 edits between 01 January 2008 and 29 May 2008 (has {$edits})...",
                    "does not have 50 edits between 01 January 2008 and 29 May 2008 (has {$edits})."
                );

                ########
                ## Exit conditions
                ########
                /* eligible */
                if ($script->eligible) {
                    break;
                }
            } while (!$script->eligible && $script->getNext());
            break;


        ############################
        ## No such event
        ############################
        default:
            echo '<div class="fail">No such event.</div>';
    }
    $script->profiler->stop('verify requirements');


    ############################
    ## Print result
    ############################
    if ($script->event) {
        ########
        ## Script results
        ########
        $event = $script->event;
        $action = isset($event->action) ? $event->action : 'vote';
        $class = $script->eligible ? 'success' : 'fail';
        $name = $script->user->name . ($script->unified ? '' : '@' . $script->wiki->domain);

        echo
        '<h3>Result</h3>',
        '<div class="', $class, '" id="result" data-is-eligible="', ($script->eligible ? 1 : 0), '">',
        $script->formatText($name), ' is ', ($script->eligible ? '' : 'not '), 'eligible to ', $action, ' in the <a href="', $event->url, '" title="', $backend->formatValue($event->name), '">', $event->name, '</a> in ', $event->year, '. ';
        if ($script->eligible && isset($script->event->append_eligible))
            echo $script->event->append_eligible;
        elseif (!$script->eligible && isset($script->event->append_ineligible))
            echo $script->event->append_ineligible;
        echo '</div>';

        ########
        ## Mention additional requirements
        ########
        if ($script->eligible && !empty($script->event->extraRequirements)) {
            echo '<div class="error" style="border-color:#CC0;"><strong>There are additional requirements</strong> that can\'t be checked by this script:<ul style="margin:0;">';
            foreach ($script->event->extraRequirements as $req)
                echo '<li>', $req, '</li>';
            echo '</ul></div>';
        } elseif (!$script->eligible) {
            if (!empty($script->event->exceptions)) {
                echo '<div class="neutral"><strong>This account might be eligible if it fits rule exceptions that cannot be checked by this script:<ul style="margin:0;">';
                foreach ($script->event->exceptions as $exc)
                    echo '<li>', $exc, '</li>';
                echo '</ul></div>';
            }
            if (isset($script->event->warn_ineligible))
                echo '<div class="error" style="border-color:#CC0;">', $script->event->warn_ineligible, '</div>';
        }

        ########
        ## Add links for manual verification
        ########
        echo '<small>See also: <a href="', $backend->url('/stalktoy/' . urlencode($script->user->name)), '" title="global account details">global account details</a></small>.';
    }

    /* exit loop */
    break;
}

if ($script->user->name)
    echo '</div>';


/* globals, templates */
$backend->footer();

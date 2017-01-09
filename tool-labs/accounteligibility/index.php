<?php
require_once("../backend/modules/Backend.php");
require_once("../backend/models/LocalUser.php");
require_once("events.php");
spl_autoload_register(function ($className) {
    foreach (["constants/$className.php", "framework/$className.php", "models/$className.php", "rules/$className.php"] as $path) {
        if (file_exists($path))
            include($path);
    }
});

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
    public $wikis = [];

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
        $events = (new EventFactory())->getEvents();
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
            $this->profiler->start('init wiki queue: fetch unified wikis');
            $unifiedDbnames = $this->db->getUnifiedWikis($this->user->name);
            if (!$unifiedDbnames) {
                $this->selectManually = true;
                $encoded = urlencode($this->user->name);
                echo '<div id="result" class="neutral" data-is-error="1">', $this->formatText($this->user->name), ' has no global account, so we cannot auto-select an eligible wiki. Please select a wiki (see <a href="', $this->backend->url('/stalktoy/' . $encoded), '" title="global details about this user">global details about this user</a>).</div>';
                return false;
            }
            $this->profiler->stop('init wiki queue: fetch unified wikis');

            /* fetch user edit count for each wiki & sort by edit count */
            $this->profiler->start('init wiki queue: fetch edit counts');
            foreach ($unifiedDbnames as $unifiedDbname) {
                if (!isset($this->wikis[$unifiedDbname]))
                    continue; // skip private wikis (not listed in meta_p.wiki)
                $this->db->connect($unifiedDbname);
                $this->queue[$unifiedDbname] = $this->db->query('SELECT user_editcount FROM user WHERE user_name = ? LIMIT 1', [$this->user->name])->fetchColumn();
            }
            $this->profiler->stop('init wiki queue: fetch edit counts');

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

            /* initialize queue */
            asort($this->queue);
            $this->queue = array_filter($this->queue, 'filter');
            $this->queue = array_keys($this->queue);
            $this->nextQueueIndex = count($this->queue) - 1;
            $this->unified = true;

            /* report queue */
            $this->msg('Auto-selected ' . count($this->queue) . ' unified accounts with at least ' . $minEdits . ' edit' . ($minEdits != 1 ? 's' : '') . '.', 'is-metadata');
        }

        ########
        ## Connect & return
        ########
        $this->profiler->start('init wiki queue: fetch user data from first wiki');
        $result = $this->getNext(false /*don't output name@wiki yet*/);
        $this->profiler->stop('init wiki queue: fetch user data from first wiki');
        return $result;
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
     * Write a message to the output.
     * @param string $message The message to print.
     * @param string $classes The CSS classes to add to the output line.
     */
    function msg($message, $classes = null)
    {
        $classes = $classes ? trim($classes) : 'is-note';
        echo "<div class='$classes'>$message</div>";
    }

    /**
     * Print a 'name@wiki...' header for the current wiki.
     */
    function printWiki()
    {
        $name = $this->user->name;
        $domain = $this->wiki->domain;
        $this->msg("On <a href='//$domain/wiki/User:$name' title='$name&apos;s user page on $domain'>$domain</a>:", 'is-wiki');
    }

    /**
     * Verify eligibility for an event using a rule manager.
     * @param Script $script The script engine.
     * @param RuleManager $rules The rules to verify.
     */
    function verify($script, $rules)
    {
        $script->printWiki();

        do {
            foreach ($rules->accumulate($script->db, $script->wiki, $script->user) as $result) {
                // print result
                switch ($result->result) {
                    case Result::FAIL:
                        $this->msg("• {$result ->message}", "is-fail");
                        break;

                    case Result::ACCUMULATING:
                        $this->msg("• {$result->message}", "is-warn");
                        break;

                    case Result::PASS:
                        $this->msg("• {$result->message}", "is-pass");
                        break;

                    default:
                        throw new InvalidArgumentException("Unknown rule eligibility result '{$result->result}'");
                }

                // print warnings
                if ($result->warnings) {
                    foreach ($result->warnings as $warning)
                        $this->msg("{$warning}", "is-subnote is-warn");
                }

                // print notes
                if ($result->notes) {
                    foreach ($result->notes as $note)
                        $this->msg("{$note}", "is-subnote");
                }
            }
        } while (!$rules->final && $script->getNext());
        $script->eligible = $rules->result == Result::PASS;
    }
}


############################
## Initialize
############################
$event = $backend->get('event') ?: $backend->getRouteValue() ?: Script::DEFAULT_EVENT;
$user = $backend->get('user') ?: $backend->getRouteValue(2) ?: '';
$wiki = $backend->get('wiki', null);
$backend->profiler->start('init engine');
$script = new Script($backend, $user, $event, $wiki);
$backend->profiler->stop('init engine');

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
    $script->profiler->start('init wiki queue');
    if (!$script->initWikiQueue($script->event->onlyDB, $script->event->minEditsForAutoselect)) {
        if (!$script->selectManually)
            $script->msg('Selection failed, aborted.');
        break;
    }
    $script->profiler->stop('init wiki queue');

    /* validate user exists */
    if (!$script->user->id) {
        echo '<div class="error">', $script->formatText($script->user->name), ' does not exist on ', $script->formatText($script->wiki->domain), '.</div>';
        break;
    }

    ##########
    ## Verify requirements
    ##########
    $script->profiler->start('verify requirements');
    switch ($script->event->id) {
        ##########
        ## 2016 Commons Picture of the Year 2015
        ##########
        case 39:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(201601), Workflow::ON_ANY_WIKI)// registered before 01 January 2016
                ->addRule(new EditCountRule(75, null, 201601), Workflow::ON_ANY_WIKI)// 75 edits before 01 January 2016
            );
            break;

        ##########
        ## 2016 steward elections
        ##########
        case 38:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new EditCountRule(600, null, 201511, EditCountRule::ACCUMULATE))// 600 edits before 01 November 2015
                ->addRule(new EditCountRule(50, 201508, 201602, EditCountRule::ACCUMULATE))// 50 edits between 01 August 2015 and 31 January 2016
            );
            break;

        ##########
        ## 2016 steward elections (candidates)
        ##########
        case 37:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(20150808), Workflow::ON_ANY_WIKI)// registered for six months
                ->addRule(new HasGroupDurationRule('sysop', 90, 20160208), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
            );
            break;

        ##########
        ## 2015 WMF elections
        ##########
        case 36:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBlockedRule(1), Workflow::HARD_FAIL)// not blocked on more than one wiki
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new EditCountRule(300, null, 20150416, EditCountRule::ACCUMULATE))// 300 edits before 15 April 2015
                ->addRule(new EditCountRule(20, 20141015, 20150416, EditCountRule::ACCUMULATE))// 20 edits between 15 October 2014 and 15 April 2015
            );
            break;

        ##########
        ## 2015 steward elections
        ##########
        case 35:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new EditCountRule(600, null, 201411, EditCountRule::ACCUMULATE))// 600 edits before 01 November 2014
                ->addRule(new EditCountRule(50, 201408, 201502, EditCountRule::ACCUMULATE))// 50 edits between 01 August 2014 and 31 January 2015
            );
            break;

        ##########
        ## 2015 steward elections (candidates)
        ##########
        case 34:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(20140808), Workflow::ON_ANY_WIKI)// registered for six months
                ->addRule(new HasGroupDurationRule('sysop', 90, 20150208), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
            );
            break;

        ##########
        ## 2015 Commons Picture of the Year 2014
        ##########
        case 33:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(201501), Workflow::ON_ANY_WIKI)// registered before 01 January 2015
                ->addRule(new EditCountRule(75, null, 201501), Workflow::ON_ANY_WIKI)// 75 edits before 01 January 2015
            );
            break;

        ##########
        ## 2014 Commons Picture of the Year 2013
        ##########
        case 32:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(201401), Workflow::ON_ANY_WIKI)// registered before 01 January 2014
                ->addRule(new EditCountRule(75, null, 201401), Workflow::ON_ANY_WIKI)// 75 edits before 01 January 2014
            );
            break;

        ##########
        ## 2014 steward elections
        ##########
        case 31:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new EditCountRule(600, null, 201311, EditCountRule::ACCUMULATE))// 600 edits before 01 November 2013
                ->addRule(new EditCountRule(50, 201308, 201402, EditCountRule::ACCUMULATE))// 50 edits between 2013-Aug-01 and 2014-Jan-31
            );
            break;

        ##########
        ## 2014 steward elections (candidates)
        ##########
        case 30:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(20130808), Workflow::ON_ANY_WIKI)// registered for six months
                ->addRule(new HasGroupDurationRule('sysop', 90, 20140208), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
            );
            break;

        ##########
        ## 2013 steward elections
        ##########
        case 29:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new EditCountRule(600, null, 201211, EditCountRule::ACCUMULATE))// 600 edits before 01 November 2012
                ->addRule(new EditCountRule(50, 201208, 201302, EditCountRule::ACCUMULATE))// 50 edits between 01 August 2012 and 31 January 2013
            );
            break;

        ##########
        ## 2013 steward elections (candidates)
        ##########
        case 28:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(20120808), Workflow::ON_ANY_WIKI)// registered for six months
                ->addRule(new HasGroupDurationRule('sysop', 90, 20130208), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
            );
            break;

        ##########
        ## 2013 Commons Picture of the Year 2012
        ##########
        case 27:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(201301), Workflow::ON_ANY_WIKI)// registered before 01 January 2013
                ->addRule(new EditCountRule(75, null, 201301), Workflow::ON_ANY_WIKI)// 75 edits before 01 January 2013
            );
            break;

        ##########
        ## 2012 enwiki arbcom elections (voters)
        ##########
        case 26:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBlockedRule())
                ->addRule((new EditCountRule(150, null, 20121102))->inNamespace(0))// 150 main-namespace edits before 02 Nov 2012
            );
            break;

        ##########
        ## 2012 enwiki arbcom elections (candidates)
        ##########
        case 25:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBlockedRule())
                ->addRule((new EditCountRule(500, null, 20121102))->inNamespace(0))// 500 main-namespace edits before 02 November 2012
            );
            break;

        ##########
        ## 2012 Commons Picture of the Year 2011
        ##########
        case 24:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(201204), Workflow::ON_ANY_WIKI)// registered before 01 April 2012
                ->addRule(new EditCountRule(75, null, 201204), Workflow::ON_ANY_WIKI)// 75 edits before 01 April 2012
            );
            break;

        ##########
        ## 2012 steward elections
        ##########
        case 23:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new EditCountRule(600, null, 201111, EditCountRule::ACCUMULATE))// 600 edits before 01 November 2011
                ->addRule(new EditCountRule(50, 201108, 201202, EditCountRule::ACCUMULATE))// 50 edits between 01 August 2011 and 31 January 2012
            );
            break;

        ##########
        ## 2012 steward elections (candidates)
        ##########
        case 22:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(20110710), Workflow::ON_ANY_WIKI)// registered for six months
                ->addRule(new HasGroupDurationRule('sysop', 90, 20120129), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
            );
            break;

        ##########
        ## 2011 enwiki arbcom elections
        ##########
        case 20:
        case 21:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBlockedRule())
                ->addRule((new EditCountRule(150, null, 201111))->inNamespace(0))// 150 main-namespace edits before 01 November 2011
            );
            break;

        ##########
        ## 2011-09 steward elections
        ##########
        case 19:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new EditCountRule(600, null, 20110615, EditCountRule::ACCUMULATE))// 600 edits before 15 June 2011
                ->addRule(new EditCountRule(50, 20110315, 20110914, EditCountRule::ACCUMULATE)) // 50 edits between 15 March 2011 and 14 September 2011
            );
            break;

        ##########
        ## 2011 steward elections (candidates)
        ##########
        case 18:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(20110314), Workflow::ON_ANY_WIKI)// registered for six months
                ->addRule(new HasGroupDurationRule('sysop', 90, 20110913), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
            );
            break;

        ##########
        ## 2011 Board elections
        ##########
        case 17:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new NotBlockedRule(1), Workflow::HARD_FAIL)// not blocked on more than one wiki
                ->addRule(new EditCountRule(300, null, 20110415, EditCountRule::ACCUMULATE))// 300 edits before 15 April 2011
                ->addRule(new EditCountRule(20, 20101115, 20110516, EditCountRule::ACCUMULATE))// 20 edits between 15 November 2010 and 15 May 2011
            );
            break;

        ##########
        ## 2011 Commons Picture of the Year 2010
        ##########
        case 16:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(201101), Workflow::ON_ANY_WIKI)// registered before 01 January 2011
                ->addRule(new EditCountRule(200, null, 201101), Workflow::ON_ANY_WIKI)// 200 edits before 01 January 2011
            );
            break;

        ##########
        ## 2011 steward confirmations
        ##########
        case 15:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new EditCountRule(1, null, 201102, EditCountRule::ACCUMULATE))// one edit before 01 February 2011
            );
            break;

        ##########
        ## 2011 steward elections
        ##########
        case 14:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new EditCountRule(600, null, 201011, EditCountRule::ACCUMULATE))// 600 edits before 01 November 2010
                ->addRule(new EditCountRule(50, 201008, 201102, EditCountRule::ACCUMULATE))// 50 edits between 01 August 2010 and 31 January 2011
            );
            break;

        ##########
        ## 2011 steward elections (candidates)
        ##########
        case 13:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(20100829), Workflow::ON_ANY_WIKI)// registered for six months
                ->addRule(new HasGroupDurationRule('sysop', 90, 20110129), Workflow::ON_ANY_WIKI)// flagged as a sysop for three months
            );
            break;

        ##########
        ## 2010 enwiki arbcom elections
        ##########
        case 12:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBlockedRule())
                ->addRule((new EditCountRule(150, null, 20101102))->inNamespace(0))// 150 main-namespace edits by 01 November 2010
            );
            break;

        ##########
        ## 2010 steward elections, September
        ##########
        case 11:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new EditCountRule(600, null, 201006, EditCountRule::ACCUMULATE))// 600 edits before 01 June 2010
                ->addRule(new EditCountRule(50, 201003, 201009, EditCountRule::ACCUMULATE))// 50 edits between 01 March 2010 and 31 August 2010
            );
            break;

        ##########
        ## 2010 steward elections, September (candidates)
        ##########
        case 10:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(20100329))// registered before 29 March 2010
            );
            break;

        ##########
        ## 2010 Commons Picture of the Year 2009
        ##########
        case 9:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(201001), Workflow::ON_ANY_WIKI)// registered before 01 January 2010
                ->addRule(new EditCountRule(200, null, 20100116), Workflow::ON_ANY_WIKI)// 200 edits before 16 January 2010
            );
            break;

        ##########
        ## 2010 steward elections, February
        ##########
        case 8:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBotRule(), Workflow::HARD_FAIL)
                ->addRule(new EditCountRule(600, null, 200911, EditCountRule::ACCUMULATE))// 600 edits before 01 November 2009
                ->addRule(new EditCountRule(50, 200908, 201002, EditCountRule::ACCUMULATE))// 50 edits between 01 August 2009 and 31 January 2010
            );
            break;

        ##########
        ## 2010 steward elections, February (candidates)
        ##########
        case 7:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(20091029))// registered for three months
            );
            break;

        ##########
        ## 2010 global sysops vote
        ##########
        case 6:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(200910), Workflow::ON_ANY_WIKI)// registered for three months
                ->addRule(new EditCountRule(150, null, 201001), Workflow::ON_ANY_WIKI)// 150 edits before 01 January 2010
            );
            break;

        ##########
        ## 2009 enwiki arbcom elections
        ##########
        case 5:
            $script->verify($script, (new RuleManager())
                ->addRule((new EditCountRule(150, null, 20091102))->inNamespace(0))// 150 main-namespace edits before 02 November 2009
            );
            break;

        ##########
        ## 2009 Commons Picture of the Year 2008
        ##########
        case 4:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(200901), Workflow::ON_ANY_WIKI)// registered before 01 January 2009
                ->addRule(new EditCountRule(200, null, 20090212), Workflow::ON_ANY_WIKI)// 200 edits before 12 February 2009
            );
            break;

        ##########
        ## 2009 steward elections (candidates)
        ##########
        case 3:
            $script->verify($script, (new RuleManager())
                ->addRule(new DateRegisteredRule(200811))// registered for three months before 01 November 2008
            );
            break;

        ##########
        ## 2009 steward elections
        ##########
        case 2:
            $script->verify($script, (new RuleManager())
                ->addRule((new NotBlockedRule())->onWiki('metawiki'), Workflow::HARD_FAIL)
                ->addRule(new NotBotRule())
                ->addRule(new DateRegisteredRule(200901))// registered before 01 January 2009
                ->addRule(new EditCountRule(600, null, 200811))// 600 edits before 01 November 2008
                ->addRule(new EditCountRule(50, 200808, 200902))// 50 edits between 01 August 2008 and 31 January 2009
            );
            break;

        ##########
        ## 2008 enwiki arbcom elections
        ##########
        case 1:
            $script->verify($script, (new RuleManager())
                ->addRule((new EditCountRule(150, null, 20081102))->inNamespace(0))// 150 main-namespace before 02 November 2008
            );
            break;

        ##########
        ## 2008 Board elections
        ##########
        case 0:
            $script->verify($script, (new RuleManager())
                ->addRule(new NotBlockedRule())
                ->addRule(new NotBotRule())
                ->addRule(new EditCountRule(600, null, 200803))// 600 edits before 01 March 2008
                ->addRule(new EditCountRule(50, 200801, 20080529))// 50 edits between 01 January and 29 May 2008
            );
            break;

        ##########
        ## No such event
        ##########
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

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

        /* set instances */
        $this->backend = $backend;
        $this->db = $backend->GetDatabase();
        $this->profiler = $backend->profiler;

        /* set user */
        $this->username = $backend->formatUsername($user);

        /* load events */
        $this->profiler->start("init events");
        $eventFactory = new EventFactory();
        $events = $eventFactory->getEvents();
        foreach ($events as $event)
            $this->events[$event->id] = $event;
        $this->eventID = isset($eventID) ? $eventID : $eventFactory->getDefaultEventID();
        $this->event = $this->events[$this->eventID];
        $this->profiler->stop("init events");

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
}


############################
## Initialize
############################
$event = $backend->get('event') ?: $backend->getRouteValue();
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
        echo "<option value='$dbname' ", ($selected ? " selected" : ""), ">{$script->formatText($domain)}</option>";
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
            ", ($id == $script->event->id ? " selected " : ""), "
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
    /* validate event */
    if (!$script->event) {
        echo '<div class="error">There is no event matching the given ID.</div>';
        break;
    }

    /* print header */
    echo '<h3>Analysis', ($script->user->name == 'Shanel' ? '♥' : ''), ' </h3>';

    /* validate selected wiki */
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

    /* verify eligibility rules */
    $script->profiler->start('verify requirements');
    $rules = new RuleManager($script->event->rules);

    $script->printWiki();
    do {
        foreach ($rules->accumulate($script->db, $script->wiki, $script->user) as $result) {
            // print result
            switch ($result->result) {
                case Result::FAIL:
                    $script->msg("• {$result ->message}", "is-fail");
                    break;

                case Result::ACCUMULATING:
                    $script->msg("• {$result->message}", "is-warn");
                    break;

                case Result::PASS:
                    $script->msg("• {$result->message}", "is-pass");
                    break;

                default:
                    throw new InvalidArgumentException("Unknown rule eligibility result '{$result->result}'");
            }

            // print warnings
            if ($result->warnings) {
                foreach ($result->warnings as $warning)
                    $script->msg("{$warning}", "is-subnote is-warn");
            }

            // print notes
            if ($result->notes) {
                foreach ($result->notes as $note)
                    $script->msg("{$note}", "is-subnote");
            }
        }
    } while (!$rules->final && $script->getNext());
    $script->eligible = $rules->result == Result::PASS;
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

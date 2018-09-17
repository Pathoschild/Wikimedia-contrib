<?php
/**
 * Provides account eligibility methods and event data.
 */
class AccountEligibilityEngine extends Base
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

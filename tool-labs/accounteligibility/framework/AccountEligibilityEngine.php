<?php
declare(strict_types=1);

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
     * @var array<int, Event>
     */
    public array $events = [];

    ##########
    ## Properties
    ##########
    /**
     * The underlying database manager.
     */
    public Toolserver $db;

    /**
     * Provides basic performance profiling.
     */
    public Profiler $profiler;

    /**
     * Provides a wrapper used by page scripts to generate HTML, interact with the database, and so forth.
     */
    private Backend $backend;

    /**
     * The selected event ID.
     */
    private int $eventId;

    /**
     * Whether the user must select a wiki manually, because there is no matching global account.
     */
    public bool $selectManually = false;

    /**
     * The selected wiki.
     */
    public ?Wiki $wiki = null;

    /**
     * The account username to analyse.
     */
    public string $username;

    /**
     * The current local user account.
     */
    public ?LocalUser $user = null;

    /**
     * The selected event.
     */
    public Event $event;

    /**
     * The available wikis.
     * @var Wiki[]
     */
    public array $wikis = [];

    /**
     * The user's local accounts as a database name => local account lookup.
     * @var LocalUser[]
     */
    public array $users = [];

    /**
     * The list of database names to analyse.
     * @var string[]
     */
    public array $queue = [];

    /**
     * The index of the next item in the wiki queue.
     */
    public int $nextQueueIndex = -1;

    /**
     * Whether the user has met all the rules.
     */
    public bool $eligible = true;

    /**
     * Whether the user has a unified global account.
     */
    public bool $unified = false;


    ############################
    ## Constructor
    ############################
    /**
     * Construct an instance.
     * @param Backend $backend Provides a wrapper used by page scripts to generate HTML, interact with the database, and so forth.
     * @param string $user The username to analyse.
     * @param int $eventId The event ID to analyse.
     * @param string $dbname The wiki database name to analyse.
     */
    public function __construct(Backend $backend, string $user, ?string $eventId, ?string $dbname)
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
        foreach ($eventFactory->getEvents() as $event)
            $this->events[$event->id] = $event;
        $this->eventId = $eventId !== null ? intval($eventId) : $eventFactory->getDefaultEventID();
        $this->event = $this->events[$this->eventId];
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
    public function isQueueEmpty(): bool
    {
        return $this->nextQueueIndex >= 0;
    }

    /**
     * Load the next wiki in the queue.
     * @param bool $echo Whether to write the wiki name to the output.
     * @return bool Whether a wiki was successfully loaded from the queue.
     */
    public function getNext(bool $echo = true): bool
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
     * @param string|null $dbname The database name to load.
     */
    public function connect(?string $dbname): void
    {
        /* reset variables */
        $this->user = null;

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
    public function connectNext(): bool
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
     * @param string[]|null $defaultDbNames The default database names to use if the user didn't specify one.
     * @param int $minEdits The minimum number of edits.
     * @return bool Whether at least one wiki was successfully loaded.
     */
    public function initWikiQueue(?array $defaultDbNames = null, int $minEdits = 1): bool
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
        ## Set default wikis
        ########
        elseif ($defaultDbNames) {
            $this->queue = $defaultDbNames;
            $this->nextQueueIndex = 0;
            $this->msg('Auto-selected ' . implode(', ', $defaultDbNames) . '.', 'is-metadata');
        }

        ########
        ## Queue unified wikis
        ########
        else {
            /* fetch unified wikis */
            $this->profiler->start('init wiki queue: fetch unified wikis');
            $unifiedDbnames = $this->db->getUnifiedWikis($this->username);
            if (!$unifiedDbnames) {
                $this->selectManually = true;
                $encoded = urlencode($this->username);
                echo '<div id="result" class="neutral" data-is-error="1">', $this->formatText($this->username), ' has no global account, so we cannot auto-select an eligible wiki. Please select a wiki (see <a href="', $this->backend->url('/stalktoy/' . $encoded), '" title="global details about this user">global details about this user</a>).</div>';
                return false;
            }
            $this->profiler->stop('init wiki queue: fetch unified wikis');

            /* fetch user edit count for each wiki & sort by edit count */
            $this->profiler->start('init wiki queue: fetch edit counts');
            foreach ($unifiedDbnames as $unifiedDbname) {
                if (!isset($this->wikis[$unifiedDbname]))
                    continue; // skip private wikis (not listed in meta_p.wiki)
                $this->db->connect($unifiedDbname);
                $this->queue[$unifiedDbname] = $this->db->query('SELECT user_editcount FROM user WHERE user_name = ? LIMIT 1', [$this->username])->fetchColumn();
            }
            $this->profiler->stop('init wiki queue: fetch edit counts');

            /* initialize queue */
            asort($this->queue);
            $this->queue = array_filter($this->queue, function($count) use($minEdits) { return $count >= $minEdits; });
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
    public function getUser(): LocalUser
    {
        $dbname = $this->wiki->dbName;

        if (!isset($this->users[$dbname]))
            $this->users[$dbname] = $this->db->getUserDetails($dbname, $this->username);

        $this->user = $this->users[$dbname];
        return $this->user;
    }

    /**
     * Write a message to the output.
     * @param string $message The message to print.
     * @param string|null $classes The CSS classes to add to the output line.
     */
    function msg(string $message, ?string $classes = null): void
    {
        $classes = $classes ? trim($classes) : 'is-note';
        echo "<div class='$classes'>$message</div>";
    }

    /**
     * Print a 'name@wiki...' header for the current wiki.
     */
    function printWiki(): void
    {
        $name = $this->username;
        $domain = $this->wiki->domain;
        $this->msg("On <a href='https://$domain/wiki/User:" . $this->backend->formatWikiUrlTitle($name) . "' title='" . $this->backend->formatValue($name) . "&apos;s user page on $domain'>$domain</a>:", 'is-wiki');
    }
}

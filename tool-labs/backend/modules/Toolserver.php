<?php
require_once('Database.php');
require_once('Wikimedia.php');

/**
 * Extends the database with methods and optimizations for Wikimedia Toolforge. On
 * construction,the class fetches wiki and database data from the Toolforge DB. When connecting to
 * a database name, it aliases it to its server host to minimize the number of server connections.
 */
class Toolserver extends Database
{
    ##########
    ## Properties
    ##########
    /**
     * A dbname => host lookup.
     * @var array
     */
    private $dbnHosts = [];

    /**
     * The Wikimedia wiki data.
     * @var Wikimedia
     */
    private $wikis;

    /**
     * The database names that should be ignored.
     * @var string[]
     */
    private $ignoreDbNames = [
        "alswikibooks", // deleted
        "alswikiquote", // deleted
        "alswiktionary", // deleted
        "mowiktionary", // deleted
        "ukwikimedia", // broken
        "votewiki" // not a wiki
    ];


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Profiler $profiler Provides basic performance profiling.
     * @param Logger $logger Logs trace messages for troubleshooting.
     * @param Cacher $cache Handles reading and writing data to a directory with expiry dates.
     * @param integer $options Additional mode options which can be bitwise ORed together (available options: ERROR_THROW, ERROR_PRINT).
     * @param string $defaultUser The username to use when authenticating to the database, or null to retrieve it from the user configuration file.
     * @param string $defaultPassword The password to use when authenticating to the database, or null to retrieve it from the user configuration file.
     */
    public function __construct($profiler, $logger, $cache, $options = null, $defaultUser = null, $defaultPassword = null)
    {
        parent::__construct($profiler, $logger, $options, $defaultUser, $defaultPassword);

        /* fetch toolserver data */
        $this->wikis = new Wikimedia($this, $cache, $profiler, $this->ignoreDbNames);

        /* select random DB slice (every slice has every DB, but picking a random one reduces our dependence on any given one) */
        $slices = [];
        foreach ($this->wikis->getWikis() as $wiki)
            $slices[$wiki->host] = True;
        $slices = array_keys($slices);
        $slice = $slices[array_rand($slices)];

        /* set DB host lookup */
        foreach ($this->wikis->getWikis() as $wiki)
            $this->dbnHosts[$wiki->dbName] = $slice;//$wiki->host;
    }

    /**
     * Connect to a database server.
     * @param string $host The server address to connect to.
     * @param string $database The name of the database to connect to.
     * @param string $username The username to use when authenticating to the database, or null to authenticate with the default username.
     * @param string $password The password to use when authenticating to the database, or null to authenticate with the default password.
     * @return bool Whether the connection was successfully established.
     */
    public function connect($host, $database = null, $username = null, $password = null)
    {
        /* alias host */
        $dbname = $this->normalizeDbn($host);
        if (isset($this->dbnHosts[$dbname]) && $this->dbnHosts[$dbname]) {
            $database = $dbname;
            $host = $this->dbnHosts[$dbname];
        }

        return parent::connect($host, $database, $username, $password);
    }

    /**
     * Normalise a database name into a consistent format like "enwiki".
     * @param string $dbname The database name.
     * @return string
     */
    public function normalizeDbn($dbname)
    {
        $dbn = str_replace('-', '_', $dbname);
        if (substr($dbn, -2) == '_p')
            $dbn = substr($dbn, 0, -2);
        return $dbn;
    }

    /**
     * Get a database name => wiki lookup.
     * @return Wiki[]
     */
    public function getWikis()
    {
        return $this->wikis->getWikis();
    }

    /**
     * Get the data for a wiki.
     * @param string $dbname The wiki's unique database name.
     * @return Wiki
     */
    public function getWiki($dbname)
    {
        return $this->wikis->getWiki($dbname);
    }

    /**
     * Get a database name => domain lookup.
     * @return array
     */
    public function getDomains()
    {
        return $this->wikis->getDomains();
    }

    /**
     * Get a database name => host lookup.
     * @return array
     */
    public function getDbnHosts()
    {
        return $this->dbnHosts;
    }

    /**
     * Get the domain for a database name.
     * @param string $dbname The database name to find.
     * @return string|null
     */
    public function getDomain($dbname)
    {
        return $this->wikis->getDomain($dbname);
    }

    /**
     * Get the host name for a database name.
     * @param string $dbname The database name to find.
     * @return string|null
     */
    public function getHost($dbname)
    {
        return $this->wikis->getHost($dbname);
    }

    /**
     * Get whether the wiki has been locked.
     * @param string $dbname The database name to find.
     * @return bool|null
     */
    public function getLocked($dbname)
    {
        $wiki = $this->wikis->getWiki($dbname);
        return $wiki != null ? $wiki->isClosed : null;
    }

    /**
     * Get a global account's home wiki.
     * @param string $user The username to search.
     * @return string|null
     */
    public function getHomeWiki($user)
    {
        try {
            $this->connect('metawiki');

            $query = $this->db->prepare('
                SELECT lu_wiki
                FROM centralauth_p.localuser
                WHERE
                    lu_name=?
                    AND lu_attached_method IN ("primary", "new")
                LIMIT 1
            ');
            $query->execute([$user]);

            $this->connectPrevious();

            $wiki = $query->fetchColumn();
            return $wiki ? $wiki : null;
        } catch (PDOException $exc) {
            $this->handleException($exc, 'Could not retrieve home wiki for user "' . htmlentities($user) . '".');
            return null;
        }
    }

    /**
     * Get a global account's list of unified wikis.
     * @param string $user The username to search.
     * @return string[]|null
     */
    public function getUnifiedWikis($user)
    {
        try {
            $this->connect('metawiki');

            $query = $this->db->prepare('
                SELECT lu_wiki
                FROM centralauth_p.localuser
                WHERE lu_name=?
            ');
            $query->execute([$user]);

            $this->connectPrevious();

            $wikis = [];
            foreach ($query as $row) {
                if(!in_array($row['lu_wiki'], $this->ignoreDbNames))
                    $wikis[] = $row['lu_wiki'];
            }

            return $wikis;
        } catch (PDOException $exc) {
            $this->handleException($exc, 'Could not retrieve unified wikis for user "' . htmlentities($user) . '".');
            return null;
        }
    }

    /**
     * Get a local account's details including its id, registration date, and edit count.
     * @param string $wiki The wiki database name.
     * @param string $username The username to search.
     * @param string $dateFormat The format to use for dates.
     * @return LocalUser|null
     */
    public function getUserDetails($wiki, $username, $dateFormat = '%Y-%b-%d %H:%i')
    {
        try {
            // fetch basic user info
            $query = $this->db->prepare('
                SELECT
                    user_id AS id,
                    user_name AS name,
                    user_registration AS registration_raw,
                    DATE_FORMAT(user_registration, "' . $dateFormat . '") as registration,
                    user_editcount AS edits
                FROM user
                WHERE user_name = ?
                LIMIT 1
            ');
            $query->execute([$username]);
            $user = $query->fetch(PDO::FETCH_ASSOC);

            // fetch actor ID
            $query = $this->db->prepare('
                SELECT actor_id
                FROM actor
                WHERE actor_user = ?
                LIMIT 1
            ');
            $query->execute([$user['id']]);
            $actor = $query->fetch(PDO::FETCH_ASSOC);

            // return model
            return new LocalUser($user['id'], $user['name'], $user['registration_raw'], $user['registration'], $user['edits'], $actor['actor_id']);
        } catch (PDOException $exc) {
            $this->handleException($exc, 'Could not retrieve local account details for user "' . htmlentities($username) . '" at wiki "' . htmlentities($wiki) . '".');
            return null;
        }
    }

    /**
     * Get a local account's registration date as an array containing the raw and formatted value.
     * @param int $userID The user ID.
     * @param int $actorID The user's actor ID.
     * @param string $format The date format.
     * @param bool $skipUserTable Whether to ignore the user table (e.g. because you already checked there).
     * @return array|null
     */
    public function getRegistrationDate($userID, $actorID, $format = '%Y-%m-%d %H:%i', $skipUserTable = false)
    {
        if ($this->borked)
            return null;
        try {
            /* try date field in user table */
            if (!$skipUserTable) {
                $query = $this->db->prepare('
                    SELECT
                        user_registration AS raw,
                        DATE_FORMAT(user_registration, "' . $format . '") AS formatted
                    from user
                    WHERE user_id=?
                    LIMIT 1
                ');
                $query->execute([$userID]);
                $date = $query->fetch(PDO::FETCH_ASSOC);
                if (isset($date['raw']))
                    return $date;
            }

            /* try extracting from logs */
            $query = null;
            $query = $this->db->prepare('
                SELECT
                    log_timestamp AS raw,
                    DATE_FORMAT(log_timestamp, "' . $format . '") AS formatted
                FROM logging_userindex
                WHERE
                    log_actor = ?
                    AND log_type = "newusers"
                    AND log_title = "Userlogin"
                LIMIT 1');
            $query->execute([$actorID]);
            $date = $query->fetch(PDO::FETCH_ASSOC);
            if (isset($date['raw']))
                return $date;

            /* failed */
            return ['raw' => null, 'formatted' => 'in 2005 or earlier'];
        } catch (PDOException $exc) {
            $this->handleException($exc, 'Could not retrieve registration date for user id "' . htmlentities($userID) . '".');
            return null;
        }
    }
}

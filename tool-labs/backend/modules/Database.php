<?php
require_once('Wikimedia.php');

/**
 * Provides database operations with optimizations and connection caching.
 *
 * This implementation stores open database connections internally to avoid opening new connections
 * unnecessarily, exposes the underlying PDO object for direct use, and wraps many of PDO's methods
 * for error handling (eg, when Database::ERROR_PRINT is set). When an error occurs, it ignores all
 * further calls until the next Connect() or resetException() call.
 */
class Database
{
    ##########
    ## Properties
    ##########
    #####
    ## constants
    #####
    /**
     * A bitflag indicating exceptions should be thrown.
     * @var int
     */
    const ERROR_THROW = 1;

    /**
     * A bitflag indicating exceptions should be printed to the screen.
     * @var int
     */
    const ERROR_PRINT = 2;

    /**
     * A bitflag matching any valid error flag.
     */
    const ERROR_MODES = 3;

    /**
     * The PDO database vendor driver.
     * @var string
     */
    const DRIVER = 'mysql';

    #####
    ## Settings
    #####
    /**
     * The current error mode.
     * @var int
     */
    protected $errorMode = 2; // ERROR_PRINT

    /**
     * The configuration file path from which to read the default database settings.
     * @var string
     */
    protected $configFile; // set in constructor

    /**
     * The default DB username.
     * @var string
     */
    protected $defaultUser;

    /**
     * The default DB password.
     * @var string
     */
    protected $defaultPassword;

    #####
    ## State
    #####
    /* connection arrays */
    /**
     * A host => PDO connection lookup hash.
     * @var array
     */
    protected $connections = Array();

    /**
     * A host => Exception lookup hash.
     * @var array
     */
    protected $exceptions = Array();

    /**
     * The current DB host name.
     * @var string
     */
    protected $host;

    /**
     * The current DB username.
     * @var string
     */
    protected $username;

    /**
     * The current DB password.
     * @var string
     */
    protected $password;

    /**
     * The current DB name.
     * @var string
     */
    protected $database;

    /**
     * Whether an error occurred in the current session.
     * @var bool
     */
    protected $borked = false;

    /**
     * The previous DB host name.
     * @var string
     */
    protected $prevHost;

    /**
     * The previous DB username.
     * @var string
     */
    protected $prevUsername;

    /**
     * The previous DB password.
     * @var string
     */
    protected $prevPassword;

    /**
     * The previous DB name.
     * @var string
     */
    protected $prevDatabase;

    /**
     * The last prepared query.
     * @var PDOStatement
     */
    protected $query = null;

    /**
     * The underlying logger.
     * @var Logger
     */
    protected $logger = null;

    /**
     * Provides basic performance profiling.
     * @var Profiler
     */
    protected $profiler = null;

    /**
     * Whether the database has been disposed.
     * @var bool
     */
    private $disposed = false;


    ##########
    ## Accessors
    ##########
    /**
     * The underlying database connection.
     * @var PDO
     */
    public $db = null;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Profiler $profiler Provides basic performance profiling.
     * @param Logger $logger Writes messages to a log file for troubleshooting.
     * @param integer $options Additional mode options which can be bitwise ORed together (one of {@see Database::ERROR_THROW} or {@see Database::ERROR_PRINT}).
     * @param string $default_username The username to use when authenticating to the database, or null to retrieve it from the user configuration file.
     * @param string $default_password The password to use when authenticating to the database, or null to retrieve it from the user configuration file.
     */
    public function __construct($profiler, $logger, $options = null, $default_username = null, $default_password = null)
    {
        /* configuration */
        $this->configFile = REPLICA_CNF_PATH;
        $this->profiler = $profiler;
        $this->logger = $logger;

        /* get default login details */
        $this->default_username = $default_username;
        $this->defaultPassword = $default_password;

        if ((!$default_username || !$default_password) && file_exists($this->configFile)) {
            $config = parse_ini_file($this->configFile);
            $this->default_username = ($default_username ? $default_username : $config['user']);
            $this->defaultPassword = ($default_password ? $default_password : $config['password']);
        }

        /* get options */
        if ($options & self::ERROR_MODES)
            $this->errorMode = self::ERROR_MODES & $options;
    }

    /**
     * Close all database connections and release resources.
     */
    public function dispose()
    {
        if ($this->disposed)
            return;

        $keys = array_keys($this->connections);
        foreach ($keys as $key)
            $this->connections[$key] = null;

        $this->disposed = true;
    }

    /**
     * Close all database connections and release resources.
     */
    public function __destruct()
    {
        $this->dispose();
    }

    /**
     * Open a database connection. This will reuse an existing server connection if it has been previously opened.
     * @param string $host The server address to connect to.
     * @param string $database The name of the database to connect to.
     * @param string $username The username to use when authenticating to the database, or null to authenticate with the default username.
     * @param string $password The password to use when authenticating to the database, or null to authenticate with the default password.
     * @return bool Whether the connection was successfully established.
     */
    public function connect($host, $database = null, $username = null, $password = null)
    {
        /* normalize database name for Tools Labs */
        if (isset($database) && substr($database, -2) != '_p')
            $database .= '_p';
        if (FORCE_DB_HOST)
            $host = FORCE_DB_HOST;

        /* change states */
        $this->borked = false;

        $this->prevHost = $this->host;
        $this->prevUsername = $this->username;
        $this->prevPassword = $this->password;
        $this->prevDatabase = $this->database;

        $this->host = $host;
        $this->username = ($username ? $username : $this->default_username);
        $this->password = ($password ? $password : $this->defaultPassword);
        $this->database = $database;

        /* create new connection, if can't recycle one */
        if (!isset($this->connections[$host]) || !$this->connections[$this->host]) {
            $this->log('connecting: host=[' . $host . '], database=[' . $database . ']');
            try {
                $this->connections[$this->host] = new PDO(
                    self::DRIVER . ':host=' . addslashes($this->host) . ';dbname=' . addslashes($database),
                    $this->username,
                    $this->password
                );
                $this->connections[$this->host]->setAttribute(
                    PDO::ATTR_ERRMODE,
                    PDO::ERRMODE_EXCEPTION
                );
            } catch (PDOException $exc) {
                return $this->handleException($exc, 'Could not connect to database host "' . htmlentities($host) . '".');
            }
        }

        /* alias connection */
        $this->db = &$this->connections[$this->host];

        /* set database */
        $this->db->query('use `' . $database . '`');
        return true;
    }

    /**
     * Reopen the previous connection. This is typically used after establishing a temporary connection to a different database.
     * @return bool Whether the connection was successfully established.
     */
    public function connectPrevious()
    {
        if ($this->prevHost)
            return $this->connect($this->prevHost, $this->prevDatabase, $this->prevUsername, $this->prevPassword);
        return false;
    }


    #############################
    ## Query
    #############################
    /**
     * Submit a parameterized SQL query to the database.
     * @param string $sql The raw SQL command to submit.
     * @param array $values The values to substituted for parameterized placeholders in the SQL command.
     * @return Database|false The Database instance for method chaining (or false if the query failed).
     */
    public function query($sql, $values = [])
    {
        $sql .= " /*{$this->logger->key}*/";

        if ($this->borked)
            return null;
        try {
            if ($this->db == null)
                throw new Exception('Not connected to a database.');

            $this->query = null;
            $this->query = $this->db->prepare($sql);
            if ($values != null && !is_array($values))
                $values = Array($values);

            $this->log('query ' . $this->database . ': [' . $this->query->queryString . '], values=[' . preg_replace('/\s+/', ' ', print_r($values, true)) . ']');
            $this->query->execute($values);
            $this->log('query done');

            return $this;
        } catch (Exception $exc) {
            return $this->handleException($exc, 'Could not perform query:<br /><small>' . $this->getQueryDebugData() . '</small>');
        }
    }

    #############################
    ## Read row from query result
    #############################
    /**
     * Fetch the next row as a hash of field names and values, and advance the internal pointer.
     * @return array|false The hash of field names and values, or false if the query failed.
     */
    public function fetchAssoc()
    {
        if ($this->borked)
            return null;
        try {
            return $this->query->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $exc) {
            return $this->handleException($exc, 'Could not fetch result from query:<br /><small>' . $this->getQueryDebugData() . '</small>');
        }
    }

    /**
     * Fetch a single value from the next row, and advance the internal pointer.
     * @param $columnNumber int The numeric index of the column to retrieve.
     * @return string|false The value of the retrieved field, or false if the query failed.
     */
    public function fetchColumn($columnNumber = 0)
    {
        return $this->fetchValue($columnNumber);
    }

    /**
     * Fetch a single value from the next row, and advance the internal pointer.
     * @param $columnNumber int The numeric index of the column to retrieve.
     * @return string|false The value of the retrieved field, or false if the query failed.
     */
    public function fetchValue($columnNumber = 0)
    {
        if ($this->borked)
            return null;
        try {
            return $this->query->fetchColumn($columnNumber);
        } catch (PDOException $exc) {
            return $this->handleException($exc, 'Could not fetch result from query:<br /><small>' . $this->getQueryDebugData() . '</small>');
        }
    }

    /**
     * Fetch all result rows as an array of field name & value hashes.
     * @return array|false An array of field name & value hashes, or false if the query failed.
     */
    public function fetchAllAssoc()
    {
        if ($this->borked)
            return null;
        try {
            return $this->query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exc) {
            return $this->handleException($exc, 'Could not fetch result from query:<br /><small>' . $this->getQueryDebugData() . '</small>');
        }
    }


    /**
     * Get the number of rows returned by the query.
     * @return int The number of rows returned by the query.
     */
    public function countRows()
    {
        if (!$this->query)
            return 0;
        return $this->query->rowCount();
    }


    ##########
    ## Private methods
    ##########
    /**
     * Handle an exception.
     * @param Exception $exception The intercepted exception to handle.
     * @param string $error_message The error message to log (if any).
     * @throws Exception Throws the received exception if required by the error mode.
     * @return false
     */
    protected function handleException($exception, $error_message = null)
    {
        $this->log("exception: error=[{$exception->getMessage()}], message=[{$error_message}]");

        $this->exceptions[$this->host] = $exception;
        $this->borked = true;

        if ($this->errorMode & self::ERROR_PRINT) {
            if ($error_message)
                echo '<div class="error">', $error_message, '<br /><small>Exception: ', htmlentities($this->exceptions[$this->host]->getMessage()), '</small></div>';
            else
                echo '<div class="error">Exception: ', htmlentities($this->exceptions[$this->host]->getMessage()), '</div>';
            return false;
        }
        if ($this->errorMode & self::ERROR_THROW)
            throw $exception;
        return false;
    }


    /**
     * Get a human-readable data dump about the current query.
     * @return string
     */
    protected function getQueryDebugData()
    {
        if ($this->query == null)
            return null;
        ob_start();
        $this->query->debugDumpParams();
        return ob_get_clean();
    }


    /**
     * Log a trace message.
     * @param string $message The message to log.
     */
    protected function log($message)
    {
        if ($this->logger != null)
            $this->logger->log('Database> ' . $message);
    }
}

/**
 * Extends the database with methods and optimizations for the Wikimedia Tools Labs. On
 * construction,the class fetches wiki and database data from the Tools Labs DB. When connecting to
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


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Profiler $profiler Provides basic performance profiling.
     * @param Logger $logger Logs trace messages for troubleshooting.
     * @param Cacher $cache Handles reading and writing data to a directory with expiry dates.
     * @param integer $options Additional mode options which can be bitwise ORed together (available options: ERROR_THROW, ERROR_PRINT).
     * @param string $default_username The username to use when authenticating to the database, or null to retrieve it from the user configuration file.
     * @param string $default_password The password to use when authenticating to the database, or null to retrieve it from the user configuration file.
     */
    public function __construct($profiler, $logger, $cache, $options = null, $default_username = null, $default_password = null)
    {
        parent::__construct($profiler, $logger, $options, $default_username, $default_password);

        /* fetch toolserver data */
        $this->wikis = new Wikimedia($this, $cache, $profiler);

        /* select random DB slice (every slice has every DB, but picking a random one reduces our dependence on any given one) */
        $slices = Array();
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

            $query = $this->db->prepare('SELECT lu_wiki FROM centralauth_p.localuser WHERE lu_name=? AND lu_attached_method IN ("primary", "new") LIMIT 1');
            $query->execute(Array($user));

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

            $query = $this->db->prepare('SELECT lu_wiki FROM centralauth_p.localuser WHERE lu_name=?');
            $query->execute(Array($user));

            $this->connectPrevious();

            $wikis = Array();
            foreach ($query as $row)
                $wikis[] = $row['lu_wiki'];

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
            $query = $this->db->prepare('SELECT user_id AS id, user_name AS name, user_registration AS registration_raw, DATE_FORMAT(user_registration, "' . $dateFormat . '") as registration, user_editcount AS edits FROM user WHERE user_name = ? LIMIT 1');
            $query->execute(array($username));
            $user = $query->fetch(PDO::FETCH_ASSOC);
            return new LocalUser($user['id'], $user['name'], $user['registration_raw'], $user['registration'], $user['edits']);
        } catch (PDOException $exc) {
            $this->handleException($exc, 'Could not retrieve local account details for user "' . htmlentities($username) . '" at wiki "' . htmlentities($wiki) . '".');
            return null;
        }
    }

    /**
     * Get a local account's registration date as an array containing the raw and formatted value.
     * @param int $userID The user ID.
     * @param string $format The date format.
     * @param bool $skipUserTable Whether to ignore the user table, which can be very slow.
     * @return array|null
     */
    public function getRegistrationDate($userID, $format = '%Y-%m-%d %H:%i', $skipUserTable = false)
    {
        if ($this->borked)
            return null;
        try {
            /* try date field in user table */
            if (!$skipUserTable) {
                $query = $this->db->prepare('SELECT user_registration AS raw, DATE_FORMAT(user_registration, "' . $format . '") AS formatted from user WHERE user_id=? LIMIT 1');
                $query->execute([$userID]);
                $date = $query->fetch(PDO::FETCH_ASSOC);
                if (isset($date['raw']))
                    return $date;
            }

            /* try extracting from logs */
            $query = null;
            $query = $this->db->prepare('SELECT log_timestamp AS raw, DATE_FORMAT(log_timestamp, "' . $format . '") AS formatted FROM logging WHERE log_user = ? AND log_type = "newusers" AND log_title = "Userlogin" LIMIT 1');
            $query->execute(array($userID));
            $date = $query->fetch(PDO::FETCH_ASSOC);
            if (isset($date['raw']))
                return $date;

            /* failed */
            return Array('raw' => null, 'formatted' => 'in 2005 or earlier');
        } catch (PDOException $exc) {
            $this->handleException($exc, 'Could not retrieve registration date for user id "' . htmlentities($userID) . '".');
            return null;
        }
    }
}

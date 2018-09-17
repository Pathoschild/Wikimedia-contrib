<?php
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
     * @var PDOStatement|null
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
     * @param string $defaultUser The username to use when authenticating to the database, or null to retrieve it from the user configuration file.
     * @param string $defaultPassword The password to use when authenticating to the database, or null to retrieve it from the user configuration file.
     */
    public function __construct($profiler, $logger, $options = null, $defaultUser = null, $defaultPassword = null)
    {
        /* configuration */
        $this->configFile = REPLICA_CNF_PATH;
        $this->profiler = $profiler;
        $this->logger = $logger;

        /* get default login details */
        $this->defaultUser = $defaultUser;
        $this->defaultPassword = $defaultPassword;

        if ((!$defaultUser || !$defaultPassword) && file_exists($this->configFile)) {
            $config = parse_ini_file($this->configFile);
            $this->defaultUser = ($defaultUser ? $defaultUser : $config['user']);
            $this->defaultPassword = ($defaultPassword ? $defaultPassword : $config['password']);
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
        /* normalize database name for Toolforge */
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
        $this->username = ($username ? $username : $this->defaultUser);
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
            return false;
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
            return false;
        try {
            return $this->query->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $exc) {
            return $this->handleException($exc, 'Could not fetch result from query:<br /><small>' . $this->getQueryDebugData() . '</small>');
        }
    }

    /**
     * Fetch a single value from the next row, and advance the internal pointer.
     * @param int $columnNumber The numeric index of the column to retrieve.
     * @return string|false The value of the retrieved field, or false if the query failed.
     */
    public function fetchColumn($columnNumber = 0)
    {
        return $this->fetchValue($columnNumber);
    }

    /**
     * Fetch a single value from the next row, and advance the internal pointer.
     * @param int $columnNumber The numeric index of the column to retrieve.
     * @return string|false The value of the retrieved field, or false if the query failed.
     */
    public function fetchValue($columnNumber = 0)
    {
        if ($this->borked)
            return false;
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
            return false;
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
     * @return string|null
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

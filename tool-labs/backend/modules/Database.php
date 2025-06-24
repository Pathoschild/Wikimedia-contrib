<?php
declare(strict_types=1);

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
     */
    const ERROR_THROW = 1;

    /**
     * A bitflag indicating exceptions should be printed to the screen.
     */
    const ERROR_PRINT = 2;

    /**
     * A bitflag matching any valid error flag.
     */
    const ERROR_MODES = 3;

    /**
     * The PDO database vendor driver.
     */
    const DRIVER = 'mysql';

    #####
    ## Settings
    #####
    /**
     * The current error mode.
     */
    protected int $errorMode = 2; // ERROR_PRINT

    /**
     * The configuration file path from which to read the default database settings.
     */
    protected string $configFile; // set in constructor

    /**
     * The default DB username.
     */
    protected string $defaultUser;

    /**
     * The default DB password.
     */
    protected string $defaultPassword;

    #####
    ## State
    #####
    /* connection arrays */
    /**
     * A host => PDO connection lookup hash.
     * @var array<string, PDO|null>
     */
    protected array $connections = [];

    /**
     * A host => Exception lookup hash.
     * @var array<string, Exception>
     */
    protected array $exceptions = [];

    /**
     * The current DB host name.
     */
    protected ?string $host = null;

    /**
     * The current DB username.
     */
    protected ?string $username = null;

    /**
     * The current DB password.
     */
    protected ?string $password = null;

    /**
     * The current DB name.
     */
    protected ?string $database = null;

    /**
     * Whether an error occurred in the current session.
     */
    protected bool $borked = false;

    /**
     * The previous DB host name.
     */
    protected ?string $prevHost = null;

    /**
     * The previous DB username.
     */
    protected ?string $prevUsername = null;

    /**
     * The previous DB password.
     */
    protected ?string $prevPassword = null;

    /**
     * The previous DB name.
     */
    protected ?string $prevDatabase = null;

    /**
     * The last prepared query.
     * @var PDOStatement|null
     */
    protected ?PDOStatement $query = null;

    /**
     * The underlying logger.
     */
    protected Logger $logger;

    /**
     * Provides basic performance profiling.
     * @var Profiler
     */
    protected Profiler $profiler;

    /**
     * Whether the database has been disposed.
     */
    private bool $disposed = false;


    ##########
    ## Accessors
    ##########
    /**
     * The underlying database connection.
     * @var PDO
     */
    public ?PDO $db = null;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Profiler $profiler Provides basic performance profiling.
     * @param Logger $logger Writes messages to a log file for troubleshooting.
     * @param int|null $options Additional mode options which can be bitwise ORed together (one of {@see Database::ERROR_THROW} or {@see Database::ERROR_PRINT}).
     * @param string|null $defaultUser The username to use when authenticating to the database, or null to retrieve it from the user configuration file.
     * @param string|null $defaultPassword The password to use when authenticating to the database, or null to retrieve it from the user configuration file.
     */
    public function __construct(Profiler $profiler, Logger $logger, ?int $options = null, ?string $defaultUser = null, ?string $defaultPassword = null)
    {
        /* configuration */
        $this->configFile = REPLICA_CNF_PATH;
        $this->profiler = $profiler;
        $this->logger = $logger;

        /* get default login details */
        if ((!$defaultUser || !$defaultPassword) && file_exists($this->configFile)) {
            $config = parse_ini_file($this->configFile);
            $defaultUser = ($defaultUser ? $defaultUser : $config['user']);
            $defaultPassword = ($defaultPassword ? $defaultPassword : $config['password']);
        }
        $this->defaultUser = $defaultUser;
        $this->defaultPassword = $defaultPassword;

        /* get options */
        if ($options & self::ERROR_MODES)
            $this->errorMode = self::ERROR_MODES & $options;
    }

    /**
     * Close all database connections and release resources.
     */
    public function dispose(): void
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
     * @param string|null $database The name of the database to connect to.
     * @param string|null $username The username to use when authenticating to the database, or null to authenticate with the default username.
     * @param string|null $password The password to use when authenticating to the database, or null to authenticate with the default password.
     * @return bool Whether the connection was successfully established.
     */
    public function connect(string $host, ?string $database = null, ?string $username = null, ?string $password = null): bool
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
    public function connectPrevious(): bool
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
     * @param array<string, mixed> $values The values to substituted for parameterized placeholders in the SQL command.
     * @return Database|false The Database instance for method chaining (or false if the query failed).
     */
    public function query(string $sql, array $values = []): self|false
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
     * @return array<string, mixed>|false The hash of field names and values, or false if the query failed.
     */
    public function fetchAssoc(): array|false
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
     * @return mixed The value of the retrieved field, or false if the query failed.
     */
    public function fetchColumn(int $columnNumber = 0): mixed
    {
        return $this->fetchValue($columnNumber);
    }

    /**
     * Fetch a single value from the next row, and advance the internal pointer.
     * @param int $columnNumber The numeric index of the column to retrieve.
     * @return mixed The value of the retrieved field, or false if the query failed.
     */
    public function fetchValue(int $columnNumber = 0): mixed
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
     * @return array<string, mixed>|false An array of field name & value hashes, or false if the query failed.
     */
    public function fetchAllAssoc(): array|false
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
    public function countRows(): int
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
     * @param string|null $error_message The error message to log (if any).
     * @throws Exception Throws the received exception if required by the error mode.
     * @return false
     */
    protected function handleException(Exception $exception, ?string $error_message = null): false
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
     */
    protected function getQueryDebugData(): string|null|false
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
    protected function log(string $message): void
    {
        if ($this->logger != null)
            $this->logger->log('Database> ' . $message);
    }
}

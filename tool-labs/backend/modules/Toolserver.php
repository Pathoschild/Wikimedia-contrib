<?php
declare(strict_types=1);

require_once('Database.php');
require_once('Wikimedia.php');
require_once(__DIR__ . '/../models/WikiQueryResult.php');

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
     * The maximum number of per-wiki SELECTs to combine into per-shard UNION ALL queries.
     *
     * This is a tradeoff between the number of branches (which can be expensive for the query
     * planner) and number of database round trips (which can keep connections open longer).
     */
    private const SHARD_BATCH_SIZE = 250;

    /**
     * The maximum number of wikis to try when connecting to a shard to list its databases, before
     * assuming that the shard itself is offline rather than individual databases.
     */
    private const SHARD_CONNECT_ATTEMPTS = 3;

    /**
     * How long to cache the list of databases on a shard, as an ISO 8601 duration.
     */
    private const SHARD_DATABASE_CACHE_TIME = 'PT1H';

    /**
     * A dbname => host lookup.
     * @var array<string, string>
     */
    private array $dbnHosts = [];

    /**
     * The Wikimedia wiki data.
     */
    private Wikimedia $wikis;

    /**
     * Handles reading and writing data to a directory with expiry dates.
     */
    private Cacher $cache;

    /**
     * A lookup of wiki databases by shard, indexed by shard hostname and then dbname. A null entry
     * means the shard couldn't be accessed.
     * @var array<string, array<string, true>|null>
     */
    private array $visibleDatabases = [];

    /**
     * The database names that should be ignored.
     * @var string[]
     */
    private array $ignoreDbNames = [
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
     * @param int|null $options Additional mode options which can be bitwise ORed together (available options: ERROR_THROW, ERROR_PRINT).
     * @param string|null $defaultUser The username to use when authenticating to the database, or null to retrieve it from the user configuration file.
     * @param string|null $defaultPassword The password to use when authenticating to the database, or null to retrieve it from the user configuration file.
     */
    public function __construct(Profiler $profiler, Logger $logger, Cacher $cache, ?int $options = null, ?string $defaultUser = null, ?string $defaultPassword = null)
    {
        parent::__construct($profiler, $logger, $options, $defaultUser, $defaultPassword);
        $this->cache = $cache;

        /* fetch toolserver data */
        $this->wikis = new Wikimedia($this, $cache, $profiler, $this->ignoreDbNames);

        /* set DB host lookup */
        foreach ($this->wikis->getWikis() as $wiki)
            $this->dbnHosts[$wiki->dbName] = $wiki->host;
    }

    /**
     * Connect to a database server.
     * @param string $host The server address to connect to.
     * @param string $database The name of the database to connect to.
     * @param string $username The username to use when authenticating to the database, or null to authenticate with the default username.
     * @param string $password The password to use when authenticating to the database, or null to authenticate with the default password.
     * @return bool Whether the connection was successfully established.
     */
    public function connect(string $host, ?string $database = null, ?string $username = null, ?string $password = null): bool
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
     */
    public function normalizeDbn(?string $dbname): string
    {
        if (!$dbname)
            return '';

        $dbn = str_replace('-', '_', $dbname);
        if (substr($dbn, -2) == '_p')
            $dbn = substr($dbn, 0, -2);
        return $dbn;
    }

    /**
     * Get a database name => wiki lookup.
     * @return Wiki[]
     */
    public function getWikis(): array
    {
        return $this->wikis->getWikis();
    }

    /**
     * Get the data for a wiki.
     * @param string $dbname The wiki's unique database name.
     */
    public function getWiki(string $dbname): ?Wiki
    {
        return $this->wikis->getWiki($dbname);
    }

    /**
     * Get a database name => domain lookup.
     * @return array<string, string>
     */
    public function getDomains(): array
    {
        return $this->wikis->getDomains();
    }

    /**
     * Get a database name => host lookup.
     * @return array<string, string>
     */
    public function getDbnHosts(): array
    {
        return $this->dbnHosts;
    }

    /**
     * Get the domain for a database name.
     * @param string $dbname The database name to find.
     */
    public function getDomain(string $dbname): ?string
    {
        return $this->wikis->getDomain($dbname);
    }

    /**
     * Get the host name for a database name.
     * @param string $dbname The database name to find.
     */
    public function getHost(string $dbname): ?string
    {
        return $this->wikis->getHost($dbname);
    }

    /**
     * Get whether the wiki has been locked.
     * @param string $dbname The database name to find.
     */
    public function getLocked(string $dbname): ?bool
    {
        $wiki = $this->wikis->getWiki($dbname);
        return $wiki != null ? $wiki->isClosed : null;
    }

    /**
     * Run a `SELECT` query against many wikis with per-shard batching.
     *
     * Every wiki on a shard is served by the same database server, so this combines per-wiki
     * queries into batched `UNION ALL` queries using qualified table names to reduce DB round
     * trips.
     *
     * If a batched statement fails, the affected wikis are retried individually.
     *
     * @param string[] $dbnames The database names to query. These values are normalized if needed before use.
     * @param string $sqlTemplate The `SELECT` query to run on each wiki, where `{db}` is replaced by the wiki's quoted DB name and `{dbname}` by a quoted string equivalent.
     * @param mixed[] $values The parameter values for a single SELECT. This is reused automatically for each batched query.
     * @return WikiQueryResult The query results.
     */
    public function queryManyWikis(array $dbnames, string $sqlTemplate, array $values = []): WikiQueryResult
    {
        $result = new WikiQueryResult();

        foreach ($this->groupByShard($dbnames, $result) as $host => $shardDbNames) {
            foreach (array_chunk($shardDbNames, self::SHARD_BATCH_SIZE) as $chunk) {
                $chunkRows = $this->queryUnion($chunk, $sqlTemplate, $values);

                if ($chunkRows !== null)
                    $result->rows = array_merge($result->rows, $chunkRows);
                else {
                    $this->log("batched query failed on host [$host]; falling back to one query per wiki");
                    $result->add($this->queryEachWiki($chunk, $sqlTemplate, $values));
                }
            }
        }

        return $result;
    }

    /**
     * Get a global account's details.
     * @param string $user The username to search.
     */
    public function getGlobalAccount(string $user): ?GlobalUser
    {
        try {
            $this->connect('metawiki');

            $query = $this->db->prepare('
                SELECT
                    gu_id,
                    gu_name,
                    gu_home_db,
                    gu_locked,
                    gu_registration
                FROM centralauth_p.globaluser
                WHERE gu_name = ?
                LIMIT 1
            ');
            $query->execute([$user]);

            $this->connectPrevious();

            if (!$query)
                return null;

            $row = $query->fetch(PDO::FETCH_ASSOC);
            return new GlobalUser(
                id: intval($row['gu_id']),
                name: $row['gu_name'],
                homeWiki: $row['gu_home_db'],
                isLocked: boolval($row['gu_locked']),
                registered: $row['gu_registration']
            );
        }
        catch (PDOException $exc) {
            $this->handleException($exc, 'Could not retrieve global account for user "' . htmlentities($user) . '".');
            return null;
        }
    }

    /**
     * Get a global account's list of unified wikis.
     * @param string $user The username to search.
     * @return string[]|null
     */
    public function getUnifiedWikis(string $user): ?array
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
        }
        catch (PDOException $exc) {
            $this->handleException($exc, 'Could not retrieve unified wikis for user "' . htmlentities($user) . '".');
            return null;
        }
    }

    /**
     * Get a local account's details including its id, registration date, and edit count.
     * @param string $wiki The wiki database name.
     * @param string $username The username to search.
     * @param string $dateFormat The format to use for dates.
     */
    public function getUserDetails(string $wiki, string $username, string $dateFormat = '%Y-%b-%d %H:%i'): ?LocalUser
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
        }
        catch (PDOException $exc) {
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
     * @return array<string, string>|null
     */
    public function getRegistrationDate(int $userId, int $actorId, string $format = '%Y-%m-%d %H:%i', bool $skipUserTable = false): ?array
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
                $query->execute([$userId]);
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
            $query->execute([$actorId]);
            $date = $query->fetch(PDO::FETCH_ASSOC);
            if (isset($date['raw']))
                return $date;

            /* failed */
            return ['raw' => null, 'formatted' => 'in 2005 or earlier'];
        }
        catch (PDOException $exc) {
            $this->handleException($exc, 'Could not retrieve registration date for user id "' . htmlentities((string)$userId) . '".');
            return null;
        }
    }


    ##########
    ## Private methods
    ##########
    /**
     * Group database names by their shard hostname.
     *
     * Inaccessible databases are marked as failed and skipped, to avoid breaking batch queries.
     *
     * @param string[] $dbnames The database names to group.
     * @param WikiQueryResult $result The results with which to track dropped database names.
     * @return array<string, string[]> A lookup of database names by shard hostname.
     */
    private function groupByShard(array $dbnames, WikiQueryResult $result): array
    {
        $byHost = [];

        // get dbnames by shard
        foreach ($dbnames as $dbname) {
            $dbname = $this->normalizeDbn($dbname);

            if (self::isValidDbName($dbname) && !empty($this->dbnHosts[$dbname]))
                $byHost[$this->dbnHosts[$dbname]][] = $dbname;
            else
                $result->failedWikis[] = $dbname;
        }

        // filter out unreachable DBs
        foreach ($byHost as $host => $shardDbNames) {
            $visible = $this->getVisibleDatabases($shardDbNames);
            if ($visible === null)
                continue;

            $byHost[$host] = [];
            foreach ($shardDbNames as $dbname) {
                if (isset($visible[$dbname]))
                    $byHost[$host][] = $dbname;
                else
                    $result->failedWikis[] = $dbname;
            }
        }

        return $byHost;
    }

    /**
     * Get the wiki databases which exist on a shard host, as a `dbname => true` lookup.
     *
     * `meta_p.wiki` lists wikis with no reachable database (e.g. private wikis), which break
     * batched queries which contain them. This allows omitting them to minimize defaulting to
     * individual DB queries.
     *
     * @param string[] $shardDbNames The databases on the host.
     * @return array<string, true>|null The lookup, or null if it couldn't be fetched.
     */
    private function getVisibleDatabases(array $shardDbNames): ?array
    {
        // get shard hostname
        $host = $this->dbnHosts[$shardDbNames[0]];
        if (array_key_exists($host, $this->visibleDatabases))
            return $this->visibleDatabases[$host];

        // fetch with cache
        $cacheKey = "shard-databases-$host";
        $visible = $this->cache->get($cacheKey);
        if (!$visible) {
            $visible = $this->fetchVisibleDatabases($shardDbNames);
            if ($visible)
                $this->cache->save($cacheKey, $visible, new DateInterval(self::SHARD_DATABASE_CACHE_TIME));
        }
        $this->visibleDatabases[$host] = $visible;

        return $visible;
    }

    /**
     * Fetch the wiki databases which exist on a shard host, by connecting through one of its wikis.
     *
     * @param string[] $shardDbNames The databases on the host, any of which can be used to connect to it.
     * @return array<string, true>|null The lookup, or null if it couldn't be fetched.
     */
    private function fetchVisibleDatabases(array $shardDbNames): ?array
    {
        // try fetching DBs from shard
        return $this->withSuppressedErrors(function() use ($shardDbNames) {
            foreach (array_slice($shardDbNames, 0, self::SHARD_CONNECT_ATTEMPTS) as $dbname) {
                if (!$this->tryConnect($dbname))
                    continue;

                $query = $this->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA');
                if (!$query)
                    return null;

                $names = $query->fetchAllAssoc();
                if (!$names)
                    return null;

                // build results
                $visible = [];
                foreach ($names as $row)
                    $visible[$this->normalizeDbn($row['SCHEMA_NAME'])] = true;
                return $visible;
            }

            return null;
        });
    }

    /**
     * Run a batched `UNION ALL` query which combines a `SELECT` query for each of the given wikis.
     *
     * @param string[] $dbnames The database names to query. These must all be on the same shard.
     * @param string $sqlTemplate The `SELECT` query to run on each wiki, where `{db}` is replaced by the wiki's quoted DB name and `{dbname}` by a quoted string equivalent.
     * @param mixed[] $values The parameter values for a single SELECT. This is reused automatically for each batched query.
     * @return array<int, array<string, mixed>>|null The concatenated result rows, or null if the statement failed.
     */
    private function queryUnion(array $dbnames, string $sqlTemplate, array $values): ?array
    {
        // build combined query
        $selects = [];
        $batchValues = [];
        foreach ($dbnames as $dbname) {
            $selects[] = $this->expandTemplate($sqlTemplate, $dbname);
            foreach ($values as $value)
                $batchValues[] = $value;
        }

        // run query
        return $this->withSuppressedErrors(function() use ($dbnames, $selects, $batchValues) {
            if (!$this->tryConnect($dbnames[0]))
                return null;

            $query = $this->query(implode("\nUNION ALL\n", $selects), $batchValues);
            if (!$query)
                return null;

            $rows = $query->fetchAllAssoc();
            return $rows === false
                ? null
                : $rows;
        });
    }

    /**
     * Run a per-wiki SELECT as a separate query for each wiki. This should usually only be done if
     * {@see Toolserver::queryManyWikis} fails.
     *
     * @param string[] $dbnames The database names to query.
     * @param string $sqlTemplate The `SELECT` query to run on each wiki, where `{db}` is replaced by the wiki's quoted DB name and `{dbname}` by a quoted string equivalent.
     * @param mixed[] $values The parameter values for a single SELECT. This is reused automatically for each batched query.
     * @return WikiQueryResult The query results.
     */
    private function queryEachWiki(array $dbnames, string $sqlTemplate, array $values): WikiQueryResult
    {
        $result = new WikiQueryResult();

        // fetch results
        $this->withSuppressedErrors(function() use ($dbnames, $sqlTemplate, $values, $result) {
            foreach ($dbnames as $dbname) {
                if (!$this->tryConnect($dbname)) { // also clears the error state left a previous failed batch
                    $result->failedWikis[] = $dbname;
                    continue;
                }

                $sql = $this->expandTemplate($sqlTemplate, $dbname);
                $query = $this->query($sql, $values);
                if (!$query) {
                    $result->failedWikis[] = $dbname;
                    continue;
                }

                $wikiRows = $query->fetchAllAssoc();
                if ($wikiRows === false) {
                    $result->failedWikis[] = $dbname;
                    continue;
                }

                if ($wikiRows)
                    $result->rows = array_merge($result->rows, $wikiRows);
            }
        });

        if ($result->failedWikis)
            $this->log('query failed on some wikis: [' . implode(', ', $result->failedWikis) . '].');

        return $result;
    }

    /**
     * Expand a per-wiki SQL template for a given wiki.
     *
     * @param string $sqlTemplate The `SELECT` query to expand, where `{db}` is replaced by the wiki's quoted DB name and `{dbname}` by a quoted string equivalent.
     * @param string $dbname The wiki's database name.
     */
    private function expandTemplate(string $sqlTemplate, string $dbname): string
    {
        return str_replace(
            ['{db}', '{dbname}'],
            ["`{$dbname}_p`", "'{$dbname}'"],
            $sqlTemplate
        );
    }

    /**
     * Get whether a database name is safe to interpolate into SQL.
     *
     * @param string $dbname The database name to check.
     */
    private static function isValidDbName(string $dbname): bool
    {
        return (bool)preg_match('/^[a-z0-9_]+$/', $dbname);
    }
}

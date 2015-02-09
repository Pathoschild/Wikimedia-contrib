<?php
require_once( 'Wikimedia.php' );

/*
	The database class wraps PHP Data Objects (PDO) with optimization and convenience methods.

	It stores open database connections internally, to avoid opening new connections unnecessarily.
	It exposes Database->db, the PDO object for the currently active database, for direct use. It
	also wraps many of PDO's methods for error handling (eg, when Database::ERROR_PRINT set). When
	an error occurs, it ignores all further calls until the next Connect() or resetException() call.

	----

	The toolserver subclass adds optimizations for the Wikimedia Tools Labs. On construction, the
	class fetches wiki and database data from the Tools Labs DB. When connecting to a database name,
	it aliases it to its server host to minimize the number of connections to the number of servers.
*/

/**
 * Provides database operations with optimizations and connection caching.
 */
class Database {
	#################################################
	## Properties
	#################################################
	/* constants */
	const ERROR_THROW = 1;
	const ERROR_PRINT = 2;
	const ERROR_SUPPRESS = 4;

	const DRIVER = 'mysql';
	const ERROR_MODES = 7;

	/* default configuration */
	protected $error_mode = 2; // ERROR_THROW | ERROR_PRINT
	protected $config_file; // set in constructor

	/* default settings */
	protected $default_user;
	protected $default_password;

	/* connection arrays */
	protected $connections = Array(); // hash of host => PDO
	protected $exceptions = Array();  // hash of host => Exception

	/* current/previous connection's details */
	protected $host;
	protected $username;
	protected $password;
	protected $database;
	protected $borked = false; // boolean whether error occured

	protected $prev_host;
	protected $prev_username;
	protected $prev_password;
	protected $prev_database;

	/* last query performed, for convenience methods */
	protected $query = NULL;

	/* logger */
	protected $logger = NULL;
	protected $logger_key = '';

	/* public interface */
	public $db = NULL;

	/* internal */
	private $disposed = false;


	#################################################
	## Public DB methods
	#################################################
	#############################
	## Constructor
	#############################
	/**
	 * Initialize a database object.
	 *
	 * @param string $default_username The username to use when authenticating to the database, or NULL to retrieve it from the user configuration file.
	 * @param string $default_password The password to use when authenticating to the database, or NULL to retrieve it from the user configuration file.
	 * @param integer $options Additional mode options which can be bitwise ORed together (available options: ERROR_THROW, ERROR_PRINT, ERROR_SUPPRESS).
	 */
	public function __construct( $logger = NULL, $options = NULL, $default_username = NULL, $default_password = NULL ) {
		/* configuration */
		$this->config_file = '/data/project/meta/replica.my.cnf';
		$this->logger = $logger;
		if( $this->logger != null )
			$this->logger_key = $this->logger->key;

		/* get default login details */
		$this->default_username = $default_username;
		$this->default_password = $default_password;

		if( (!$default_username || !$default_password) && file_exists($this->config_file) ) {
			$config = parse_ini_file( $this->config_file );
			$this->default_username = ( $default_username ? $default_username : $config['user'] );
			$this->default_password = ( $default_password ? $default_password : $config['password'] );
		}

		/* get options */
		if( $options & self::ERROR_MODES )
			$this->error_mode = self::ERROR_MODES & $options;
	}


	#############################
	## Destructor
	#############################
	/**
	 * Close all database connections and release resources.
	 */
	public function Dispose() {
		if( $this->disposed )
			return;

		$keys = array_keys( $this->connections );
		foreach( $keys as $key )
			$this->connections[$key] = NULL;

		$this->disposed = true;
	}

	/**
	 * Close all database connections and release resources.
	 */
	public function __destruct() {
		$this->Dispose();
	}


	#############################
	## Connect
	#############################
	/**
	 * Open a database connection. This will reuse an existing server connection if it has been previously opened.
	 * @param $host The server address to connect to.
	 * @param $database The name of the database to connect to.
	 * @param $username The username to use when authenticating to the database, or NULL to authenticate with the default username.
	 * @param $password The password to use when authenticating to the database, or NULL to authenticate with the default password.
	 * @return bool Whether the connection was successfully established.
	 */
	public function Connect( $host, $database = NULL, $username = NULL, $password = NULL ) {
		/* normalize database name for Tools Labs */
		if(isset($database) && substr($database, -2) != '_p')
			$database .= '_p';

		/* change states */
		$this->borked = false;

		$this->prev_host     = $this->host;
		$this->prev_username = $this->username;
		$this->prev_password = $this->password;
		$this->prev_database = $this->database;

		$this->host = $host;
		$this->username = ( $username ? $username : $this->default_username );
		$this->password = ( $password ? $password : $this->default_password );
		$this->database = $database;

		/* create new connection, if can't recycle one */
		if( !isset($this->connections[$host]) || !$this->connections[$this->host] ) {
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
			}
			catch( PDOException $exc ) {
				return $this->handleException( $exc, 'Could not connect to database host "' . htmlentities($host) . '".' );
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
	public function ConnectPrevious() {
		if($this->prev_host)
			$this->Connect( $this->prev_host, $this->prev_database, $this->prev_username, $this->prev_password );
	}


	#############################
	## Query
	#############################
	/**
	 * Submit a parameterized SQL query to the database.
	 * @param $sql string The raw SQL command to submit.
	 * @values $values array The values to substituted for parameterized placeholders in the SQL command.
	 * @return Database The Database instance for method chaining (or false if the query failed).
	 */
	public function Query( $sql, $values = Array() ) {
		$sql .= ' /*' . $this->logger_key . '*/';

		if( $this->borked )
			return NULL;
		try {
			if($this->db == NULL)
				throw new Exception('Not connected to a database.');

			$this->query = NULL;
			$this->query = $this->db->prepare( $sql );
			if($values != null && !is_array($values))
				$values = Array($values);

			$this->log('query ' . $this->database . ': [' . $this->query->queryString . '], values=[' . preg_replace('/\s+/', ' ', print_r($values, true)) . ']');
			$this->query->execute( $values );
			$this->log('query done');

			return $this;
		}
		catch( Exception $exc ) {
			return $this->handleException( $exc, 'Could not perform query:<br /><small>' . $this->getQueryDebugData() . '</small>' );
		}
	}

	#############################
	## Read row from query result
	#############################
	/**
	 * Fetch the next row as a hash of field names and values, and advance the internal pointer.
	 * @return array The hash of field names and values.
	 */
	public function fetchAssoc() {
		if( $this->borked )
			return NULL;
		try {
			return $this->query->fetch( PDO::FETCH_ASSOC );
		}
		catch( PDOException $exc ) {
			return $this->handleException( $exc, 'Could not fetch result from query:<br /><small>' . $this->getQueryDebugData() . '</small>' );
		}
	}

	/**
	 * Fetch a single value from the next row, and advance the internal pointer.
	 * @param $columnNumber int The numeric index of the column to retrieve.
	 * @return string The value of the retrieved field.
	 */
	public function fetchColumn( $columnNumber = 0 ) {
		return $this->fetchValue( $columnNumber );
	}
	public function fetchValue( $columnNumber = 0 ) {
		if( $this->borked )
			return NULL;
		try {
			return $this->query->fetchColumn( $columnNumber );
		}
		catch( PDOException $exc ) {
			return $this->handleException( $exc, 'Could not fetch result from query:<br /><small>' . $this->getQueryDebugData() . '</small>' );
		}
	}
	
	/**
	 * Fetch all result rows as an array of field name & value hashes.
	 * @return array An array of field name & value hashes.
	 */
	public function fetchAllAssoc() {
		if( $this->borked )
			return NULL;
		try {
			return $this->query->fetchAll( PDO::FETCH_ASSOC );
		}
		catch( PDOException $exc ) {
			return $this->handleException( $exc, 'Could not fetch result from query:<br /><small>' . $this->getQueryDebugData() . '</small>' );
		}
	}

	
	/**
	 * Get the number of rows returned by the query.
	 * @return int The number of rows returned by the query.
	 */
	public function countRows() {
		if( !$this->query )
			return 0;
		return $this->query->rowCount();
	}


	#################################################
	## Internal methods
	#################################################
	#############################
	## Handle a PDO exception
	#############################
	protected function handleException( $exc, $error_message = NULL ) {
		$this->log('exception: error=[' . $exc->getMessage() . '], message=[' . $error_message . ']');

		$this->exceptions[$this->host] = $exc;
		$this->borked = true;

		if( $this->error_mode & self::ERROR_PRINT )
			$this->printException( $error_message );
		if( $this->error_mode & self::ERROR_THROW )
			throw $exc;
		return false;
	}


	#############################
	## Print exception details
	#############################
	protected function printException( $error_message = NULL ) {
		if( $error_message )
			echo '<div class="error">', $error_message, '<br /><small>Exception: ', htmlentities($this->exceptions[$this->host]->getMessage()), '</small></div>';
		else
			echo '<div class="error">Exception: ', htmlentities($this->exceptions[$this->host]->getMessage()), '</div>';
		return false;
	}


	#############################
	## Get query debug data
	#############################
	protected function getQueryDebugData() {
		if($this->query == NULL)
			return NULL;
		ob_start();
		$this->query->debugDumpParams();
		return ob_get_clean();
	}


	#############################
	## Log a trace message
	#############################
	protected function log( $message ) {
		if( $this->logger != null )
			$this->logger->log( 'Database> ' . $message );
	}
}

class Toolserver extends Database {
	#################################################
	## Properties
	#################################################
	protected $dbn_hosts = array();   // hash of dbname => host
	protected $wikimedia = NULL;

	#############################
	## Constructor
	#############################
	public function __construct( $logger = NULL, $cache = NULL, $options = NULL, $default_username = NULL, $default_password = NULL ) {
		parent::__construct( $logger, $options, $default_username, $default_password );

		/* fetch toolserver data */
		$this->wikis = new Wikimedia($this, $cache);

		/* select random DB slice (every slice has every DB, but picking a random one reduces our dependence on any given one) */
		$slices = Array();
		foreach($this->wikis->GetWikis() as $wiki)
			$slices[$wiki->host] = True;
		$slices = array_keys($slices);
		$slice = $slices[array_rand($slices)];

		/* set DB host lookup */
		foreach($this->wikis->GetWikis() as $wiki)
			$this->dbn_hosts[$wiki->dbName] = $slice;//$wiki->host;
	}

	#############################
	## Connect to host or DBN
	#############################
	public function Connect( $host, $database = NULL, $username = NULL, $password = NULL ) {
		/* alias host */
		if( isset($this->dbn_hosts[$host]) && $this->dbn_hosts[$host] ) {
			$database = $host;
			$host     = $this->dbn_hosts[$host];
		}

		parent::Connect( $host, $database, $username, $password );
	}

	#############################
	## Normalize db_name
	#############################
	public function normalizeDbn( $dbn ) {
		$dbn = str_replace( '-', '_', $dbn );
		if( substr($dbn, -2) == '_p' )
			$dbn = substr($dbn, 0, -2);
		return $dbn;
	}

	#################################################
	## Public getters
	#################################################
	#############################
	## Get dbn => Wiki() hash
	#############################
	public function getWikis() {
		return $this->wikis->GetWikis();
	}

	#############################
	## Get DBN => DB host hash
	#############################
	public function getDbnHosts() {
		return $this->dbn_hosts;
	}


	#############################
	## Get DBN => domain hash
	#############################
	public function getDomains() {
		return $this->wikis->GetDomainList();
	}


	############################
	## Get host for a DBN
	############################
	public function getDomain($dbname) {
		return $this->wikis->GetDomain($dbname);
	}
	
	
	############################
	## Get host for a DBN
	############################
	public function getHost( $dbname ) {
		return $this->wikis->GetHost($dbname);
	}
	
	
	############################
	## Get boolean indicating wiki locked?
	############################
	public function getLocked( $dbname ) {
		if($wiki = $this->wikis->GetWiki($dbname))
			return $wiki->isClosed;
		return NULL;
	}


	#################################################
	## Public query methods
	#################################################
	#############################
	## Get a global account's home wiki
	#############################
	public function getHomeWiki( $user ) {
		try {
			$this->Connect( 'metawiki' );

			$query = $this->db->prepare( 'SELECT lu_wiki FROM centralauth_p.localuser WHERE lu_name=? AND lu_attached_method IN ("primary", "new") LIMIT 1' );
			$query->execute(Array( $user ));

			$this->ConnectPrevious();

			$wiki = $query->fetchColumn();
			if( $wiki )
				return $wiki;
			return NULL;
		}
		catch( PDOException $exc ) {
			return $this->handleException( $exc, 'Could not retrieve home wiki for user "' . htmlentities($user) . '".' );
		}
	}


	#############################
	## Get a global account's unified wikis
	#############################
	public function getUnifiedWikis( $user ) {
		try {
			$this->Connect( 'metawiki' );

			$query = $this->db->prepare( 'SELECT lu_wiki FROM centralauth_p.localuser WHERE lu_name=?' );
			$query->execute(Array( $user ));

			$this->ConnectPrevious();

			$wikis = Array();
			foreach( $query as $row )
				$wikis[] = $row['lu_wiki'];

			return $wikis;
		}
		catch( PDOException $exc ) {
			return $this->handleException( $exc, 'Could not retrieve unified wikis for user "' . htmlentities($user) . '".' );
		}
	}


	#############################
	## Get a local account's details (id, registration date, edit count)
	#############################
	public function getUserDetails( $wiki, $user_name, $date_format = '%Y-%b-%d %H:%i' ) {
		try {
			/* fetch availabe user details */
			$query = $this->db->prepare( 'SELECT user_id AS id, user_registration AS registration_raw, DATE_FORMAT(user_registration, "' . $date_format . '") as registration, user_editcount AS editcount FROM user WHERE user_name = ? LIMIT 1' );
			$query->execute(array($user_name));
			$user = $query->fetch( PDO::FETCH_ASSOC );
			
			/* if needed, use more complex date algorithm */
			if( $user['id'] && !$user['registration_raw'] ) {
				$date = $this->getRegistrationDate( $user['id'], $date_format, true );
				$user['registration_raw'] = $date['raw'];
				$user['registration']     = $date['formatted'];
			}
			
			/* done! */
			return $user;
		}
		catch( PDOException $exc ) {
			return $this->handleException( $exc, 'Could not retrieve local account details for user "' . htmlentities($user_name) . '" at wiki "' . htmlentities($wiki) . '".' );
		}
	}


	#############################
	## Get a local account's registration date as a (raw,formatted) array
	#############################
	public function getRegistrationDate( $user_id, $format = '%Y-%m-%d %H:%i', $skip_user_table = false ) {
		if( $this->borked )
			return NULL;
		try {
			/* try date field in user table */
			if( !$skip_user_table ) {
				$query = $this->db->prepare( 'SELECT user_registration AS raw, DATE_FORMAT(user_registration, "' . $format . '") AS formatted from user WHERE user_id=? LIMIT 1' );
				$query->execute(Array( $user_id ));
				$date = $query->fetch( PDO::FETCH_ASSOC );
				if( isset($date['raw']) )
					return $date;
			}
			
			/* try extracting from logs */
			$query = NULL;
			$query = $this->db->prepare( 'SELECT log_timestamp AS raw, DATE_FORMAT(log_timestamp, "' . $format . '") AS formatted FROM logging WHERE log_user = ? AND log_type = "newusers" AND log_title = "Userlogin" LIMIT 1' );
			$query->execute(array( $user_id ));
			$date = $query->fetch( PDO::FETCH_ASSOC );
			if( isset($date['raw']) )
				return $date;
			
			/* failed */
			return Array( 'raw' => NULL, 'formatted' => 'in 2005 or earlier' );
		}
		catch( PDOException $exc ) {
			return $this->handleException( $exc, 'Could not retrieve registration date for user id "' . htmlentities($user_id) . '".' );
		}
	}
}

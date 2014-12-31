<?php
/**
 * Represents a Wikimedia wiki and database.
 */
class Wiki {
	/*########
	## Properties
	########*/
	/**
	 * The simplified database name (dbname), like 'enwiki'.
	 * @var string
	 */
	public $name = NULL;

	/**
	 * The database name (dbname), like 'enwiki'.
	 * @var string
	 */
	public $dbName = NULL;

	/**
	 * The ISO 639 language code associated with the wiki. (A few wikis have invalid codes like 'zh-classical' or 'noboard-chapters'.)
	 * @var string
	 */
	public $lang = NULL;

	/**
	 * The wiki family (project name), like 'wikibooks'.
	 * @var string
	 */
	public $family = NULL;

	/**
	 * The domain portion of the URL, like 'en.wikisource.org'. This may be NULL for closed wikis.
	 * @var string
	 */
	public $domain = NULL;

	/**
	 * The number of articles on the wiki (?).
	 * @var int
	 */
	public $size = NULL;

	/**
	 * Whether the wiki is locked and no longer editable by the public.
	 * @var bool
	 */
	public $isClosed = NULL;

	/**
	 * The name of the server on which the wiki's replicated database is located.
	 * @var int
	 */
	public $serverName = NULL;

	/**
	 * The host name of the server on which the wiki's replicated database is located.
	 * @var bool
	 */
	public $host = NULL;

	/**
	 * Whether the wiki contains content in multiple languages.
	 * @var bool
	 */
	public $isMultilingual = NULL;


	/*########
	## Methods
	########*/
	/**
	 * Construct a Wiki instance.
	 */
	public function __construct($name, $lang, $family, $domain, $size, $isClosed, $serverName) {
		$this->dbName = $name;
		$this->name = $name;
		$this->lang = $lang;
		switch($name) {
			case 'commonswiki': $this->family = 'commons'; break;
			case 'incubatorwiki': $this->family = 'incubator'; break;
			case 'mediawikiwiki': $this->family = 'mediawiki'; break;
			case 'metawiki': $this->family = 'meta'; break;
			case 'specieswiki': $this->family = 'wikispecies'; break;
			case 'wikidatawiki': $this->family = 'wikidata'; break;
			default: $this->family = $family; break;
		}
		$this->domain = $domain;
		$this->size = $size;
		$this->isClosed = $isClosed;
		$this->serverName = $serverName;
		$this->host = $serverName;
		$this->isMultilingual = in_array($name, array('commonswiki', 'incubatorwiki', 'mediawikiwiki', 'metawiki', 'specieswiki', 'wikidatawiki'));
	}
}

/**
 * Manages data about Wikimedia wikis and database connections.
 */
class Wikimedia {
	/*########
	## Properties
	########*/
	/**
	 * The underlying Wikimedia wiki and database data.
	 */
	protected $wikis = NULL;


	/*########
	## Properties
	########*/
	/**
	 * Construct a Wikimedia instance.
	 * @param Database $db The database with which to connect to the database.
	 * @param Cacher $cache The cache with which to read and write cached data.
	 */
	public function __construct($db, $cache) {
		$this->wikis = $cache->Get('wikimedia-wikis');
		if(!$this->wikis) {
			// build wiki list
			$this->wikis = array();
			$db->Connect('metawiki.labsdb', 'metawiki_p');
			foreach($db->Query('SELECT dbname, lang, family, REPLACE(url, "http://", "") AS domain, size, is_closed, slice FROM meta_p.wiki WHERE url IS NOT NULL')->fetchAllAssoc() as $row) {
				if($row['dbname'] == 'votewiki')
					continue; // DB schema is broken
				$this->wikis[$row['dbname']] = new Wiki($row['dbname'], $row['lang'], $row['family'], $row['domain'], $row['size'], $row['is_closed'], $row['slice']);
			}

			// cache result
			if( count($this->wikis) ) // if the fetch failed, we *don't* want to cache the result for a full day
				$cache->Save('wikimedia-wikis', $this->wikis);
			$db->ConnectPrevious();
		}
	}
	
	/**
	 * Get the list of wikis.
	 */
	public function GetWikis() {
		return $this->wikis;
	}
	
	/**
	 * Get the data for a wiki.
	 * @param string $dbname The wiki's unique database name.
	 */
	public function GetWiki($dbname) {
		if(array_key_exists($dbname, $this->wikis))
			return $this->wikis[$dbname];
		return NULL;
	}
	
	/**
	 * Get the domain portion of a wiki's URL.
	 * @param string $dbname The wiki's unique database name.
	 */
	public function GetDomain($dbname) {
		if($wiki = $this->GetWiki($dbname))
			return $wiki->domain;
		return NULL;
	}

	/**
	 * Get the host of the wiki's database server.
	 * @param string $dbname The wiki's unique database name.
	 */
	public function GetHost($dbname) {
		if($wiki = $this->GetWiki($dbname))
			return $wiki->host;
		return NULL;
	}
	
	/**
	 * Get a dbname => domain array of wikis.
	 */
	public function GetDomainList($includeClosed = false) {
		$wikis = array();
		foreach($this->wikis as $wiki) {
			if($includeClosed || !$wiki->isClosed)
				$wikis[$wiki->dbName] = $wiki->domain;
		}
		asort($wikis);
		return $wikis;
	}
}
?>

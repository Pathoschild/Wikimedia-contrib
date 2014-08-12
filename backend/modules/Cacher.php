<?php

/**
 * Represents a cached item with expiry.
 */
class CacheItem {
	/*########
	## Properties
	########*/
	/**
	 * The date on which the item was cached.
	 */
	public $date = NULL;
	
	/**
	 * The expiry date.
	 */
	public $expiry = NULL;
	
	/**
	 * The cached data.
	 */
	public $data = NULL;
	
	/*########
	## Public methods
	########*/
	/**
	 * Construct a new cache item.
	 * @param object $data The data to cache.
	 * @param DateInterval $interval The amount of time for which to cache the item (or null for one day).
	 */
	public function __construct($data, $interval = NULL) {
		// set data
		$this->data = $data;
		
		// set timestamp
		$this->date = new DateTime('now', new DateTimeZone('UTC'));
		
		// set expiry
		if($interval == NULL)
			$interval = new DateInterval('P1D');
		$this->expiry = new DateTime('now', new DateTimeZone('UTC'));
		$this->expiry->add($interval);
	}
	
	/**
	 * Get whether the cache item should be purged.
	 */
	public function IsPurged($minDate = NULL) {
		return $minDate && $this->date <= $minDate;
	}
	
	/**
	 * Get whether the cache item has expired (or been purged).
	 */
	public function IsExpired($minDate = NULL) {
		return $this->IsPurged($minDate) || $this->expiry <= new DateTime('now', new DateTimeZone('UTC'));
	}
	
	/**
	 * Get the cache date as a human-readable string.
	 */
	public function GetFormattedDate() {
		return $this->date->format('Y-m-d H:i:s \(\U\T\C\)');
	}
	
	/**
	 * Get the expiry date as a human-readable string.
	 */
	public function GetFormattedExpiry() {
		return $this->expiry->format('Y-m-d H:i:s \(\U\T\C\)');
	}
}

/**
 * Handles reading and writing data to a directory with expiry dates.
 */
class Cacher {
	/*########
	## Properties
	########*/
	/**
	 * The full path to the directory to read and write to.
	 */
	protected $path = NULL;
	
	/**
	 * Ignore all data cached before this date.
	 */
	protected $purgeDate = NULL;
	
	/**
	 * Writes tracing messages to the log file.
	 */
	protected $logger = NULL;


	/*########
	## Methods
	########*/
	/**
	 * Construct a new cacher instance.
	 * @param string $path The path to the cache directory.
	 */
	public function __construct($path, $logger, $purge = null) {
		$path = pathinfo($path);
		$this->path = $path['dirname'] . '/' . $path['basename'] . '/';
		if($purge)
			$this->purgeDate = new DateTime('now', new DateTimeZone('UTC'));
		$this->logger = $logger;
	}

	/**
	 * Get cached data saved with a key.
	 * @param string $key The unique key identifying the cache item.
	 */
	public function GetWithMetadata($key) {
		// parse path
		$path = $this->GetPath($key);
	
		// fetch data & handle expiry
		if(!file_exists($path)) {
			$this->logger->log('cache miss (file does not exist): ' . $path);
			return NULL;
		}
		$data = file_get_contents($path);
		$data = unserialize($data);
		if(!$data) {
			$this->logger->log('cache miss (file could not be deserialized): ' . $path);
			return NULL;
		}
		if($data->IsExpired($this->purgeDate)) {
			if($data->IsPurged($this->purgeDate))
				$this->logger->log('cache miss (data purged): ' . $path);
			else
				$this->logger->log('cache miss (data expired): ' . $path);
			unlink($path);
			return NULL;
		}
		
		$this->logger->log('cache hit (cached=' . $data->GetFormattedDate() . ', expires=' . $data->GetFormattedExpiry() . '): ' . $path);
		return $data;
	}

	
	/**
	 * Get cached data saved with a key.
	 * @param string $key The unique key identifying the cache item.
	 */
	public function Get($key) {
		$cached = $this->GetWithMetadata($key);
		if($cached == NULL)
			return NULL;
		return $cached->data;
	}
	
	/**
	 * Save a cached value.
	 * @param string $key The unique key identifying the cached data.
	 * @param object $data The data to save.
	 * @param DateInterval The amount of time for which to cache the data (or NULL for one day).
	 */
	 public function Save($key, $data, $interval = NULL) {
		$path = $this->GetPath($key);
		$item = new CacheItem($data, $interval);
		$item = serialize($item);
		if($item == NULL) {
			$this->logger->log('cache failed (data could not be serialized): ' . $path);
			return;
		}
		
		$bytes = file_put_contents($path, $item);
		if($bytes === false)
			$this->logger->log('cache failed (file write failed): ' . $path);
		else
			$this->logger->log('cache updated (' . $bytes . ' bytes): ' . $path);
	}
	
	protected function GetPath($key) {
		return $this->path . urlencode($key) . '.dat';
	}
}
?>

<?php

/**
 * Represents a cached item with expiry.
 */
class CacheItem
{
    ##########
    ## Accessors
    ##########
    /**
     * The date on which the item was cached.
     * @var DateTime
     */
    public $date = null;

    /**
     * The expiry date.
     * @var DateTime
     */
    public $expiry = null;

    /**
     * The cached data.
     * @var mixed
     */
    public $data = null;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param mixed $data The data to cache.
     * @param DateInterval $interval The amount of time for which to cache the item (or null for one day).
     */
    public function __construct($data, $interval = null)
    {
        // set data
        $this->data = $data;

        // set timestamp
        $this->date = new DateTime('now', new DateTimeZone('UTC'));

        // set expiry
        if ($interval == null)
            $interval = new DateInterval('P1D');
        $this->expiry = new DateTime('now', new DateTimeZone('UTC'));
        $this->expiry->add($interval);
    }

    /**
     * Get whether the cache item should be purged.
     * @param DateTime $minDate The oldest cache date to keep.
     * @return bool
     */
    public function isPurged($minDate = null)
    {
        return $minDate && $this->date <= $minDate;
    }

    /**
     * Get whether the cache item has expired (or been purged).
     * @param DateTime $minDate The oldest cache date to keep.
     * @return bool
     */
    public function isExpired($minDate = null)
    {
        return $this->isPurged($minDate) || $this->expiry <= new DateTime('now', new DateTimeZone('UTC'));
    }

    /**
     * Get the cache date as a human-readable string.
     * @return string
     */
    public function getFormattedDate()
    {
        return $this->date->format('Y-m-d H:i:s \(\U\T\C\)');
    }

    /**
     * Get the expiry date as a human-readable string.
     * @return string
     */
    public function getFormattedExpiry()
    {
        return $this->expiry->format('Y-m-d H:i:s \(\U\T\C\)');
    }
}

/**
 * Reads and writes data to a cache with expiry dates.
 */
class Cacher
{
    ##########
    ## Properties
    ##########
    /**
     * The full path to the directory to read and write to.
     * @var string
     */
    private $path = null;

    /**
     * The oldest cache date to purge.
     * @var DateTime
     */
    private $purgeDate = null;

    /**
     * Writes messages to a log file for troubleshooting.
     * @var Logger
     */
    private $logger = null;


    ##########
    ## Public methods
    ##########
    /**
     * Construct a new cacher instance.
     * @param string $path The path to the cache directory.
     * @param Logger $logger Writes messages to a log file for troubleshooting.
     * @param bool $purge Whether to purge expired messages.
     */
    public function __construct($path, $logger, $purge = null)
    {
        $path = pathinfo($path);
        $this->path = $path['dirname'] . '/' . $path['basename'] . '/';
        if ($purge)
            $this->purgeDate = new DateTime('now', new DateTimeZone('UTC'));
        $this->logger = $logger;
    }

    /**
     * Get cached data saved with a key.
     * @param string $key The unique key identifying the cache item.
     * @return mixed
     */
    public function getWithMetadata($key)
    {
        // parse path
        $path = $this->getPath($key);

        // fetch data & handle expiry
        if (!file_exists($path)) {
            $this->logger->log('cache miss (file does not exist): ' . $path);
            return null;
        }
        $data = file_get_contents($path);
        $data = unserialize($data);
        if (!$data) {
            $this->logger->log('cache miss (file could not be deserialized): ' . $path);
            return null;
        }
        if ($data->IsExpired($this->purgeDate)) {
            if ($data->IsPurged($this->purgeDate))
                $this->logger->log('cache miss (data purged): ' . $path);
            else
                $this->logger->log('cache miss (data expired): ' . $path);
            unlink($path);
            return null;
        }

        $this->logger->log('cache hit (cached=' . $data->GetFormattedDate() . ', expires=' . $data->GetFormattedExpiry() . '): ' . $path);
        return $data;
    }


    /**
     * Get cached data saved with a key.
     * @param string $key The unique key identifying the cache item.
     * @return mixed
     */
    public function get($key)
    {
        $cached = $this->getWithMetadata($key);
        if ($cached == null)
            return null;
        return $cached->data;
    }

    /**
     * Save a cached value.
     * @param string $key The unique key identifying the cached data.
     * @param object $data The data to save.
     * @param DateInterval $interval The amount of time for which to cache the data (or null for one day).
     */
    public function save($key, $data, $interval = null)
    {
        $path = $this->getPath($key);
        $item = new CacheItem($data, $interval);
        $item = serialize($item);
        if ($item == null) {
            $this->logger->log('cache failed (data could not be serialized): ' . $path);
            return;
        }

        $bytes = file_put_contents($path, $item);
        if ($bytes === false)
            $this->logger->log('cache failed (file write failed): ' . $path);
        else
            $this->logger->log('cache updated (' . $bytes . ' bytes): ' . $path);
    }


    ##########
    ## Private methods
    ##########
    /**
     * Get the cache file path.
     * @param string $key The cache key.
     * @return string
     */
    private function getPath($key)
    {
        return $this->path . urlencode($key) . '.dat';
    }
}

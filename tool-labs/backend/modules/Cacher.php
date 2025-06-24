<?php
declare(strict_types=1);

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
    public DateTime $date;

    /**
     * The expiry date.
     * @var DateTime
     */
    public DateTime $expiry;

    /**
     * The cached data.
     * @var mixed
     */
    public mixed $data;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param mixed $data The data to cache.
     * @param DateInterval|null $interval The amount of time for which to cache the item (or null for one day).
     */
    public function __construct(mixed $data, ?DateInterval $interval = null)
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
     */
    public function isPurged(?DateTime $minDate = null): bool
    {
        return $minDate && $this->date <= $minDate;
    }

    /**
     * Get whether the cache item has expired (or been purged).
     * @param DateTime $minDate The oldest cache date to keep.
     */
    public function isExpired(?DateTime $minDate = null): bool
    {
        return $this->isPurged($minDate) || $this->expiry <= new DateTime('now', new DateTimeZone('UTC'));
    }

    /**
     * Get the cache date as a human-readable string.
     */
    public function getFormattedDate(): string
    {
        return $this->date->format('Y-m-d H:i:s \(\U\T\C\)');
    }

    /**
     * Get the expiry date as a human-readable string.
     */
    public function getFormattedExpiry(): string
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
     */
    private string $path;

    /**
     * The oldest cache date to purge.
     */
    private ?DateTime $purgeDate = null;

    /**
     * Writes messages to a log file for troubleshooting.
     */
    private Logger $logger;


    ##########
    ## Public methods
    ##########
    /**
     * Construct a new cacher instance.
     * @param string $path The path to the cache directory.
     * @param Logger $logger Writes messages to a log file for troubleshooting.
     * @param bool $purge Whether to purge expired messages.
     */
    public function __construct(string $path, Logger $logger, ?bool $purge = null)
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
     */
    public function getWithMetadata(string $key): mixed
    {
        // parse path
        $path = $this->getPath($key);

        // fetch data & handle expiry
        if (!file_exists($path)) {
            $this->logger->log('cache miss (file does not exist): ' . $path);
            return null;
        }
        $data = file_get_contents($path);
        if ($data)
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
    public function get(string $key): mixed
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
     * @param DateInterval|null $interval The amount of time for which to cache the data (or null for one day).
     */
    public function save(string $key, mixed $data, ?DateInterval $interval = null): void
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
    private function getPath(string $key): string
    {
        return $this->path . urlencode($key) . '.dat';
    }
}

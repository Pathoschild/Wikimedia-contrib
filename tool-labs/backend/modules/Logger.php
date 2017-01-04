<?php
require_once('external/KLogger.php');

/**
 * Writes messages to a log file for troubleshooting.
 */
class Logger
{
    ##########
    ## Properties
    ##########
    /**
     * A unique session key used to group related log entries.
     * @var string
     */
    public $key = null;

    /**
     * The underlying logging library.
     * @var KLogger|null
     */
    private $logger = null;

    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $path The directory path to which logs should be written.
     * @param string $key A unique session key used to group related log entries.
     * @param bool $enabled Whether to enable logging.
     */
    public function __construct($path, $key, $enabled)
    {
        $this->key = $key;
        $this->logger = $enabled
            ? new KLogger($path, KLogger::DEBUG)
            : null;
    }

    /**
     * Write a message to the log.
     * @param string $message The message to log.
     */
    public function log($message)
    {
        if ($this->logger != null) {
            $message = "[{$this->key}] $message";
            $this->logger->logInfo($message);
        }
    }
}

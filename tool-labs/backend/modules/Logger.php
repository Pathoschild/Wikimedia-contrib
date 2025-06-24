<?php
declare(strict_types=1);

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
     */
    public string $key;

    /**
     * The underlying logging library.
     */
    private ?KLogger $logger = null;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $path The directory path to which logs should be written.
     * @param string $key A unique session key used to group related log entries.
     * @param bool $enabled Whether to enable logging.
     */
    public function __construct(string $path, string $key, bool $enabled)
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
    public function log(string $message): void
    {
        if ($this->logger != null) {
            $message = "[{$this->key}] $message";
            $this->logger->logInfo($message);
        }
    }
}

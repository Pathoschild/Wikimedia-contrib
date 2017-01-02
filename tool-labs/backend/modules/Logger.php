<?php
require_once( 'KLogger.php' );

/**
 * Writes messages to a dated log file.
 */
class Logger {
    ##########
    ## Properties
    ##########
    /**
     * A unique session key used to group related log entries.
     * @var string
     */
    public $key = NULL;

    /**
     * The underlying logging library.
     * @var KLogger|null
     */
    private $logger = NULL;

    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param $path string The directory path to which logs should be written.
     * @param $key string A unique session key used to group related log entries.
     */
    public function __construct($path, $key) {
        $this->key = $key;
        #$this->logger = new KLogger($path, KLogger::DEBUG);
    }

    public function log($message) {
        #$message = '[' . $this->key . '] ' . $message;
        #$this->logger->logInfo($message);
    }
}
?>

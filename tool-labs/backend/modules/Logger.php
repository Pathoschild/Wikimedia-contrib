<?php
declare(strict_types=1);

/**
 * Writes error messages to the tool's error log.
 */
class Logger
{
    ##########
    ## Properties
    ##########
    /**
     * A unique session key used to group related log entries, and match SQL requests to their
     * session.
     */
    public string $key;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $key A unique session key used to group related log entries.
     */
    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * Write an error to the tool's `error.log` file.
     * @param string $message The message to log.
     */
    public function error(string $message): void
    {
        $message = preg_replace('/\s+/', ' ', "[{$this->key}] $message");
        error_log($message);
    }
}

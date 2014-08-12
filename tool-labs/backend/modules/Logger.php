<?php
require_once( 'KLogger.php' );

class Logger {
	public $key = NULL;
	protected $logger = NULL;
	
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

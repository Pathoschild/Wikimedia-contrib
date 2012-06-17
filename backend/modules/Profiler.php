<?php
/**
 * Provides basic performance profiling.
 */
class Profiler {
	#################################################
	## Properties
	#################################################
	/**
	 * The benchmarking time results, each being an array of millisecond times in the form (startTime, endTime).
	 */
	private $times = Array();
	
	/**
	 * The millisecond time at which the Base class was first constructed.
	 */
	private $timeStart = NULL;
	

	#################################################
	## Methods
	#################################################
	/**
	 * Initialize the base class.
	 */
	public function __construct() {
		$this->timeStart = $this->getCurrentTime();
	}

		/**
	* Start a benchmarking timer.
	* @param string $key The unique name to associate with the timer.
	*/
	public function start( $key ) {
		$this->times[$key] = Array( $this->getCurrentTime(), NULL );
	}

	/**
	* Stop a benchmarking timer.
	* @param string $key The unique name of the timer.
	*/
	public function stop( $key ) {
		if (!isset($this->times[$key]))
			throw new InvalidArgumentException('There is no timer named "' . $key . '".');
		if (isset($this->times[$key][1]))
			throw new Exception('Cannot stop timer "' . $key . '", it is already stopped.');
		$this->times[$key][1] = $this->getCurrentTime();
	}

	/**
	* Get the total time elapsed since the script started running.
	* @return integer The total time elapsed in milliseconds.
	*/
	public function getElapsedSinceStart()
	{
		if(!$this->timeStart)
			throw new Exception("Script start time was not initialized.");
		return $this->getCurrentTime() - $this->timeStart;
	}

	/**
	* Get a benchmarking timer's elapsed time in decimal seconds.
	* @param string $key The unique name of the timer.
	* @return integer The total time in milliseconds that elapsed between starting and stopping the named timer.
	*/
	public function getElapsed( $key ) {
		if (!isset($this->times[$key]))
			throw new InvalidArgumentException('There is no timer named "' . $key . '".');
		return $this->times[$key][1] - $this->times[$key][0];
	}

	/**
	* Delete a benchmarking timer.
	* @param string $key The unique name of the timer.
	*/
	public function delete( $key ) {
		unset($this->times[$key]);
	}
	
	/**
	* Get all benchmarking timer keys.
	* @return array An array of available benchmarking keys.
	*/
	public function getKeys() {
		return array_keys($this->times);
	}

	/**
	* Get the current microtime in milliseconds.
	* @return integer The current microtime in milliseconds.
	*/
	private function getCurrentTime() {
		$time = explode( ' ', microtime() );
		return $time[0] + $time[1];
	}
}

<?php
/**
 * Base class for script and framework classes that provides generic functionality for
 * tracing, profiling, argument handling, and data sanitizing and validation.
 */
abstract class Base {
	#################################################
	## Properties
	#################################################
	/**
	 * The benchmarking time results, each being an array of millisecond times in the form (startTime, endTime).
	 */
	private $_times = Array();
	
	/**
	 * The millisecond time at which the Base class was first constructed.
	 */
	private $_timeStart = NULL;
	
	#################################################
	## Constructor, profiling & tracing
	#################################################
	/**
	 * Initialize the base class.
	 */
	public function __construct() {
		$this->_timeStart = $this->TimerGetCurrentTime();
	}

	
	#################################################
	## Argument handling
	#################################################
	/**
	 * Enforces a schema defining valid arguments and default values on a key=>value array.
	 * Argument keys not found in the schema will throw an exception. Missing keys will be added
	 * using the default values specified in the schema.
	 * @param array $arguments An associative array to apply the schema to.
	 * @param array $schema An associative array whose keys are the allowed keys in $arguments,
	 *              and whose values are the default values to apply to missing keys.
	 * @return array The modified argument array.
	 * @throws UnexpectedValueException The argument array contains keys not found in the schema.
	 */
	public function ApplyArgumentSchema( $arguments, $schema ) {
		/* no arguments */
		if(!isset($arguments) || count($arguments) == 0)
		{
			$arguments = $schema;
			return $arguments;
		}
		
		/* filter invalid keys */
		foreach($arguments as $key => $value)
		{
			if(!array_key_exists($key, $schema))
				throw new UnexpectedValueException('The argument array contains keys not found in the schema. Found key ' + $key + ' in [' . join(', ', array_keys($arguments)) . '], expected one of [' + join(',', array_keys($schema)) + '].');
		}
		
		/* apply schema */
		foreach($schema as $key => $value)
		{
			if(!array_key_exists($key, $arguments))
				$arguments[$key] = $value;
		}
		
		return $arguments;
	}


	#################################################
	## Profiling & debugging
	#################################################
	/**
	* Start a benchmarking timer.
	* @param string $key The unique name to associate with the timer.
	*/
	public function TimerStart( $key ) {
		$this->_times[$key] = Array( $this->TimerGetCurrentTime(), NULL );
	}

	/**
	* Stop a benchmarking timer.
	* @param string $key The unique name of the timer.
	*/
	public function TimerStop( $key ) {
		if (!isset($this->_times[$key]))
			throw new InvalidArgumentException('There is no timer named "' . $key . '".');
		if (isset($this->_times[$key][1]))
			throw new Exception('Cannot stop timer "' . $key . '", it is already stopped.');
		$this->_times[$key][1] = $this->TimerGetCurrentTime();
	}

	/**
	* Get the total time elapsed since the script started running.
	* @return integer The total time elapsed in milliseconds.
	*/
	public function TimerGetElapsedSinceStart()
	{
		if(!$this->_timeStart)
			throw new Exception("Script start time was not initialized.");
		return $this->TimerGetCurrentTime() - $this->_timeStart;
	}

	/**
	* Get a benchmarking timer's elapsed time in decimal seconds.
	* @param string $key The unique name of the timer.
	* @return integer The total time in milliseconds that elapsed between starting and stopping the named timer.
	*/
	public function TimerGetElapsed( $key ) {
		if (!isset($this->_times[$key]))
			throw new InvalidArgumentException('There is no timer named "' . $key . '".');
		return $this->_times[$key][1] - $this->_times[$key][0];
	}

	/**
	* Delete a benchmarking timer.
	* @param string $key The unique name of the timer.
	*/
	public function TimerDelete( $key ) {
		unset($this->_times[$key]);
	}
	
	/**
	* Get all benchmarking timer keys.
	* @return array An array of available benchmarking keys.
	*/
	public function TimerGetKeys() {
		return array_keys($this->_times);
	}

	/**
	* Get the current microtime in milliseconds.
	* @return integer The current microtime in milliseconds.
	*/
	private function TimerGetCurrentTime() {
		$time = explode( ' ', microtime() );
		return $time[0] + $time[1];
	}
	

	#################################################
	## String manipulation
	#################################################
	#############################
	## Format string for form output
	#############################
	/**
	* Format a string for output in a form field.
	* @param string $str The string to format.
	* @return string The formatted string.
	*/
	public function FormatFormValue( $str ) {
		return htmlentities($str, ENT_QUOTES, 'UTF-8');
	}
	
	/**
	* Make the first character in the string uppercase. This is a workaround for Unicode handling: PHP's ucfirst is not multi-byte safe.
	* @param string $str The string to format.
	* @return string The formatted string, with uppercase first letter.
	**/
	public function FormatUppercaseFirst( $str ) {
		return mb_strtoupper(mb_substr($str, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($str, 1, mb_strlen($str, 'UTF-8') - 1, 'UTF-8');	
	}
	
	/**
	 * Format a string as a wiki username.
	 * @param string $str The string to format.
	 * @return string The formatted string: trimmed, with an uppercase first letter, and with underscores replaced with spaces.
	 */
	public function FormatUsername( $str ) {
		/* normalize whitespace */
		$str = str_replace('_', ' ', trim($str));

		/* make uppercase */
		return $this->FormatUppercaseFirst($str);
	}
	
	/**
	* Format a string as an HTML anchor value.
	* @param string $str The string to format.
	* @return string The formatted string.
	*/
	public function FormatAnchor( $str ) {
		return $this->strip_nonlatin($str);
	}
	public function strip_nonlatin( $str ) {
		return strtolower(str_replace('%', '_', urlencode($str)));
	}
}

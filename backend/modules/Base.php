<?php
require_once(__DIR__.'/Profiler.php');

/**
 * Base class for script and framework classes that provides generic functionality for
 * tracing, profiling, argument handling, and data sanitizing and validation.
 */
abstract class Base {
	#################################################
	## Properties
	#################################################
	/**
	 * Provides basic performance profiling.
	 */
	public $profiler;

	
	#################################################
	## Constructor, profiling & tracing
	#################################################
	/**
	 * Initialize the base class.
	 */
	public function __construct() {
		$this->profiler = new Profiler();
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

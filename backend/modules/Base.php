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
	## String manipulation
	#################################################
	#############################
	## Format string for form output
	#############################
	/**
	 * Format a string as an attribute value.
	 * @param string $str The string to format.
	 * @return string The formatted string.
	 */
	public function formatValue( $str ) {
		return htmlentities($str, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Format a string as a plaintext HTML output.
	 * @param string $str The string to format.
	 * @return string The formatted string.
	 */
	public function formatText( $str ) {
		return htmlentities($str, ENT_NOQUOTES, 'UTF-8');
	}

	/**
	 * Make the first character in the string uppercase. This is a workaround for Unicode handling: PHP's ucfirst is not multi-byte safe.
	 * @param string $str The string to format.
	 * @return string The formatted string, with uppercase first letter.
	 **/
	public function formatInitialCapital( $str ) {
		return mb_strtoupper(mb_substr($str, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($str, 1, mb_strlen($str, 'UTF-8') - 1, 'UTF-8');	
	}
	
	/**
	 * Format a string as a wiki username.
	 * @param string $str The string to format.
	 * @return string The formatted string: trimmed, with an uppercase first letter, and with underscores replaced with spaces.
	 */
	public function formatUsername( $str ) {
		/* normalize whitespace */
		$str = str_replace('_', ' ', trim($str));

		/* make uppercase */
		return $this->formatInitialCapital($str);
	}
	
	/**
	 * Format a string as an HTML anchor value.
	 * @param string $str The string to format.
	 * @return string The formatted string.
	 */
	public function formatAnchor( $str ) {
		return strtolower(str_replace('%', '_', urlencode($str)));
	}
}

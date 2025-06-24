<?php
declare(strict_types=1);

require_once(__DIR__ . '/Profiler.php');

/**
 * Base class for script and framework classes that provides generic functionality for
 * tracing, profiling, argument handling, and data sanitizing and validation.
 */
abstract class Base
{
    ##########
    ## Accessors
    ##########
    /**
     * Provides basic performance profiling.
     */
    public Profiler $profiler;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     */
    public function __construct()
    {
        $this->profiler = new Profiler();
    }

    #####
    ## String manipulation
    #####
    /**
     * Format a string as an attribute value.
     * @param int|string|null $str The string to format.
     * @return string The formatted string.
     */
    public function formatValue(int|string|null $str): string
    {
        return $str !== null
            ? htmlentities((string)$str, ENT_QUOTES, 'UTF-8')
            : '';
    }

    /**
     * Format the title segment of a Wikimedia URL.
     * @param int|string|null $str The string to format.
     * @return string The formatted string.
     */
    public function formatWikiUrlTitle(int|string|null $str): string
    {
        return $str !== null
            ? urlencode(str_replace(' ', '_', trim((string)$str)))
            : '';
    }

    /**
     * Format a string as a plaintext HTML output.
     * @param int|string|null $str The string to format.
     * @return string The formatted string.
     */
    public function formatText(int|string|null $str): string
    {
        return $str !== null
            ? htmlentities((string)$str, ENT_NOQUOTES, 'UTF-8')
            : '';
    }

    /**
     * Make the first character in the string uppercase. This is a workaround for Unicode handling: PHP's ucfirst is not multi-byte safe.
     * @param int|string|null $str The string to format.
     * @return string The formatted string, with uppercase first letter.
     **/
    public function formatInitialCapital(int|string|null $str): string
    {
        return $str !== null
            ? mb_strtoupper(mb_substr((string)$str, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr((string)$str, 1, mb_strlen((string)$str, 'UTF-8') - 1, 'UTF-8')
            : '';
    }

    /**
     * Format a string as a wiki username.
     * @param int|string|null $str The string to format.
     * @return string The formatted string: trimmed, with an uppercase first letter, and with underscores replaced with spaces.
     */
    public function formatUsername(int|string|null $str): string
    {
        if ($str === null)
            return '';

        /* normalize whitespace */
        $str = str_replace('_', ' ', trim((string)$str));

        /* make uppercase */
        return $this->formatInitialCapital($str);
    }

    /**
     * Format a string as an HTML anchor value.
     * @param int|string|null $str The string to format.
     * @return string The formatted string.
     */
    public function formatAnchor(int|string|null $str): string
    {
        return $str !== null
            ? strtolower(str_replace('%', '_', urlencode((string)$str)))
            : '';
    }
}

<?php

/**
 * Wraps a {@see DateTime} with formatting options.
 */
class DateWrapper
{
    ##########
    ## Accessors
    ##########
    /**
     * The date in MediaWiki's database format.
     * @var int
     */
    public $mediawiki;

    /**
     * The human-readable representation of the date.
     * @var string
     */
    public $readable;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param int $date The date in MediaWiki format, which consists of a number containing the zero-padded year, month, day, hour, minute, and second (like '20171231235959'). May optionally omit trailing zeros (e.g. '201701' instead of '20170100000000').
     * @throws InvalidArgumentException The specified date is empty or not in a valid format.
     */
    public function __construct($date)
    {
        // normalise date
        if(!$date)
            throw new InvalidArgumentException("Cannot create a date wrapper with an empty date");
        $normalised = str_pad($date, strlen(20171231235959), '0', STR_PAD_RIGHT); // zero-pad short dates (e.g. 201701 => 20170100000000)

        // parse date
        $parsed = DateTime::createFromFormat("YmdHis", $normalised, new DateTimeZone("UTC"));
        if(!$parsed)
            throw new InvalidArgumentException("Cannot parse MediaWiki date number '$date' (normalised as '$normalised')");

        // save
        $this->mediawiki = $normalised;
        $this->readable = $parsed->format("d F Y");
    }
}

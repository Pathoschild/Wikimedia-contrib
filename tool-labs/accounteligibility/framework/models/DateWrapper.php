<?php
declare(strict_types=1);

/**
 * Wraps a {@see DateTime} with formatting options.
 */
class DateWrapper
{
    ##########
    ## Accessors
    ##########
    /**
     * The date in PHP format.
     */
    public DateTime $date;

    /**
     * The date in MediaWiki's database format.
     */
    public string $mediawiki;

    /**
     * The human-readable representation of the date.
     */
    public string $readable;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $date The date to wrap as a MediaWiki-style date number (a number containing the zero-padded year,
     *        month, day, hour, minute, and second like '20171231235959'). This may optionally omit trailing defaults
     *        (e.g. '201701' instead of '20170101000000'), and may be prefixed with '<' to get the preceding date value
     *        (e.g. '<201701' instead of '20161231235959').
     * @throws InvalidArgumentException The specified date is empty or not in a valid format.
     */
    public function __construct(string $date)
    {
        $this->date = $this->getDate($date);
        $this->mediawiki = $this->date->format("YmdHis");
        $this->readable = $this->date->format("d F Y");

        $time = $this->date->format("H:i");
        if ($time != "00:00")
            $this->readable .= " at " . $time . " UTC";
    }


    ##########
    ## Private methods
    ##########
    /**
     * Get a full date from a date number.
     * @param string $input The date to wrap in a format accepted by {@see DateWrapper::__construct}.
     * @throws InvalidArgumentException The specified date is empty or not in a valid format.
     */
    private function getDate(string $input): DateTime
    {
        // validate
        if (!$input)
            throw new InvalidArgumentException("Can't create a date wrapper with an empty date");

        // parse
        $tokens = [];
        if (!preg_match('/^(?<modifier>[<])?(?<year>\d{4})(?<month>\d{2})?(?<day>\d{2})?(?<hour>\d{2})?(?<minute>\d{2})?(?<second>\d{2})?$/', $input, $tokens))
            throw new InvalidArgumentException("Can't parse value '$input' as a date; make sure it's a balanced date number like 201701 or 20170131235959.");

        // get values
        $usePreceding = isset($tokens['modifier']) && $tokens['modifier'] == '<';
        $year = intval($tokens['year']);
        $month = isset($tokens['month']) ? intval($tokens['month']) : 1;
        $day = isset($tokens['day']) ? intval($tokens['day']) : 1;
        $hour = isset($tokens['hour']) ? intval($tokens['hour']) : 0;
        $minute = isset($tokens['minute']) ? intval($tokens['minute']) : 0;
        $second = isset($tokens['second']) ? intval($tokens['second']) : 0;

        // get date
        $date = (new DateTime())->setDate($year, $month, $day)->setTime($hour, $minute, $second);
        if ($usePreceding)
            $date->sub(new DateInterval("PT1S"));
        return $date;
    }
}

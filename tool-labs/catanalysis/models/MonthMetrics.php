<?php

/**
 * Provides metadata about a test project in a given month.
 */
class MonthMetrics
{
    ##########
    ## Accessors
    ##########
    /**
     * The month's display name.
     * @var string
     */
    public $name;

    /**
     * The number of edits.
     * @var int
     */
    public $edits = 0;

    /**
     * The number of edits excluding those by users marked as bots.
     * @var int
     */
    public $editsExcludingBots = 0;

    /**
     * The number of pages created.
     * @var int
     */
    public $newPages = 0;

    /**
     * The net bytes added during the month.
     */
    public $bytesAdded = 0;

    /**
     * The user metadata indexed by ID.
     * @var UserData[]
     */
    public $users = [];
}

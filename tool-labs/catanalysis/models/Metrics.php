<?php

/**
 * Provides metadata about a test project.
 */
class Metrics
{
    ##########
    ## Accessors
    ##########
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
     * More detailed metrics by month.
     * @var MonthMetrics[]
     */
    public $months = [];

    /**
     * The page metadata indexed by ID.
     * @var PageData[]
     */
    public $pages = [];

    /**
     * The user metadata indexed by ID.
     * @var UserData[]
     */
    public $users = [];
}

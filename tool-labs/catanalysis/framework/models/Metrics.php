<?php
declare(strict_types=1);

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
     */
    public int $edits = 0;

    /**
     * The number of edits excluding those by users marked as bots.
     */
    public int $editsExcludingBots = 0;

    /**
     * More detailed metrics by month.
     * @var MonthMetrics[]
     */
    public array $months = [];

    /**
     * The page metadata indexed by ID.
     * @var PageData[]
     */
    public array $pages = [];

    /**
     * The user metadata indexed by ID.
     * @var UserData[]
     */
    public array $users = [];
}

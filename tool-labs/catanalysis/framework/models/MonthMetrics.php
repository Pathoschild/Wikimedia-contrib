<?php
declare(strict_types=1);

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
     */
    public string $name;

    /**
     * The number of edits.
     */
    public int $edits = 0;

    /**
     * The number of edits excluding those by users marked as bots.
     */
    public int $editsExcludingBots = 0;

    /**
     * The number of pages created.
     */
    public int $newPages = 0;

    /**
     * The net bytes added during the month.
     */
    public int $bytesAdded = 0;

    /**
     * The user metadata indexed by ID.
     * @var UserData[]
     */
    public array $users = [];
}

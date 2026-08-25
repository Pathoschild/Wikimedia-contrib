<?php
declare(strict_types=1);

/**
 * The result of a query run across many wikis.
 */
class WikiQueryResult
{
    ##########
    ## Accessors
    ##########
    /**
     * The result rows which were fetched successfully.
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    /**
     * The database names of the wikis which couldn't be queried. These don't have any rows in {@see WikiQueryResult::$rows}.
     * @var string[]
     */
    public array $failedWikis = [];


    ##########
    ## Public methods
    ##########
    /**
     * Merge another result into this one.
     *
     * @param WikiQueryResult $result The result to merge into this one.
     */
    public function add(self $result): void
    {
        if ($result->rows)
            $this->rows = array_merge($this->rows, $result->rows);
        if ($result->failedWikis)
            $this->failedWikis = array_merge($this->failedWikis, $result->failedWikis);
    }
}

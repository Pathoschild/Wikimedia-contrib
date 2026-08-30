<?php
declare(strict_types=1);

/**
 * Provides global user search methods.
 */
class GUserSearchEngine extends Base
{
    ##########
    ## Properties
    ##########
    const T_GLOBALUSER = 'globaluser';
    const T_GLOBALGROUPS = 'global_user_groups';
    const OP_REGEXP = 'REGEXP';
    const OP_LIKE = 'LIKE';
    const OP_EQUAL = '=';
    const OP_NOT_EQUAL = '!=';

    /**
     * The minimum limit on the number of records that can be returned.
     */
    const MIN_LIMIT = 1;

    /**
     * The maximum limit on the number of records that can be returned.
     */
    const MAX_LIMIT = 5000;

    /**
     * The default limit on the number of records that can be returned.
     */
    const DEFAULT_LIMIT = 50;

    /**
     * The current username. (Only used for pagination.)
     */
    public string $name = '';

    /**
     * Whether the current search is a regex pattern. (Only used for pagination.)
     */
    public bool $useRegex;

    /**
     * Whether to show locked users. (Only used for pagination.)
     */
    public bool $showLocked;

    /**
     * Whether the search is case-insensitive. (Only used for pagination.)
     */
    public bool $caseInsensitive;

    /**
     * The SQL filters to apply, as a `table name => [operator, value]` lookup.
     * @var array<string, array<string, string[]>>
     */
    protected array $filters = [];

    /**
     * The human-readable string representations of the SQL filters.
     * @var string[]
     */
    protected array $filterDescriptions = [];

    /**
     * Whether the script has been disposed.
     */
    private bool $disposed = false;

    /**
     * The maximum number of results to show.
     */
    public int $limit = 50;

    /**
     * The number of results to skip.
     */
    public int $offset = 0;

    /**
     * The SQL query to execute.
     */
    public string $query = "";

    /**
     * The parameterised values for the SQL query.
     * @var mixed[]
     */
    public array $values = [];

    /**
     * Provides a wrapper used by page scripts to generate HTML, interact with the database, and so forth.
     */
    private Backend $backend;

    /**
     * The underlying database connection.
     */
    public Toolserver $db;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Backend $backend Provides a wrapper used by page scripts to generate HTML, interact with the database, and so forth.
     */
    public function __construct(Backend $backend)
    {
        parent::__construct();

        $this->backend = $backend;
        $this->db = $backend->GetDatabase(Toolserver::ERROR_PRINT);
    }

    /**
     * Add an SQL filter to apply to the results.
     * @param string $table The SQL table to filter (one of {@see GUserSearchEngine::T_GLOBALUSER} or {@see GUserSearchEngine::T_GLOBALGROUPS}).
     * @param string $field The SQL field to filter.
     * @param string $operator The SQL comparison operator (one of {@see GUserSearchEngine::OP_REGEXP}, {@see GUserSearchEngine::OP_LIKE}, {@see GUserSearchEngine::OP_EQUAL}, or {@see GUserSearchEngine::OP_NOT_EQUAL}).
     * @param string $value The value to compare against.
     */
    public function filter(string $table, string $field, string $operator, string $value): void
    {
        $this->filters[$table][$field] = [$operator, $value];
    }

    /**
     * Add a human-readable filter label for the summary output.
     * @param string $text The filter label.
     */
    public function describeFilter(string $text): void
    {
        array_push($this->filterDescriptions, $this->formatText($text));
    }

    /**
     * Set the maximum number of records to return.
     * @param int $limit The maximum number of records to return. This should be a value between {@see GUserSearchEngine::MIN_LIMIT} and {@see GUserSearchEngine::MAX_LIMIT}.
     */
    public function setLimit(int $limit): void
    {
        $limit = (int)$limit;

        /* validate */
        if ($limit < self::MIN_LIMIT)
            $limit = self::MIN_LIMIT;
        else if ($limit > self::MAX_LIMIT)
            $limit = self::MAX_LIMIT;

        $this->limit = $limit;
    }

    /**
     * Set the number of records to skip.
     * @param int $offset The number of records to skip.
     */
    public function setOffset(int $offset): void
    {
        $offset = (int)$offset;
        if ($offset < 0)
            $offset = 0;

        $this->offset = $offset;
    }

    /**
     * Get the value of a filter.
     * @param string $table The filtered SQL table.
     * @param string $field The filtered SQL field.
     */
    public function getFilterValue(string $table, string $field): ?string
    {
        if (isset($this->filters[$table][$field]))
            return $this->filters[$table][$field][1];
        return null;
    }

    /**
     * Get a human-readable summary of the query result and search options.
     * @param bool $showQueryResult Whether to show the query results, rather than just the filter summary.
     */
    public function getFormattedSummary(bool $showQueryResult): string
    {
        $summary = 'global accounts';

        // add filters
        if ((bool)$this->filterDescriptions)
            $summary .= ' where [' . implode('] and [', $this->filterDescriptions) . ']';

        // add offset
        if ($this->offset > 0)
        {
            $summary .= $this->offset % $this->limit === 0
                ? " (page " . (intdiv($this->offset, $this->limit) + 1) . ")"
                : " (skip {$this->offset} results)";
        }

        // add query results
        if ($showQueryResult)
        {
            $count = $this->db->countRows();

            if ($count < 1)
                $summary = "Found no results for $summary";
            else
            {
                $adjective = $count < $this->limit
                    ? ($this->offset > 0 ? 'remaining' : 'all') // reached end of list
                    : ($this->offset > 0 ? 'next' : 'latest');  // more results available

                $summary = "Found $adjective $count $summary";
            }
        }
        else
            $summary = "Search $summary";

        return $summary;
    }

    /**
     * Generate the HTML for a pagination link.
     * @param int $limit The maximum number of records to return. This should be a value between {@see GUserSearchEngine::MIN_LIMIT} and {@see GUserSearchEngine::MAX_LIMIT}.
     * @param int $offset The number of records to skip.
     * @param string $label The link text.
     */
    public function getPaginationLinkHtml($limit, $offset, $label = null): string
    {
        $link = "<a href='?name=" . urlencode($this->name);

        if ($limit != self::DEFAULT_LIMIT)
            $link .= "&amp;limit={$limit}";
        if ($offset > 0)
            $link .= "&amp;offset={$offset}";
        if ($this->useRegex)
            $link .= "&amp;regex=1";
        if ($this->showLocked)
            $link .= "&amp;show_locked=1";
        if ($this->caseInsensitive)
            $link .= "&amp;icase=1";

        $link .= "&amp;defer=1' title='{$label}' data-undefer>{$label}</a>";

        return $link;
    }

    /**
     * Apply the selected filters now, appending to {@see GUserSearchEngine::$values} and returning the condition SQL to
     * add to the query. If there are no filters, this does nothing and returns an empty string.
     *
     * @param string $table The table whose filters to prepare.
     * @param string $prefix The SQL keyword to prefix to any conditions returned (e.g. `WHERE` or `AND`).
     */
    protected function applyFilters(string $table, string $prefix = 'WHERE'): string
    {
        if (!isset($this->filters[$table]) || !count($this->filters[$table]))
            return "";

        $output = "{$prefix} ";
        foreach ($this->filters[$table] as $field => $opts) {
            $output .= "{$field} {$opts[0]} ? AND ";
            array_push($this->values, $opts[1]);
        }
        $output = substr($output, 0, -4);
        return $output;
    }

    /**
     * Prepare and execute the SQL query.
     */
    public function query(): void
    {
        $db = $this->db;
        $profiler = $this->backend->profiler;

        ##########
        ## connect to DB
        ##########
        $profiler->start('prepare database connections');
        $db->connect('metawiki');
        $profiler->stop('prepare database connections');

        ##########
        ## Build query
        ##########
        $profiler->start('build search query');
        $globalUsers = self::T_GLOBALUSER;
        $globalGroups = self::T_GLOBALGROUPS;
        $this->query .= "
            SELECT
                gu_id,
                gu_name,
                DATE_FORMAT(gu_registration, '%Y-%b-%d %H:%i') AS gu_registration,
                gu_locked,
                (
                    SELECT GROUP_CONCAT(gug_group ORDER BY gug_group SEPARATOR ', ')
                    FROM centralauth_p.{$globalGroups}
                    WHERE
                        gug_user = gu_id
                        " . $this->applyFilters($globalGroups, 'AND') . "
                ) AS gu_groups
            FROM centralauth_p.{$globalUsers}
            " . $this->applyFilters($globalUsers) . "
            ORDER BY gu_id DESC
            LIMIT {$this->limit}
            OFFSET {$this->offset}
            ";
        $profiler->stop('build search query');

        ##########
        ## Fetch results & close connection
        ##########
        $profiler->start('execute search');
        $db->query($this->query, $this->values);
        $profiler->stop('execute search');
        $db->dispose();
    }

    /**
     * Release resources used by script.
     */
    public function dispose(): void
    {
        if ($this->disposed)
            return;
        $this->disposed = true;
        $this->db->dispose();
    }

    /**
     * Release resources used by script.
     */
    public function __destruct()
    {
        unset($this->db);
    }
}

<?php
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
    const T_LOCALWIKIS = 'localuser';
    const OP_REGEXP = 'REGEXP';
    const OP_LIKE = 'LIKE';
    const OP_EQUAL = '=';
    const OP_NOT_EQUAL = '!=';

    /**
     * The minimum limit on the number of records that can be returned.
     * @var int
     */
    const MIN_LIMIT = 1;

    /**
     * The maximum limit on the number of records that can be returned.
     * @var int
     */
    const MAX_LIMIT = 5000;

    /**
     * The default limit on the number of records that can be returned.
     * @var int
     */
    const DEFAULT_LIMIT = 50;

    /**
     * The current username. (Only used for pagination.)
     * @var string
     */
    public $name;

    /**
     * Whether the current search is a regex pattern. (Only used for pagination.)
     * @var bool
     */
    public $useRegex;

    /**
     * Whether to show locked users. (Only used for pagination.)
     * @var bool
     */
    public $showLocked;

    /**
     * Whether to show hidden users. (Only used for pagination.)
     * @var bool
     */
    public $showHidden;

    /**
     * Whether the search is case-insensitive. (Only used for pagination.)
     */
    public $caseInsensitive;

    /**
     * The earliest registration date for which to show users.
     * @var string
     */
    public $minDate;

    /**
     * The SQL filters to apply, as a `table name => [operator, value]` lookup.
     * @var array
     */
    protected $filters = [];

    /**
     * The human-readable string representations of the SQL filters.
     * @var string[]
     */
    protected $filterDescriptions = [];

    /**
     * Whether the script has been disposed.
     * @var bool
     */
    private $disposed = false;

    /**
     * The maximum number of results to show.
     * @var int
     */
    public $limit = 50;

    /**
     * The number of results to skip.
     * @var int
     */
    public $offset = 0;

    /**
     * The SQL query to execute.
     * @var string
     */
    public $query = "";

    /**
     * The parameterised values for the SQL query.
     * @var array
     */
    public $values = [];

    /**
     * Provides a wrapper used by page scripts to generate HTML, interact with the database, and so forth.
     * @var Backend
     */
    private $backend;

    /**
     * The underlying database connection.
     * @var Toolserver
     */
    public $db;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Backend $backend Provides a wrapper used by page scripts to generate HTML, interact with the database, and so forth.
     */
    public function __construct($backend)
    {
        parent::__construct();

        $this->backend = $backend;
        $this->db = $backend->GetDatabase(Toolserver::ERROR_PRINT);
    }

    /**
     * Add an SQL filter to apply to the results.
     * @param string $table The SQL table to filter (one of {@see GUserSearchGUserSearchEngine::T_GLOBALUSER}, {@see GUserSearchEngine::T_GLOBALGROUPS}, or {@see GUserSearchEngine::T_LOCALWIKIS}).
     * @param string $field The SQL field to filter.
     * @param string $operator The SQL comparison operator (one of {@see GUserSearchEngine::OP_REGEXP}, {@see GUserSearchEngine::OP_LIKE}, {@see GUserSearchEngine::OP_EQUAL}, or {@see GUserSearchEngine::OP_NOT_EQUAL}).
     * @param string $value The value to compare against.
     */
    public function filter($table, $field, $operator, $value)
    {
        $this->filters[$table][$field] = [$operator, $value];
    }

    /**
     * Add a human-readable filter label for the summary output.
     * @param string $text The filter label.
     */
    public function describeFilter($text)
    {
        array_push($this->filterDescriptions, $this->formatText($text));
    }

    /**
     * Set the maximum number of records to return.
     * @param int $limit The maximum number of records to return. This should be a value between {@see GUserSearchEngine::MIN_LIMIT} and {@see GUserSearchEngine::MAX_LIMIT}.
     */
    public function setLimit($limit)
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
    public function setOffset($offset)
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
     * @return string|null
     */
    public function getFilterValue($table, $field)
    {
        if (isset($this->filters[$table][$field]))
            return $this->filters[$table][$field][1];
        return null;
    }

    /**
     * Get a human-readable summary of the query result and search options.
     * @return string
     */
    public function getFormattedSummary()
    {
        $count = $this->db->countRows();
        $output = '';

        if ($count) {
            $output .= ($count < $this->limit ? "Found all " : "Found latest ");
            $output .= $this->db->countRows() . " global accounts where ";
        } else
            $output .= "Found <b>no global accounts</b> matching ";

        return $output . '[' . implode('] and [', $this->filterDescriptions) . ']';
    }

    /**
     * Generate the HTML for a pagination link.
     * @param int $limit The maximum number of records to return. This should be a value between {@see GUserSearchEngine::MIN_LIMIT} and {@see GUserSearchEngine::MAX_LIMIT}.
     * @param int $offset The number of records to skip.
     * @param string $label The link text.
     * @return string
     */
    public function getPaginationLinkHtml($limit, $offset, $label = null)
    {
        $link = "<a href='?name=" . urlencode($this->name);

        if ($limit != self::DEFAULT_LIMIT)
            $link .= "&limit={$limit}";
        if ($offset > 0)
            $link .= "&offset={$offset}";
        if ($this->useRegex)
            $link .= "&regex=1";
        if ($this->showLocked)
            $link .= "&show_locked=1";
        if ($this->showHidden)
            $link .= "&show_hidden=1";
        if ($this->caseInsensitive)
            $link .= "&icase=1";

        $link .= "' title='{$label}'>{$label}</a>";

        return $link;
    }

    /**
     * Prepare the SQL filters for execution and return the generated SQL where clause.
     * @param string $table The table whose filters to prepare.
     * @return string
     */
    protected function prepareFilters($table)
    {
        if (!isset($this->filters[$table]) || !count($this->filters[$table]))
            return "";

        $output = "WHERE ";
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
    public function query()
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
        ## Set date limit (will minimize scan for long queries, but slow down fast queries)
        ##########
        if ($this->minDate) {
            $profiler->start('calculate range for date filter');

            $minID = $db->query('SELECT gu_id FROM centralauth_p.globaluser WHERE gu_registration < ? ORDER BY gu_id DESC LIMIT 1', $this->minDate)->fetchValue();
            if ($minID) {
                $this->filter(GUserSearchEngine::T_GLOBALUSER, 'gu_registration', '>', $minID);
            }

            $profiler->stop('calculate range for date filter');
        }

        ##########
        ## Build query
        ##########
        $profiler->start('build search query');
        $globalUsers = self::T_GLOBALUSER;
        $globalGroups = self::T_GLOBALGROUPS;
        $this->query .= "
            SELECT t_user.*, t_groups.gu_groups
            FROM (
                SELECT gu_id, gu_name, DATE_FORMAT(gu_registration, '%Y-%b-%d %H:%i') AS gu_registration, gu_locked, gu_hidden
                FROM centralauth_p.{$globalUsers}
                " . $this->prepareFilters($globalUsers) . "
                ORDER BY gu_id DESC
                LIMIT {$this->limit}
                OFFSET {$this->offset}
            ) AS t_user
            LEFT JOIN (
                SELECT gug_user, GROUP_CONCAT(gug_group SEPARATOR ', ') AS gu_groups
                FROM centralauth_p.{$globalGroups}
                GROUP BY gug_user
                " . $this->prepareFilters($globalGroups) . "
            ) AS t_groups ON gu_id = gug_user
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
    public function dispose()
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

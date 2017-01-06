<?php
require_once('../backend/modules/Backend.php');
require_once('../backend/modules/Form.php');
$backend = Backend::create('gUser search', 'Provides searching and filtering of global users on Wikimedia wikis.')
    ->link('/gusersearch/stylesheet.css')
    ->link('/gusersearch/javascript.js')
    ->header();

#############################
## Script methods
#############################
/**
 * Provides global user search methods.
 */
class Script extends Base
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
     * @param string $table The SQL table to filter (one of {@see Script::T_GLOBALUSER}, {@see Script::T_GLOBALGROUPS}, or {@see Script::T_LOCALWIKIS}).
     * @param string $field The SQL field to filter.
     * @param string $operator The SQL comparison operator (one of {@see Script::OP_REGEXP}, {@see Script::OP_LIKE}, {@see Script::OP_EQUAL}, or {@see Script::OP_NOT_EQUAL}).
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
     * @param int $limit The maximum number of records to return. This should be a value between {@see Script::MIN_LIMIT} and {@see Script::MAX_LIMIT}.
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
     * @param int $limit The maximum number of records to return. This should be a value between {@see Script::MIN_LIMIT} and {@see Script::MAX_LIMIT}.
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
                $this->filter(Script::T_GLOBALUSER, 'gu_registration', '>', $minID);
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
        $this->db = null;
    }
}


#############################
## Instantiate script engine
#############################
$script = new Script($backend);
$script->minDate = $backend->get('date');
$backend->profiler->start('initialize');

/* get arguments */
$name = $backend->get('name', $backend->getRouteValue());
$useRegex = (bool)$backend->get('regex');
$showLocked = (bool)$backend->get('show_locked');
$showHidden = (bool)$backend->get('show_hidden');
$caseInsensitive = (bool)$backend->get('icase');

/* add user name filter */
if ($name != null) {
    $script->name = $name;
    $operator = ($useRegex ? Script::OP_REGEXP : Script::OP_LIKE);

    if ($caseInsensitive) {
        $script->filter(Script::T_GLOBALUSER, 'UPPER(CONVERT(gu_name USING utf8))', $operator, strtoupper($name));
        $script->filter(Script::T_LOCALWIKIS, 'UPPER(CONVERT(lu_name USING utf8))', $operator, strtoupper($name));
        $script->describeFilter("username {$operator} {$name}");
    } else {
        $script->filter(Script::T_GLOBALUSER, 'gu_name', $operator, $name);
        $script->filter(Script::T_LOCALWIKIS, 'lu_name', $operator, $name);
        $script->describeFilter("username {$operator} {$name}");
    }
}

/* add lock status filter */
if (!$showLocked) {
    $script->filter(Script::T_GLOBALUSER, 'gu_locked', Script::OP_NOT_EQUAL, '1');
    $script->describeFilter("NOT locked");
}

/* add hide status filter */
if (!$showHidden) {
    $script->filter(Script::T_GLOBALUSER, 'gu_hidden', Script::OP_NOT_EQUAL, 'lists');
    $script->filter(Script::T_GLOBALUSER, '`gu_hidden`', Script::OP_NOT_EQUAL, 'suppressed');
    $script->describeFilter("NOT hidden");
}

/* add date filter */
if ($script->minDate) {
    $script->describeFilter("registered after {$script->minDate}");
}

/* set limit */
if ($x = $backend->get('limit'))
    $script->setLimit($x);
$limit = $script->limit;

/* set offset */
if ($x = $backend->get('offset'))
    $script->setOffset($x);
$offset = $script->offset;

$script->useRegex = $useRegex;
$script->showLocked = $showLocked;
$script->showHidden = $showHidden;

#############################
## Input form
#############################
$formUser = $backend->formatValue(isset($name) ? $name : '');

echo "
    <form action='{$backend->url('/gusersearch')}' method='get'>
        <input type='text' name='name' value='{$formUser}' />
        ", (($limit != Script::DEFAULT_LIMIT) ? "<input type='hidden' name='limit' value='{$limit}' />" : ""), "

        <input type='submit' value='Search »' /><br />
        <div style='padding-left:0.5em; border:1px solid gray; color:gray;'>
            ", Form::checkbox('show_locked', $showLocked), "
            <label for='show_locked'>Show locked accounts</label><br />

            ", Form::checkbox('show_hidden', $showHidden), "
            <label for='show_hidden'>Show hidden accounts</label><br />

            ", Form::checkbox('regex', $useRegex, ['onClick' => 'script.toggleRegex(this.checked);']), "
            <label for='regex'>Use <a href='http://www.wellho.net/regex/mysql.html' title='MySQL regex reference'>regular expression</a> (much slower)</label><br />

            ", Form::checkbox('icase', $caseInsensitive), "
            <label for='icase'>Match any capitalization (much slower)</label><br />
            
            <p>
                <b>Search syntax:</b>
                <span id='tips-regex'", ($useRegex ? "" : " style='display:none;'"), ">
                    Regular expressions are much slower, but much more powerful. You will need to escape special characters like [.*^$]. See the <a href='http://www.wellho.net/regex/mysql.html' title='MySQL regex reference'>MySQL regex reference</a>.
                </span>
                <span id='tips-like'", ($useRegex ? " style='display:none;'" : ""), ">
                    Add % to your search string for multicharacter wildcards, and _ for a single-character wildcard. For example, '%Joe%' finds every username containing the word 'Joe').
                </span>
            </p>
            <p>Beware: search is <strong><em>much slower</em></strong> if the user name starts with a wildcard!</p>
        </div>
    </form>
    ";


#############################
## Perform search
#############################
$backend->profiler->stop('initialize');
$script->query();
$backend->profiler->start('output');
$count = $script->db->countRows();
$hasResults = (int)!$count;

echo "
    <h2>Search results</h2>
    <p id='search-summary' class='search-results-{$hasResults}'>{$script->getFormattedSummary()}.</p>
    ";

#############################
## Output
#############################
if ($count) {
    /* pagination */
    echo "[",
    ($offset > 0 ? $script->getPaginationLinkHtml($limit, $offset - $limit, "&larr;newer {$limit}") : "&larr;newer {$limit}"),
    " | ",
    ($script->db->countRows() >= $limit ? $script->getPaginationLinkHtml($limit, $offset + $limit, "older {$limit}&rarr;") : "older {$limit}&rarr;"),
    "] [show {$script->getPaginationLinkHtml(50, $offset, 50)}, {$script->getPaginationLinkHtml(250, $offset, 250)}, {$script->getPaginationLinkHtml(500, $offset, 500)}]";

    /* table */
    echo "
        <table class='pretty' id='search-results'>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Unification date</th>
                <th>Status</th>
                <th>Global groups</th>
                <th>Links</th>
            </tr>
        ";

    $anyOversighted = false;
    while ($row = $script->db->fetchAssoc()) {
        /* get values */
        $inGroups = ($row['gu_groups'] ? '1' : '0');
        $isLocked = (int)$row['gu_locked'];
        $isHidden = ($row['gu_hidden'] == "lists" ? 1 : 0);
        $isOversighted = ($row['gu_hidden'] == "suppressed" ? 1 : 0);
        $isOkay = (!$isLocked && !$isHidden && !$isOversighted ? 1 : 0);
        $linkTarget = urlencode($row['gu_name']);

        $isNameHidden = ($isHidden || $isOversighted);
        if ($isNameHidden)
            $anyOversighted = true;

        /* summarize status */
        $statusLabel = "";
        $statuses = [];
        if ($isLocked)
            array_push($statuses, 'locked');
        if ($isHidden)
            array_push($statuses, 'hidden');
        if ($isOversighted)
            array_push($statuses, 'oversighted');

        if (count($statuses) > 0)
            $statusLabel = implode(' | ', $statuses);

        /* output */
        echo "
            <tr class='user-okay-{$isOkay} user-locked-{$isLocked} user-hidden-{$isHidden} user-oversighted-{$isOversighted} user-in-groups-{$inGroups}'>
                <td class='id'>{$row['gu_id']}</td>
                <td class='name'>", ($isNameHidden ? str_pad("", mb_strlen($row['gu_name'], 'utf-8'), "*") : "<a href='" . $backend->url('/stalktoy/' . $linkTarget) . "' title='about user'>{$row['gu_name']}</a>"), "</td>
                <td class='registration'>{$row['gu_registration']}</td>
                <td class='status'>{$statusLabel}</td>
                <td class='groups'>{$row['gu_groups']}</td>
                <td class='linkies'>", ($isNameHidden ? "&mdash;" : "<a href='//meta.wikimedia.org/wiki/Special:CentralAuth?target={$linkTarget}' title='CentralAuth'>CentralAuth</a>"), "</td>
            </tr>";
    }
    echo "</table>";
}

if ($name && (($useRegex && !preg_match('/[+*.]/', $name)) || (!$useRegex && !preg_match('/[_%]/', $name))))
    echo "<p><strong><big>※</big></strong>You searched for an exact match; did you want partial matches? See <em>Search syntax</em> above.</p>";
if (isset($anyOversighted) && $anyOversighted)
    echo "<p><strong><big>※</big></strong>Hidden or oversighted names are censored for privacy reasons.</p>";

$backend->profiler->stop('output');
$backend->footer();

<?php

/**
 * A rule which checks that the user has a minimum number of edits between two dates.
 */
class EditCountRule implements Rule
{
    ##########
    ## Properties
    ##########
    /**
     * A bit flag indicating edits should be accumulated crosswiki.
     * @var int
     */
    const ACCUMULATE = 1;

    /**
     * Include the number of deleted edits in the count. This increases query time.
     * @var int
     */
    const COUNT_DELETED = 2;

    /**
     * The minimum number of edits.
     * @var int
     */
    private $minCount;

    /**
     * The minimum date for which to consider edits, or null for no minimum.
     * @var DateWrapper|null
     */
    private $minDate;

    /**
     * The maximum date for which to consider edits, or null for no maximum.
     * @var DateWrapper|null
     */
    private $maxDate;

    /**
     * The namespace for which to count edits, or for any namespace.
     * @var int|null
     */
    private $namespace;

    /**
     * Whether edits can be accumulated across multiple wikis.
     * @var bool
     */
    private $accumulate;

    /**
     * Whether to count deleted edits. This significantly increases query time.
     * @var bool
     */
    private $countDeleted;

    /**
     * The number of edits on all analysed wikis.
     * @var int
     */
    private $totalEdits = 0;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param int $minCount The minimum number of edits.
     * @param string|null $minDate The minimum date for which to consider edits in a format recognised by {@see DateWrapper::__construct}, or null for no minimum.
     * @param string|null $maxDate The maximum date for which to consider edits in a format recognised by {@see DateWrapper::__construct}, or null for no maximum.
     * @param int $options The eligibility options (any of {@see EditCountRule::ACCUMULATE} or {@see EditCountRule::COUNT_DELETED}).
     */
    public function __construct($minCount, $minDate, $maxDate, $options = 1)
    {
        $this->minCount = $minCount;
        $this->minDate = $minDate ? new DateWrapper($minDate) : null;
        $this->maxDate = $maxDate ? new DateWrapper($maxDate) : null;
        $this->accumulate = (bool)($options & self::ACCUMULATE);
        $this->countDeleted = (bool)($options & self::COUNT_DELETED);
    }

    /**
     * Only count edits to the specified namespace.
     * @param int $namespace The namespace ID.
     */
    public function inNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * Collect information from a wiki and return whether the rule has been met.
     * @param Toolserver $db The database wrapper.
     * @param Wiki $wiki The current wiki.
     * @param LocalUser $user The local user account.
     * @return ResultInfo|null The eligibility check result, or null if the rule doesn't apply to this wiki.
     */
    public function accumulate($db, $wiki, $user)
    {
        // accumulate
        $localEdits = $this->getEditCount($db, $user, $wiki);
        $this->totalEdits += $localEdits;
        $isMet = $this->accumulate
            ? $this->totalEdits >= $this->minCount
            : $localEdits >= $this->minCount;

        // get result
        $result = null;
        if ($isMet)
            $result = Result::PASS;
        else if ($this->accumulate)
            $result = Result::ACCUMULATING;
        else
            $result = Result::FAIL;

        // get message
        $start = $this->minDate;
        $end = $this->maxDate;

        $message = $isMet ? "has at least {$this->minCount} edits " : "does not have at least {$this->minCount} edits ";
        if ($this->namespace !== null)
            $message .= "in namespace {$this->namespace} ";
        if ($this->minDate && $this->maxDate)
            $message .= "between {$start->readable} and {$end->readable} ";
        else if ($start)
            $message .= "after {$start->readable} ";
        else if ($end)
            $message .= "as of {$end->readable} ";

        $message .= $this->accumulate
            ? " (has {$this->totalEdits} so far)"
            : " (has {$localEdits})";
        $message .= $isMet ? "." : "...";

        // build result
        return new ResultInfo($result, $message, $this->accumulate && $isMet/*isFinal*/);
    }


    ##########
    ## Private methods
    ##########
    /**
     * Get the number of live edits the user has on the current wiki.
     * @param Database $db The database wrapper.
     * @param LocalUser $user The local user account.
     * @param Wiki $wiki The current wiki.
     * @return int
     */
    private function getEditCount($db, $user, $wiki)
    {
        $count = 0;

        ##########
        ## Live edits
        ##########
        // not filtered by namespace
        if ($this->namespace === null) {
            $values = [$user->id];
            $sql =
                'SELECT COUNT(rev_id) FROM revision_userindex WHERE rev_user=? AND '
                . $this->getDateFilterSql('rev_timestamp', $this->minDate, $this->maxDate, $values);
            $db->query($sql, $values);
            $count += $db->fetchColumn();
        }

        // filtered by namespace
        // SQL derived from query written by [[en:user:Cobi]] at toolserver.org/~sql/sqlbot.txt
        else {
            $ns = $this->namespace;
            $values = [$user->id];
            $sql =
                "SELECT data.count FROM ("
                . "SELECT IFNULL(page_namespace, 0) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ("
                . "SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND "
                . $this->getDateFilterSql('rev_timestamp', $this->minDate, $this->maxDate, $values)
                . " GROUP BY rev_page"
                . ") AS rev WHERE rev.rev_page=page_id AND page_namespace=$ns"
                . ") AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname='{$wiki->dbName}'";

            $db->query($sql, $values);
            $count += $db->fetchColumn();
        }

        ##########
        ## Deleted edits
        ##########
        if ($this->countDeleted) {
            $values = [$user->id];
            $sql =
                'SELECT COUNT(ar_id) FROM archive_userindex WHERE ar_user=? AND '
                . $this->getDateFilterSql('ar_timestamp', $this->minDate, $this->maxDate, $values);
            if ($this->namespace) {
                $sql .= 'AND ar_namespace = ?';
                $values[] = $this->namespace;
            }
            $db->query($sql, $values);
            $count += $db->fetchColumn();
        }

        return $count;
    }

    /**
     * Get the SQL expression for a date range.
     * @param string $fieldName The name of the date field.
     * @param DateWrapper|null $start The minimum date for which to consider edits, or null for no minimum.
     * @param DateWrapper|null $end The maximum date for which to consider edits, or null for no maximum.
     * @param string[] $tokens The parameterised SQL values to populate.
     * @return string
     */
    private function getDateFilterSql($fieldName, $start, $end, &$tokens)
    {
        if ($start && $end) {
            $tokens[] = $start->mediawiki;
            $tokens[] = $end->mediawiki;
            return "$fieldName BETWEEN ? AND ?";
        } elseif ($start) {
            $tokens[] = $start->mediawiki;
            return "$fieldName >= ?";
        } elseif ($end) {
            $tokens[] = $end->mediawiki;
            return "$fieldName <= ?";
        } else
            return "1 = 1"; // not filtered by range
    }
}

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
     * The minimum number of edits.
     * @var int
     */
    private $minCount;

    /**
     * The minimum date for which to consider edits, or null for no minimum.
     * @var DateWrapper
     */
    private $minDate;

    /**
     * The maximum date for which to consider edits, or null for no maximum.
     * @var DateWrapper
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
     * @param string $minDate The minimum date for which to consider edits in a format recognised by {@see DateWrapper::__construct}, or null for no minimum.
     * @param string $maxDate The maximum date for which to consider edits in a format recognised by {@see DateWrapper::__construct}, or null for no maximum.
     * @param int $options The eligibility options (any of {@see EditCountRule::ACCUMULATE}).
     */
    public function __construct($minCount, $minDate, $maxDate, $options = 1)
    {
        $this->minCount = $minCount;
        $this->minDate = $minDate ? new DateWrapper($minDate) : null;
        $this->maxDate = $maxDate ? new DateWrapper($maxDate) : null;
        $this->accumulate = (bool)($options & self::ACCUMULATE);
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
     * Get the number of edits the user has on the current wiki.
     * @param Database $db The database wrapper.
     * @param LocalUser $user The local user account.
     * @param Wiki $wiki The current wiki.
     * @return int
     */
    private function getEditCount($db, $user, $wiki)
    {
        $start = $this->minDate->mediawiki;
        $end = $this->maxDate->mediawiki;
        $ns = $this->namespace;

        // not filtered by namespace
        if ($ns === null) {
            // all edits
            if (!$this->minDate && !$this->maxDate)
                return $user->edits;

            // within date range
            $sql = 'SELECT COUNT(rev_id) FROM revision_userindex WHERE rev_user=? AND rev_timestamp ';
            if ($start && $end)
                $db->query("$sql BETWEEN ? AND ?", [$user->id, $start, $end]);
            elseif ($start)
                $db->query("$sql >= ?", [$user->id, $start]);
            elseif ($end)
                $db->query("$sql <= ?", [$user->id, $end]);

            return $db->fetchColumn();
        }

        // filtered by namespace
        // SQL derived from query written by [[en:user:Cobi]] at toolserver.org/~sql/sqlbot.txt
        else {
            $values = [];

            // start SQL
            $sql = /** @lang text -- prevent SQL validation errors due to incomplete SQL */
                "SELECT data.count FROM ("
                . "SELECT IFNULL(page_namespace, $ns) AS page_namespace, IFNULL(SUM(rev.count), 0) AS count FROM page, ("
                . "SELECT rev_page, COUNT(*) AS count FROM revision_userindex WHERE rev_user=? AND rev_timestamp";
            $values[] = $user->id;

            // date filter
            if ($start && $end) {
                $sql .= " BETWEEN ? AND ?";
                $values[] = $start;
                $values[] = $end;
            } elseif ($start) {
                $sql .= " >= ?";
                $values[] = $start;
            } elseif ($end) {
                $sql .= " <= ?";
                $values[] = $end;
            }

            // end SQL
            $sql .=
                " GROUP BY rev_page"
                . ") AS rev WHERE rev.rev_page=page_id AND page_namespace=$ns"
                . ") AS data, toolserver.namespace AS toolserver WHERE ns_id=page_namespace AND dbname='{$wiki->dbName}'";

            // fetch values
            $db->query($sql, $values);
            return $db->fetchColumn();
        }
    }
}

<?php
declare(strict_types=1);

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
     */
    const ACCUMULATE = 1;

    /**
     * Include the number of deleted edits in the count. This increases query time.
     */
    const COUNT_DELETED = 2;

    /**
     * The minimum number of edits.
     */
    private int $minCount;

    /**
     * The minimum date for which to consider edits, or null for no minimum.
     */
    private ?DateWrapper $minDate;

    /**
     * The maximum date for which to consider edits, or null for no maximum.
     */
    private ?DateWrapper $maxDate;

    /**
     * The namespace for which to count edits, or for any namespace.
     */
    private ?int $namespace = null;

    /**
     * Whether edits can be accumulated across multiple wikis.
     */
    private bool $accumulate;

    /**
     * Whether to count deleted edits. This significantly increases query time.
     */
    private bool $countDeleted;

    /**
     * The number of edits on all analysed wikis.
     */
    private int $totalEdits = 0;


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
    public function __construct(int $minCount, ?string $minDate, ?string $maxDate, int $options = 0)
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
    public function inNamespace(int $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Also count deleted edits. This significantly increases query time.
     */
    public function includeDeleted(): self
    {
        $this->countDeleted = true;
        return $this;
    }

    /**
     * Collect information from a wiki and return whether the rule has been met.
     * @param Toolserver $db The database wrapper.
     * @param Wiki $wiki The current wiki.
     * @param LocalUser $user The local user account.
     * @return ResultInfo|null The eligibility check result, or null if the rule doesn't apply to this wiki.
     */
    public function accumulate(Toolserver $db, Wiki $wiki, LocalUser $user): ?ResultInfo
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
        {
            $message .= $this->namespace === 0
                ? "in the main namespace "
                : "in namespace {$this->namespace} ";
        }
        if ($this->countDeleted)
            $message .= "(including deleted) ";
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
     */
    private function getEditCount(Toolserver $db, LocalUser $user, Wiki $wiki): int
    {
        $count = 0;

        ##########
        ## Live edits
        ##########
        // not filtered by namespace
        if ($this->namespace === null) {
            $values = [$user->actorID];
            $sql =
                'SELECT COUNT(rev_id) FROM revision_userindex WHERE rev_actor=? AND '
                . $this->getDateFilterSql('rev_timestamp', $this->minDate, $this->maxDate, $values);
            $db->query($sql, $values);
            $count += $db->fetchColumn();
        }

        // filtered by namespace
        else {
            $ns = $this->namespace;
            $values = [$user->actorID, $this->namespace];
            $sql = '
                SELECT COUNT(*)
                FROM
                    revision_userindex
                    INNER JOIN page ON rev_page = page_id
                WHERE
                    rev_actor = ?
                    AND page_namespace = ?
                    AND ' . $this->getDateFilterSql('rev_timestamp', $this->minDate, $this->maxDate, $values) . '
            ';

            $db->query($sql, $values);
            $count += $db->fetchColumn();
        }

        ##########
        ## Deleted edits
        ##########
        if ($this->countDeleted) {
            $values = [$user->actorID];
            $sql =
                'SELECT COUNT(ar_id) FROM archive_userindex WHERE ar_actor=? AND '
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
     */
    private function getDateFilterSql(string $fieldName, ?DateWrapper $start, ?DateWrapper $end, array &$tokens): string
    {
        if ($start && $end) {
            $tokens[] = $start->mediawiki;
            $tokens[] = $end->mediawiki;
            return "$fieldName BETWEEN ? AND ?";
        }
        elseif ($start) {
            $tokens[] = $start->mediawiki;
            return "$fieldName >= ?";
        }
        elseif ($end) {
            $tokens[] = $end->mediawiki;
            return "$fieldName <= ?";
        }
        else
            return "1 = 1"; // not filtered by range
    }
}

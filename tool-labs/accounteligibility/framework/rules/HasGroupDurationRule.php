<?php
declare(strict_types=1);

/**
 * A rule which checks whether the account had a group flag for a minimum duration.
 */
class HasGroupDurationRule implements Rule
{
    ##########
    ## Properties
    ##########
    /**
     * The group key to find.
     */
    private string $group;

    /**
     * The minimum number of days required.
     */
    private int $minDays;

    /**
     * The maximum date by which the minimum duration should have been met.
     */
    private ?DateWrapper $maxDate;

    /**
     * The user's role assignment/removal logs on Meta as a role => array hash.
     * @var array<string, array<string, mixed>>
     */
    private array $metaLogCache = [];


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $group The group key to find.
     * @param int $minDays The minimum number of days required.
     * @param string $maxDate The maximum date by which the minimum duration should have been met in a format recognised by {@see DateWrapper::__construct}.
     */
    public function __construct(string $group, int $minDays, ?string $maxDate)
    {
        $this->group = $group;
        $this->minDays = $minDays;
        $this->maxDate = $maxDate ? new DateWrapper($maxDate) : null;
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
        // get data
        $days = $this->getLongestRoleDuration($db, $user, $this->group, $wiki);

        // build result
        $result = $days >= $this->minDays ? Result::PASS : Result::FAIL;
        $message = $result == Result::PASS
            ? "was flagged as a {$this->group} for a continuous period of at least {$this->minDays} days as of {$this->maxDate->readable} (longest flag duration was {$days} days)."
            : "was not flagged as a {$this->group} for a continuous period of at {$this->minDays} days as of {$this->maxDate->readable} (" . ($days > 0 ? "longest flag duration was {$days} days" : "never flagged") . ")...";
        $result = new ResultInfo($result, $message);

        // add warning for edge case where user was registered before 2005 (before flag changes were logged)
        if (!$result->isPass() && (!$user->registered || $user->registered < 20050000000000))
            $result->addWarning("{$user->name}'s account on this wiki might predate the rights log. If they're not eligible due to this rule, you may need to verify manually.");

        // add note
        $result->addNote("See <a href='https://{$wiki->domain}/wiki/Special:Log/rights?page=User:{$user->name}' title='local rights log'>local</a> and <a href='https://meta.wikimedia.org/wiki/Special:Log/rights?page=User:{$user->name}@{$wiki->dbName}' title='crosswiki rights log'>crosswiki</a> rights logs.");

        // get result
        return $result;
    }


    ##########
    ## Private methods
    ##########
    /**
     * Get the longest duration (in days) that the user had the specified role on the current wiki.
     * @param Toolserver $db The database wrapper.
     * @param LocalUser $user The local user account.
     * @param string $group The group key to find.
     * @param Wiki $wiki The current wiki.
     * @return float The number of days flagged.
     */
    private function getLongestRoleDuration(Toolserver $db, LocalUser $user, string $group, Wiki $wiki): float
    {
        // SQL to determine the current groups after each log entry
        // (depending on how it was stored on that particular day)
        $sql = '
            SELECT
                log_title,
                log_timestamp,
                log_params,
                comment_text AS log_comment
            FROM
                logging_logindex
                LEFT JOIN comment ON log_comment_id = comment_id
            WHERE
                log_type = "rights"
                AND log_title
        ';
        $logName = str_replace(' ', '_', $user->name);

        // fetch local logs
        $db->query("$sql = ?", [$logName]);
        $local = $db->fetchAllAssoc();

        // merge with Meta logs
        if (!array_key_exists($group, $this->metaLogCache)) {
            $db->connect('metawiki');
            $db->query("$sql LIKE ?", ["$logName@%"]);
            $this->metaLogCache[$group] = $db->fetchAllAssoc();
            $db->connectPrevious();
        }

        $local = array_merge($local, $this->metaLogCache[$group]);

        // parse log entries
        $logs = [];
        foreach ($local as $row) {
            // read values
            $title = $row['log_title'];
            $date = $row['log_timestamp'];
            $params = $row['log_params'];
            $comment = $row['log_comment'];

            // filter logs for wrong wiki / deadline
            if ($title != $logName && $title != "$logName@{$wiki->dbName}")
                continue;
            if ($date > $this->maxDate)
                continue;

            // add metadata to timeline
            $parsed = $this->parseLogParams($params, $group);
            if ($parsed != null)
                $logs[$date] = $parsed;
        }
        if (count($logs) == 0)
            return 0;
        ksort($logs);

        // extract active ranges
        $ranges = $this->getRanges($logs);
        if (count($ranges) == 0)
            return 0;

        // find longest range
        $longestRange = 0;
        $maxDuration = 0;
        foreach ($ranges as $i => $range) {
            $duration = $range[1] - $range[0];
            if ($duration > $maxDuration) {
                $maxDuration = $duration;
                $longestRange = $i;
            }
        }

        // get day length
        $start = DateTime::createFromFormat('YmdHis', $ranges[$longestRange][0]);
        $end = DateTime::createFromFormat('YmdHis', $ranges[$longestRange][1]);
        return $start->diff($end)->days;
    }

    /**
     * Extract the timestamp ranges when the user had the group.
     * @param array<string, array<string, mixed>> $logs The sorted log entries, in the format returned by parseLogParams.
     * @return array<string[]> An array of tuples representing timestamp ranges.
     */
    private function getRanges(array $logs): array
    {
        $ranges = [];
        $i = -1;
        $wasInRole = false;
        $wasExpiry = null;
        foreach ($logs as $timestamp => $data) {
            $nowInRole = $data['new_group'];

            // last range expired
            if ($wasInRole && $wasExpiry != null && $wasExpiry < $timestamp)
            {
                $ranges[$i][1] = $wasExpiry;
                $wasInRole = false;
            }

            // handle change
            if ($wasInRole != $nowInRole)
            {
                // removed, end last range
                if (!$nowInRole)
                    $ranges[$i][1] = $timestamp;

                // added, start new range
                else {
                    ++$i;
                    $ranges[$i] = [$timestamp, $this->maxDate->mediawiki];
                }
            }

            // update tracking
            $wasInRole = $nowInRole;
            $wasExpiry = $nowInRole ? $data['expiry'] : null;
        }

        if ($wasInRole && $wasExpiry != null && $wasExpiry < $this->maxDate->mediawiki)
            $ranges[$i][1] = $wasExpiry;

        return $ranges;
    }

    /**
     * Parse the log_params field for a log entry.
     * @param string $params The log_parse value.
     * @param string $group The group key to find.
     * @return array<string, array<string, mixed>>|null A representation of the log metadata for the given log entry, with three keys: old_group (whether the user had the group before the log entry), new_group (whether the user had it after the log entry), and expiry (the date when the permission will auto-expire, if applicable).
     */
    private function parseLogParams(string $params, string $group): ?array
    {
        if (empty(trim($params)))
            return null;

        // 2005 to 2012 (comma-separated values on two lines, old then new)
        if (($i = strpos($params, "\n")) !== false) {
            return [
                'old_group' => in_array($group, explode(', ', substr($params, 0, $i))),
                'new_group' => in_array($group, explode(', ', substr($params, $i + 1))),
                'expiry' => null
            ];
        }

        // 2012 onwards (serialized structure)
        $data = unserialize($params);
        $oldGroups = $data['4::oldgroups'];
        $newGroups = $data['5::newgroups'];
        $metadata = $data['newmetadata'];

        $newGroupIndex = array_search($group, $newGroups);
        $hasGroup = $newGroupIndex !== false;
        $hasExpiryField = $hasGroup && $metadata != null && array_key_exists($newGroupIndex, $metadata) && array_key_exists('expiry', $metadata[$newGroupIndex]);

        return [
            'old_group' => in_array($group, $oldGroups),
            'new_group' => $hasGroup,
            'expiry' => $hasExpiryField
                ? $metadata[$newGroupIndex]['expiry']
                : null
        ];
    }
}

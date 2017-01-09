<?php

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
     * @var string
     */
    private $group;

    /**
     * The minimum number of days required.
     * @var int
     */
    private $minDays;

    /**
     * The maximum date by which the minimum duration should have been met.
     * @var DateWrapper
     */
    private $maxDate;

    /**
     * The user's role assignment/removal logs on Meta as a role => array hash.
     * @var array
     */
    private $metaLogCache = [];


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $group The group key to find.
     * @param int $minDays The minimum number of days required.
     * @param string $maxDate The maximum date by which the minimum duration should have been met in a format recognised by {@see DateWrapper::__construct}.
     */
    public function __construct($group, $minDays, $maxDate)
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
    public function accumulate($db, $wiki, $user)
    {
        // get data
        $count = $this->getLongestRoleDuration($db, $user, $this->group, $wiki);

        // build result
        $result = $count >= $this->minDays ? Result::PASS : Result::FAIL;
        $message = $result == Result::PASS
            ? "was flagged as a {$this->group} for a continuous period of at least {$this->minDays} days as of {$this->maxDate->readable} (longest flag duration was {$count} days)."
            : "was not flagged as a {$this->group} for a continuous period of at {$this->minDays} days as of {$this->maxDate->readable} (" . ($count > 0 ? "longest flag duration was {$count} days" : "never flagged") . ")...";
        $result = new ResultInfo($result, $message);

        // add warning for edge case where user was registered before 2005 (before flag changes were logged)
        if (!$result->isPass() && (!$user->registered || $user->registered < 20050000000000))
            $result->addWarning("{$user->name} registered here before 2005, so they might have been flagged before the rights log was created.");

        // add note
        $result->addNote("See <a href='//{$wiki->domain}/wiki/Special:Log/rights?page=User:{$user->name}' title='local rights log'>local</a> and <a href='//meta.wikimedia.org/wiki/Special:Log/rights?page=User:{$user->name}@{$wiki->dbName}' title='crosswiki rights log'>crosswiki</a> rights logs.");

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
     * @return float
     */
    public function getLongestRoleDuration($db, $user, $group, $wiki)
    {
        // SQL to determine the current groups after each log entry
        // (depending on how it was stored on that particular day)
        $sql = '
			SELECT
				log_title,
				log_timestamp,
				log_params,
				log_comment'/*,
				CASE
					WHEN log_params <> "" THEN
						CASE WHEN INSTR("\n", log_params) >= 0
							THEN SUBSTR(log_params, INSTR(log_params, "\n") + 1)
							ELSE log_params
						END
					ELSE log_comment
				END AS "log_resulting_groups"*/ . '
			FROM logging_logindex
			WHERE
				log_type = "rights"
				AND log_title';
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
            // alias fields
            $title = $row['log_title'];
            $date = $row['log_timestamp'];
            $params = $row['log_params'];
            $comment = $row['log_comment'];

            // filter logs for wrong wiki / deadline
            if ($title != $logName && $title != "$logName@{$wiki->dbName}")
                continue;
            if ($date > $this->maxDate)
                continue;

            // parse format (changed over the years)
            if (($i = strpos($params, "\n")) !== false) // params: old\nnew
                $groups = substr($params, $i + 1);
            else if ($params != '')                     // ...or params: new
                $groups = $params;
            else                                       // ...or comment: +new +new OR =
                $groups = $comment;

            // append to timeline
            $logs[$date] = $groups;
        }
        if (count($logs) == 0)
            return 0;
        ksort($logs);

        // parse ranges
        $ranges = [];
        $i = -1;
        $wasInRole = $nowInRole = false;
        foreach ($logs as $timestamp => $roles) {
            $nowInRole = (strpos($roles, $group) !== false);

            // start range
            if (!$wasInRole && $nowInRole) {
                ++$i;
                $ranges[$i] = [$timestamp, $this->maxDate];
            }

            // end range
            if ($wasInRole && !$nowInRole)
                $ranges[$i][1] = $timestamp;

            // update trackers
            $wasInRole = $nowInRole;
        }
        if (count($ranges) == 0)
            return 0;

        // determine widest range
        $maxDuration = 0;
        foreach ($ranges as $i => $range) {
            $duration = $range[1] - $range[0];
            if ($duration > $maxDuration) {
                $maxDuration = $duration;
            }
        }

        // calculate range length
        $start = DateTime::createFromFormat('YmdHis', $ranges[$i][0]);
        $end = DateTime::createFromFormat('YmdHis', $ranges[$i][1]);
        $diff = $start->diff($end);
        $months = $diff->days / (365.25 / 12);
        return round($months, 2);
    }
}

<?php
declare(strict_types=1);

/**
 * An abstract rule accumulator that collects information about the user's global account to
 * determine whether the rule matches.
 */
interface GlobalRule
{
    /**
     * Check the rule against the global account.
     * @param Toolserver $db The database wrapper for metawiki.
     * @param GlobalUser|null $user The global user account to analyze, or null if it doesn't exist.
     * @return ResultInfo The eligibility check result.
     */
    function verify(Toolserver $db, ?GlobalUser $user): ResultInfo;
}

<?php
declare(strict_types=1);

/**
 * A rule which checks that the global account isn't globally locked.
 */
class NotGloballyLockedRule implements GlobalRule
{
    ##########
    ## Public methods
    ##########
    /**
     * Check the rule against the global account.
     * @param Toolserver $db The database wrapper for metawiki.
     * @param GlobalUser|null $user The global user account to analyze, or null if it doesn't exist.
     * @return ResultInfo The eligibility check result.
     */
    function verify(Toolserver $db, ?GlobalUser $user): ResultInfo
    {
        return $user && $user->isLocked
            ? new ResultInfo(Result::FAIL, "globally locked.")
            : new ResultInfo(Result::PASS, "not globally locked.");
    }
}

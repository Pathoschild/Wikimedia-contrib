<?php
declare(strict_types=1);

/**
 * A rule which checks that the global account isn't globally blocked.
 */
class NotGloballyBlockedRule implements GlobalRule
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
        return $user && $this->isGloballyBlocked($db, $user)
            ? new ResultInfo(Result::FAIL, "globally blocked.")
            : new ResultInfo(Result::PASS, "not globally blocked.");
    }


    ##########
    ## Private methods
    ##########
    /**
     * Get whether the user is globally blocked.
     * @param Toolserver $db The database wrapper.
     * @param GlobalUser $user The global user account.
     */
    private function isGloballyBlocked(Toolserver $db, GlobalUser $user): bool
    {
        $db->query('SELECT 1 FROM centralauth_p.globalblocks WHERE gb_target_central_id = ? LIMIT 1', [$user->id]);
        return boolval($db->fetchColumn());
    }
}

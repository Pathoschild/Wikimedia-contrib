<?php
declare(strict_types=1);

/**
 * An abstract rule accumulator that collects information from each wiki to determine whether the
 * rule matches.
 */
interface Rule
{
    /**
     * Collect information from a wiki and return whether the rule has been met.
     * @param Toolserver $db The database wrapper.
     * @param Wiki $wiki The current wiki.
     * @param LocalUser $user The local user account.
     * @return ResultInfo|null The eligibility check result, or null if the rule doesn't apply to this wiki.
     */
    function accumulate(Toolserver $db, Wiki $wiki, LocalUser $user): ?ResultInfo;
}

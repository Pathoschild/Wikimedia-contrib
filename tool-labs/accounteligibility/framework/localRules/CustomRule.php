<?php
declare(strict_types=1);

/**
 * A rule which uses injected behaviour.
 */
class CustomRule implements LocalRule
{
    ##########
    ## Properties
    ##########
    /**
     * A callback which gets the result for a wiki.
     * @var callable(Toolserver $db, Wiki $wiki, LocalUser $user): ?ResultInfo
     */
    private callable $accumulator;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param callable(Toolserver $db, Wiki $wiki, LocalUser $user): ?ResultInfo $accumulator A callback which gets the result for a wiki.
     */
    public function __construct(callable $accumulator)
    {
        $this->accumulator = $accumulator;
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
        return ($this->accumulator)($db, $wiki, $user);
    }
}

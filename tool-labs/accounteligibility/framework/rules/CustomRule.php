<?php

/**
 * A rule which uses injected behaviour.
 */
class CustomRule implements Rule
{
    ##########
    ## Properties
    ##########
    /**
     * A callback which gets the result for a wiki.
     * @var Closure
     */
    private $accumulator;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Closure $accumulator A callback which gets the result for a wiki.
     */
    public function __construct($accumulator)
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
    public function accumulate($db, $wiki, $user)
    {
        return $this->accumulator->call($this, [$db, $wiki, $user]);
    }
}

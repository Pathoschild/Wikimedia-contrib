<?php
declare(strict_types=1);

/**
 * A rule which checks when the account was registered.
 */
class DateRegisteredRule implements LocalRule
{
    ##########
    ## Properties
    ##########
    /**
     * The maximum date by which the account should have been registered.
     */
    private DateWrapper $maxDate;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $maxDate The maximum date by which the account should have been registered in a format recognised by {@see DateWrapper::__construct}.
     */
    public function __construct(string $maxDate)
    {
        $this->maxDate = new DateWrapper($maxDate);
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
        $registered = $user->registered;
        if (!$user->registered)
            $registered = $db->getRegistrationDate($user->id, $user->actorID, "d F Y", true)[0];
        $isMet = !$registered/*before 2005*/ || $registered <= $this->maxDate->mediawiki;

        // get result
        $result = $isMet ? Result::PASS : Result::FAIL;
        $message = $isMet
            ? "was registered as of {$this->maxDate->readable}."
            : "was not registered as of {$this->maxDate->readable}...";
        return new ResultInfo($result, $message);
    }
}

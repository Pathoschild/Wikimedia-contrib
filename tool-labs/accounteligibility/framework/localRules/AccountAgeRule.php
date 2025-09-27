<?php

declare(strict_types=1);

/**
 * A rule which checks that the account is at least the given number of days old.
 */
class AccountAgeRule implements LocalRule
{
    ##########
    ## Properties
    ##########
    /**
     * The minimum number of days for which the account must be registered.
     */
    private int $minDaysOld;

    /**
     * The maximum date up to which to count the age, or null for no maximum.
     */
    private ?DateWrapper $maxDate;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param int $minDaysOld The minimum number of days for which the account must be registered.
     * @param string|null $maxDate The maximum date up to which to count the age in a format recognised by {@see DateWrapper::__construct}, or null for no maximum.
     */
    public function __construct(int $minDaysOld, ?string $maxDate = null)
    {
        $this->minDaysOld = $minDaysOld;
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
        // get result
        $daysOld = $this->getAccountAge($db, $user);
        $isOldEnough = is_null($daysOld) || $daysOld >= $this->minDaysOld;
        $result = $isOldEnough ? Result::PASS : Result::ACCUMULATING;

        // get message
        $message =
            ($isOldEnough ? "was" : "was not")
            . " registered at least {$this->minDaysOld} days "
            . ($this->maxDate
                ? "before {$this->maxDate->readable} "
                : "ago "
            )
            . (is_null($daysOld)
                ? " (registered before 2005)"
                : (
                    " (registered {$daysOld} days "
                    . ($this->maxDate ? "before" : "ago")
                    . ")"
                )
            )
            . ($isOldEnough
                ? "."
                : "..."
            );

        // build result
        return new ResultInfo($result, $message);
    }


    ##########
    ## Private methods
    ##########
    /**
     * Get the local account age in days.
     * @param Toolserver $db The database wrapper.
     * @param LocalUser $user The local user account.
     * @return int|null Returns the number of days between the user's registration and the max (or current) date, or null if the user was registered before registration dates started being tracked in 2005.
     */
    private function getAccountAge(Toolserver $db, LocalUser $user): ?int
    {
        $registered = $user->registered;
        if (!$user->registered)
        {
            $rawRegistered = $db->getRegistrationDate($user->id, $user->actorID, skipUserTable: true);
            $registered = $rawRegistered ? $rawRegistered['raw'] : null;
        }

        if (!$registered)
            return null; // before 2005

        $start = DateTime::createFromFormat('YmdHis', $registered);
        $end = $this->maxDate
            ? $this->maxDate->date
            : new DateTime();

        return $start->diff($end)->days;
    }
}

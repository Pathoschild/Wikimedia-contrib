<?php
declare(strict_types=1);

/**
 * Provides metadata about a user.
 */
class UserData
{
    ##########
    ## Accessors
    ##########
    /**
     * The user name.
     */
    public string $name;

    /**
     * The number of edits.
     */
    public int $edits = 0;

    /**
     * Whether this user is a bot.
     */
    public bool $isBot = false;

    /**
     * Whether this user is anonymous.
     */
    public bool $isAnonymous = false;
}

<?php

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
     * @var string
     */
    public $name;

    /**
     * The number of edits.
     * @var int
     */
    public $edits = 0;

    /**
     * Whether this user is a bot.
     * @var boolean
     */
    public $isBot = false;

    /**
     * Whether this user is anonymous.
     * @var boolean
     */
    public $isAnonymous = false;
}

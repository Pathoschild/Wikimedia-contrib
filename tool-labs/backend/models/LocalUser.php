<?php

/**
 * Metadata about a local user account.
 */
class LocalUser
{
    ##########
    ## Accessors
    ##########
    /**
     * The unique local user ID.
     * @var int
     */
    public $id;

    /**
     * The user name.
     * @var string
     */
    public $name;

    /**
     * When the local account was registered.
     * @var int
     */
    public $registered;

    /**
     * When the local account was registered, as a formatted human-readable string.
     * @var string
     */
    public $registeredStr;

    /**
     * The total number of edits by this local account.
     * @var int
     */
    public $edits;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param int $id The unique local user ID.
     * @param string $name The user name.
     * @param int $registered When the local account was registered.
     * @param string $registeredStr When the local account was registered, as a formatted human-readable string.
     * @param int $edits The total number of edits by this local account.
     */
    public function __construct($id, $name, $registered, $registeredStr, $edits)
    {
        $this->id = $id;
        $this->name = $name;
        $this->registered = $registered;
        $this->registeredStr = $registeredStr;
        $this->edits = $edits;
    }
}
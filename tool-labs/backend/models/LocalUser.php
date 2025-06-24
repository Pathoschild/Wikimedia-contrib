<?php
declare(strict_types=1);

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
     */
    public int $id;

    /**
     * The user name.
     */
    public string $name;

    /**
     * When the local account was registered, in the numeric MediaWiki format.
     */
    public ?string $registered;

    /**
     * When the local account was registered, as a formatted human-readable string.
     */
    public ?string $registeredStr;

    /**
     * The total number of edits by this local account.
     */
    public int $edits;

    /**
     * The user's unique actor ID.
     */
    public int $actorID;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param int $id The unique local user ID.
     * @param string $name The user name.
     * @param string $registered When the local account was registered, in the numeric MediaWiki format.
     * @param string $registeredStr When the local account was registered, as a formatted human-readable string.
     * @param int $edits The total number of edits by this local account.
     * @param int $actorID The user's unique actor ID.
     */
    public function __construct(int $id, string $name, ?string $registered, ?string $registeredStr, int $edits, int $actorID)
    {
        $this->id = $id;
        $this->name = $name;
        $this->registered = $registered;
        $this->registeredStr = $registeredStr;
        $this->edits = $edits;
        $this->actorID = $actorID;
    }
}

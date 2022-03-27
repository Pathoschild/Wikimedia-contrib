<?php
namespace Stalktoy;

/**
 * Represents a user's global account.
 */
class GlobalAccount
{
    /**
     * Whether the user account exists.
     * @var bool
     */
    public $exists;

    /**
     * The unique identifier for this account.
     * @var int
     */
    public $id;

    /**
     * The username of this account.
     * @var string
     */
    public $name;

    /**
     * The name of the wiki registered as the primary for this account.
     * @var string
     */
    public $homeWiki;

    /**
     * When the account was registered (formatted yyyy-mm-dd hh-ii).
     * @var string
     */
    public $registered;

    /**
     * Whether the global account is locked, so that it can no longer log in or edit.
     * @var bool
     */
    public $isLocked;

    /**
     * The global groups to which the user account belongs (as a comma-separated list).
     * @var string[]
     */
    public $groups;

    /**
     * The wikis on which this account is registered.
     * @var string[]
     */
    public $wikis;

    /**
     * A wiki database name lookup hash.
     * @var bool[]
     */
    public $wikiHash;
}

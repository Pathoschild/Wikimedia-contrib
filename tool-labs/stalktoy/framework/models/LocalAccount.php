<?php
namespace Stalktoy;

/**
 * Represents a user's local account.
 */
class LocalAccount
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
     * When the account was registered (formatted yyyy-mm-dd hh-ii).
     * @var string
     */
    public $registered;

    /**
     * When the account was registered.
     * @var int
     */
    public $registeredRaw;

    /**
     * The number of edits made by the account.
     */
    public $editCount;

    /**
     * Whether the user is currently blocked.
     * @var bool
     */
    public $isBlocked;

    /**
     * Whether the local account is linked to the global account of the same name.
     * @var bool
     */
    public $isUnified;

    /**
     * The global groups to which the user account belongs (as a comma-separated list).
     * @var string
     */
    public $groups;

    /**
     * Details about the user's current block.
     * @var \Stalktoy\Block
     */
    public $block;

    /**
     * The wiki on which this account resides.
     * @var \Wiki
     */
    public $wiki;
}

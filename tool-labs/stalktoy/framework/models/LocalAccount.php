<?php
declare(strict_types=1);

namespace Stalktoy;

/**
 * Represents a user's local account.
 */
class LocalAccount
{
    /**
     * Whether the user account exists.
     */
    public bool $exists;

    /**
     * The unique identifier for this account.
     */
    public int $id = -1;

    /**
     * The unique 'actor' ID for this account.
     */
    public int $actorId = -1;

    /**
     * When the account was registered (as a formatted `yyyy-mm-dd hh-ii` date string).
     */
    public ?string $registered = null;

    /**
     * When the account was registered (as a numeric value).
     */
    public ?string $registeredRaw = null;

    /**
     * The number of edits made by the account.
     */
    public int $editCount = 0;

    /**
     * Whether the user is currently blocked.
     */
    public bool $isBlocked = false;

    /**
     * Whether the local account is linked to the global account of the same name.
     */
    public bool $isUnified = false;

    /**
     * The global groups to which the user account belongs (as a comma-separated list), or null if they have none.
     */
    public ?string $groups = null;

    /**
     * Details about the user's current block.
     */
    public ?\Stalktoy\Block $block = null;

    /**
     * The wiki on which this account resides.
     */
    public \Wiki $wiki;
}

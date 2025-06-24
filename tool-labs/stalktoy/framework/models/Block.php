<?php
declare(strict_types=1);

namespace Stalktoy;

/**
 * Represents a block against editing by a user account or IP address.
 */
class Block
{
    /**
     * The username who blocked this user.
     */
    public string $by;

    /**
     * The blocked username or IP address.
     */
    public string $target;

    /**
     * The reason given for the block.
     */
    public string $reason;

    /**
     * When the block was placed (formatted as yyyy-mm-dd hh:ii).
     */
    public string $timestamp;

    /**
     * When the block will expire (formatted as yyyy-mm-dd hh:ii), or null if it has no expiry.
     */
    public ?string $expiry;

    /**
     * Whether the account has been suppressed by this block and no longer appears in edit histories and public logs (only applicable to account blocks).
     */
    public bool $isHidden;

    /**
     * Whether only anonymous (non-logged-in) users are affected by the block (only applicable to IP blocks).
     */
    public bool $anonOnly;
}

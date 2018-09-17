<?php
namespace Stalktoy;

/**
 * Represents a block against editing by a user account or IP address.
 */
class Block
{
    /**
     * The username who blocked this user.
     * @var string
     */
    public $by;

    /**
     * The blocked username or IP address.
     */
    public $target;

    /**
     * The reason given for the block.
     * @var string
     */
    public $reason;

    /**
     * When the block was placed (formatted as yyyy-mm-dd hh:ii).
     * @var string
     */
    public $timestamp;

    /**
     * When the block will expire (formatted as yyyy-mm-dd hh:ii).
     * @var string
     */
    public $expiry;

    /**
     * Whether the account has been oversighted by this block and no longer appears in edit histories and public logs (only applicable to account blocks).
     * @var bool
     */
    public $isHidden;

    /**
     * Whether only anonymous (non-logged-in) users are affected by the block (only applicable to IP blocks).
     */
    public $anonOnly;
}

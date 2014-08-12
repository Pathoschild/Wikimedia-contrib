<?php
namespace Stalktoy;

/**
 * Represents a user's global account.
 */
class GlobalAccount {
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
	 * Whether the global account is hidden, so that it is censored from public lists.
	 * @var bool
	 */
	public $isHidden;

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

/**
 * Represents a user's local account.
 */
class LocalAccount {
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
	 * @var Wiki
	 */
	public $wiki;
}

/**
 * Represents a block against editing by a user account or IP address.
 */
class Block {
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

/**
 * Represents global details about an IP address.
 */
class GlobalIP {
	/**
	 * The underlying IP address.
	 * @var IPAddress
	 */
	public $ip;

	/**
	 * The global blocks placed against this IP address or IP addresses within this range.
	 * @var \Stalktoy\Block[]
	 */
	public $globalBlocks;
}

/**
 * Represents statistics about a user's global account.
 */
class GlobalAccountStats {
	/**
	 * The number of wikis on which the account is registered.
	 * @var int
	 */
	public $wikis = 0;

	/**
	 * The number of edits the user has made across all wikis.
	 * @var int
	 */
	public $editCount = 0;

	/**
	 * The maximum number of edits the user has made on any one wiki.
	 * @var int
	 */
	public $maxEditCount = 0;

	/**
	 * The name of the wiki on which the user has made the most edits.
	 * @var string
	 */
	public $maxEditCountDomain = null;

	/**
	 * When the user registered his earliest account.
	 * @var int
	 */
	public $earliestRegisteredRaw = null;

	/**
	 * When the user registered his earliest account (formatted as yyyy-mm-dd hh:ii).
	 * @var int
	 */
	public $earliestRegistered = null;

	/**
	 * The domain on which the user registered their earliest account.
	 * @var string
	 */
	public $earliestRegistedDomain = null;
}

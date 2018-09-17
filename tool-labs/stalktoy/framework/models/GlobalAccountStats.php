<?php
namespace Stalktoy;

/**
 * Represents statistics about a user's global account.
 */
class GlobalAccountStats
{
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

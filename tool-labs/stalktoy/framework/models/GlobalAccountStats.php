<?php
declare(strict_types=1);

namespace Stalktoy;

/**
 * Represents statistics about a user's global account.
 */
class GlobalAccountStats
{
    /**
     * The number of wikis on which the account is registered.
     */
    public int $wikis = 0;

    /**
     * The number of edits the user has made across all wikis.
     */
    public int $editCount = 0;

    /**
     * The maximum number of edits the user has made on any one wiki.
     */
    public int $maxEditCount = 0;

    /**
     * The name of the wiki on which the user has made the most edits.
     */
    public string $maxEditCountDomain = null;

    /**
     * When the user registered his earliest account.
     */
    public int $earliestRegisteredRaw = null;

    /**
     * When the user registered his earliest account (formatted as yyyy-mm-dd hh:ii).
     */
    public int $earliestRegistered = null;

    /**
     * The domain on which the user registered their earliest account.
     */
    public string $earliestRegisteredDomain = null;
}

<?php

/**
 * A rule which checks that the account has no current local blocks.
 */
class NotBlockedRule implements Rule
{
    ##########
    ## Properties
    ##########
    /**
     * The maximum number of current blocks allowed. (A value > 1 only makes sense when accumulating blocks crosswiki.)
     * @var int
     */
    private $maxBlocks;

    /**
     * Only check for blocks on this wiki.
     * @var string
     */
    private $onlyForWiki;

    /**
     * The number of current blocks found.
     * @var int
     */
    private $totalBlocks;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param int $maxBlocks The maximum number of current blocks allowed, counted crosswiki.
     */
    public function __construct($maxBlocks = 0)
    {
        $this->maxBlocks = $maxBlocks;
    }

    /**
     * Only check for blocks on a specified wiki.
     * @param string $wiki The wiki dbname.
     */
    public function onWiki($wiki)
    {
        $this->onlyForWiki = $wiki;
        return $this;
    }

    /**
     * Collect information from a wiki and return whether the rule has been met.
     * @param Toolserver $db The database wrapper.
     * @param Wiki $wiki The current wiki.
     * @param LocalUser $user The local user account.
     * @return ResultInfo|null The eligibility check result, or null if the rule doesn't apply to this wiki.
     */
    public function accumulate($db, $wiki, $user)
    {
        // skip if not applicable
        if ($this->onlyForWiki && $wiki->dbName != $this->onlyForWiki)
            return null;

        // accumulate
        $isBlocked = $this->isBlocked($db, $user);
        $this->totalBlocks += (int)$isBlocked;

        // get result
        if ($this->totalBlocks == 0) // not blocked
            return new ResultInfo(Result::SOFT_PASS, "not currently blocked..."); // still need check other wikis
        else if ($this->maxBlocks <= 0) // one block with none allowed
            return new ResultInfo(Result::FAIL, "blocked on this wiki.");
        else if ($this->totalBlocks > $this->maxBlocks) // too many blocks
            return new ResultInfo(Result::FAIL, "blocked on too many wikis (can't be more than {$this->maxBlocks}).");
        else { // some blocks but under the limit
            return $isBlocked
                ? new ResultInfo(Result::ACCUMULATING, "blocked on this wiki but still eligible ({$this->totalBlocks} out of max {$this->maxBlocks} so far)...")
                : new ResultInfo(Result::SOFT_PASS, "not currently blocked ({$this->totalBlocks} out of max {$this->maxBlocks} so far)..."); // still need check other wikis
        }
    }


    ##########
    ## Private methods
    ##########
    /**
     * Get whether the user is blocked on the current wiki.
     * @param Toolserver $db The database wrapper.
     * @param LocalUser $user The local user account.
     * @return bool
     */
    private function isBlocked($db, $user)
    {
        $db->query('SELECT COUNT(ipb_expiry) FROM ipblocks_ipindex WHERE ipb_user=? LIMIT 1', [$user->id]);
        return (bool)$db->fetchColumn();
    }
}

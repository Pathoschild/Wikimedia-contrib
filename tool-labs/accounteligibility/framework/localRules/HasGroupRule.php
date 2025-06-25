<?php
declare(strict_types=1);

/**
 * A rule which checks whether the account has a local group.
 */
class HasGroupRule implements LocalRule
{
    ##########
    ## Properties
    ##########
    /**
     * The group name to match.
     * @var 'bot'|'sysop'
     */
    private string $group;

    /**
     * Whether the presence of the group is a failing condition; set by subclass rules like {@see NotBotRule}.
     */
    protected bool $negate = false;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $group The group name to match (one of 'bot' or 'sysop').
     * @throws InvalidArgumentException The group is not whitelisted.
     */
    public function __construct(string $group)
    {
        if ($group != 'bot' && $group != 'sysop')
            throw new InvalidArgumentException("Unrecognized role '$group' not found in whitelist.");
        $this->group = $group;
    }

    /**
     * Collect information from a wiki and return whether the rule has been met.
     * @param Toolserver $db The database wrapper.
     * @param Wiki $wiki The current wiki.
     * @param LocalUser $user The local user account.
     * @return ResultInfo|null The eligibility check result, or null if the rule doesn't apply to this wiki.
     */
    public function accumulate(Toolserver $db, Wiki $wiki, LocalUser $user): ?ResultInfo
    {
        // accumulate
        $hasGroup = (bool)$db->query('SELECT COUNT(ug_user) FROM user_groups WHERE ug_user=? AND ug_group=? LIMIT 1', [$user->id, $this->group])->fetchColumn();
        $isMet = $hasGroup == !$this->negate;

        // get message
        $message = $hasGroup
            ? "is a {$this->group}"
            : "is not a {$this->group}";
        $message .= $isMet ? "..." : ".";

        // build result
        return new ResultInfo($isMet ? Result::PASS : Result::FAIL, $message);
    }
}

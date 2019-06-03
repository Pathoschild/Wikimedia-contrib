<?php

/**
 * A rule entry with workflow logic.
 */
class RuleEntry
{
    ##########
    ## Properties
    ##########
    /**
     * The eligibility rule.
     * @var Rule
     */
    public $rule;

    /**
     * Whether to skip the remaining rules for the current wiki if this rule fails.
     * @var bool
     */
    public $shouldSkipOnFail = false;

    /**
     * Whether to immediately consider the user ineligible if this rule fails.
     * @var bool
     */
    public $shouldFailHard = false;

    /**
     * This rule only needs to pass on one wiki.
     * @var bool
     */
    public $onAnyWiki = false;

    /**
     * The result of the last eligibility check.
     * @var ResultInfo
     */
    public $lastResult;

    /**
     * The aggregate result (one of the {@see Result} values).
     * @var string
     */
    public $result = Result::ACCUMULATING;

    /**
     * Whether the result is final (i.e. there's no need to check further wikis).
     * @var bool
     */
    public $isFinal = false;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Rule $rule The eligibility rule.
     * @param int $workflow The workflow options (a bit flag of {@see Workflow} options).
     */
    public function __construct($rule, $workflow)
    {
        if (!$rule)
            throw new Exception('Can\'t create a rule entry with a null rule.');

        $this->rule = $rule;
        $this->shouldSkipOnFail = (bool)($workflow & Workflow::SKIP_ON_FAIL);
        $this->shouldFailHard = (bool)($workflow & Workflow::HARD_FAIL);
        $this->onAnyWiki = (bool)($workflow & Workflow::ON_ANY_WIKI);
    }

    /**
     * Collect information from a wiki and return whether all rules has been met.
     * @param Toolserver $db The database wrapper.
     * @param Wiki $wiki The current wiki.
     * @param LocalUser $user The local user account.
     * @return ResultInfo|null The eligibility check result, or null if the rule doesn't apply to this wiki.
     */
    public function accumulate($db, $wiki, $user)
    {
        // already final
        if ($this->isFinal)
            return null;

        // accumulate rule
        $result = $this->rule->accumulate($db, $wiki, $user);
        if (!$result)
            return null; // not applicable
        $this->lastResult = $result;

        // mark final
        $this->isFinal =
            $this->lastResult->isFinal
            || ($this->shouldFailHard && $this->lastResult->isFail())
            || ($this->onAnyWiki && $this->lastResult->isPass());
        if ($this->isFinal)
            $this->result = $this->lastResult->result;

        return $this->lastResult;
    }
}

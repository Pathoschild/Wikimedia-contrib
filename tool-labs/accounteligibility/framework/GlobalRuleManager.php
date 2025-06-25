<?php

/**
 * Manages eligibility and workflow between {@LocalRule} instances.
 */
class GlobalRuleManager
{
    ##########
    ## Properties
    ##########
    /**
     * The managed rules.
     * @var GlobalRule[]
     */
    public array $rules = [];

    /**
     * The aggregate result of the eligibility checks (one of the {@see Result} values).
     */
    public string $result = Result::ACCUMULATING;


    ##########
    ## Public methods
    ##########
    /**
     * Set the eligibility rules to validatate.
     * @param GlobalRule[] $rules The eligibility rules to check.
     */
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Check the rule against the global account.
     * @param Toolserver $db The database wrapper for metawiki.
     * @param GlobalUser|null $user The global user account to analyze, or null if it doesn't exist.
     * @return ResultInfo[] The eligibility check results.
     */
    function verify(Toolserver $db, ?GlobalUser $user): array
    {
        // check rules
        $results = [];
        $anyFailed = false;
        foreach ($this->rules as $rule) {
            $result = $rule->verify($db, $user);

            if ($result->isFail())
                $anyFailed = true;

            array_push($results, $result);
        }

        // handle workflow
        $this->result = $anyFailed
            ? Result::FAIL
            : Result::PASS;

        return $results;
    }
}

<?php

/**
 * Manages eligibility and workflow between {@Rule} instances.
 */
class RuleManager
{
    ##########
    ## Properties
    ##########
    /**
     * The managed rules.
     * @var RuleEntry[]
     */
    public $rules = [];

    /**
     * The aggregate result of the eligibility checks (one of the {@see Result} values).
     * @var string
     */
    public $result = Result::ACCUMULATING;

    /**
     * Whether the accumulated result is final (i.e. there's no need to check further wikis).
     * @var bool
     */
    public $final = false;


    ##########
    ## Public methods
    ##########
    /**
     * Add a new eligibility rule to the manager.
     * @param RuleEntry[] $rules The eligibility rules to check.
     */
    public function __construct($rules)
    {
        $this->rules = $rules;
    }

    /**
     * Collect information from a wiki and return whether all rules has been met.
     * @param Toolserver $db The database wrapper.
     * @param Wiki $wiki The current wiki.
     * @param LocalUser $user The local user account.
     * @return ResultInfo[] The eligibility check results for this wiki.
     */
    public function accumulate($db, $wiki, $user)
    {
        // validate
        if ($this->final)
            throw new LogicException("The rule manager cannot be accumulated because a final eligibility result has already been reached.");

        // accumulate rules
        $results = [];
        $allPassed = true;
        $allPassedOrSoftPassed = true;
        $anyFailedHard = null;
        foreach ($this->rules as $rule) {
            // ignore finalised rules
            if ($rule->isFinal)
                continue;

            // accumulate
            $result = $rule->accumulate($db, $wiki, $user);
            if (!$result) {
                $allPassed = false;
                continue; // not applicable to this wiki
            }

            if (!$result->isPass()) {
                $allPassed = false;

                if (!$result->isSoftPass())
                    $allPassedOrSoftPassed = false;
            }

            if ($result->isFail() && ($result->isFinal || $rule->shouldFailHard))
                $anyFailedHard = true;
            
            array_push($results, $result);
        }

        // handle workflow
        if ($allPassed) {
            $this->final = true;
            $this->result = Result::PASS;
        }
        else if ($allPassedOrSoftPassed)
            $this->result = Result::SOFT_PASS;
        else if ($anyFailedHard) {
            $this->final = true;
            $this->result = Result::FAIL;
        }

        return $results;
    }
}

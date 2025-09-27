<?php

/**
 * Manages eligibility and workflow between {@LocalRule} instances.
 */
class LocalRuleManager
{
    ##########
    ## Properties
    ##########
    /**
     * The managed rules.
     * @var LocalRuleEntry[]
     */
    public array $rules = [];

    /**
     * The aggregate result of the eligibility checks (one of the {@see Result} values).
     */
    public string $result = Result::ACCUMULATING;

    /**
     * Whether the accumulated result is final (i.e. there's no need to check further wikis).
     */
    public bool $final = false;


    ##########
    ## Public methods
    ##########
    /**
     * Set the eligibility rules to validatate.
     * @param LocalRuleEntry[] $rules The eligibility rules to check.
     */
    public function __construct(array $rules)
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
    public function accumulate(Toolserver $db, Wiki $wiki, LocalUser $user): array
    {
        // validate
        if ($this->final)
            throw new LogicException("The local rule manager cannot be accumulated because a final eligibility result has already been reached.");

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

            if ($rule->shouldSkipOnFail && $result->isFail())
                break;
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

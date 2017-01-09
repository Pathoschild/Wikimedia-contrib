<?php

/**
 * An enumeration representing the current eligibility status of a rule.
 */
class Result
{
    /**
     * The user is not eligible.
     * @var int
     */
    const FAIL = 0;

    /**
     * The user is not eligible yet, but the rule is collecting data for crosswiki eligibility (e.g. edit count across all wikis).
     * @var int
     */
    const ACCUMULATING = 2;

    /**
     * The user is eligible.
     * @var int
     */
    const PASS = 3;
}

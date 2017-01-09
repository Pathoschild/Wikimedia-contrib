<?php

/**
 * An enumeration representing the current eligibility status of a rule.
 */
class Result
{
    /**
     * The user is not eligible.
     * @var string
     */
    const FAIL = 'fail';

    /**
     * The user is not eligible yet, but the rule is collecting data for crosswiki eligibility (e.g. edit count across all wikis).
     * @var string
     */
    const ACCUMULATING = 'accumulating';

    /**
     * The user is eligible.
     * @var string
     */
    const PASS = 'pass';
}

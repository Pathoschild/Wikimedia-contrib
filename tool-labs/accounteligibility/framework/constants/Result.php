<?php
declare(strict_types=1);

/**
 * An enumeration representing the current eligibility status of a rule.
 */
class Result
{
    /**
     * The user is not eligible.
     */
    const FAIL = 'fail';

    /**
     * The user is not eligible yet, but the rule is collecting data for crosswiki eligibility (e.g. edit count across all wikis).
     */
    const ACCUMULATING = 'accumulating';

    /**
     * The user is eligible, but we should still check other wikis.
     */
    const SOFT_PASS = 'soft_pass';

    /**
     * The user is eligible.
     */
    const PASS = 'pass';
}

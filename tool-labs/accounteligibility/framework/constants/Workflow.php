<?php

/**
 * An enumeration representing workflow options.
 */
class Workflow
{
    /**
     * If this rule fails, don't try to run the remaining rules for this wiki.
     * @var int
     */
    const SKIP_ON_FAIL = 1;

    /**
     * If this rule passes on any wiki, it's considered failed on all subsequent wikis.
     */
    const HARD_FAIL = 2;

    /**
     * If this rule passes on any wiki, it's considered passed on all subsequent wikis.
     */
    const ON_ANY_WIKI = 4;
}

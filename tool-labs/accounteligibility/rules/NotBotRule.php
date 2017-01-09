<?php

/**
 * A rule which checks whether the account does not have a bot flag on any wiki.
 */
class NotBotRule extends HasGroupRule
{
    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     */
    public function __construct()
    {
        parent::__construct('bot');
        $this->negate = true;
    }
}

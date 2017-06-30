<?php

/**
 * Provides metadata about a page.
 */
class PageData
{
    ##########
    ## Accessors
    ##########
    /**
     * The page ID.
     * @var int
     */
    public $id;

    /**
     * The page namespace ID.
     * @var int
     */
    public $namespace;

    /**
     * The display name.
     * @var string
     */
    public $name;

    /**
     * The number of edits to this page.
     * @var int
     */
    public $edits = 0;

    /**
     * The current page size.
     * @var int
     */
    public $size = false;

    /**
     * Whether the current page is a redirect.
     * @var int
     */
    public $isRedirect = false;
}

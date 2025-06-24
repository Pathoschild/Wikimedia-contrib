<?php
declare(strict_types=1);

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
     */
    public int $id;

    /**
     * The page namespace ID.
     */
    public int $namespace;

    /**
     * The display name.
     */
    public string $name;

    /**
     * The number of edits to this page.
     */
    public int $edits = 0;

    /**
     * The current page size.
     */
    public int $size = 0;

    /**
     * Whether the current page is a redirect.
     */
    public bool $isRedirect = false;
}

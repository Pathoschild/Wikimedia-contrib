<?php
declare(strict_types=1);

namespace Stalktoy;

/**
 * Represents a user's global account.
 */
class GlobalAccount
{
    /**
     * Whether the user account exists.
     */
    public bool $exists;

    /**
     * The unique identifier for this account.
     */
    public int $id;

    /**
     * The username of this account.
     */
    public string $name;

    /**
     * The name of the wiki registered as the primary for this account.
     */
    public string $homeWiki;

    /**
     * When the account was registered (formatted yyyy-mm-dd hh-ii).
     */
    public string $registered;

    /**
     * Whether the global account is locked, so that it can no longer log in or edit.
     */
    public bool $isLocked;

    /**
     * The global groups to which the user account belongs (as a comma-separated list).
     * @var string[]
     */
    public array $groups = [];

    /**
     * The wikis on which this account is registered.
     * @var string[]
     */
    public array $wikis = [];

    /**
     * A wiki database name lookup hash.
     * @var array<string, Wiki>
     */
    public array $wikiHash = [];
}

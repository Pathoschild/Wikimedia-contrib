<?php
declare(strict_types=1);

/**
 * Metadata about a global user account.
 */
class GlobalUser
{
    /**
     * Construct an instance.
     * @param int $id The unique global user ID.
     * @param string $name The user name.
     * @param string|null $homeWiki The global account's home wiki (like 'metawiki'), if known.
     * @param bool $isLocked Whether the account is globally locked.
     * @param string|null $registered When the local account was registered, in the numeric MediaWiki format.
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $homeWiki,
        public bool $isLocked,
         public ?string $registered
    ) { }
}

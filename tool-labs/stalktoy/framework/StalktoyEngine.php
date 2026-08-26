<?php
declare(strict_types=1);

/**
 * Implements logic for the Stalktoy tool.
 */
class StalktoyEngine extends Base
{
    ##########
    ## Accessors
    ##########
    /**
     * The lookup target.
     */
    public ?string $target = null;

    /**
     * The lookup target formatted for injection into a URL.
     */
    public ?string $targetUrl = null;

    /**
     * The lookup target formatted for injection into the page name portion of a wiki URL.
     */
    public ?string $targetWikiUrl = null;

    /**
     * A lookup hash of wiki data.
     * @var array<string, Wiki>
     */
    public array $wikis = [];

    /**
     * A lookup hash of wiki domains.
     * @var string[]
     */
    public array $domains = [];

    /**
     * The selected wiki.
     */
    public ?string $wiki = null;

    /**
     * (User lookups only.) Whether to show all wikis, even if the user doesn't have an account there.
     */
    public bool $showAllWikis = false;

    /**
     * (User lookups only.) Whether to search every wiki for local accounts which aren't attached to
     * the global account.
     */
    public bool $showDetached = false;

    /**
     * (User lookups only.) Whether to list relevant global groups next to each wiki.
     */
    public bool $showGroupsPerWiki = false;

    /**
     * The database wrapper.
     */
    public ?Toolserver $db = null;

    /**
     * The database names of the wikis which couldn't be queried.
     * @var string[]
     */
    public array $failedWikis = [];


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Backend $backend The tool backend framework.
     * @param string|null $target The username or IP address to analyze.
     */
    public function __construct(Backend $backend, ?string $target)
    {
        parent::__construct();

        if (!$target)
            return;

        /* instantiate objects */
        $this->db = $backend->getDatabase(Toolserver::ERROR_PRINT);
        $this->db->connect('metawiki');

        /* store target (name, address, or range) */
        $this->target = $this->formatUsername($target);
        $this->targetUrl = urlencode($this->target);
        $this->targetWikiUrl = str_replace('+', '_', $this->targetUrl);

        /* fetch wikis */
        $this->domains = $this->db->getDomains();
        $this->wikis = $this->db->getWikis();
    }

    /**
     * Whether there is a username or IP address to analyze.
     */
    public function isValid(): bool
    {
        return !!$this->target;
    }

    /**
     * Set the current wiki to analyze.
     * @param string $wiki The database name of the wiki to analyze.
     */
    public function setWiki(string $wiki): void
    {
        $this->wiki = $wiki;
        $this->db->connect($wiki);
    }

    /**
     * Get details about a global account.
     * @param string $target The username for which to fetch details.
     */
    public function getGlobal(string $target): \Stalktoy\GlobalAccount
    {
        // fetch details
        $row = $this->db->query(
            '
                SELECT
                    gu_id,
                    gu_name,
                    DATE_FORMAT(gu_registration, "%Y-%m-%d %H:%i") AS gu_timestamp,
                    gu_locked,
                    GROUP_CONCAT(gug_group SEPARATOR ",") AS gu_groups,
                    lu_wiki
                FROM
                    centralauth_p.globaluser
                    LEFT JOIN centralauth_p.global_user_groups ON gu_id = gug_user
                    LEFT JOIN centralauth_p.localuser ON lu_name = ? AND lu_attached_method IN ("primary", "new")
                WHERE gu_name = ?
                LIMIT 1
            ',
            [$target, $target]
        )->fetchAssoc();

        // create model
        $account = new Stalktoy\GlobalAccount();
        $account->exists = isset($row['gu_id']);
        if ($account->exists) {
            $account->id = intval($row['gu_id']);
            $account->name = $row['gu_name'];
            $account->isLocked = boolval($row['gu_locked']);
            $account->registered = $row['gu_timestamp'];
            $account->groups = ($row['gu_groups'] ? explode(',', $row['gu_groups']) : []);
            $account->homeWiki = $row['lu_wiki'];
            $account->wikis = $this->db->getUnifiedWikis($this->target);
            $account->wikiHash = array_flip($account->wikis);
        }
        return $account;
    }

    /**
     * Get the user's global groups that apply for each wiki.
     * @param int $id The user's global account ID.
     * @param string[] $wikis The database names of the wikis on which the user's account is unified.
     * @returns array<string, string[]> An array of groups in the form `[dbname => string[]]`.
     */
    public function getGlobalGroupsByWiki(int $id, array $wikis): array
    {
        // fetch details
        $rows = $this->db->query(
            '
                SELECT
                    gug_group,
                    ws_type,
                    ws_wikis
                FROM
                    centralauth_p.global_user_groups
                    LEFT JOIN centralauth_p.global_group_restrictions ON gug_group = ggr_group
                    LEFT JOIN centralauth_p.wikiset ON ggr_set = ws_id
                WHERE gug_user = ?
            ',
            [$id]
        )->fetchAllAssoc();

        // extract groups for each wiki
        $groups = [];
        foreach ($wikis as $wiki)
            $groups[$wiki] = [];
        foreach ($rows as $row) {
            // prettify name
            $group = str_replace('_', ' ', $row['gug_group']);

            // parse opt-in or opt-out list
            $optList = [];
            if ($row['ws_wikis'] != null) {
                $list = explode(',', $row['ws_wikis']);
                foreach ($list as $wiki)
                    $optList[] = $wiki;
            }

            // apply groups
            switch ($row['ws_type']) {
                // all wikis
                case null:
                    foreach ($wikis as $wiki)
                        $groups[$wiki][] = $group;
                    break;

                // some wikis
                case 'optin':
                    foreach ($optList as $wiki)
                        $groups[$wiki][] = $group;
                    break;

                // all except some wikis
                case 'optout':
                    $optout = array_flip($optList);
                    foreach ($wikis as $wiki) {
                        if (!isset($optout[$wiki]))
                            $groups[$wiki][] = $group;
                    }
                    break;
            }
        }
        return $groups;
    }

    /**
     * Get global details about an IP address or range.
     * @param string|null $target The IP address or range for which to fetch details.
     */
    public function getGlobalIp(?string $target): \Stalktoy\GlobalIP
    {
        $ip = new Stalktoy\GlobalIP();

        // fetch IP address
        $ip->ip = new IPAddress($target);
        if (!$ip->ip->isValid())
            return $ip;

        // fetch global blocks
        $ip->globalBlocks = [];
        $start = $ip->ip->getEncoded(IPAddress::START);
        $end = $ip->ip->getEncoded(IPAddress::END);
        $query = $this->db->query(
            '
                SELECT
                    gb_address,
                    gu_name,
                    gb_reason,
                    DATE_FORMAT(gb_timestamp, "%Y-%b-%d") AS timestamp,
                    gb_anon_only,
                    DATE_FORMAT(gb_expiry, "%Y-%b-%d") AS expiry
                FROM
                    centralauth_p.globalblocks
                    LEFT JOIN centralauth_p.globaluser ON gb_by_central_id = gu_id
                WHERE
                    (gb_range_start <= ? AND gb_range_end >= ?)
                    OR (gb_range_start >= ? AND gb_range_end <= ?)
                ORDER BY gb_timestamp
            ',
            [$start, $end, $start, $end]
        )->fetchAllAssoc();

        foreach ($query as $row) {
            $block = new Stalktoy\Block();
            $block->by = $row['gu_name'];
            $block->target = $row['gb_address'];
            $block->timestamp = $row['timestamp'];
            $block->expiry = $row['expiry'];
            $block->reason = $row['gb_reason'];
            $block->anonOnly = boolval($row['gb_anon_only']);
            $block->isHidden = false;
            $ip->globalBlocks[] = $block;
        }

        return $ip;
    }

    /**
     * Get details about a user's local account on each of the given wikis.
     *
     * @param string $userName The name of the user for which to fetch local details.
     * @param array<string, Wiki> $wikis The wikis to check, indexed by database name.
     * @param array<string, int> $unifiedHash A lookup of the wikis on which the user's account is unified.
     * @return array<string, \Stalktoy\LocalAccount> The accounts which exist, indexed by database name, in the order the wikis were given.
     */
    public function getLocalAccounts(string $userName, array $wikis, array $unifiedHash): array
    {
        // fetch details
        $result = $this->db->queryManyWikis(
            array_keys($wikis),
            '
                SELECT
                    {dbname} AS wiki,
                    user_id,
                    user_registration,
                    DATE_FORMAT(user_registration, "%Y-%m-%d %H:%i") AS registration,
                    user_editcount,
                    GROUP_CONCAT(DISTINCT ug_group ORDER BY ug_group SEPARATOR ", ") AS user_groups,
                    actor.actor_id,
                    blocked_by_actor.actor_name AS bl_by_name,
                    comment_text AS bl_reason,
                    DATE_FORMAT(bl_timestamp, "%Y-%m-%d %H:%i") AS bl_timestamp,
                    bl_deleted,
                    COALESCE(DATE_FORMAT(bl_expiry, "%Y-%m-%d %H:%i"), bl_expiry) AS bl_expiry
                FROM
                    {db}.`user`
                    LEFT JOIN {db}.user_groups ON user_id = ug_user
                    LEFT JOIN {db}.actor ON user_id = actor.actor_user
                    LEFT JOIN {db}.block_target ON user_id = bt_user
                    LEFT JOIN {db}.block ON bt_id = bl_target
                    LEFT JOIN {db}.actor AS blocked_by_actor ON bl_by_actor = blocked_by_actor.actor_id
                    LEFT JOIN {db}.comment ON bl_reason_id = comment_id
                WHERE user_name = ?
                GROUP BY user_id
            ',
            [$userName]
        );
        $this->failedWikis = $result->failedWikis;

        // index by wiki
        $rowsByWiki = [];
        foreach ($result->rows as $row)
            $rowsByWiki[$row['wiki']] = $row;

        // build models, in the order the wikis were given
        $accounts = [];
        foreach ($wikis as $dbname => $wiki) {
            if (isset($rowsByWiki[$dbname]))
                $accounts[$dbname] = $this->buildLocalAccount($rowsByWiki[$dbname], $wiki, $userName, isset($unifiedHash[$dbname]));
        }

        // backfill registration dates for accounts created before MediaWiki tracked them (late
        // 2005 or earlier).
        foreach ($accounts as $dbname => $account) {
            if ($account->registeredRaw)
                continue;

            $this->setWiki($dbname);
            $date = $this->db->getRegistrationDate($account->id, $account->actorId, skipUserTable: true);
            if ($date) {
                $account->registered = $date['formatted'];
                $account->registeredRaw = $date['raw'];
            }
        }

        return $accounts;
    }

    /**
     * Build a model for a user which has no account on a wiki.
     *
     * @param Wiki $wiki The wiki on which the account doesn't exist.
     */
    public function getMissingLocalAccount(Wiki $wiki): \Stalktoy\LocalAccount
    {
        $account = new Stalktoy\LocalAccount();
        $account->wiki = $wiki;
        $account->exists = false;
        return $account;
    }

    /**
     * Get whether a wiki is participating in CentralAuth for global accounts.
     * @param string $dbname The database name.
     */
    public function getWikiUnifiable(string $dbname): bool
    {
        // in https://noc.wikimedia.org/conf/highlight.php?file=dblists/nonglobal.dblist
        return !in_array($dbname, ['labswiki', 'labtestwiki']);
    }

    ########
    ## Get hash of local IP blocks
    ########
    /**
     * Get the local blocks against editing by an IP address on each of the given wikis.
     *
     * @param \Stalktoy\GlobalIP $ip The IP address for which to fetch local blocks.
     * @param array<string, Wiki> $wikis The wikis to check, indexed by database name.
     * @return array<string, \Stalktoy\Block[]> The blocks on each wiki, indexed by database name.
     */
    public function getLocalIpBlocksByWiki(\Stalktoy\GlobalIP $ip, array $wikis): array
    {
        // init results
        $blocks = [];
        foreach ($wikis as $dbname => $wiki)
            $blocks[$dbname] = [];

        // find IP blocks on each wiki
        //
        // This first query deliberately avoids joins when possible. There's a significant overhead
        // to joining on the other views (even if there's no block to join with), and usually only
        // a few wikis will have blocked the IP.
        $start = $ip->ip->getEncoded(IPAddress::START);
        $end = $ip->ip->getEncoded(IPAddress::END);
        $result = $this->db->queryManyWikis(
            array_keys($wikis),
            '
                SELECT
                    {dbname} AS wiki,
                    bt_id,
                    bt_address
                FROM
                    {db}.block_target_ipindex
                WHERE
                    bt_address IS NOT NULL
                    AND CASE
                        WHEN bt_range_end IS NULL THEN
                            bt_ip_hex BETWEEN ?/*start*/ AND ?/*end*/
                        ELSE
                            bt_range_end >= ?/*start*/
                            AND bt_range_start <= ?/*end*/
                    END
            ',
            [$start, $end, $start, $end]
        );
        $this->failedWikis = $result->failedWikis;

        // group blocked IPs by wiki (dbname => address[])
        $targets = [];
        foreach ($result->rows as $row)
            $targets[$row['wiki']][$row['bt_id']] = $row['bt_address'];

        // fetch block info from each wiki
        foreach ($targets as $dbname => $addresses) {
            $blocks[$dbname] = $this->getBlockDetails($dbname, $addresses);
        }

        return $blocks;
    }

    /**
     * Get an HTML warning box if some wikis couldn't be queried.
     */
    public function renderQueryErrors(): string
    {
        if (!$this->failedWikis)
            return '';

        $count = count($this->failedWikis);
        if ($count <= 10) {
            $domains = [];
            foreach ($this->failedWikis as $dbname)
                $domains[] = $this->formatValue($this->wikis[$dbname]->domain ?? $dbname);
            $detail = implode(', ', $domains);
        }
        else
            $detail = "$count of " . count($this->wikis);

        return "<div class='error'><strong>Warning:</strong> couldn't query some wikis ($detail), so the results below may be incomplete.</div>\n";
    }

    /**
     * Get an HTML link for a domain.
     * @param string $domain The domain URL (if any).
     * @param string $title The link title.
     * @param string|null $text The link text (or null to use the title).
     */
    public function link(string $domain, string $title, string|int|null $text = null): string
    {
        if ($text === null)
            $text = $title;

        if (!$domain)
            return $text;
        else
            return "<a href='https://{$domain}/wiki/$title' title='$title'>$text</a>";
    }

    /**
     * Convert wikilink syntax in a block reason to HTML.
     * @param string $text The block reason to convert.
     * @param string $domain The wiki domain URL.
     */
    public function formatReason(string $text, string $domain): string
    {
        if (!preg_match_all('/\[\[([^\]]+)\]\]/', $text, $links))
            return $text;

        foreach ($links[1] as $i => $link) {
            $pieces = explode('|', $link);
            $linkTarget = $pieces[0];
            $linkText = isset($pieces[1]) ? $pieces[1] : $linkTarget;

            $text = str_replace($links[0][$i], "<a href='https://{$domain}/wiki/{$linkTarget}' title='{$linkText}'>{$linkText}</a>", $text);
        }

        return $text;
    }


    ##########
    ## Private methods
    ##########
    /**
     * Build a local account model from a DB row for a valid user.
     *
     * @param array<string, mixed> $row The result row.
     * @param Wiki $wiki The wiki on which the account was found.
     * @param string $userName The name of the user.
     * @param bool $isUnified Whether the user has a unified account on this wiki.
     */
    private function buildLocalAccount(array $row, Wiki $wiki, string $userName, bool $isUnified): \Stalktoy\LocalAccount
    {
        $account = new Stalktoy\LocalAccount();
        $account->wiki = $wiki;
        $account->exists = true;

        // account details
        $account->id = intval($row['user_id']);
        $account->actorId = intval($row['actor_id']);
        $account->registered = $row['registration'];
        $account->registeredRaw = $row['user_registration'];
        $account->editCount = intval($row['user_editcount']);
        $account->groups = $row['user_groups'];
        $account->isUnified = $isUnified;

        // block details
        $account->isBlocked = isset($row['bl_timestamp']);
        if ($account->isBlocked) {
            $account->block = new Stalktoy\Block();
            $account->block->by = $row['bl_by_name'] ?? '';
            $account->block->target = $userName;
            $account->block->reason = $row['bl_reason'] ?? '';
            $account->block->timestamp = $row['bl_timestamp'];
            $account->block->isHidden = boolval($row['bl_deleted']);
            $account->block->expiry = $row['bl_expiry'];
        }

        return $account;
    }

    /**
     * Get the block details for a set of block targets on one wiki.
     *
     * @param string $dbname The wiki's dbname.
     * @param array<int, string> $addresses The blocked addresses whose info to fetch, indexed by block target ID.
     * @return array<Stalktoy\Block> The fetched blocks.
     */
    private function getBlockDetails(string $dbname, array $addresses): array
    {
        // skip if invalid
        if (!$addresses)
            return [];

        if (!$this->db->tryConnect($dbname)) {
            $this->failedWikis[] = $dbname;
            return [];
        }

        // fetch from DB
        $query = $this->db->query(
            '
                SELECT
                    bl_target,
                    actor_name AS bl_by_name,
                    comment_text AS bl_reason,
                    bl_anon_only,
                    DATE_FORMAT(bl_timestamp, "%Y-%b-%d") AS timestamp,
                    DATE_FORMAT(bl_expiry, "%Y-%b-%d") AS expiry
                FROM
                    block
                    LEFT JOIN actor ON bl_by_actor = actor_id
                    LEFT JOIN comment ON bl_reason_id = comment_id
                WHERE bl_target IN (' . implode(',', array_fill(0, count($addresses), '?')) . ')
            ',
            array_keys($addresses)
        );
        if (!$query) {
            $this->failedWikis[] = $dbname;
            return [];
        }

        $rows = $query->fetchAllAssoc();
        if ($rows === false)
            return [];

        // build blocks
        $blocks = [];
        foreach ($rows as $row) {
            $block = new Stalktoy\Block();
            $block->target = $addresses[$row['bl_target']] ?? '';
            $block->by = $row['bl_by_name'] ?? '';
            $block->reason = $row['bl_reason'] ?? '';
            $block->timestamp = $row['timestamp'];
            $block->expiry = $row['expiry'];
            $block->anonOnly = boolval($row['bl_anon_only']);
            $block->isHidden = false;

            $blocks[] = $block;
        }
        return $blocks;
    }
}

<?php
/**
 * Implements logic for the Stalktoy tool.
 */
class StalktoyEngine extends Base
{
    ##########
    ## Properties
    ##########
    /**
     * The local user details.
     * @var array
     */
    private $local;


    ##########
    ## Accessors
    ##########
    /**
     * The lookup target.
     * @var string
     */
    public $target;

    /**
     * The lookup target formatted for injection into a URL.
     * @var string
     */
    public $targetUrl;

    /**
     * The lookup target formatted for injection into the page name portion of a wiki URL.
     * @var string
     */
    public $targetWikiUrl;

    /**
     * A lookup hash of wiki data.
     * @var Wiki[]
     */
    public $wikis;

    /**
     * A lookup hash of wiki domains.
     * @var string[]
     */
    public $domains;

    /**
     * The selected wiki.
     * @var Wiki|null
     */
    public $wiki;

    /**
     * (User lookups only.) Whether to show all wikis, even if the user doesn't have an account there.
     * @var bool
     */
    public $showAllWikis = false;

    /**
     * (User lookups only.) Whether to list relevant global groups next to each wiki.
     * @var bool
     */
    public $showGroupsPerWiki = false;

    /**
     * The database wrapper.
     * @var Toolserver
     */
    public $db;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Backend $backend The tool backend framework.
     * @param string $target The username or IP address to analyze.
     */
    public function __construct($backend, $target)
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
    public function isValid()
    {
        return !!$this->target;
    }

    /**
     * Set the current wiki to analyze.
     * @param string $wiki The database name of the wiki to analyze.
     */
    public function setWiki($wiki)
    {
        $this->wiki = $wiki;
        $this->db->connect($wiki);
        $this->local = [];
    }

    /**
     * Get details about a global account.
     * @param string $target The username for which to fetch details.
     * @return \Stalktoy\GlobalAccount
     */
    public function getGlobal($target)
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
            $account->id = $row['gu_id'];
            $account->name = $row['gu_name'];
            $account->isLocked = $row['gu_locked'];
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
     * @returns array An array of groups in the form array(dbname => string[]).
     */
    public function getGlobalGroupsByWiki($id, $wikis)
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
     * @param string $target The IP address or range for which to fetch details.
     * @return \Stalktoy\GlobalIP
     */
    public function getGlobalIP($target)
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
            $block->anonOnly = $row['gb_anon_only'];
            $block->isHidden = false;
            $ip->globalBlocks[] = $block;
        }

        return $ip;
    }

    /**
     * Get details about a local account.
     * @param Toolserver $db The database from which to fetch details.
     * @param string $userName The name of the user for which to fetch local details.
     * @param bool $isUnified Whether the user has a unified account on this wiki.
     * @param Wiki $wiki The wiki on which the account is being fetched.
     * @return \Stalktoy\LocalAccount
     */
    public function getLocal($db, $userName, $isUnified, $wiki)
    {
        // fetch details
        $row = $db->query(
            '
                SELECT
                    user_id,
                    user_registration,
                    DATE_FORMAT(user_registration, "%Y-%m-%d %H:%i") AS registration,
                    user_editcount,
                    GROUP_CONCAT(ug_group SEPARATOR ", ") AS user_groups,
                    ipb_by_actor,
                    ipb_reason_id,
                    DATE_FORMAT(ipb_timestamp, "%Y-%m-%d %H:%i") AS ipb_timestamp,
                    ipb_deleted,
                    COALESCE(DATE_FORMAT(ipb_expiry, "%Y-%m-%d %H:%i"), ipb_expiry) AS ipb_expiry
                FROM
                    user
                    LEFT JOIN user_groups ON user_id = ug_user
                    LEFT JOIN ipblocks_ipindex ON user_id = ipb_user
                WHERE user_name = ?
                LIMIT 1
            ',
            [$userName]
        )->fetchAssoc();

        // fetch actor ID if needed
        if ($row['user_id'])
            $row['actor_id'] = $db->query('SELECT actor_id FROM actor WHERE actor_user = ? LIMIT 1', [$row['user_id']])->fetchValue();

        // fetch block reason if needed
        if ($row['ipb_reason_id'])
        {
            $row['ipb_by_text'] = $db->query('SELECT actor_name FROM actor WHERE actor_id = ? LIMIT 1', [$row['ipb_by_actor']])->fetchValue();
            $row['ipb_reason'] = $db->query('SELECT comment_text FROM comment WHERE comment_id = ? LIMIT 1', [$row['ipb_reason_id']])->fetchValue();
        }

        // build model
        $account = new Stalktoy\LocalAccount();
        $account->exists = isset($row['user_id']);
        $account->wiki = $wiki;
        if ($account->exists) {
            // account details
            $account->id = $row['user_id'];
            $account->actorID = $row['actor_id'];
            $account->registered = $row['registration'];
            $account->registeredRaw = $row['user_registration'];
            $account->editCount = $row['user_editcount'];
            $account->groups = $row['user_groups'];
            $account->isUnified = $isUnified;

            // handle edge cases with older accounts
            if (!$account->registeredRaw) {
                $date = $db->getRegistrationDate($account->id, $account->actorID);
                $account->registered = $date['formatted'];
                $account->registeredRaw = $date['raw'];
            }

            // block details
            $account->isBlocked = isset($row['ipb_timestamp']);
            if ($account->isBlocked) {
                $account->block = new Stalktoy\Block();
                $account->block->by = $row['ipb_by_text'];
                $account->block->target = $userName;
                $account->block->reason = $row['ipb_reason'];
                $account->block->timestamp = $row['ipb_timestamp'];
                $account->block->isHidden = $row['ipb_deleted'];
                $account->block->expiry = $row['ipb_expiry'];
            }
        }

        return $account;
    }

    /**
     * Get whether a wiki is participating in CentralAuth for global accounts.
     * @param string $dbname The database name.
     * @return bool
     */
    public function getWikiUnifiable($dbname)
    {
        // in https://noc.wikimedia.org/conf/highlight.php?file=dblists/nonglobal.dblist
        return !in_array($dbname, ['labswiki', 'labtestwiki']);
    }

    ########
    ## Get hash of local IP blocks
    ########
    /**
     * Get a list of local blocks against editing by this IP address.
     * @param \Stalktoy\GlobalIP $ip
     * @return Stalktoy\Block[]
     */
    public function getLocalIPBlocks($ip)
    {
        // get blocks
        $start = $ip->ip->getEncoded(IPAddress::START);
        $end = $ip->ip->getEncoded(IPAddress::END);
        $query = $this->db->query(
            '
                -- find range blocks (which have `ipb_range_start` and `ipb_range_end` set)
                (
                    SELECT
                        ipb_by_actor,
                        ipb_address,
                        ipb_reason_id,
                        ipb_anon_only,
                        DATE_FORMAT(ipb_timestamp, "%Y-%b-%d") AS timestamp,
                        DATE_FORMAT(ipb_expiry, "%Y-%b-%d") AS expiry
                    FROM ipblocks_ipindex
                    WHERE
                        ipb_address IS NOT NULL
                        AND ipb_range_start IS NOT NULL
                        AND ipb_range_end >= ?/*start*/
                        AND ipb_range_start <= ?/*end*/
                )

                -- find single-IP blocks (where we need to calculate the IP hex ourselves)
                UNION ALL (
                    SELECT
                        ipb_by_actor,
                        ipb_address,
                        ipb_reason_id,
                        ipb_anon_only,
                        DATE_FORMAT(ipb_timestamp, "%Y-%b-%d") AS timestamp,
                        DATE_FORMAT(ipb_expiry, "%Y-%b-%d") AS expiry
                    FROM ipblocks_ipindex
                    WHERE
                        ipb_address IS NOT NULL
                        AND ipb_range_start IS NULL
                        AND LPAD(HEX(INET_ATON(ipb_address)), 8, \'0\') BETWEEN ?/*start*/ and ?/*end*/
                )
            ',
            [$start, $end, $start, $end]
        )->fetchAllAssoc();

        // build model
        $blocks = [];
        foreach ($query as $row) {
            $block = new Stalktoy\Block();
            $block->target = $row['ipb_address'];
            $block->timestamp = $row['timestamp'];
            $block->expiry = $row['expiry'];
            $block->anonOnly = $row['ipb_anon_only'];
            $block->isHidden = false;

            $block->by = $this->db->query("SELECT actor_name FROM actor WHERE actor_id = ? LIMIT 1", [$row['ipb_by_actor']])->fetchValue();
            if ($row['ipb_reason_id'])
                $block->reason = $this->db->query("SELECT comment_text FROM comment WHERE comment_id = ? LIMIT 1", [$row['ipb_reason_id']])->fetchValue();

            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Get an HTML link for a domain.
     * @param string $domain The domain URL (if any).
     * @param string $title The link title.
     * @param string|null $text The link text (or null to use the title).
     * @return string
     */
    function link($domain, $title, $text = null)
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
     * @return string
     */
    function formatReason($text, $domain)
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
}

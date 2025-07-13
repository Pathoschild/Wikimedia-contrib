<?php
declare(strict_types=1);

/**
 * The tool engine.
 */
class CatanalysisEngine extends Base
{
    ##########
    ## Public methods
    ##########
    /**
     * Get the HTML for a bar to show in a bar graph.
     * @param string $label The bar label.
     * @param int $total The total value across all bars.
     * @param int $barvalue The value of this bar.
     * @param bool $strike Whether to format the label as struck out.
     */
    public function getBarHtml(string $label, int $total, int $barvalue, bool $strike = false): string
    {
        $bars = floor($total / $barvalue);
        $out = '';

        $out .= '<tr><td';
        if ($strike)
            $out .= ' class="struckout"';
        $out .= '>' . $label . '</td><td><b>';
        for ($i = 0; $i < $bars; $i++)
            $out .= '|';
        $out .= '</b></td><td><small>';
        if ($total < 0)
            $out .= '<span style="color:#C00; font-weight:bold;">' . $total . '</span>';
        else
            $out .= $total;
        $out .= '</small></td></tr>';
        return $out;
    }

    /**
     * Get the HTML for a link.
     * @param string $url The base wiki URL.
     * @param string $target The link URL.
     * @param string|null $text The link text.
     */
    public function getLinkHtml(string $url, string $target, ?string $text = null): string
    {
        $text = $this->formatText($text ? $text : $target);
        $target = $this->formatValue($target);
        return "<a href='$url/wiki/$target' title='$target'>$text</a>";
    }

    /**
     * Get the namespace name given the namespace ID.
     * @param int $id The namespace ID.
     * @return string
     */
    public function getNamespaceName(int $id): string
    {
        switch ($id) {
            // built-in namespaces (https://www.mediawiki.org/wiki/Manual:Namespace#Built-in_namespaces)
            case 0:
                return '';
            case 1:
                return 'Talk';
            case 2:
                return 'User';
            case 3:
                return 'User talk';
            case 4:
                return 'Project';
            case 5:
                return 'Project talk';
            case 6:
                return 'Image';
            case 7:
                return 'Image talk';
            case 8:
                return 'MediaWiki';
            case 9:
                return 'MediaWiki talk';
            case 10:
                return 'Template';
            case 11:
                return 'Template talk';
            case 12:
                return 'Help';
            case 13:
                return 'Help talk';
            case 14:
                return 'Category';
            case 15:
                return 'Category talk';

            // Wikisource
            case 104:
                return 'Page';
            case 105:
                return 'Page talk';
            case 106:
                return 'Index';
            case 107:
                return 'Index talk';
            case 108:
                return 'Author';
            case 109:
                return 'Author talk';

            // special
            case 828:
                return 'Module';
            case 829:
                return 'Module talk';
            case 2300:
                return 'Gadget';
            case 2301:
                return 'Gadget talk';
            case 2302:
                return 'Gadget definition';
            case 2303:
                return 'Gadget definition talk';
            case 2600:
                return 'Topic';

            // fallback
            default:
                return '{{ns:' . $id . '}}';
        }
    }

    /**
     * Get a SQL query that fetches all the edits to pages with a common prefix.
     * @param Database $db The connected database instance.
     * @param string|null $namespace The namespace to search.
     * @param string $title The page prefix to search.
     * @return Database The Database instance to chain query methods.
     */
    public function getEditsByPrefix(Database $db, ?string $namespace, string $title): Database
    {
        /* build initial query */
        $sql = '
            SELECT
                page.page_namespace,
                page.page_title,
                page.page_is_redirect,
                page.page_is_new,
                revision.rev_minor_edit,
                revision.rev_actor,
                revision.rev_timestamp,
                revision.rev_len,
                revision.rev_page
            FROM
                revision
                LEFT JOIN page ON page.page_id = revision.rev_page
        ';
        $values = [];

        /* add namespace */
        if ($namespace) {
            $sql .= '
                    JOIN toolserver.namespace ON page.page_namespace = toolserver.namespace.ns_id
                WHERE
                    toolserver.namespace.ns_name = ?
                    AND ';
            $values[] = $namespace;
        }
        else
            $sql .= 'WHERE ';

        /* add prefix & revision filter */
        $sql .= '
                revision.rev_actor IS NOT NULL
                AND revision.rev_len IS NOT NULL -- ignore suppressed revisions
                AND (page_title=? OR page_title LIKE ?)
            ORDER BY revision.rev_timestamp
        ';
        $values[] = str_replace(' ', '_', $title);
        $values[] = str_replace(' ', '_', $title . '%');

        /* fetch results */
        return $db->query($sql, $values);
    }

    /**
     * Get a SQL query that fetches all the edits to pages in a category or its subcategories.
     * @param Database $db The connected database instance.
     * @param string $title The category title to search.
     * @return Database The Database instance to chain query methods.
     */
    public function getEditsByCategory(Database $db, string $title): Database
    {
        /* fetch list of subcategories */
        $cats = [];
        $queue = [$title];
        while (count($queue)) {
            /* fetch subcategories of currently-known categories */
            $dbCatQuery = 'SELECT page_title FROM page JOIN categorylinks ON page_id=cl_from WHERE page_namespace=14 AND cl_to IN (';
            $dbCatValues = [];
            while (count($queue)) {
                if (!in_array($queue[0], $cats)) {
                    $dbCatQuery .= '?,';
                    $dbCatValues[] = str_replace(' ', '_', $queue[0]);
                    $cats[] = array_shift($queue);
                } else
                    array_shift($queue);
            }
            $dbCatQuery = rtrim($dbCatQuery, ',') . ')';

            /* queue subcategories */
            if (count($dbCatValues) == 0)
                continue;
            $subcats = $db->query($dbCatQuery, $dbCatValues)->fetchAllAssoc();
            foreach ($subcats as $subcat) {
                $queue[] = $subcat['page_title'];
            }
        }

        /* build initial SQL query */
        $sql = '
            SELECT
                page.page_namespace,
                page.page_title,
                page.page_is_redirect,
                page.page_is_new,
                revision.rev_minor_edit,
                revision.rev_actor,
                revision.rev_timestamp,
                revision.rev_len,
                revision.rev_page
            FROM
                revision
                INNER JOIN page ON page.page_id = revision.rev_page
                INNER JOIN (
                    SELECT DISTINCT cl_from
                    FROM categorylinks
                    WHERE cl_to IN (
        ';

        /* add category values */
        $values = [];
        foreach ($cats as $cat) {
            $sql .= '?,';
            $values[] = str_replace(' ', '_', $cat);
        }
        $sql = rtrim($sql, ', ');
        
        /* finish query */
        $sql .= '
                    )
                ) AS catlink ON page.page_id = catlink.cl_from
            WHERE
                revision.rev_actor IS NOT NULL
                AND revision.rev_len IS NOT NULL -- ignore suppressed revisions
            ORDER BY revision.rev_timestamp
        ';

        /* fetch results */
        return $db->query($sql, $values);
    }

    /**
     * Get whether the given username matches an anonymous user.
     * @param string $name The username.
     */
    public function isAnonymousUser(string $name): bool
    {
        $ip = new IPAddress($name);
        return $ip->isValid();
    }

    /**
     * Get metadata about matching revisions.
     * @param Database $db The connected database instance.
     * @param Database $revisionQuery The revision query.
     * @return Metrics The revision metrics.
     */
    public function getEditMetrics(Database $db, Database $revisionQuery): Metrics {
        $metrics = new Metrics();

        // fetch revisions
        $revisions = $revisionQuery->fetchAllAssoc();

        // fetch actor names
        $actorNames = [];
        foreach ($revisions as $row)
            $actorNames[$row['rev_actor']] = null;
        if (count($actorNames) > 0)
        {
            foreach ($db->query('SELECT actor_id, actor_name FROM actor WHERE actor_id IN (' . implode(',', array_keys($actorNames)) .')')->fetchAllAssoc() as $actor)
                $actorNames[$actor['actor_id']] = $actor['actor_name'];
        }

        // process data
        foreach ($revisions as $row) {
            $row['actor_name'] = $actorNames[$row['rev_actor']];

            $monthKey = preg_replace('/^(\d{4})(\d{2}).+$/', '$1-$2', $row['rev_timestamp']);
            $isNew = !array_key_exists($row['rev_page'], $metrics->pages);
            $isAnonymous = $this->isAnonymousUser($row['actor_name']);
            $username = $isAnonymous ? '(Anonymous)' : $row['actor_name'];

            // init hashes if needed
            $month = $this->initArrayKey($metrics->months, $monthKey, fn() => new MonthMetrics());
            $user = $this->initArrayKey($metrics->users, $username, fn() => new UserData());
            $page = $this->initArrayKey($metrics->pages, (string)$row['rev_page'], fn() => new PageData());
            $monthUser = $this->initArrayKey($month->users, $username, fn() => new UserData());

            // update metrics
            $metrics->edits++;
            $month->name = $monthKey;
            $month->edits++;
            $month->bytesAdded += $row['rev_len'] - $page->size;
            if ($isNew)
                $month->newPages++;

            // update user data
            $user->name = $username;
            $user->edits++;
            $user->isAnonymous = $isAnonymous;
            $monthUser->name = $username;
            $monthUser->edits++;
            $monthUser->isAnonymous = $isAnonymous;

            // update page data
            $page->id = $row['rev_page'];
            $page->namespace = $row['page_namespace'];
            if (!isset($page->name)) {
                $page->name = str_replace('_', ' ', $row['page_title']);
                $namespaceName = $this->getNamespaceName($page->namespace);
                if($namespaceName)
                    $page->name = $namespaceName . ':' . $page->name;
            }
            $page->edits++;
            $page->size = $row['rev_len'];
            $page->isRedirect = boolval($row['page_is_redirect']);
        }
        unset($bytesAdded, $month, $monthKey, $prevSize, $query, $row);

        // collapse lookups into arrays & sort
        $metrics->months = array_values($metrics->months);
        $metrics->pages = array_values($metrics->pages);
        $metrics->users = array_values($metrics->users);

        usort($metrics->months, fn($a, $b) => strcmp($a->name, $b->name));
        usort($metrics->pages, fn($a, $b) => strcmp($a->name, $b->name));
        usort($metrics->users, fn($a, $b) => strcmp($a->name, $b->name));

        foreach($metrics->months as &$month) {
            $month->users = array_values($month->users);
            usort($month->users, fn($a, $b) => strcmp($a->name, $b->name));
        }

        return $metrics;
    }

    /**
     * Fetch bot flags and update the given metrics.
     * @param Database $db The connected database instance.
     * @param Metrics $metrics The revision metrics.
     * @return Metrics The revision metrics.
     */
    public function flagBots(Database $db, Metrics &$metrics): Metrics
    {
        // get list of usernames
        $users = array_map(fn($user) => $user->name, $metrics->users);

        // get flags
        $bots = [];
        if (count($users) > 0)
        {
            $query = $db->query('SELECT user_name FROM user INNER JOIN user_groups ON user_id = ug_user WHERE user_name IN (' . rtrim(str_repeat('?,', count($users)), ',') . ') AND ug_group = "bot"', $users);
            while ($user = $query->fetchValue())
                $bots[$user] = true;
            unset($user, $query);
        }

        // flag bots in overall metrics
        foreach ($metrics->users as &$user)
            $user->isBot = array_key_exists($user->name, $bots);
        unset($user);

        // flag bots in monthly metrics
        foreach ($metrics->months as &$month)
        {
            foreach ($month->users as &$user)
                $user->isBot = array_key_exists($user->name, $bots);
        }
        unset($month, $user);

        // count non-bot edits
        foreach ($metrics->months as &$month) {
            foreach ($month->users as &$user) {
                if (!$user->isBot) {
                    $metrics->editsExcludingBots += $user->edits;
                    $month->editsExcludingBots += $user->edits;
                }
            }
        }
        unset($month, $user);

        return $metrics;
    }


    ##########
    ## Private methods
    ##########
    /**
     * Initialise an array key with the given default value if it isn't defined yet.
     * @template T
     * @param array<string, mixed> $array The array whose key to set.
     * @param string $key The array key to set.
     * @param callable(): T $default The default value to set if the key isn't defined.
     * @return T Returns the value at that key.
     */
    private function initArrayKey(array &$array, string $key, callable $default): mixed
    {
        if (!array_key_exists($key, $array))
            $array[$key] = $default();

        return $array[$key];
    }
}

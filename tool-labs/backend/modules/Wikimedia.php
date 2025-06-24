<?php
declare(strict_types=1);

require_once(__DIR__ . '/../models/Wiki.php');

/**
 * Manages data about Wikimedia wikis.
 */
class Wikimedia
{
    ##########
    ## Properties
    ##########
    /**
     * The underlying Wikimedia wiki and database data.
     * @var Wiki[]
     */
    private array $wikis;

    /**
     * The database names that should be ignored.
     * @var string[]
     */
    private array $ignoreDbNames;


    ##########
    ## Public methods
    ##########
    /**
     * Construct a Wikimedia instance.
     * @param Database $db The database with which to connect to the database.
     * @param Cacher $cache The cache with which to read and write cached data.
     * @param Profiler $profiler Provides basic performance profiling.
     * @param string[] $ignoreDbNames The database names that should be ignored.
     */
    public function __construct(Database $db, Cacher $cache, Profiler $profiler, array $ignoreDbNames)
    {
        $this->ignoreDbNames = $ignoreDbNames;

        $wikis = $cache->get('wikimedia-wikis');
        if (!$wikis) {
            $profiler->start('DB: fetch wiki metadata');
            // build wiki list
            $wikis = [];
            $db->connect('metawiki.web.db.svc.wikimedia.cloud', 'metawiki_p');
            foreach ($db->query('SELECT dbname, lang, family, url, size, is_closed, slice FROM meta_p.wiki WHERE url IS NOT NULL')->fetchAllAssoc() as $row)
            {
                if(!in_array($row['dbname'], $ignoreDbNames))
                {
                    $shard = preg_replace('/^(s\d+).*$/', '$1', $row['slice']); // e.g. s2.labsdb => s2
                    $host = $shard . '.web.db.svc.wikimedia.cloud';
                    $wikis[$row['dbname']] = new Wiki(
                        name: $row['dbname'],
                        lang: $row['lang'],
                        family: $row['family'],
                        url: $row['url'],
                        size: intval($row['size']),
                        isClosed: boolval($row['is_closed']),
                        serverName: $host
                    );
                }
            }

            // cache result
            if (count($wikis)) // if the fetch failed, we *don't* want to cache the result for a full day
                $cache->save('wikimedia-wikis', $wikis);
            $db->connectPrevious();
            $profiler->stop('DB: fetch wiki metadata');
        }

        $this->wikis = $wikis;
    }

    /**
     * Get a database name => wiki lookup.
     * @return Wiki[]
     */
    public function getWikis(): array
    {
        return $this->wikis;
    }

    /**
     * Get the data for a wiki.
     * @param string $dbname The wiki's unique database name.
     */
    public function getWiki(string $dbname): ?Wiki
    {
        if (array_key_exists($dbname, $this->wikis))
            return $this->wikis[$dbname];
        return null;
    }

    /**
     * Get the domain for a database name.
     * @param string $dbname The database name to find.
     */
    public function getDomain(string $dbname): ?string
    {
        $wiki = $this->getWiki($dbname);
        return $wiki != null ? $wiki->domain : null;
    }

    /**
     * Get the host name for a database name.
     * @param string $dbname The database name to find.
     */
    public function getHost(string $dbname): ?string
    {
        $wiki = $this->getWiki($dbname);
        return $wiki != null ? $wiki->host : null;
    }

    /**
     * Get a database name => domain lookup.
     * @param bool $includeClosed Whether to include closed wikis.
     * @return array<string, string>
     */
    public function getDomains(bool $includeClosed = false): array
    {
        $wikis = [];
        foreach ($this->wikis as $wiki) {
            if ($includeClosed || !$wiki->isClosed)
                $wikis[$wiki->dbName] = $wiki->domain;
        }
        asort($wikis);
        return $wikis;
    }
}

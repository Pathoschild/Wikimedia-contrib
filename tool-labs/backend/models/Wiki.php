<?php
declare(strict_types=1);

/**
 * Represents a Wikimedia wiki and database.
 */
class Wiki
{
    ##########
    ## Accessors
    ##########
    /**
     * The simplified database name (dbname), like 'enwiki'.
     */
    public string $name;

    /**
     * The database name (dbname), like 'enwiki'.
     */
    public string $dbName;

    /**
     * The ISO 639 language code associated with the wiki. (A few wikis have invalid codes like 'zh-classical' or 'noboard-chapters'.)
     */
    public string $lang;

    /**
     * The wiki family (project name), like 'wikibooks'.
     */
    public string $family;

    /**
     * The base URL, like 'https://en.wikisource.org'.
     */
    public string $url;

    /**
     * The domain portion of the URL, like 'en.wikisource.org'.
     */
    public string $domain;

    /**
     * The number of articles on the wiki (?).
     */
    public int $size;

    /**
     * Whether the wiki is locked and no longer editable by the public.
     */
    public bool $isClosed;

    /**
     * The name of the server on which the wiki's replicated database is located.
     */
    public string $serverName;

    /**
     * The host name of the server on which the wiki's replicated database is located.
     */
    public string $host;

    /**
     * Whether the wiki contains content in multiple languages.
     */
    public bool $isMultilingual;


    ##########
    ## Public methods
    ##########
    /**
     * Construct a Wiki instance.
     * @param string $name The simplified database name (dbname), like 'enwiki'.
     * @param string $lang The ISO 639 language code associated with the wiki. (A few wikis have invalid codes like 'zh-classical' or 'noboard-chapters'.)
     * @param string $family The wiki family (project name), like 'wikibooks'.
     * @param string $url The base URL, like 'https://en.wikisource.org'.
     * @param int $size The number of articles on the wiki (?).
     * @param bool|int $isClosed Whether the wiki is locked and no longer editable by the public.
     * @param string $serverName The name of the server on which the wiki's replicated database is located.
     */
    public function __construct(string $name, string $lang, string $family, string $url, int $size, bool|int $isClosed, string $serverName)
    {
        $this->dbName = $name;
        $this->name = $name;
        $this->lang = $lang;
        switch ($name) {
            case 'commonswiki':
                $this->family = 'commons';
                break;
            case 'incubatorwiki':
                $this->family = 'incubator';
                break;
            case 'mediawikiwiki':
                $this->family = 'mediawiki';
                break;
            case 'metawiki':
                $this->family = 'meta';
                break;
            case 'specieswiki':
                $this->family = 'wikispecies';
                break;
            case 'wikidatawiki':
                $this->family = 'wikidata';
                break;
            default:
                $this->family = $family;
                break;
        }
        $this->url = $url;
        $this->domain = preg_replace('/^https?:\/\//', '', $url);
        $this->size = $size;
        $this->isClosed = boolval($isClosed);
        $this->serverName = $serverName;
        $this->host = $serverName;
        $this->isMultilingual = in_array($name, ['commonswiki', 'incubatorwiki', 'mediawikiwiki', 'metawiki', 'specieswiki', 'wikidatawiki']);
    }
}

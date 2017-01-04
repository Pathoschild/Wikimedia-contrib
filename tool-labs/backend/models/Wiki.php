<?php

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
     * @var string
     */
    public $name = null;

    /**
     * The database name (dbname), like 'enwiki'.
     * @var string
     */
    public $dbName = null;

    /**
     * The ISO 639 language code associated with the wiki. (A few wikis have invalid codes like 'zh-classical' or 'noboard-chapters'.)
     * @var string
     */
    public $lang = null;

    /**
     * The wiki family (project name), like 'wikibooks'.
     * @var string
     */
    public $family = null;

    /**
     * The base URL, like 'https://en.wikisource.org'.
     * @var string
     */
    public $url = null;

    /**
     * The domain portion of the URL, like 'en.wikisource.org'.
     * @var string
     */
    public $domain = null;

    /**
     * The number of articles on the wiki (?).
     * @var int
     */
    public $size = null;

    /**
     * Whether the wiki is locked and no longer editable by the public.
     * @var bool
     */
    public $isClosed = null;

    /**
     * The name of the server on which the wiki's replicated database is located.
     * @var int
     */
    public $serverName = null;

    /**
     * The host name of the server on which the wiki's replicated database is located.
     * @var bool
     */
    public $host = null;

    /**
     * Whether the wiki contains content in multiple languages.
     * @var bool
     */
    public $isMultilingual = null;


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
     * @param bool $isClosed Whether the wiki is locked and no longer editable by the public.
     * @param string $serverName The name of the server on which the wiki's replicated database is located.
     */
    public function __construct($name, $lang, $family, $url, $size, $isClosed, $serverName)
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
        $this->isClosed = $isClosed;
        $this->serverName = $serverName;
        $this->host = $serverName;
        $this->isMultilingual = in_array($name, array('commonswiki', 'incubatorwiki', 'mediawikiwiki', 'metawiki', 'specieswiki', 'wikidatawiki'));
    }
}
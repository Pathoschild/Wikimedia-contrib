<?php
/**
 * Encapsulates searching the code database.
 */
class Iso639dbEngine extends Base
{
    ##########
    ## Properties
    ##########
    /**
     * The maximum number of languages to list.
     */
    const MAX_LIMIT = 1000;

    /**
     * The language name to search for.
     */
    public $name = null;

    /**
     * The language code to search for.
     */
    public $code = null;

    /**
     * The number of records to skip.
     */
    public $offset = 0;

    /**
     * The ISO 639 datasets to search.
     */
    public $filters = [];

    /**
     * Provides a wrapper used by page scripts to generate HTML, interact with the database, and so forth.
     * @var Backend
     */
    private $backend;

    /**
     * The possible values for predefined fields.
     * @var string[][]
     */
    private $fieldValues = [
        'list' => ['1', '2', '2/B', '3'],
        'scope' => ['individual', 'dialect', 'macrolanguage', 'collection', 'reserved', 'special'],
        'type' => ['living', 'extinct', 'ancient', 'historic', 'constructed']
    ];


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Backend $backend Provides a wrapper used by page scripts to generate HTML, interact with the database, and so forth.
     */
    public function __construct($backend)
    {
        parent::__construct();

        $this->backend = $backend;
        $this->name = $backend->get('name');
        $this->code = $backend->get('code');
        $this->filters = $backend->get('filters') ?? [];

        $this->offset = $backend->get('offset', 0);
        if ($this->offset < 0 || !is_int($this->offset))
            $this->offset = 0;
    }

    /**
     * Fetch the matching language data from the database.
     * @return array
     */
    public function execute()
    {
        // build SQL query
        $conditions = [];
        $values = [];
        if ($this->name || $this->code) {
            $q = [];

            if ($this->name) {
                $q[] = 'INSTR(`name`, ?)';
                $q[] = 'INSTR(`native_name`, ?)';
                $values[] = $this->name;
                $values[] = $this->name;
            }
            if ($this->code) {
                $q[] = '`code` = ?';
                $values[] = $this->code;
            }
            $conditions[] = '(' . implode(' OR ', $q) . ')';
        }

        if ($this->filters != null) {
            foreach ($this->fieldValues as $field => $fieldValues) {
                $v = [];
                foreach ($fieldValues as $fieldValue) {
                    if (in_array($fieldValue, $this->filters)) {
                        $values[] = $fieldValue;
                        $v[] = '?';
                    }
                }

                if (count($v))
                    $conditions[] = '`' . $field . '` IN(' . implode(',', $v) . ')';
            }
        }

        $sql = 'SELECT * FROM `codes`';
        if (count($conditions))
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $sql .= ' ORDER BY `list`, `code` LIMIT ' . Iso639dbEngine::MAX_LIMIT . ' OFFSET ' . $this->offset;

        // fetch results database
        $db = $this->backend->getDatabase();
        $db->connect('sql.toolserver.org', 'u_pathoschild_iso639');
        $db->query($sql, $values);
        $result = $db->fetchAllAssoc();
        $db->dispose();

        return $result;
    }

    /**
     * Get HTML for a filter dropdown option.
     * @param string $key The filter key.
     * @param string $text The display text.
     * @return string
     */
    function getFilterOptionHtml($key, $text = null)
    {
        if (!$text)
            $text = $key;
        $checked = in_array($key, $this->filters);
        return "<option value='{$this->backend->formatValue($key)}'" . ($checked ? " selected='selected'" : "") . ">{$this->backend->formatText($text)}</option>";
    }
}

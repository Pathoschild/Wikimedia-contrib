<?php
require_once('../backend/modules/Backend.php');
require_once('../backend/modules/Database.php');
$backend = Backend::create('ISO 639 database', 'A searchable database of languages and ISO 639 codes augmented by native language names from Wikipedia.')
    ->link('/iso639db/stylesheet.css')
    ->link('/content/jquery.collapse/jquery.collapse.js')
    ->link('/content/jquery.collapse/jquery.cookie.js')
    ->link('/content/jquery.multiselect/jquery.multiselect.js')
    ->link('/content/jquery.multiselect/jquery.multiselect.css')
    ->link('scripts.js')
    ->header();

####################
## Script
####################
/**
 * Encapsulates searching the code database.
 */
class Script
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
        $this->backend = $backend;
        $this->name = $backend->get('name');
        $this->code = $backend->get('code');
        $this->filters = $backend->get('filters');

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
        $sql .= ' ORDER BY `list`, `code` LIMIT ' . Script::MAX_LIMIT . ' OFFSET ' . $this->offset;

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

####################
## Output form
####################
$script = new Script($backend);

echo "
    <div class='search'>
        <h2>Which languages would you like to see?</h2>
    
        <form action='{$backend->url('/iso639db')}' method='get'>
            <label for='code'>By ISO 639 code:</label>
            <input type='text' id='code' name='code' value='{$backend->formatValue($script->code)}' style='width:3em;' disabled />
    
            <label for='name'>or language name:</label>
            <input type='text' id='name' name='name' value='{$backend->formatValue($script->name)}' disabled />
    
            <select id='filters' name='filters[]' multiple='multiple' disabled><!--Only show languages...-->
                <optgroup label='in:'>",
                    $script->getFilterOptionHtml('1', 'ISO 639-1'),
                    $script->getFilterOptionHtml('2', 'ISO 639-2'),
                    $script->getFilterOptionHtml('2/B', 'ISO 639-2/B'),
                    $script->getFilterOptionHtml('3', 'ISO 639-3'), "
                </optgroup>
                <optgroup label='of scope:'>
                    <!--<a href='http://www.sil.org/iso639-3/scope.asp' title='about scopes'>scope</a>-->",
                    $script->getFilterOptionHtml('individual'),
                    $script->getFilterOptionHtml('dialect'),
                    $script->getFilterOptionHtml('macrolanguage'),
                    $script->getFilterOptionHtml('collection'),
                    $script->getFilterOptionHtml('reserved'),
                    $script->getFilterOptionHtml('special'), "
                </optgroup>
                <optgroup label='of type:'>",
                    $script->getFilterOptionHtml('living'),
                    $script->getFilterOptionHtml('extinct'),
                    $script->getFilterOptionHtml('ancient'),
                    $script->getFilterOptionHtml('historic'),
                    $script->getFilterOptionHtml('constructed'), "
                </optgroup>
            </select><br/>
    
            <input type='submit' value='search' disabled/>
        </form>
    </div>
    ";

####################
## Disabled
####################
echo "<div class='error'>This tool didn't survive the transition to Wikimedia Toolforge. It's temporarily offline until it's reimplemented. Sorry!</div>";
$backend->footer();
die();

####################
## Search result
####################
$rows = $script->execute();
if (!count($rows))
    echo "<div class='neutral'>There are no results matching your search.</div>";
else {
    echo "
        <table>
            <tr>
                <th>standard</th>
                <th>code</th>
                <th>name</th>
                <th>type</th>
                <th>scope</th>
                <th>notes</th>
            </tr>
            <tbody>
        ";
    foreach ($rows as $row) {
        echo "
            <tr>
                <td><span style='color:gray;'>ISO 639-</span>{$row['list']}</td>
                <td>{$row['code']}</td>
                <td>{$row['name']}", ($row['native_name'] && $row['name'] != $row['native_name'] ? "; <i>{$row['native_name']}</i>" : ""), "</td>
                <td>{$row['type']}</td>
                <td>{$row['scope']}</td>
                <td>{$row['notes']}</td>
            </tr>
            ";
    }
    echo "
            </tbody>
        </table>
        ";

    if (count($rows) == Script::MAX_LIMIT)
        echo "<div class='neutral'>Only the first ", Script::MAX_LIMIT, " matching languages are shown.</div>";
}

$backend->footer();
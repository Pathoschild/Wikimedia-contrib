<?php
require_once('../backend/modules/Backend.php');
require_once('../backend/modules/Database.php');
$backend = Backend::create('ISO 639 database', 'A searchable database of languages and ISO 639 codes augmented by native language names from Wikipedia.')
	->link('/iso639db/stylesheet.css')
	->link('/content/jquery.collapse/jquery.collapse.js')
	->link('/content/jquery.collapse/jquery.cookie.js')
	->link('/content/jquery.multiselect/jquery.multiselect.js')
	->link('/content/jquery.multiselect/jquery.multiselect.css')
	->header();

####################
## Script
####################
/**
 * Encapsulates searching the code database.
 */
class Script {
	##########
	## Properties
	##########
	const MAX_LIMIT = 1000;
	
	/**
	 * The language name to search for.
	 */
	public $name = NULL;
	
	/**
	 * The language code to search for.
	 */
	public $code = NULL;
	
	/**
	 * The number of records to skip.
	 */
	public $offset = 0;
	
	/**
	 * The ISO 639 datasets to search.
	 */
	public $filters = array();
	
	/**
	 * The database query representing the search.
	 */
	public $query = '';
	
	/**
	 * The SQL conditions represented by the search criteria.
	 */
	public $conditions = array();
	
	/**
	 * The parameterised values passed into the query.
	 */
	public $values = array();
	
	protected $fieldValues = array(
		'list' => array('1', '2', '2/B', '3'),
		'scope' => array('individual', 'dialect', 'macrolanguage', 'collection', 'reserved', 'special'),
		'type' => array('living', 'extinct', 'ancient', 'historic', 'constructed')
	);

	##########
	## Methods
	##########
	public function __construct() {
		global $backend;
		
		// search
		$this->name = $backend->get('name');
		$this->code = $backend->get('code');
		$this->filters = $backend->get('filters');
		
		// pagination
		$this->offset = $backend->get('offset', 0);
		if( $this->offset < 0 || !is_int($this->offset) )
			$this->offset = 0;
	}
	
	public function execute() {
		$this->buildQuery();
		global $backend;
		
		$db = new Database($backend->logger);
		$db->connect('sql.toolserver.org', 'u_pathoschild_iso639');
		$db->query($this->query, $this->values);
		$result = $db->fetchAllAssoc();
		$db->dispose();
		
		return $result;
	}

	protected function buildQuery() {
		$conditions = array();
		$values = array();
		
		// search strings
		if( $this->name || $this->code ) {
			$q = array();
			
			if( $this->name ) {
				$q[] = 'INSTR(`name`, ?)';
				$q[] = 'INSTR(`native_name`, ?)';
				$values[] = $this->name;
				$values[] = $this->name;
			}
			if( $this->code ) {
				$q[] = '`code` = ?';
				$values[] = $this->code;
			}
			$conditions[] = '(' . implode(' OR ', $q) . ')';
		}
		
		// filters
		if($this->filters != null) {
			foreach($this->fieldValues as $field => $fieldValues) {
				$v = array();
				foreach($fieldValues as $fieldValue) {
					if(in_array($fieldValue, $this->filters)) {
						$values[] = $fieldValue;
						$v[] = '?';
					}
				}
				
				if( count($v) )
					$conditions[] = '`' . $field . '` IN(' . implode(',', $v) . ')';
			}
		}

		/* build query */
		$sql = 'SELECT * FROM `codes`';
		if( count($conditions) )
			$sql .= ' WHERE ' . implode(' AND ', $conditions);
		$sql .= ' ORDER BY `list`, `code` LIMIT ' . Script::MAX_LIMIT . ' OFFSET ' . $this->offset;
		
		$this->query = $sql;
		$this->values = $values;
	}
};
$script = new Script();

####################
## Output form
####################
//input form
function filterOption($key, $text = NULL) {
	global $script, $backend;
	if(!$text)
		$text = $key;
	$checked = in_array($key, $script->filters);
	echo '<option value="', $backend->formatValue($key), '"', ($checked ? ' selected="selected"' : ''), '">', $backend->formatText($text), '</option>';
}
?>

<div class="search">
	<h2>Which languages would you like to see?</h2>
	
	<form action="<?=$backend->url('/iso639db')?>" method="get">
		<label for="code">By ISO 639 code:</label>
		<input type="text" id="code" name="code" value="<?php echo $backend->formatValue($script->code); ?>" style="width:3em;" disabled />

		<label for="name">or language name:</label>
		<input type="text" id="name" name="name" value="<?php echo $backend->formatValue($script->name); ?>" disabled />
		
		<select id="filters" name="filters[]" multiple="multiple" disabled><!--Only show languages...-->
			<optgroup label="in:"><?php
				filterOption('1', 'ISO 639-1');
				filterOption('2', 'ISO 639-2');
				filterOption('2/B', 'ISO 639-2/B');
				filterOption('3', 'ISO 639-3');
			?></optgroup>
			<optgroup label="of scope:"><!--<a href="http://www.sil.org/iso639-3/scope.asp" title="about scopes">scope</a>--><?php
				filterOption('individual');
				filterOption('dialect');
				filterOption('macrolanguage');
				filterOption('collection');
				filterOption('reserved');
				filterOption('special');
			?></optgroup>
			<optgroup label="of type:"><?php
				filterOption('living');
				filterOption('extinct');
				filterOption('ancient');
				filterOption('historic');
				filterOption('constructed');
			?></optgroup>
		</select><br />

		<input type="submit" value="search" disabled />
	</div>
</form>

<script type="text/javascript">
	function animation() {
		this.animate({
			opacity: 'toggle',
			height: 'toggle'
		}, 200);
	}
	$('form').collapse({
		head: 'h3',
		group: '.extended-search',
		show: animation,
		hide: animation
	});
	
	$('#filters').multiSelect({
		noneSelected: 'no filters',
		oneOrMoreSelected: '% filters',
		selectAll: false
	});
</script>

<?php
echo '<div class="error">This tool didn\'t survive the transition to Tool Labs. It\'s temporarily offline until it\'s reimplemented. Sorry!</div>';
$backend->footer();
die();

####################
## Search result
####################
$rows = $script->execute();
echo '<!--query=', $backend->formatText($script->query), ' | values=', $backend->formatText(print_r($script->values, true)), '-->';
if( !count($rows) )
	echo '<div class="neutral">There are no results matching your search.</div>';
else {
	echo
		'<table>',
		'<tr>',
		'<th>standard</th>',
		'<th>code</th>',
		'<th>name</th>',
		'<th>type</th>',
		'<th>scope</th>',
		'<th>notes</th>',
		'</tr><tbody>';
	foreach($rows as $row) {
		echo
			'<tr>',
			'<td><span style="color:gray;">ISO 639-</span>', $row['list'], '</td>',
			'<td>', $row['code'], '</td>',
			'<td>', $row['name'], ($row['native_name'] && $row['name'] != $row['native_name'] ? '; <i>' . $row['native_name'] : ''), '</td>',
			'<td>', $row['type'], '</td>',
			'<td>', $row['scope'], '</td>',
			'<td>', $row['notes'], '</td>',
			'</tr>';
	}
	echo '</tbody></table>';

	if( count($rows) == Script::MAX_LIMIT )
		echo '<div class="neutral">Only the first ', Script::MAX_LIMIT, ' matching languages are shown.</div>';
		
	echo '<!--', $backend->formatText($script->query), '-->';
	echo '<!--', $backend->formatText(print_r($script->values, true)), '-->';
}

$backend->footer(); ?>

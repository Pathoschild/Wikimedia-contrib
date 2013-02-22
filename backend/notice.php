<?
if($enabled = true) {
	/* configure */
	$type = 'warn';
	$message = 'The Wikidata database is temporarily unavailable as the toolserver adjusts to breaking database changes.';
	
	#$type = 'error';
	#$message = 'The Wikimedia Toolserver is experiencing <a href="https://jira.toolserver.org/browse/TS-1309" title="Toolserver bug ticket: daphne s2/s5 corrupted and offline">database server failures</a>. Some tools may be unusable.';
	
	/* render */
	echo '<div id="global-notice" class="is-', $type, '">', $message, '</div>';
}
?>

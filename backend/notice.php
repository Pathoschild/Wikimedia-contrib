<?
if($enabled = false) {
	/* configure */
	$type = 'error';
	$message = 'This tool may fail &mdash; the Wikimedia Toolserver (which hosts this tool) is experiencing database replication problems.';
	
	#$type = 'error';
	#$message = 'The Wikimedia Toolserver is experiencing <a href="https://jira.toolserver.org/browse/TS-1309" title="Toolserver bug ticket: daphne s2/s5 corrupted and offline">database server failures</a>. Some tools may be unusable.';
	
	/* render */
	echo '<div id="global-notice" class="is-', $type, '">', $message, '</div>';
}
?>

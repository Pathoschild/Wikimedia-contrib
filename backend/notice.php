<?
if($enabled = true) {
	/* configure */
	$type = 'warn';
	$message = 'This is the experimental Tools Labs port of the tool. It may be functional, but likely has lower performance due to the current Tools Labs infrastructure. You should use the <a href="//toolserver.org/~pathoschild/">Toolserver version</a> instead for now.';

	#$type = 'error';
	#$message = 'The Wikimedia Toolserver is experiencing <a href="https://jira.toolserver.org/browse/TS-1309" title="Toolserver bug ticket: daphne s2/s5 corrupted and offline">database server failures</a>. Some tools may be unusable.';

	/* render */
	echo '<div id="global-notice" class="is-', $type, '">', $message, '</div>';
}
?>

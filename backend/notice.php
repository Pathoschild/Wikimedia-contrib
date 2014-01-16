<?
if($enabled = true) {
	/* configure */
	$type = 'warn';
	$message = 'The Wikimedia Toolserver is experiencing intermittent database issues. Performance may be degraded.';
	
	#$type = 'error';
	#$message = 'The Wikimedia Toolserver is experiencing <a href="https://jira.toolserver.org/browse/TS-1309" title="Toolserver bug ticket: daphne s2/s5 corrupted and offline">database server failures</a>. Some tools may be unusable.';
	
	/* render */
	echo '<div class="global-notice is-', $type, '">', $message, '<div style="font-size:smaller; color:gray;">The infrastructure hosting this tool has been deprecated by decision of the Wikimedia Foundation. It will be migrated to their new infrastructure as soon as the <a href="https://bugzilla.wikimedia.org/show_bug.cgi?id=55929" title="Wikimedia bug 55929: Separate replica datacenter causes high query latency">remaining issues</a> are resolved. In the meantime, intermittent server issues may occur as support is withdrawn. For further information, see <a href="https://en.wikipedia.org/wiki/Wikipedia:Toolserver#Decommissioning_and_replacement">Wikipedia:Toolserver</a>.</div></div>';
}
?>

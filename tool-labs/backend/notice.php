<?
if($enabled = false) {
	/* configure */
	$type = 'info';
	$message = 'This tool is now running on the new Tool Labs infrastructure. Performance and reliability should be noticeably improved; please <a href="//meta.wikimedia.org/wiki/User_talk:Pathoschild">report any migration issues</a>.';

	/* render */
	echo '<div class="global-notice is-', $type, '">', $message, '</div>';
}
?>

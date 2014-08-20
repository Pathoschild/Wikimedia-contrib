<?php
require_once( '../backend/modules/Backend.php' );
$backend = Backend::create('pgkbot', 'A python bot by <a href="//en.wikipedia.org/wiki/user:pgk" title="pgk\'s Wikipedia userpage">pgk</a> that processes and filters IRC change feeds for wiki installations (see the <a href="//meta.wikimedia.org/wiki/CVN/Bots" title="online documentation">onwiki documentation</a>).')
	->link( '/pgkbot/stylesheet.css' )
	->header();
?>

<p class="neutral">The <a href="//meta.wikimedia.org/wiki/Pgkbot" title="pgkbot package">pgkbot package</a> is obsolete and no longer actively maintained. This mirror is maintained for historical interest.</p>
<h3>Download</h3>
<ul>
	<li><a href="pgkbot_1.7.zip" title="pgkbot archive">pgkbot 1.7</a> (2007-03-11, 381kB)</li>
	<li><a href="pgkbot_1.6.zip" title="pgkbot archive">pgkbot 1.6</a> (2006-11-22, 393kB)</li>
	<li><a href="pgkbot_1.5.zip" title="pgkbot archive">pgkbot 1.5</a> (unknown date, 373kB)</li>
	<li><a href="pgkbot_1.4.zip" title="pgkbot archive">pgkbot 1.4</a> (unknown date, 373kB)</li>
	<li><a href="pgkbot_1.3.zip" title="pgkbot archive">pgkbot 1.3</a> (unknown date, 493kB)</li>
	<li><a href="pgkbot_1.2.zip" title="pgkbot archive">pgkbot 1.2</a> (unknown date, 481kB)</li>
	<li><a href="pgkbot_1.1.zip" title="pgkbot archive">pgkbot 1.1</a> (unknown date, 452kB)</li>
	<li><s><a href="svn://hemlock.knams.wikimedia.org/pgk/svnCVUBot/" title="Get pgkbot from Wikimedia subversion">development version</a></s></li>
</ul>

<?php
$backend->footer();

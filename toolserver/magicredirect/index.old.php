<?php
/* globals, templates */
$locals = array (
	'title'       => 'RedirSpecial',
	'description' => 'Redirects to a Wikimedia special page with parsed arguments, primarily intended for {{<a href="//meta.wikimedia.org/wiki/Template:sr-request" title="template:sr-request on Meta">sr-request</a>}}.',
	'files'       => array('index.php'),
	'path'        => &$globals['urls']['toolserver']
);
include('../backend/legacy/database.php');

/***************
* Get data
***************/
// raw input
$ipage   = $_GET['page'];
$idomain = $_GET['domain'];
$iuser   = $_GET['user'];
$iredir  = $_GET['redir'];

// parsed input
$page   = strtolower($ipage);
$domain = preg_replace('/^(?:https?:\/\/)?(?:www\.)?(.+?)(?:\.org.*)?$/', '$1.org', addslashes($idomain));
$user   = ucfirst(str_replace('_', ' ', $iuser));
$redir  = $iredir;

$redir_to;

/***************
* Parse and redirect
***************/
$error = '';
$redir_to = '';
switch($page) {
	case 'special:userrights':
		/* determine prefix */
		dbconnect('metawiki-p');
		$prefix = db_fetch_value('SELECT dbname FROM toolserver.wiki WHERE domain="' . $domain . '"', 'dbname');
		$prefix = preg_replace('/_p$/', '', $prefix);
		mysql_close();
	
		/* die on error or redirect */
	      	if($prefix=='')
	      		$error .= '<div class="fail">Could not determine database prefix for ' . $domain . '.</div>';
	      	else
	      		$redir_to = '//meta.wikimedia.org/wiki/Special:Userrights?user=' . urlencode($user) . '@' . $prefix;
	case '':
	break;
	default:
		$error .= '<div class="fail">Unknown special page.</div>';	
	break;
}

/***************
* Output
***************/
if( $error || !$redir_to || !$redir ) {
	include('../backend/legacy/globals.php');

	if($error)
		echo $error;
	else if($redir_to)
		echo '<div class="success">Current redirect:<br /><a href="', $redir_to, '">', $redir_to, '</a>.<br /><small>Check "redirect to page" below to redirect automatically.</small></div>';
	
	/* input form */
	?>
	<form action="<?php echo $script_path; ?>" method="get">
		<fieldset>
			<p>Select a special page, then fill in the relevant boxes.</p>
			<select name="page">
				<option value="special:userrights">m:Special:UserRights</option>
			</select>
			<label for="page">special page</label><br />
			
			<input type="text" name="domain" id="domain" value="<?php echo $idomain; ?>" />
			<label for="domain">domain</label><br />
			
			<input type="text" name="user" id="user" value="<?php echo $iuser; ?>" />
			<label for="user">user</label><br />
			
			<input type="checkbox" name="redir" id="redir" <?php gCheckBox($iredir, 0); ?> /><label for="redir"> <b>redirect to page</b></label><br />
			<?php gDebugOption(); ?><br />
			<input type="submit" value="Submit" id="submit" class="smallsubmit" />
		</fieldset>
	</form>
	
	<?php

	/* debug */
	gDebug(get_defined_vars());
	
	/* globals, templates */
	makeFooter($license);
}
else {
	header( 'Location: ' . $redir_to );
	echo 'Redirecting...';
}
?>

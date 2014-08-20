<?php
require_once('../backend/modules/Backend.php');
$backend = Backend::create('A template\'s magic redirect', 'Redirects to an arbitrary URL with tokens based on user and wiki filled in. This is primarily intended for Wikimedia templates such as {{<a href="//meta.wikimedia.org/wiki/Template:sr-request" title="template:sr-request on Meta">sr-request</a>}} (see <a href="?url=//{wiki.domain}/wiki/Special:UserRights/{user.name}@{wiki.name}&wiki=metawiki&user=Pathoschild" title="reload with example values">example</a>).')
	->link('/magicredirect/stylesheet.css')
	->link('/content/jquery.collapse/jquery.collapse.js')
	->link('/content/jquery.collapse/jquery.cookie.js')
	->addScript('
		$(document).ready(function() {
			$("#token-documentation").collapse({head:"span", group:"ul"});
		});
	');

$tokens = array(
	'wiki' => array(
		'name' => 'The simplified database name, like \'enwiki\'.',
		'dbName' => 'The database name, like \'enwiki\'.',
		'lang' => 'The ISO 639 language associated with the wiki. (A few wikis have invalid codes like \'zh-classical\' or \'noboard-chapters\'.)',
		'family' => 'The project name, like \'wikibooks\'.',
		'domain' => 'The domain portion of the URL, like \'fr.wikisource.org\'.',
		'size' => 'The number of articles on the wiki.',
		'isClosed' => 'Whether the wiki is locked and no longer publicly editable.',
		'serverNumber' => 'The number of the server on which the wiki\'s <em>replicated</em> database is located.',
		'host' => 'The host name of the server on which the wiki\'s <em>replicated</em> database is located.'
	),
	'user' => array(
		'id' => 'The user\'s global identifier number.',
		'name' => 'The unique name of the user.',
		'registration' => 'The date on which the global account was registered.',
		'locked' => 'Whether the global account has been <a href="http://meta.wikimedia.org/wiki/Steward_handbook#Managing_global_accounts" title="about account locking">locked</a>.',
		'hidden' => 'Whether the global account has been <a href="http://meta.wikimedia.org/wiki/Steward_handbook#Managing_global_accounts" title="about account hiding">hidden</a>.'
	)
);

##########
## Handle request
##########
/* get input */
$url    = $backend->get('url');
$redirect  = $backend->get('redirect', false);
$wiki   = $backend->get('wiki');
$user   = $backend->get('user');

/* apply data */
$error = '';
$target = $url;
if($target) {
	$db = $backend->GetDatabase();
	$db->Connect('metawiki');

	/* apply wiki */
	if($wiki && strpos($target, '{wiki.') !== false) {
		$dbname = preg_replace('/_p$/', '', $wiki);
		$domain = preg_replace('/^(?:https?:\/\/|\/\/)?(?:www\.)?(.+?)(?:\.org.*)?$/', '$1.org', $wiki);
		$found = false;
		foreach($db->getWikis() as $data) {
			if($data->name == $dbname || $data->domain == $domain) {
				$found = true;
				foreach($tokens['wiki'] as $token => $description)
					$target = str_replace('{wiki.' . $token . '}', $data->$token, $target);
				break;
			}
		}
		if(!$found)
			$error = '<div class="fail">Could not find a wiki with a domain or database name like "' . $backend->formatText ($dbname) . '".</div>';
	}

	/* apply user */
	if($user && strpos($target, '{user.') !== false) {
		$user = $backend->formatUsername($user);
		$row = $db->Query('SELECT gu_id AS id, gu_name AS name, gu_registration AS registration, gu_locked AS locked, gu_hidden AS hidden FROM centralauth_p.globaluser WHERE gu_name = ? LIMIT 1', array($user))->fetchAssoc();
		if($row) {
			foreach($tokens['user'] as $token => $description)
				$target = str_replace('{user.' . $token . '}', $row[$token], $target);
		}
		else
			$error .= '<div class="fail">Could not find a user with the name "' . $backend->formatText($user) . '".</div>';
	}
}

/* redirect */
if($target && $redirect && !$error) {
	header( 'Location: ' . $target );
	exit();
}

##########
## Output
##########
$backend->header();

/* input form */
?>
<form action="<?=$backend->url('/magicredirect')?>" method="get">
	<input id="url" name="url" type="text" value="<?php echo $backend->formatValue($url); ?>" />
	<label for="url">URL</label><br />
	
	<input id="wiki" name="wiki" type="text" value="<?php echo $backend->formatValue($wiki); ?>" />
	<label for="wiki">wiki name</label><br />
	
	<input id="user" name="user" type="text" value="<?php echo $backend->formatValue($user); ?>" />
	<label for="user">user name</label><br />

	<input type="checkbox" name="redirect" id="redirect" <?php echo $redirect ? 'checked="checked"' : '' ?> />
	<label for="redirect">redirect to page</label><br />

	<input type="submit" value="Submit" id="submit" class="smallsubmit" />
	
	<div id="token-documentation">
		<span>available tokens:</span>
		<ul><?php
		foreach($tokens as $key => $fields) {
			echo '<li>', $key, ':<ul>';
			foreach($fields as $token => $description)
				echo '<li><code>{', $key, '.', $token, '}</code>: ', $description, '</li>';
			echo '</ul></li>';
		}
		?></ul>
	</div>
</form>

<?php
if($error || $target) {
	echo '<div class="result-box">';
	if($error)
		echo $error;
	else if($target) {
		/* build URL */
		$magicUrl = $backend->url('/magicredirect/?redirect=1');
		if($user)
			$magicUrl .= '&user=' . urlencode($user);
		if($wiki)
			$magicUrl .= '&wiki=' . urlencode($wiki);
		$magicUrl .= '&url=' . urlencode($url);
		
		/* output details */
		echo '<div class="success">This would redirect to <a href="', $backend->formatValue($target), '">', $backend->formatText($target), '</a>.<br />',
			'<small>(<a href="', $backend->formatValue($magicUrl), '" title="automatic redirect">automatic redirect</a>)</small>',
			'</div>';
	}
	echo '</div>';
}

$backend->footer();
?>

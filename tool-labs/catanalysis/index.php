<?php
require_once( '../backend/modules/Backend.php' );
require_once( '../backend/modules/Form.php' );
$backend = Backend::create('Catanalysis', 'Analyzes edits to pages in the category tree rooted at the specified category (or pages rooted at a prefix). This is primarily intended for test project analysis by the Wikimedia Foundation <a href="//meta.wikimedia.org/wiki/Language_committee" title="language committee">language committee</a>.')
	->link('/catanalysis/stylesheet.css')
	->header();

/***************
* Variables
***************/
/* configuration */
$USEREDIT_LIMIT_FOR_INACTIVITY = 10; // users with <= this are marked inactive (in given month)
$USERCOUNT_LIMIT_FOR_INACTIVITY = 3; // months with <= users are marked inactive

/* input */
$fullTitle = $backend->formatInitialCapital($backend->get('title'));
$database  = $backend->get('wiki', $backend->get('db', 'incubatorwiki'));
$cat       = !!$backend->get('cat', true);
$listpages = $backend->get('listpages');

/* normalise database */
if($database && substr($database, -2) == '_p')
	$database = substr($database, 0, -2);

/* parse title */
$i = strpos($fullTitle, ':');
if($i) {
	$namespace = substr($fullTitle, 0, $i);
	$title = substr($fullTitle, $i + 1);
}
else {
	$namespace = null;
	$title = $fullTitle;
}

/* initialize */
$db = $backend->GetDatabase();

/***************
* Input form
***************/
?>
	<form action="<?=$backend->url('/catanalysis')?>" method="get">
		<fieldset>
			<p>Enter a category name to analyse members of, or a prefix to analyze subpages of (see <a href="index.php?title=Wp/kab&cat=0&db=incubatorwiki" title="example">prefix</a> and <a href="index.php?title=Hindi&cat=1&db=sourceswiki" title="example">category</a> examples).</p>

			<input type="text" id="title" name="title" value="<?= $backend->formatValue($fullTitle) ?>" />
			(this is a <?= Form::Select('cat', $cat, array(1 => 'category', 0 => 'prefix')) ?> on <select name="wiki" id="wiki">
			<?php
				foreach ($db->getWikis() as $wiki) {
					if(!$wiki->isClosed) {
						$selected = $wiki->dbName == $database;
						echo '<option value="', $wiki->dbName, '"', ($selected ? ' selected="yes" ' : '') , '>', $backend->formatText($wiki->domain), '</option>';
					}
				}
			?>
			</select>)<br /><br />

			<?= Form::Checkbox('listpages', $listpages) ?>
			<label for="listpages">List all pages and redirects (not recommended)</label>
			<br />

			<input type="submit" value="analyze" />
		</fieldset>
	</form>
<?php

do {
	/***************
	* Validation
	***************/
	// missing data (break)
	if(!$title)
		break;
	
	// category mode (warn)
	if($cat) {
		echo '<p class="neutral" style="border-color:#C66;">You have selected category mode, which can be skewed by incorrect categorization. Please review the list of pages generated below.</p>';
		$listpages = true;
	}
	if($namespace) {
		echo '<p class="neutral" style="border-color:#C66;">You have specified the "', $backend->formatText($namespace), '" namespace in the prefix. The details below only reflect edits in that namespace.</p>';
	}


	/***************
	* Functions
	***************/
	/* generate bar for graph */
	function genBar($label, $total, $barvalue, $strike=false) {
		$bars = floor($total/$barvalue);
		$out = '';

		$out .= '<tr><td';
		if($strike)
			$out .= ' class="struckout"';
		$out .= '>' . $label . '</td><td><b>';
		for($i=0; $i<$bars; $i++)
			$out .= '|';
		$out .= '</b></td><td><small>';
		if($total<0)
			$out .= '<span style="color:#C00; font-weight:bold;">' . $total . '</span>';
		else
			$out .= $total;
		$out .= '</small></td></tr>';
		return $out;
	}

	/* generate link */	
	function genLink($target, $text = false) {
		global $domain;
		if(!$text)
			$text = $target;
		return '<a href="//' . $domain . '/wiki/' . $target . '" title="' . $target . '">' . $text . '</a>';
	}


	/***************
	* Query database
	***************/
	$db->Connect($database);
	$revisionQuery = array(
		'sql' => 'SELECT page.page_namespace,page.page_title,page.page_is_redirect,page.page_is_new,revision.rev_minor_edit,revision.rev_user_text,revision.rev_timestamp,revision.rev_len,revision.rev_page FROM revision LEFT JOIN page ON page.page_id=revision.rev_page ',
		'values' => array()
	);
	
	/* prefix mode */
	if(!$cat) {
		/* handle namespace */
		if($namespace) {
			$revisionQuery['sql'] .= 'JOIN toolserver.namespace ON page.page_namespace = toolserver.namespace.ns_id WHERE toolserver.namespace.ns_name = ? AND ';
			$revisionQuery['values'][] = $namespace;
		}
		else {
			$revisionQuery['sql'] .= 'WHERE ';
		}
	
		$revisionQuery['sql'] .= ' (CONVERT(page_title USING binary)=CONVERT(? USING BINARY) OR CONVERT(page_title USING BINARY) LIKE CONVERT(? USING BINARY)) ORDER BY revision.rev_timestamp';
		$revisionQuery['values'][] = str_replace(' ', '_', $title);
		$revisionQuery['values'][] = str_replace(' ', '_', $title . '%');
	}
	/* category mode */
	else {
		/* fetch list of subcategories */
		$cats  = array();
		$queue = array($title);
		$backend->profiler->start('fetch subcategories');
		while(count($queue)) {
			/* fetch subcategories of currently-known categories */
			$dbCatQuery = 'SELECT page_title FROM page JOIN categorylinks ON page_id=cl_from WHERE page_namespace=14 AND CONVERT(cl_to USING BINARY) IN (';
			$dbCatValues = array();
			while(count($queue)) {
				if(!in_array($queue[0], $cats)) {
					$dbCatQuery .= 'CONVERT(? USING BINARY),';
					$dbCatValues[] = str_replace(' ', '_', $queue[0]);
					$cats[] = array_shift($queue);
				}
				else
					array_shift($queue);
			}
			$dbCatQuery = rtrim($dbCatQuery, ',') . ')';

			/* queue subcategories */
			if(count($dbCatValues) == 0)
				continue;
			$subcats = $db->Query($dbCatQuery, $dbCatValues)->fetchAllAssoc();
			foreach($subcats as $subcat) {
				$queue[] = $subcat['page_title'];
			}
		}
		$backend->profiler->stop('fetch subcategories');

		/* add to query */
		$revisionQuery['sql'] .= 'JOIN categorylinks on page_id=cl_from WHERE CONVERT(cl_to USING BINARY) IN (';
		foreach($cats as $cat) {
			$revisionQuery['sql'] .= 'CONVERT(? USING BINARY),';
			$revisionQuery['values'][] = str_replace(' ', '_', $cat);
		}
		$revisionQuery['sql'] = rtrim($revisionQuery['sql'], ', ') . ') ORDER BY revision.rev_timestamp';
	}
	
	/* finalise */
	$backend->profiler->start('fetch revisions');
	$revisions = $db->Query($revisionQuery['sql'], $revisionQuery['values'])->fetchAllAssoc();
	$backend->profiler->stop('fetch revisions');

	
	/***************
	* Fetch bot flags
	***************/
	$backend->profiler->start('fetch user groups');
	
	// get unique users
	$users = array();
	foreach($revisions as $revision)
		$users[$revision['rev_user_text']] = false;
	
	// fetch bot flags
	$bots = array();
	$query = $db->Query('SELECT user_name FROM user INNER JOIN user_groups ON user_id = ug_user WHERE user_name IN (' . rtrim(str_repeat('?,', count($users)), ',') . ') AND ug_group = "bot"', array_keys($users));
	while($user = $query->fetchValue())
		$bots[$user] = true;
	unset($users);
	$backend->profiler->stop('fetch user groups');
	
	
	/***************
	* Fetch domain
	***************/
	$backend->profiler->start('fetch domain');
	$db->Connect('metawiki');
	$domain = $db->Query('SELECT REPLACE(url, "http://", "") AS domain FROM meta_p.wiki WHERE dbname=? LIMIT 1', $database)->fetchValue();
	$db->Dispose();
	$backend->profiler->stop('fetch domain');
	
	/***************
	* Generate data
	***************/
	$backend->profiler->start('analyze data');
	$data = Array(
		'totals'     => array('edits' => 0, 'minor' =>0, 'newpages' => 0),
		'months'     => array(),
		'pages'      => array(),
		'redirects'  => array(),
		'users'      => array(),
		'userstats'  => array(),
		'monthstats' => array(),
		'counts'     => array('revisions' => count($revisions)),
		'editsbyuser'=> array(),
		'revsizes'   => array('totals' => array())
	);
	if(!$data['counts']['revisions']) {
		echo '<p class="fail">No pages found.</p>';
		break;
	}

	$lastmonth = null;
	foreach($revisions as $revision) {
		/***************
		* Extract data
		***************/
		$row = array(
			'namespace'  => $revision['page_namespace'],
			'title'      => $revision['page_title'],
			'user'       => $revision['rev_user_text'],
			'timestamp'  => $revision['rev_timestamp'],
			'isRedirect' => $revision['page_is_redirect'],
			'isNew'      => $revision['page_is_new'],
			'isMinor'    => $revision['rev_minor_edit'],
			'pageid'     => $revision['rev_page'],
			'revsize'    => $revision['rev_len']
		);
		
		// get month
		preg_match('/^\d{6}/', $row['timestamp'], $row['month']);
		$row['month'] = strval($row['month'][0]);
		
		// adjust title for namespace and discard namespace
		switch($row['namespace']) {
			case 0:  break;
			case 1:  $row['title'] = 'Talk:'           . $row['title']; break;
			case 2:  $row['title'] = 'User:'           . $row['title']; break;
			case 3:  $row['title'] = 'User talk:'      . $row['title']; break;
			case 4:  $row['title'] = 'Project:'        . $row['title']; break;
			case 5:  $row['title'] = 'Project talk:'   . $row['title']; break;
			case 6:  $row['title'] = 'Image:'          . $row['title']; break;
			case 7:  $row['title'] = 'Image talk:'     . $row['title']; break;
			case 8:  $row['title'] = 'MediaWiki:'      . $row['title']; break;
			case 9:  $row['title'] = 'MediaWiki talk:' . $row['title']; break;
			case 10: $row['title'] = 'Template:'       . $row['title']; break;
			case 11: $row['title'] = 'Template talk:'  . $row['title']; break;
			case 12: $row['title'] = 'Help:'           . $row['title']; break;
			case 13: $row['title'] = 'Help talk:'      . $row['title']; break;
			case 14: $row['title'] = 'Category:'       . $row['title']; break;
			case 15: $row['title'] = 'Category talk:'  . $row['title']; break;
			default: $row['title'] = '{{ns:$namespace}}:' . $row['title'];
		}
		
		// merge anonymous users
		if(preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $row['user']))
			$row['user'] = 'Anonymous';


		/***************
		* Store data
		***************/
		// pages
		if(!in_array($row['title'], $data['pages']) && !$row['isRedirect'])
			$data['pages'][] = $row['title'];

		// redirects
		if($row['isRedirect'] && !in_array($row['title'], $data['redirects']))
			$data['redirects'][] = $row['title'];
		else if(!$row['isRedirect'] && in_array($row['title'], $data['redirects']))
			array_splice($data['redirects'], array_search($row['title'], $data['redirects']), 1);

		// store months
		if(!in_array($row['month'], $data['months']))
			$data['months'][] = $row['month'];

		/* store users */
		if(!in_array($row['user'], $data['users']))
			$data['users'][] = $row['user'];


		/***************
		* Store statistics
		***************/
		// prepare arrays
		$user  = $row['user'];
		$month = $row['month'];
		
		if(!array_key_exists($user, $data['userstats']))
			$data['userstats'][$user] = array('totals' => array('edits' => 0, 'minor' => 0, 'newpages' => 0));
			
		if(!array_key_exists($row['month'], $data['userstats'][$user]))
			$data['userstats'][$user][$month] = array('edits' => 0, 'minor' => 0, 'newpages' => 0);
			
		if(!array_key_exists($row['month'], $data['monthstats']))
			$data['monthstats'][$month] = array('edits' => 0, 'minor' => 0, 'newpages' => 0, 'users' => array());

		// edits
		$data['totals']['edits']++;
		$data['monthstats'][$month]['edits']++;
		$data['userstats'][$user]['totals']['edits']++;
		$data['userstats'][$user][$month]['edits']++;
			
		// new pages
		if($row['isNew']) {
			$data['totals']['newpages']++;
			$data['monthstats'][$month]['newpages']++;
			$data['userstats'][$user]['totals']['newpages']++;
			$data['userstats'][$user][$month]['newpages']++;
		}

		//  minor edits
		if($row['isMinor']) {
			$data['totals']['minor']++;
			$data['monthstats'][$month]['minor']++;
			$data['userstats'][$user]['totals']['minor']++;
			$data['userstats'][$user][$month]['minor']++;
		}
		
		// edit percentages (month)
		$userEdits = $data['userstats'][$user][$month]['edits'];
		$monthEdits = $data['monthstats'][$month]['edits'];
		$percentage = round((($userEdits/$monthEdits)*100),2); // calculate and round
		$percentage = sprintf("%05.2f",$percentage); // zero-padding
		$data['userstats'][$user][$month]['percent'] = $percentage;
		
		// edit percentage (total)
		$userEdits = $data['userstats'][$user]['totals']['edits'];
		$allEdits = $data['totals']['edits'];
		$percentage = round((($userEdits/$allEdits)*100),2); // calculate and round
		$percentage = sprintf("%05.2f",$percentage); // zero-padding
		$data['userstats'][$user]['totals']['percent'] = $percentage;
		
		// users per month
		if(!in_array($user,$data['monthstats'][$month]['users'])) {
			$data['monthstats'][$month]['users'][] = $user;
		}
		
		// reference edits by user
		$data['editsbyuser']['total'][$user] = &$data['userstats'][$user]['totals']['edits'];
		$data['editsbyuser'][$month][$user]  = &$data['userstats'][$user][$month]['edits'];
		
		// page size (add last month's calculated size to totals)
		if($lastmonth && $lastmonth != $month) {
			$sum = 0;
			foreach($data['revsizes'][$lastmonth] as $pageid=>$size)
				$sum += $size;
			$data['revsizes']['totals'][$lastmonth] = $sum;
		}
		
		// initiate array if new month
		if(!in_array($month, $data['revsizes'])) {
			if(count($data['revsizes']) == 1)	// first month
				$data['revsizes'][$month] = array();
			else	// every other month (copy last month's array)
				$data['revsizes'][$month] = $data['revsizes'][$lastmonth];
			$lastmonth = $month;	// store current month so we can copy it later
		}
		
		// store sizes for month
		$data['revsizes'][$month][$row['pageid']] = $row['revsize'];
	}
	
	unset($user, $month);
	unset($userEdits, $monthEdits, $allEdits, $percentage);
	unset($lastmonth, $sum);


	/***************
	* Sort 
	***************/
	sort($data['pages']);
	sort($data['users']);
	ksort($data['userstats']);
	foreach($data['editsbyuser'] as &$x)
		arsort($x);

	
	/***************
	* Count
	***************/
	$data['counts']['months']    = count($data['months']);
	$data['counts']['pages']     = count($data['pages']);
	$data['counts']['redirects'] = count($data['redirects']);
	$data['counts']['users']     = count($data['users']);
	
	$backend->profiler->stop('analyze data');
	
	/***************
	* Table of contents
	***************/
	$backend->profiler->start('generate output');
	echo '<h2 id="Generated_statistics">Generated statistics</h2>';
	if($data) {
		?>
		<div id="toc">
			<b>Table of contents</b>
			<ol>
				<li>
					<a href="#Lists">Lists</a>
					<ol>
						<li><a href="#list_editors">editors</a></li>
						<?php
						if($listpages) {
							?>
							<li><a href="#list_pages">pages</a></li>
							<li><a href="#list_redirects">redirects</a></li>
							<?php
						}
						?>
					</ol>
				</li>
				<li><a href="#Overview">Overview</a>
					<ol>
						<li><a href="#overview_edits">edits per month</a></li>
						<li><a href="#overview_editors">editors per month</a></li>
					</ol>
				</li>
				<li><a href="#Distribution">Edit distribution per month</a>
					<ol>
						<?php
						foreach($data['months'] as $month)
							echo '<li><a href="#distribution_', $month, '">', $month, '</a></li>';
						?>
					</ol>
				</li>
			</ol>
		</div>
		<?php
	
		/***************
		* Notes & links
		***************/
		echo '<p>Users with less than ', $USEREDIT_LIMIT_FOR_INACTIVITY, ' edits are discounted or struck out.</p>';
	
		/***************
		* Lists
		***************/
		echo '<h3 id="Lists">Lists</h3>';
	
		/* user list */
		echo '<h4 id="list_editors">editors</h4><ol>';
		foreach($data['editsbyuser']['total'] as $user=>$edits) {
			echo '<li';
			if($edits < $USEREDIT_LIMIT_FOR_INACTIVITY || array_key_exists($user, $bots))
				echo ' class="struckout"';
			echo '>', genLink('user:' . $user, $user), ' (<small>', $edits, ' edits</small>)';
			
			if(array_key_exists($user, $bots))
				echo ' <small>[bot]</small>';
			echo '</li>';
		}
		echo '</ol>';
		
		if($listpages) {
			/* page list */
			echo '<h4 id="list_pages">pages</h4><ol>';
			foreach($data['pages'] as $page)
				echo '<li>', genLink($page), '</li>';
			echo '</ol>';
			
			/* redirect list */
			echo '<h4 id="list_redirects">redirects</h4><ol>';
			foreach($data['redirects'] as $page)
				echo '<li>', genLink($page), '</li>';
			echo '</ol>';
		}
	
	
		/***************
		* Overall statistics
		***************/
		?>
		<h3 id="Overview">Overview</h3>
		There are:
		<ul>
			<li><?php echo $data['counts']['pages']; ?> articles, categories, templates, and talk pages;</li>
			<li><?php echo $data['counts']['redirects']; ?> redirects;</li>
			<li><?php echo $data['counts']['users']; ?> editors (including any with at least one edit);</li>
			<li><?php echo $data['totals']['edits']; ?> revisions (including <?php echo $data['totals']['minor']; ?> minor edits).</li>
		</ul>
	
		<?php
		/* edits per month */	
		echo '<h4 id="overview_edits">edits per month</h4>';
		
		echo '<table>',
		     '<tr>',
		     '<th colspan="3">Overall</th>',
		     '</tr>';
		foreach($data['months'] as $month)
			echo genBar($month,$data['monthstats'][$month]['edits'],10);
		unset($month,$edits);
	
		/* new pages per month */
		echo '<tr>',
		     '<th colspan="3">New pages</th>',
		     '</tr>';
		foreach($data['months'] as $month)
			echo genBar($month, $data['monthstats'][$month]['newpages'], 10);
		
		/* content added per month */
		echo '<tr>',
		     '<th colspan="3">Bytes added</th>',
		     '</tr>';
		if($data['months'][0]<200706) {
			echo '<tr>',
			     '<td colspan="3" style="color:gray;">(No data before 200706.)</td>',
			     '</tr>';
		}
		
		foreach($data['revsizes']['totals'] as $month=>$size) {
			// skip months before data available
			if($month>=200706) {
				$size = $data['revsizes']['totals'][$month];
				if(isset($lastmonth) && array_key_exists($lastmonth, $data['revsizes']['totals']))
					$size -= $data['revsizes']['totals'][$lastmonth];
				$lastmonth = $month; // update for next month
	
				// output
				echo genBar($month, $size, 5000);
			}
			$i++;
		}
		echo '</table>';
		
		/* editors per month */
		echo '<h4 id="overview_editors">editors per month</h4>',
		     '<table>';
		foreach($data['months'] as $month) {
			// discount those with less than editlimit
			$users = 0;
			foreach($data['editsbyuser'][$month] as $user=>$edits) {
				if($edits >= $USEREDIT_LIMIT_FOR_INACTIVITY && !array_key_exists($user, $bots))
					$users++;
			}
			echo genBar($month, $users, 1);
		}
		echo '</table>';
		
		/***************
		* Edit distribution per month
		***************/
		echo '<h3 id="Distribution">Edit distribution per month</h3>';
		
		foreach($data['months'] as $month) {
			echo '<h4 id="distribution_', $month, '">', $month, '</h4>',
			     '<table>';
			
			foreach($data['editsbyuser'][$month] as $user=>$edits) {
				$isActive = $edits > $USEREDIT_LIMIT_FOR_INACTIVITY && !array_key_exists($user, $bots);
				
				echo genBar(genLink('user:' . $user, $user), $edits, 10, !$isActive);
			}
			echo '</table>';
		}
	}
	$backend->profiler->stop('generate output');
} while (0);

$backend->footer();
?>

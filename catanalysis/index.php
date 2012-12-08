<?php
/***************
* Globals & templates
***************/
$locals = array (
	'title'       => 'Catanalysis',
	'description' => 'Analyzes edits to pages in the category tree rooted at the specified category (or pages rooted at a prefix). This is primarily intended for test project analysis by the Wikimedia Foundation <a href="//meta.wikimedia.org/wiki/Language_committee" title="language committee">language committee</a>.',
	'files'       => array('index.php', 'stylesheet.css'),
	'load_files'  => array('stylesheet.css'),
	'path'        => &$globals['urls']['toolserver']
);
include('../backend/legacy/globals.php');
include('../backend/legacy/database.php');

/***************
* Variables
***************/
/* configuration */
$USEREDIT_LIMIT_FOR_INACTIVITY = 10; // users with <= this are marked inactive (in given month)
$USERCOUNT_LIMIT_FOR_INACTIVITY = 3; // months with <= users are marked inactive

/* input */
$fullTitle = gFormatUppercaseFirst($_REQUEST['title']);
$database  = $_REQUEST['wiki'];
$cat       = $_REQUEST['cat'];
$listpages = $_REQUEST['listpages'];

/* defaults */
if(!isset($cat))
	$cat = true;
if(!isset($database))
	$database = 'incubatorwiki_p';

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

/***************
* Input form
***************/
?>
	<form action="" method="get">
		<fieldset>
			<p>Enter a category name to analyse members of, or a prefix to analyze subpages of (see <a href="index.php?title=Wp/kab&cat=0&db=incubatorwiki_p" title="example">prefix</a> and <a href="index.php?title=Hindi&cat=1&db=sourceswiki_p" title="example">category</a> examples).</p>

			<input type="text" id="title" name="title" value="<?php echo htmlspecialchars($fullTitle); ?>" />
			(this is a <select name="cat">
				<option value="1" <?php gSelect($cat, '1'); ?>>category</a>
				<option value="0" <?php gSelect($cat, '0', 'default'); ?>>prefix</a>
			</select> on <?php dbselect_list($database); ?>)
			<br /><br />

			<input type="checkbox" name="listpages" id="listpages" <?php gCheckBox($listpages); ?> />
			<label for="listpages">List all pages and redirects (not recommended)</label>
			<br />
			<?php gDebugOption(); ?>

			<input type="submit" value="analyze" />
		</fieldset>
	</form>
<?php

do {
	/***************
	* Validation
	***************/
	// missing data (break)
	if(!$_GET)
		break;
	if(!$title) {
		echo '<p class="fail">You must specify a category title or prefix.</p>';
		break;
	}
	
	// category mode (warn)
	if($cat) {
		echo '<p class="neutral" style="border-color:#C66;">You have selected category mode, which can be skewed by incorrect categorization. Please review the list of pages generated below.</p>';
		$listpages = true;
	}
	if($namespace) {
		echo '<p class="neutral" style="border-color:#C66;">You have specified the "', htmlspecialchars($namespace), '" namespace in the prefix. The details below only reflect edits in that namespace.</p>';
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
	function genLink($target,$text=false) {
		global $domain;
		if(!$text)
			$text=$target;
		return '<a href="//' . $domain . '/wiki/' . $target . '" title="' . $target . '">' . $text . '</a>';
	}


	/***************
	* Query database
	***************/
	dbconnect(str_replace('_', '-', $database));
	$title = mysql_real_escape_string($title);
	$db['query'] = 'SELECT page.page_namespace,page.page_title,page.page_is_redirect,page.page_is_new,revision.rev_minor_edit,revision.rev_user_text,revision.rev_timestamp,revision.rev_len,revision.rev_page FROM revision LEFT JOIN page ON page.page_id=revision.rev_page ';

	/* prefix mode */
	if(!$cat) {
		/* handle namespace */
		if($namespace) {
			$db['query'] .= 'JOIN toolserver.namespace ON page.page_namespace = toolserver.namespace.ns_id WHERE toolserver.namespace.ns_name = "' . addslashes($namespace) . '" AND ';
		}
		else {
			$db['query'] .= 'WHERE ';
		}
	
		$db['query'] .= ' (CONVERT(page_title USING binary)=CONVERT("' . str_replace(' ', '_', addslashes($title)) . '" USING BINARY) OR CONVERT(page_title USING BINARY) LIKE CONVERT("' . str_replace(' ', '_', addslashes($title)) . '%" USING BINARY)) ORDER BY revision.rev_timestamp';
	}
	/* category mode */
	else {
		/* fetch list of subcategories */
		$cats  = array();
		$queue = array($title);
		while(count($queue)) {
			/* fetch subcategories of currently-known categories */
			$db['catquery'] = 'SELECT page_title FROM page JOIN categorylinks ON page_id=cl_from WHERE page_namespace=14 AND CONVERT(cl_to USING BINARY) IN (';
			while(count($queue)) {
				if(!in_array($queue[0], $cats)) {
					$db['catquery'] .= 'CONVERT("' . str_replace(' ', '_', addslashes($queue[0])) . '" USING BINARY),';
					$cats[] = array_shift($queue);
				}
				else
					array_shift($queue);
			}
			$db['catquery'] = rtrim($db['catquery'], ',') . ')';

			// run query to get subcategories
			$db['result'] = mysql_query($db['catquery']) or print '<div class="error">' . mysql_error() . '</div>';
			
			/* queue subcategories */
			while($row = mysql_fetch_array($db['result']))
				$queue[] = $row[0];
		}

		/* add to query */
		$db['query'] .= 'JOIN categorylinks on page_id=cl_from WHERE CONVERT(cl_to USING BINARY) IN (';
		foreach($cats as $cat)
			$db['query'] .= 'CONVERT("' . str_replace(' ', '_', addslashes($cat)) . '" USING BINARY),';
		$db['query']  = rtrim($db['query'], ', ') . ') ORDER BY revision.rev_timestamp';
	}
	
	/* finalise whichever */
	$db['result'] = mysql_query($db['query']) or print '<div class="error">' . mysql_error() . '</div>';
	mysql_close();

	/***************
	* Fetch domain
	***************/
	db_connect('metawiki-p');
	$db['domain'] = 'SELECT domain FROM toolserver.wiki WHERE dbname="' . $database . '" LIMIT 1';
	$domain = mysql_query($db['domain']) or print '<div class="error">' . mysql_error() . '</div>';
	$domain = mysql_fetch_array($domain);
	$domain = $domain[0];
	mysql_close();
	
	/***************
	* Generate data
	***************/
	$data = Array('totals'     => array('edits' => 0, 'minor' =>0, 'newpages' => 0),
	              'months'     => array(),
	              'pages'      => array(),
	              'redirects'  => array(),
	              'users'      => array(),
	              'userstats'  => array(),
	              'monthstats' => array(),
	              'counts'     => array('revisions' => mysql_numrows($db['result'])),
	              'editsbyuser'=> array(),
	              'revsizes'   => array('totals' => array()), 
	);
	if(!$data['counts']['revisions']) {
		echo '<p class="fail">No pages found.</p>';
		break;
	}

	for($i=0; $i<$data['counts']['revisions']; $i++) {
		/***************
		* Extract data
		***************/
		$row = array('namespace'  => mysql_result($db['result'],$i,"page_namespace"),
		             'title'      => mysql_result($db['result'],$i,"page_title"),
		             'user'       => mysql_result($db['result'],$i,"rev_user_text"),
		             'timestamp'  => mysql_result($db['result'],$i,"rev_timestamp"),
		             'isRedirect' => mysql_result($db['result'],$i,"page_is_redirect"),
		             'isNew'      => mysql_result($db['result'],$i,"page_is_new"),
		             'isMinor'    => mysql_result($db['result'],$i,"rev_minor_edit"),
		             'pageid'     => mysql_result($db['result'],$i,"rev_page"),
		             'revsize'    => mysql_result($db['result'],$i,"rev_len")
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
		if(!in_array($row['title'],$data['pages']) && !$row['isRedirect'])
			$data['pages'][] = $row['title'];

		// redirects
		if($row['isRedirect'] && !in_array($row['title'],$data['redirects']))
			$data['redirects'][] = $row['title'];
		else if(!$row['isRedirect'] && in_array($row['title'],$data['redirects']))
			array_splice($data['redirects'],array_search($row['title'],$data['redirects']),1);

		// store months
		if(!in_array($row['month'],$data['months']))
			$data['months'][] = $row['month'];

		/* store users */
		if(!in_array($row['user'],$data['users']))
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
	
	unset($user,$month);
	unset($userEdits,$monthEdits,$allEdits,$percentage);
	unset($lastmonth,$sum);


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
	
	
	/***************
	* Output (render mode)
	***************/
	if($render) {
		$isActive = true;		
		$months = Array(date('Ym', strtotime('-1 month')), date('Ym', strtotime('-2 months')), date('Ym', strtotime('-3 months')));
				
		foreach($months as $month) {
			$count = 0;
			foreach($data['editsbyuser'][$month] as $user=>$edits) {
				if($edits>=$USEREDIT_LIMIT_FOR_INACTIVITY)
					$count++;
			}
			if($count<$USERCOUNT_LIMIT_FOR_INACTIVITY) {
				$isActive = false;
				break;
			}
		}
		
		echo ($isActive ? 1 : 0);
	}
	

	/***************
	* Output (human mode)
	***************/
	else {
		echo '<h2 id="Generated_statistics">Generated statistics</h2>';
		/***************
		* Table of contents
		***************/
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
		}
	
	
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
			if($edits<$USEREDIT_LIMIT_FOR_INACTIVITY)
				echo ' class="struckout"';
			echo '>', genLink('user:' . $user, $user), ' (<small>', $edits, ' edits</small>)</li>';
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
				$size = $data['revsizes']['totals'][$month]-$data['revsizes']['totals'][$lastmonth];
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
				if($edits>=$USEREDIT_LIMIT_FOR_INACTIVITY)
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
				if($edits<$USEREDIT_LIMIT_FOR_INACTIVITY)
					$isActive = true;
				else
					$isActive = false;
			
				echo genBar(genLink('user:' . $user, $user), $edits, 10, $isActive);
			}
			echo '</table>';
		}
	}
	echo '<!--', htmlspecialchars(print_r($db, true)), '-->';
} while (0);

/***************
* Debug
***************/
gDebug(get_defined_vars());

/* globals, templates */
if(!$render)
	makeFooter($license);
?>

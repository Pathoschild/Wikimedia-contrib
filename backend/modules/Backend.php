<?php
require_once( '__config__.php' );
require_once( 'Base.php' );
require_once( 'KLogger.php' );
require_once( 'Logger.php' );
require_once( 'Cacher.php' );
require_once( 'Database.php' );
require_once( 'Wikimedia.php' );

/**
 * Provides a wrapper used by page scripts to generate HTML, interact
 * with the database, and so forth.
 */
class Backend extends Base {
	/*########
	## Properties
	########*/
	/*
	 * @var string The current page's filename, like "index.php".
	 */
	private $filename  = NULL;
	
	/**
	 * @var string The page title, usually the name of the script.
	 */
	private $title     = NULL;
	private $blurb     = NULL; // a short description displayed at the top of the page; defaults to nothing.
	private $path      = NULL; // path breadcrumbs (defaults to title)
	private $source    = NULL; // array of files to display source for
	private $license   = NULL; // script license text (defaults to text in __config__.php)
	private $hook_head = NULL; // extra content to insert into HTML <head>
	private $modules_path = '../backend/modules/';
	private $valid_construct_opts = array( 'title', 'blurb', 'path', 'source', 'license', 'modules_path' );
	
	public $logger = NULL;
	public $cache = NULL;
	public $db = NULL;
	public $wikimedia = NULL;

	#################################################
	## Constructor
	## Build backend, given any of the following data in a hash (see properties):
	##	- title
	##	- blurb
	##	- path
	##	- source
	##	- license
	##	- modules_path
	#################################################
	public function __construct( $options ) {
		parent::__construct();
		
		/* validate options */
		$invalid = array_diff( array_keys($options), $this->valid_construct_opts );
		if( $invalid )
			die( "Invalid keys given to Backend constructor: '" . join("' and '", array_keys($invalid)) . "'." );
		
		/* get configuration */
		global $gconfig;
		$this->config = &$gconfig;
		
		/* handle options */
		$this->filename = basename( $_SERVER['SCRIPT_NAME'] );
		$this->title    = isset($options['title']) ? $options['title']   : $this->filename;
		$this->blurb    = isset($options['blurb']) ? $options['blurb']   : NULL;
		$this->path     = isset($options['path']) ? $options['path']     : array(&$this->title);
		$this->license  = isset($options['license']) ? $options['license'] : $gconfig['license'];
		$this->source   = isset($options['source']) ? $options['source'] : NULL;
		$this->modules_path = isset($options['modules_path']) ? $options['modules_path'] : $this->modules_path;
		$this->scripts = NULL;
		
		/* start logger */
		$key = hash('crc32b', $_SERVER['REQUEST_TIME'] . $_SERVER['HTTP_X_FORWARDED_FOR'] . $_SERVER['REQUEST_URI']);
		$this->logger = new Logger('/home/pathoschild/logs', $key);
		$this->logger->log('request: [' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . '] by [' . $_SERVER['HTTP_X_FORWARDED_FOR'] . ' ' . $_SERVER['HTTP_USER_AGENT'] . ']');
		
		/* build cache */
		$this->cache = new Cacher('/home/pathoschild/public_html/backend/modules/cache/', $this->logger, !!$this->get('purge'));
	}


	#################################################
	## Objects
	#################################################
	public function GetDatabase($options = NULL) {
		if(!$this->db)
			$this->db = new Toolserver($this->logger, $this->cache, $options);
		return $this->db;
	}
	
/*	public function GetWikimedia() {
		if(!$this->wikimedia) {
			$db = new Database('metawiki-p.rrdb.toolserver.org');
			$this->wikimedia = new Wikimedia($db, $this->cache);
		}
		return $this->wikimedia;
	}*/
	

	#################################################
	## HTTP encapsulation
	#################################################
	#############################
	## Return absolute URL given path relative to public_html
	#############################
	public function url( $path ) {
		return $this->config['root_url'] . $path;
	}

	#############################
	## Get a value from the HTTP GET values.
	#############################
	public function get( $name, $default = NULL ) {
		if(isset($_GET[$name]) && $_GET[$name] != '')
			return $_GET[$name];
		return $default;
	}


	#############################
	## Link to external files in the header
	#############################
	public function link( $files ) {
		if( !is_array($files) )
			$files = array( $files );
		$this->source = array_merge( $this->source, $files );

		foreach( $files as $file ) {
			$ext = substr( $file, -3 );
			switch( $ext ) {
				case 'css':
					$this->hook_head .= '<link rel="stylesheet" type="text/css" href="' . $file . '" />';
					break;
				case '.js':
					$this->hook_head .= '<script type="text/javascript" src="' . $file . '"></script>';
					break;
				default:
					die( "Invalid extension '{$ext}' (file '{$file}') passed to Backend::link." );
			}
		}
	}
	
	public function addScript( $script ) {
		$this->hook_head .= '<script type="text/javascript">' . "\n$script\n" . '</script>';
	}


	#############################
	## Print header
	#############################
	public function trackWithoutHtml($outlink = null) {
		require_once( __DIR__.'/external/PiwikTracker.php' );
	
		PiwikTracker::$URL = 'http://toolserver.org/~pathoschild/backend/piwik';
		$tracker = new PiwikTracker($idSite = 1);
		$tracker->setCustomVariable(1, 'tracked-without-html', 1);
		$tracker->setTokenAuth($PIWIK_AUTH_TOKEN);
		$tracker->setIp($_SERVER['HTTP_X_FORWARDED_FOR']);
		#$tracker->doTrackPageView($this->title);
		$tracker->doTrackAction('wut', 'download');
		if($outlink)
			$tracker->doTrackAction($outlink, 'link');
		
		echo '<pre>', print_r($tracker, true), '</pre>';
	}
	
	public function header() {
		/* print document head */
		echo '
<!-- begin generated header -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>', $this->title, '</title>
		<link rel="shortcut icon" href="', $this->config['style_url'], 'favicon.ico" />
		<link rel="stylesheet" type="text/css" href="', $this->config['style_url'], 'stylesheet.css?v=20120222" />
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
		', $this->hook_head, '
		<script src="//toolserver.org/~pathoschild/backend/piwik/piwik.js" type="text/javascript"></script>
	</head>
	<body>
		<div id="sidebar">
		<h4>Pathoschild\'s tools</h4>';
		
		/* print navigation menu */
		foreach( $this->config['tools'] as $section => $links ) {
			echo '
			<h5>', $section, '</h5>
			<ul>';
			
			foreach($links as $link) {
				$title = $link[0];
				$desc  = isset( $link[1] ) ? $link[1] : '';
				$desc  = str_replace( '\'', '&#38;', $desc ); 
				$url   = isset( $link[2] ) ? $link[2] : $this->config['root_url'];
				$url  .= $this->strip_nonlatin( str_replace(' ', '', $title) );
				
				echo '<li><a href="', $url, '" title="', $desc, '"',
					(isset($link[2]) ? ' class="is-legacy"' : ''),
					'>', $title, '</a></li>';
			}
			echo '</ul>';
		}
		
		/* print content head */
		echo '
		</div>
		<div id="content-column">
			<div id="content">';
		include('/home/pathoschild/public_html/backend/notice.php');
		echo '<h1>', $this->title, '<sup>beta</sup></h1>
				<p id="blurb">', $this->blurb, '</p>';
		echo '
<!-- end generated header -->';
		
		/* print source */
		if( isset($_REQUEST['action']) && $_REQUEST['action'] == 'source' )
			 $this->showSource();
	}
	
	#############################
	## Print footer
	#############################
	public function footer() {
		/* generate benchmarks */
		$precisionPercentage = $this->config['profile_perc_precision'];
		$precisionTime = $this->config['profile_time_precision'];
		$totalTime = $this->TimerGetElapsedSinceStart();
		$timerResults = array();
		foreach( $this->TimerGetKeys() as $key )
		{
			$time = $this->TimerGetElapsed($key);
			$this->_footer_benchmarks[$key] = sprintf(
				"%s (%s%%)",
				round($time, $precisionTime),
				round($time / $totalTime * 100, $precisionPercentage)
			);
		}
		$resultSeconds = round( $totalTime, $precisionTime );
		$this->logger->log('completed: ' . $resultSeconds . ' seconds.');
//		
		/* output */
		echo '
<!-- begin generated footer -->
			</div>
			<p id="license">
				You can <a href="?action=source" title="view source">view the code</a> or <a href="//meta.wikimedia.org/wiki/User_talk:Pathoschild?action=edit&section=new" title="discuss this script">discuss this script</a>. ', $this->license, '<br />
				Page generated in ', $resultSeconds, ' seconds.
		';
		
		if(count($timerResults)) {
			echo '<br />
				Benchmarks:<br />
			';
			foreach( $timerResults as $name => $time ) {
				echo '
					&emsp;&emsp;', $name, ': ', $time, '<br />';
			}
			echo '
				</table>';
		}
		
		echo '
			</p>
		</div>
	
		<script type="text/javascript">
			try {
				var piwikTracker = Piwik.getTracker(\'//toolserver.org/~pathoschild/backend/piwik/piwik.php\', 1);
				piwikTracker.trackPageView();
				piwikTracker.enableLinkTracking();
			} catch( err ) {}
		</script>
		<noscript>
			<img src="//toolserver.org/~pathoschild/backend/piwik/piwik.php?idsite=1&amp;rec=1&amp;urlref=', urlencode(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''), '" style="border:0" alt="" />
		</noscript>
	</body>
</html>';
	}

	#############################
	## Print source code
	#############################
	public function showSource() {
		/* print source header */
		echo '
				<!-- generated source code -->
				<h2>Source code (<a href="', $this->filename, '" title="hide source code">hide</a>)</h2>
				<div id="source" class="section">';
		
		/* warn about closed-source scripts */
		if( !$this->source ) {
			echo '
				<div class="error">No files are marked as open-source for this script. This is probably because they are still in development, or due to an oversight.</div>';
		}
		
	
		/* fetch list of modules */
		$modules = array();
		$path = $this->modules_path;
		$dir = dir( $path ) or print '<div class="error">Can\'t read module directory (' . $path . ').</div>';
		if( $dir ) {
			while( $file = $dir->read() ) {
				if( preg_match('/\.php$/', $file) )
					$modules[] = "$path$file";
			}
		}
		
		/* print table of contents */
		echo '
					<div id="toc">
						<b>Table of contents</b>
						<ul>';
		if( $this->source )
			$this->_source_toc( 'Script', $this->source );
		$this->_source_toc( 'Generic modules', $modules );
		echo '
						</ul>
					</div>';
		
		/* print source files */
		if( $this->source )
			$this->_source_files( 'Script', $this->source );
		$this->_source_files( 'Generic modules', $modules );
		echo '
		</div>';
	}

	#################################################
	## Private methods
	#################################################
	#############################
	## Print source code TOC entries
	#############################
	private function _source_toc( $name, $items ) {
		echo '
						<li>', $name, '
							<ol>';
		
		foreach( $items as $file ) {
			$anchor = $this->strip_nonlatin( $file );
			echo '
								<li><a href="#', $anchor, '" title="section for ', $file, '">', $file, '</a></li>';
		}
		echo '
							</ol>
						</li>';
	}


	#############################
	## Print source code files
	#############################
	private function _source_files( $name, $items ) {
		foreach( $items as $file ) {
			$anchor = $this->strip_nonlatin($file);
			echo '
					<h3 id="', $anchor, '">', $file, '</h3>
					<a href="#toc" title="toc">back to toc</a>
					<div class="source">';
			highlight_file($file);
			echo '
					</div>';
		}
	}
}
?>

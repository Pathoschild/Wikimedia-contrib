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
	/**
	 * The current page's filename, like "index.php".
	 * @var string
	 */
	private $filename  = NULL;

	/**
	 * The page title, usually the name of the script.
	 * @var string
	 */
	private $title     = NULL;

	/**
	 * A short description displayed at the top of the page; defaults to nothing.
	 * @var string
	 */
	private $blurb     = NULL;

	/**
	 * Extra content to insert into HTML <head>.
	 * @var string
	 */
	private $hook_head = NULL;

	public $logger = NULL;
	public $cache = NULL;
	public $db = NULL;
	public $wikimedia = NULL;

	#################################################
	## Constructor
	#################################################
	/**
	 * Construct a backend instance for a page.
	 * @param $title string The page title to display.
	 * @param $blurb string A short description displayed at the top of the page.
	 */
	public function __construct( $title, $blurb ) {
		parent::__construct();

		/* get configuration */
		global $settings;
		$this->config = &$settings;

		/* handle options */
		$this->filename = basename( $_SERVER['SCRIPT_NAME'] );
		$this->title    = isset($title) ? $title : $this->filename;
		$this->blurb    = isset($blurb) ? $blurb : NULL;
		$this->license  = $settings['license'];
		$this->scripts = NULL;

		/* start logger */
		$key = hash('crc32b', $_SERVER['REQUEST_TIME'] . $_SERVER['REQUEST_URI']);
		$this->logger = new Logger('/data/project/meta/logs', $key);
		$this->logger->log('request: [' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . '] by [' . $_SERVER['HTTP_USER_AGENT'] . ']');

		/* build cache */
		$this->cache = new Cacher('/data/project/meta/public_html/backend/modules/cache/', $this->logger, !!$this->get('purge'));
	}
	public static function create($title, $blurb) {
		return new Backend($title, $blurb);
	}


	#################################################
	## Objects
	#################################################
	public function GetDatabase($options = NULL) {
		if(!$this->db)
			$this->db = new Toolserver($this->logger, $this->cache, $options);
		return $this->db;
	}


	#################################################
	## HTTP encapsulation
	#################################################
	#############################
	## Get a value from the HTTP GET values.
	#############################
	/**
	 * Get a value from the HTTP request.
	 * @param string $name The name of the request argument.
	 * @param mixed $default The value to return if the request does not contain the value.
	 * @return mixed The expected or default value.
	 */
	public function get( $name, $default = NULL ) {
		if(isset($_GET[$name]) && $_GET[$name] != '')
			return $_GET[$name];
		return $default;
	}

	/**
	 * Get the value of the route placeholder (e.g, 'Pathoschild' in '/stalktoy/Pathoschild').
	 * @return mixed The expected value, or null.
	 */
	public function getRouteValue($index = 1) {
		return $this->get('@' . $index);
	}

	/**
	 * Build a URL.
	 * @param string $url The URL fragment. If it starts with '/', it will be treated as relative to the configured tools root.
	 */
	public function url($url) {
		if(substr($url, 0, 1) == '/' && substr($url, 1, 2) != '/') {
			global $settings;
			$url = $settings['root_url'] . $url;
		}
		return $url;
	}


	/**
	 * Link to external CSS or JavaScript in the header.
	 * @param string $url The URL of the CSS or JavaScript to fetch. If it starts with '/', it will be treated as relative to the configured tools root.
	 * @param string $as The reference type to render ('css' or 'js'), or null to use the file extension.
	 */
	public function link( $url, $as = null ) {
		if(!$as)
			$as = trim(substr( $url, -3 ), '\.');
		$url = $this->url($url);

		switch( $as ) {
			case 'css':
				$this->hook_head .= '<link rel="stylesheet" type="text/css" href="' . $url . '" />';
				break;
			case 'js':
				$this->hook_head .= '<script type="text/javascript" src="' . $url . '"></script>';
				break;
			default:
				die( "Invalid extension '{$as}' (URL '{$url}') passed to Backend->link." );
		}

		return $this;
	}

	public function addScript( $script ) {
		$this->hook_head .= '<script type="text/javascript">' . "\n$script\n" . '</script>';
		return $this;
	}


	#############################
	## Print header
	#############################
	public function header() {
		/* print document head */
		echo '
<!-- begin generated header -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>', $this->title, '</title>
		<link rel="shortcut icon" href="', $this->url('/content/favicon.ico'), '" />
		<link rel="stylesheet" type="text/css" href="', $this->url('/content/stylesheet.css'), '" />
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
		<script src="', $this->url('/content/jquery.collapse/jquery.collapse.js'), '" type="text/javascript"></script>
		<script src="', $this->url('/content/main.js'), '" type="text/javascript"></script>
		', $this->hook_head, '
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
				$title = isset($link[2]) ? $link[2] : $link[0];
				$desc  = isset( $link[1] ) ? $link[1] : '';
				$desc  = str_replace( '\'', '&#38;', $desc ); 
				$url   = $this->url($link[0]);

				echo '<li><a href="', $url, '" title="', $desc, '">', $title, '</a></li>';
			}
			echo '</ul>';
		}

		/* print content head */
		echo '
		</div>
		<div id="content-column">
			<div id="content">';
		include('/data/project/meta/public_html/backend/notice.php');
		echo '<h1>', $this->title, '<sup>beta</sup></h1>
				<p id="blurb">', $this->blurb, '</p>';
		echo '
<!-- end generated header -->';
		return $this;
	}

	#############################
	## Print footer
	#############################
	public function footer() {
		/* generate benchmarks */
		$precisionPercentage = $this->config['profile_perc_precision'];
		$precisionTime = $this->config['profile_time_precision'];
		$totalTime = $this->profiler->getElapsedSinceStart();
		$timerResults = array();
		foreach( $this->profiler->getKeys() as $key )
		{
			$time = $this->profiler->getElapsed($key);
			$timerResults[$key] = sprintf(
				"%s (%s%%)",
				round($time, $precisionTime),
				round($time / $totalTime * 100, $precisionPercentage)
			);
		}
		$resultSeconds = round( $totalTime, $precisionTime );
		$this->logger->log('completed: ' . $resultSeconds . ' seconds.');

		/* output */
		echo '
<!-- begin generated footer -->
			</div>
			<div id="footer">
				<div id="license">
					Hi! You can <a href="https://github.com/Pathoschild/Wikimedia-contrib" title="view source">view the source code</a> or <a href="https://github.com/Pathoschild/Wikimedia-contrib/issues" title="report issue">report a bug or suggestion</a>. ', $this->license, '
				</div>
				<div id="profiling">
					Page generated in ', $resultSeconds, ' seconds.
		';

		if(count($timerResults)) {
			echo '<span>[+]</span><ul>';
			foreach( $timerResults as $name => $time )
				echo '<li>', $name, ': ', $time, '</li>';
			echo '</ul>';
		}

		echo '
				</div>
			</div>
		</div>
	</body>
</html>';
	}
}
?>

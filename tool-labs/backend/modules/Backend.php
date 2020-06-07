<?php
require_once('__config__.php');
require_once('Base.php');
require_once('external/KLogger.php');
require_once('Logger.php');
require_once('Cacher.php');
require_once('Database.php');
require_once('Toolserver.php');
require_once('Wikimedia.php');

/**
 * Provides a wrapper used by page scripts to generate HTML, interact
 * with the database, and so forth.
 */
class Backend extends Base
{
    ##########
    ## Properties
    ##########
    /**
     * The current page's filename, like "index.php".
     * @var string
     */
    private $filename = null;

    /**
     * The page title, usually the name of the script.
     * @var string
     */
    private $title = null;

    /**
     * A short description displayed at the top of the page; defaults to nothing.
     * @var string
     */
    private $blurb = null;

    /**
     * Extra content to insert into the HTML head.
     * @var string
     */
    private $injectHead = '';

    /**
     * Writes messages to a log file for troubleshooting.
     * @var Logger
     */
    public $logger = null;

    /**
     * Reads and writes data to a cache with expiry dates.
     * @var Cacher
     */
    public $cache = null;

    /**
     * The global tool settings.
     * @var array
     */
    public $config;

    /**
     * The license text to inject.
     * @var string
     */
    public $license;

    /**
     * Provides database operations with optimizations and connection caching.
     * @var Toolserver
     */
    private $db = null;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $title The page title to display.
     * @param string $blurb A short description displayed at the top of the page.
     */
    public function __construct($title, $blurb)
    {
        parent::__construct();

        /* get configuration */
        global $settings;
        $this->config = &$settings;

        /* handle options */
        $this->filename = basename($_SERVER['SCRIPT_NAME']);
        $this->title = $title ? $title : $this->filename;
        $this->blurb = $blurb ? $blurb : null;
        $this->license = $settings['license'];

        /* start logger */
        $key = hash('crc32b', $_SERVER['REQUEST_TIME'] . $_SERVER['REQUEST_URI']);
        $this->logger = new Logger(LOG_PATH, $key, $settings['debug']);
        $this->logger->log('request: [' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . '] by [' . $_SERVER['HTTP_USER_AGENT'] . ']');

        /* build cache */
        $this->cache = new Cacher(CACHE_PATH, $this->logger, !!$this->get('purge'));
    }

    /**
     * Create a backend instance for a page.
     * @param string $title The page title to display.
     * @param string $blurb A short description displayed at the top of the page.
     * @return Backend
     */
    public static function create($title, $blurb)
    {
        return new Backend($title, $blurb);
    }

    /**
     * Get a database wrapper.
     * @param int $options The database options.
     * @return Toolserver
     */
    public function getDatabase($options = null)
    {
        if (!$this->db)
            $this->db = new Toolserver($this->profiler, $this->logger, $this->cache, $options);
        return $this->db;
    }

    /**
     * Get a value from the HTTP request.
     * @param string $name The name of the request argument.
     * @param mixed $default The value to return if the request does not contain the value.
     * @return mixed The expected or default value.
     */
    public function get($name, $default = null)
    {
        if (isset($_GET[$name]) && $_GET[$name] != '')
            return $_GET[$name];
        return $default;
    }

    /**
     * Get the value of the route placeholder (e.g, 'Pathoschild' in '/stalktoy/Pathoschild').
     * @param int $index The index of the placeholder to get.
     * @return mixed|null The expected value (if available).
     */
    public function getRouteValue($index = 0)
    {
        $path = $this->get("@path");
        if ($path)
        {
            $path = substr($path, 1); // ignore initial / in path
            $parts = explode('/', $path);
            return count($parts) > $index
                ? $parts[$index]
                : null;
        }
        return null;
    }

    /**
     * Get an absolute URL.
     * @param string $url The URL fragment. If it starts with '/', it will be treated as relative to the configured tools root.
     * @return string
     */
    public function url($url)
    {
        if (substr($url, 0, 1) == '/' && substr($url, 1, 2) != '/') {
            global $settings;
            $url = $settings['root_url'] . $url;
        }
        return $url;
    }

    /**
     * Link to external CSS or JavaScript in the header.
     * @param string $url The URL of the CSS or JavaScript to fetch. If it starts with '/', it will be treated as relative to the configured tools root.
     * @param string $as The reference type to render ('css' or 'js'), or null to use the file extension.
     * @return $this
     */
    public function link($url, $as = null)
    {
        if (!$as)
            $as = trim(substr($url, -3), '\.');
        $url = $this->url($url);

        switch ($as) {
            case 'css':
                $this->injectHead .= "<link rel='stylesheet' type='text/css' href='$url' />";
                break;
            case 'js':
                $this->injectHead .= "<script type='text/javascript' src='$url'></script>";
                break;
            default:
                die("Invalid extension '$as' (URL '$url') passed to Backend->link.");
        }

        return $this;
    }

    /**
     * Inject a JavaScript script into the page head.
     * @param string $script The script to inject.
     * @return $this
     */
    public function addScript($script)
    {
        $this->injectHead .= "<script type='text/javascript'>{$script}</script>";
        return $this;
    }

    #############################
    ## Print header
    #############################
    /**
     * Output the page head.
     * @return $this
     */
    public function header()
    {
        /* print document head */
        echo "
            <!-- begin generated header -->
            <!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.1//EN' 'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd'>
            <html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en'>
                <head>
                    <meta http-equiv='Content-Type' content='text/html; charset=utf-8' />
                    <title>{$this->title}</title>
                    <link rel='shortcut icon' href='{$this->url('/content/favicon.ico')}' />
                    <link rel='stylesheet' type='text/css' href='{$this->url('/content/stylesheet.css')}' />
                    <script src='https://tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/1.7.1/jquery.min.js' type='text/javascript'></script>
                    <script src='{$this->url('/content/jquery.collapse/jquery.collapse.js')}' type='text/javascript'></script>
                    <script src='{$this->url('/content/main.js')}' type='text/javascript'></script>
                    {$this->injectHead}
                </head>
                <body>
                    <div id='sidebar'>
                    <h4>Pathoschild's tools</h4>
            ";

        /* print navigation menu */
        foreach ($this->config['tools'] as $section => $links) {
            echo "
                <h5>$section</h5>
                <ul>
                ";

            foreach ($links as $link) {
                $title = isset($link[2]) ? $link[2] : $link[0];
                $desc = isset($link[1]) ? $link[1] : '';
                $desc = str_replace('\'', '&#38;', $desc);
                $url = $link[0];

                echo "<li><a href='$url' title='$desc'>$title</a></li>";
            }
            echo '</ul>';
        }

        /* print content head */
        echo "
            </div>
            <div id='content-column'>
                <div id='content'>";
        include(BACKEND_PATH . "/../notice.php");
        echo "
            <h1>{$this->title}</h1>
            <p id='blurb'>{$this->blurb}</p>

            <!-- end generated header -->
            ";
        return $this;
    }

    /**
     * Output the page footer.
     */
    public function footer()
    {
        /* generate benchmarks */
        $precisionPercentage = $this->config['profile_perc_precision'];
        $precisionTime = $this->config['profile_time_precision'];
        $totalTime = $this->profiler->getElapsedSinceStart();
        $timerResults = array();
        foreach ($this->profiler->getKeys() as $key) {
            $time = $this->profiler->getElapsed($key);
            $timerResults[$key] = sprintf(
                "%s (%s%%)",
                round($time, $precisionTime),
                round($time / $totalTime * 100, $precisionPercentage)
            );
        }
        $resultSeconds = round($totalTime, $precisionTime);
        $this->logger->log("completed: $resultSeconds seconds.");

        /* output */
        echo "
            <!-- begin generated footer -->
            </div>
            <div id='footer'>
                <div id='license'>
                    Hi! You can <a href='https://github.com/Pathoschild/Wikimedia-contrib' title='view source'>view the source code</a> or <a href='https://github.com/Pathoschild/Wikimedia-contrib/issues' title='report issue'>report a bug or suggestion</a>.
                    {$this->license}
                </div>
                <div id='profiling'>
                    Page generated in $resultSeconds seconds.
            ";

        if (count($timerResults)) {
            echo '<span>[+]</span><ul>';
            foreach ($timerResults as $name => $time)
                echo "<li>{$name}: {$time}</li>";
            echo '</ul>';
        }

        echo '
                        </div>
                    </div>
                </div>
            </body>
        </html>
        ';
    }
}

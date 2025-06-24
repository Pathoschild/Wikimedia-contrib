<?php
declare(strict_types=1);

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
     * The page title, usually the name of the script.
     */
    private string $title;

    /**
     * A short description displayed at the top of the page; defaults to nothing.
     */
    private ?string $blurb = null;

    /**
     * Extra content to insert into the HTML head.
     */
    private string $injectHead = '';

    /**
     * Writes messages to a log file for troubleshooting.
     */
    public Logger $logger;

    /**
     * Reads and writes data to a cache with expiry dates.
     */
    public Cacher $cache;

    /**
     * The global tool settings.
     */
    public array $config;

    /**
     * The license text to inject.
     */
    public string $license;

    /**
     * Provides database operations with optimizations and connection caching.
     */
    private ?Toolserver $db = null;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $title The page title to display.
     * @param string|null $blurb A short description displayed at the top of the page.
     */
    public function __construct(string $title, ?string $blurb)
    {
        parent::__construct();

        /* get configuration */
        global $settings;
        $this->config = &$settings;

        /* handle options */
        $this->title = $title ? $title : basename($_SERVER['SCRIPT_NAME']);
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
     * @param string|null $blurb A short description displayed at the top of the page.
     */
    public static function create(string $title, ?string $blurb): Backend
    {
        return new Backend($title, $blurb);
    }

    /**
     * Get a database wrapper.
     * @param int|null $options The database options.
     */
    public function getDatabase(?int $options = null): Toolserver
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
    public function get(string $name, mixed $default = null): mixed
    {
        if (isset($_GET[$name]) && $_GET[$name] != '')
            return $_GET[$name];
        return $default;
    }

    /**
     * Get the value of the route placeholder (e.g, 'Pathoschild' in '/stalktoy/Pathoschild').
     * @param int $index The index of the placeholder to get.
     * @return mixed The expected value (if available).
     */
    public function getRouteValue(int $index = 0): mixed
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
     */
    public function url(string $url): string
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
     */
    public function link(string $url): self
    {
        $url = $this->url($url);

        $this->injectHead .= str_ends_with($url, '.css')
            ? "<link rel='stylesheet' type='text/css' href='$url' />"
            : "<script type='text/javascript' src='$url'></script>";

        return $this;
    }

    /**
     * Inject a JavaScript script into the page head.
     * @param string $script The script to inject.
     */
    public function addScript(string $script): self
    {
        $this->injectHead .= "<script type='text/javascript'>{$script}</script>";
        return $this;
    }

    #############################
    ## Print header
    #############################
    /**
     * Output the page head.
     */
    public function header(): self
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
    public function footer(): void
    {
        /* generate benchmarks */
        $precisionPercentage = $this->config['profile_perc_precision'];
        $precisionTime = $this->config['profile_time_precision'];
        $totalTime = $this->profiler->getElapsedSinceStart();
        $timerResults = [];
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

<?php
declare(strict_types=1);

require_once('__config__.php');
require_once('Base.php');
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
     * Writes errors to the tool's error log.
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
        $this->logger = new Logger($key);

        /* build cache */
        $purge = $this->getBool('purge') ?? false;
        $this->cache = new Cacher(CACHE_PATH, $this->logger, $purge);
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
     * Get a raw value from the HTTP request's query arguments.
     * @param string $name The name of the query argument.
     * @param int $filter The filter to apply to the value (e.g. `FILTER_VALIDATE_INT`).
     * @return mixed|null The found value, or null if not found or invalid.
     */
    public function getRaw(string $name, int $filter = FILTER_DEFAULT): mixed
    {
        $rawValue = filter_input(INPUT_GET, $name);

        if ($rawValue === null || $rawValue === false)
            return null; // not found or invalid

        return $filter !== FILTER_DEFAULT
            ? filter_var($rawValue, $filter, FILTER_NULL_ON_FAILURE)
            : $rawValue;
    }

    /**
     * Parse a string value from the HTTP request's query arguments.
     * @param string $name The name of the query argument.
     * @param bool $allowBlank Whether to consider whitespace-only values valid.
     * @return string|null The found value; or null if not found or invalid.
     */
    public function getString(string $name, bool $allowBlank = true): ?string
    {
        $value = $this->getRaw($name);

        if (!$allowBlank && $value !== null && trim($value) === '')
            $value = null;

        return $value;
    }

    /**
     * Parse a boolean value from the HTTP request's query arguments.
     * @param string $name The name of the query argument.
     * @return bool|null The found value, or null if not found or invalid.
     */
    public function getBool(string $name): ?bool
    {
        return $this->getRaw($name, FILTER_VALIDATE_BOOL);
    }

    /**
     * Parse an integer value from the HTTP request's query arguments.
     * @param string $name The name of the query argument.
     * @return int|null The found value, or null if not found or invalid.
     */
    public function getInt(string $name): ?int
    {
        return $this->getRaw($name, FILTER_VALIDATE_INT);
    }

    /**
     * Get whether the request has a 'defer' marker, so it should prefill the form instead of
     * accepting the submission directly.
     *
     * This is used to avoid automatically triggering submissions when web crawlers follow links
     * between tools.
     */
    public function isDeferRequested(): bool
    {
        return $this->getBool('defer') ?? false;
    }

    /**
     * Get an HTML box which asks the user to submit the form to run a deferred action.
     *
     * @param string $buttonLabel The label of the button which needs to be clicked.
     */
    public function getDeferredHtml(string $buttonLabel): string
    {
        return "<div class='neutral' data-is-deferred='1'>Click <em>{$this->formatValue($buttonLabel)}</em> above to show the results.</div>\n";
    }

    /**
     * Get the segments after `/for/*` in the route path.
     *
     * This reads the `@route` query argument (set by the Lighttpd rewrite rules) and ignores leading/trailing/repeated
     * slashes. For example, '/for///18//Pathoschild/' produces ['18', 'Pathoschild'].
     *
     * @return string[]|null The route values, or null if no route was given.
     */
    public function getRoute(): ?array
    {
        // get raw route
        $route = $this->getRawQueryValue('@route'); // don't URL-decode yet, to avoid splitting by '/' inside submitted values
        if ($route === null)
            return null;

        // split into segments
        $values = [];
        foreach (explode('/', $route) as $value) {
            if ($value !== '')
                $values[] = urldecode($value);
        }

        return $values ?: null;
    }

    /**
     * Get the value of one segment after `/for/` in the route path.
     *
     * See remarks on {@see Backend::getRoute()}.
     *
     * @param int $index The index of the route segment to get, starting at 0 for the first segment after `/for/`.
     * @param int $filter The filter to apply to the value (e.g. `FILTER_VALIDATE_INT`).
     * @return mixed The found value, or null if not found or invalid.
     */
    public function getRouteValue(int $index = 0, int $filter = FILTER_DEFAULT): mixed
    {
        // get route
        $route = $this->getRoute();
        if (!$route)
            return null;

        // get raw value
        $value = $route[$index] ?? null;
        if ($value === null)
            return null;

        // apply filter
        if ($filter !== FILTER_DEFAULT)
            $value = filter_var($value, $filter, FILTER_NULL_ON_FAILURE);

        return $value;
    }

    /**
     * Get a value from the request's query string, without URL-decoding it.
     *
     * @param string $name The name of the query argument.
     * @return string|null The raw argument value if found, else null.
     */
    private function getRawQueryValue(string $name): ?string
    {
        //
        // note: don't use `$_GET`, since that's already URL-decoded.
        //

        $rawQueryString = $_SERVER['QUERY_STRING'] ?? '';
        $rawQueryArgs = explode('&', $rawQueryString);

        $value = null;

        foreach ($rawQueryArgs as $pair) {
            $parts = explode('=', $pair, 2);
            if (urldecode($parts[0]) === $name)
                $value = $parts[1] ?? ''; // take last value defined
        }

        return $value;
    }

    /**
     * Get an absolute URL to a file on this server.
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
     * Get an absolute URL to a tool in this repo.
     * @param string $toolName The tool name (e.g. 'stalktoy'), matching both its folder name in this repo and its Toolforge account name.
     * @param string|null $lookup The value to pass as a route value, if any. This is URL-encoded automatically.
     */
    public function getToolUrl(string $toolName, ?string $lookup = null): string
    {
        $url = sprintf($this->config['tool_url_format'], $toolName) . '/';
        if ($lookup !== null && $lookup !== '')
            $url .= 'for/' . urlencode($lookup);
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
        echo '<ul>';
        foreach ($this->config['tools'] as $toolName => $text) {
            $url = $this->getToolUrl($toolName);
            $displayName = $this->formatValue($text[0]);
            $description = $this->formatValue($text[1]);

            echo "<li><a href='$url' title='$description'>$displayName</a></li>";
        }
        echo '</ul>';

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

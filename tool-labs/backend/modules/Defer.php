<?php
declare(strict_types=1);

/**
 * Manages request deferral.
 *
 * See 'deferred requests' in the README.
 */
class Defer
{
    ##########
    ## Properties
    ##########
    #####
    ## Constants
    #####
    /**
     * The minimum number of queued requests required before some requests are deferred
     * automatically. The proportion of requests that get deferred is lerped between
     * `MIN_QUEUE_SIZE` (0%) and `MAX_QUEUE_SIZE` (100%).
     *
     * This is the point where the server is still responsive, but requests are starting to get a
     * noticeable delay.
     *
     * Deferring requests is annoying for users, so this should be tuned to avoid deferral if the
     * queue can still catch up on its own.
     */
    private const MIN_QUEUE_SIZE = 12;

    /**
     * The maximum number of queued requests required for every request to be deferred
     * automatically. See docs on {@see Defer::MIN_QUEUE_SIZE}.
     *
     * This is the point where the server is overloaded, requests are significantly delayed, and
     * further queue growth risks a death spiral.
     */
    private const MAX_QUEUE_SIZE = 32;

    /**
     * The age of '~/server-load.json' in seconds before it's considered stale and unreliable, when
     * the file indicates the server is processing requests.
     */
    private const STALE_SECONDS_WHEN_BUSY = 15;

    /**
     * The age of '~/server-load.json' in seconds before it's considered stale and unreliable, when
     * the file indicates the server has zero queued requests.
     */
    private const STALE_SECONDS_WHEN_IDLE = 600;

    /**
     * A value indicating that the request doesn't need to be deferred.
     */
    private const DEFER_NONE = 0;

    /**
     * A value indicating that the request should be deferred because there are too many queued
     * HTTP requests.
     */
    private const DEFER_OVERLOAD = 1;

    /**
     * A value indicating that the request should be deferred because the URL has an explicit
     * `defer=1` query argument.
     */
    private const DEFER_REQUESTED = 2;

    #####
    ## State
    #####
    /**
     * The backend with which to parse request parameters and format HTML output.
     */
    private Backend $backend;

    /**
     * The recommended deferral state (matching a constant like {@see Defer::DEFER_NONE}), or `null`
     * if {@see Defer::shouldDefer} hasn't been called yet.
     */
    private ?int $defer = null;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param Backend $backend The backend with which to parse request parameters and format HTML output
     */
    public function __construct(Backend $backend)
    {
        $this->backend = $backend;
    }

    /**
     * Get whether the request should be deferred if it's expensive.
     *
     * The result is cached and safe to call repeatedly.
     */
    public function shouldDefer(): bool
    {
        $this->defer ??= $this->getDefer();

        return $this->defer !== self::DEFER_NONE;
    }

    /**
     * Get an HTML box which asks the user to confirm the request.
     *
     * The output depends on why the request was deferred:
     *   - If it was deferred automatically due to server overload, the box contains a form to POST
     *     the current request. That prevents the new request from being automatically selected for
     *     deferral again.
     *   - Otherwise, the box contains a message asking the user to click the normal submit button
     *     above.
     *
     * @param string $buttonLabel The label of the button which needs to be clicked (either the pre-existing submit button, or the name used for the overload form's submit button).
     */
    public function getConfirmHtml(string $buttonLabel): string
    {
        $buttonLabel = $this->backend->formatValue($buttonLabel);

        if ($this->shouldDefer() && $this->defer === self::DEFER_OVERLOAD) {
            $url = $this->backend->formatValue($_SERVER['REQUEST_URI'] ?? '/'); // the original requested URL

            return "
                <div class='neutral' data-is-deferred='1' data-is-overloaded='1'>
                    <p>Please click the button below to show the results. (This is shown automatically when the tool is overloaded by bot traffic.)</p>
                    <form action='$url' method='post'>
                        <input type='submit' value='$buttonLabel' />
                    </form>
                </div>\n";
        }

        return "<div class='neutral' data-is-deferred='1'>Click <em>$buttonLabel</em> above to show the results.</div>\n";
    }


    ##########
    ## Private methods
    ##########
    /**
     * Get whether the request should be deferred, ignoring the cache.
     */
    private function getDefer(): int
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') // POST means the user has already confirmed the request, so don't defer it again
        {
            if ($this->backend->getBool('defer') ?? false)
                return self::DEFER_REQUESTED;

            if ($this->getShouldDeferByDefault())
                return self::DEFER_OVERLOAD;
        }

        return self::DEFER_NONE;
    }

    /**
     * Get whether the request should be deferred by default due to server load.
     */
    private function getShouldDeferByDefault(): bool
    {
        // get queued requests
        $queued = $this->getQueuedRequestCount();
        if ($queued === null)
            return false; // unknown or not tracked yet, so fallback to normal behavior

        // get ratio of requests to defer
        $deferRatio = ($queued - self::MIN_QUEUE_SIZE) / (self::MAX_QUEUE_SIZE - self::MIN_QUEUE_SIZE);
        if ($deferRatio <= 0)
            return false;

        // check if current request should be deferred
        return
            $deferRatio >= 1
            || mt_rand(1, 100) <= (int)round($deferRatio * 100);
    }

    /**
     * Get the number of requests waiting for the webservice, or null if it's unknown.
     *
     * This reads the file written by the `track-server-load` job.
     */
    private function getQueuedRequestCount(): ?int
    {
        // read file
        $raw = @file_get_contents(DATA_PATH . '/server-load.json');
        if ($raw === false)
            return null; // job hasn't run yet

        // parse data
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['queued'], $data['generated']) || !is_int($data['queued']) || !is_int($data['generated']))
            return null; // invalid or outdated file

        // ignore stale data
        $age = time() - $data['generated'];
        $maxAge = $data['queued'] > 0
            ? self::STALE_SECONDS_WHEN_BUSY
            : self::STALE_SECONDS_WHEN_IDLE;
        if ($age < 0 || $age > $maxAge)
            return null;

        // get queued count
        return $data['queued'];
    }
}

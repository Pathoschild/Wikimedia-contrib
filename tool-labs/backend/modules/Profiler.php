<?php
declare(strict_types=1);

/**
 * Provides basic performance profiling.
 */
class Profiler
{
    ##########
    ## Properties
    ##########
    /**
     * The benchmarking time results, each being an array of millisecond times in the form (startTime, endTime).
     * @var array<string, array<float|null>>
     */
    private array $times = [];

    /**
     * The second time at which the {@see Profiler} instance was constructed.
     */
    private float $timeStart;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     */
    public function __construct()
    {
        $this->timeStart = $this->getCurrentTime();
    }

    /**
     * Start a benchmarking timer.
     * @param string $key The unique name to associate with the timer.
     */
    public function start(string $key): void
    {
        $this->times[$key] = [$this->getCurrentTime(), null];
    }

    /**
     * Stop a benchmarking timer.
     * @param string $key The unique name of the timer.
     * @throws InvalidArgumentException There is no timer matching the given key.
     * @throws Exception The timer matching the given key is already stopped.
     */
    public function stop(string $key): void
    {
        if (!isset($this->times[$key]))
            throw new InvalidArgumentException("There is no timer named '$key'.");
        if (isset($this->times[$key][1]))
            throw new Exception("Cannot stop timer '$key', it is already stopped.");
        $this->times[$key][1] = $this->getCurrentTime();
    }

    /**
     * Get the total time elapsed since the script started running in seconds (with millisecond precision).
     * @return float The total time elapsed in seconds.
     * @throws Exception The script start time wasn't initialised.
     */
    public function getElapsedSinceStart(): float
    {
        if (!$this->timeStart)
            throw new Exception("Script start time was not initialized.");
        return $this->getCurrentTime() - $this->timeStart;
    }

    /**
     * Get a benchmarking timer's elapsed time in decimal seconds.
     * @param string $key The unique name of the timer.
     * @return float The total time in milliseconds that elapsed between starting and stopping the named timer.
     */
    public function getElapsed(string $key): float
    {
        if (!isset($this->times[$key]))
            throw new InvalidArgumentException('There is no timer named "' . $key . '".');
        return $this->times[$key][1] - $this->times[$key][0];
    }

    /**
     * Delete a benchmarking timer.
     * @param string $key The unique name of the timer.
     */
    public function delete(string $key): void
    {
        unset($this->times[$key]);
    }

    /**
     * Get all benchmarking timer keys.
     * @return string[] An array of available benchmarking keys.
     */
    public function getKeys(): array
    {
        return array_keys($this->times);
    }


    ##########
    ## Private methods
    ##########
    /**
     * Get the current time in seconds (with millisecond precision).
     */
    private function getCurrentTime(): float
    {
        return microtime(true);
    }
}

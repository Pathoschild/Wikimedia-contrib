<?php

/**
 * Metadata about an event for which eligibility can be analysed.
 */
class Event
{
    ##########
    ## Accessors
    ##########
    /**
     * The unique event ID.
     * @var int
     */
    public $id;

    /**
     * The year in which the event occurred.
     * @var int
     */
    public $year;

    /**
     * The human-readable event name.
     * @var string
     */
    public $name;

    /**
     * The URL for the page which provides more information about the event.
     * @var string
     */
    public $url;

    /**
     * A human-readable label for the action for which eligibility is being analysed (e.g. "be a candidate").
     * @var string
     */
    public $action;

    /**
     * A list of additional human-readable requirements that must be met which can't be verified by the script.
     * @var string[]
     */
    public $extraRequirements = [];

    /**
     * A list of exceptions that can't be verified by the script.
     * @var string[]
     */
    public $exceptions = [];

    /**
     * The minimum number of edits for a local account to be autoselected for analysis.
     * @var int
     */
    public $minEditsForAutoselect = 1;

    /**
     * The only database name to analyse (or null to allow any wiki).
     * @var string|null
     */
    public $onlyDB;

    /**
     * Whether the event is obsolete.
     * @var bool
     */
    public $obsolete;

    /**
     * The eligibility rules.
     * @var RuleEntry[]
     */
    public $rules = [];


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param int $id The unique event ID.
     * @param int $year The year in which the event occurred.
     * @param string $name The human-readable event name.
     * @param string $url The URL for the page which provides more information about the event.
     */
    public function __construct($id, $year, $name, $url)
    {
        $this->id = $id;
        $this->year = $year;
        $this->name = $name;
        $this->url = $url;
        $this->obsolete = $year < (new DateTime('now', new DateTimeZone('utc')))->format('Y');
    }

    /**
     * Add a new eligibility rule for this event.
     * @param Rule $rule The eligibility rule.
     * @param int $options An optional bit flag (see {@see Workflow}).
     * @return $this
     */
    public function addRule($rule, $options = null)
    {
        array_push($this->rules, new RuleEntry($rule, $options));
        return $this;
    }

    /**
     * Set a human-readable label for the action for which eligibility is being analysed (e.g. "be a candidate").
     * @param string $value The value to set.
     * @return $this
     */
    public function withAction($value)
    {
        $this->action = $value;
        return $this;
    }

    /**
     * Set a list of additional human-readable requirements that must be met which can't be verified by the script.
     * @param string[] $value The value to set.
     * @return $this
     */
    public function withExtraRequirements($value)
    {
        $this->extraRequirements = $value;
        return $this;
    }

    /**
     * Set a list of exceptions that can't be verified by the script.
     * @param string[] $value The value to set.
     * @return $this
     */
    public function withExceptions($value)
    {
        $this->exceptions = $value;
        return $this;
    }

    /**
     * Set the minimum number of edits for a local account to be autoselected for analysis.
     * @param int $value
     * @return $this
     */
    public function withMinEditsForAutoselect($value)
    {
        $this->minEditsForAutoselect = $value;
        return $this;
    }

    /**
     * Set the only database name to analyse.
     * @param string $value
     * @return $this;
     */
    public function withOnlyDB($value)
    {
        $this->onlyDB = $value;
        return $this;
    }
}

<?php
declare(strict_types=1);

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
     */
    public int $id;

    /**
     * The year in which the event occurred.
     */
    public int $year;

    /**
     * The human-readable event name.
     */
    public string $name;

    /**
     * The URL for the page which provides more information about the event.
     */
    public string $url;

    /**
     * A human-readable label for the action for which eligibility is being analysed (e.g. "be a candidate").
     */
    public string $action;

    /**
     * A list of additional human-readable requirements that must be met which can't be verified by the script.
     * @var string[]
     */
    public array $extraRequirements = [];

    /**
     * A list of exceptions that can't be verified by the script.
     * @var string[]
     */
    public array $exceptions = [];

    /**
     * The minimum number of edits for a local account to be autoselected for analysis.
     */
    public int $minEditsForAutoselect = 1;

    /**
     * The only database names to analyse (or null to allow any wiki).
     * @var string[]|null
     */
    public ?array $onlyDatabaseNames = null;

    /**
     * Whether the event is obsolete, so it should be grayed out in the UI.
     */
    public bool $obsolete = false;

    /**
     * The eligibility rules.
     * @var RuleEntry[]
     */
    public array $rules = [];


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
    public function __construct(int $id, int $year, string $name, string $url)
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
     */
    public function addRule(Rule $rule, ?int $options = null): self
    {
        if ($options === null)
            $options = 0;

        array_push($this->rules, new RuleEntry($rule, $options));
        return $this;
    }

    /**
     * Set a human-readable label for the action for which eligibility is being analysed (e.g. "be a candidate").
     * @param string $value The value to set.
     */
    public function withAction(string $value): self
    {
        $this->action = $value;
        return $this;
    }

    /**
     * Set a list of additional human-readable requirements that must be met which can't be verified by the script.
     * @param string[] $value The value to set.
     */
    public function withExtraRequirements(array $value): self
    {
        $this->extraRequirements = $value;
        return $this;
    }

    /**
     * Set a list of exceptions that can't be verified by the script.
     * @param string[] $value The value to set.
     */
    public function withExceptions(array $value): self
    {
        $this->exceptions = $value;
        return $this;
    }

    /**
     * Set the minimum number of edits for a local account to be autoselected for analysis.
     * @param int $value The minimum number of edits required.
     */
    public function withMinEditsForAutoselect(int $value): self
    {
        $this->minEditsForAutoselect = $value;
        return $this;
    }

    /**
     * Mark this event obsolete so it's grayed out in the UI.
     */
    public function markObsolete(): self
    {
        $this->obsolete = true;
        return $this;
    }

    /**
     * Set the only database names to analyse.
     * @param string|string[] $value The database name or names to allow.
     */
    public function withOnlyDatabaseNames(array|string $value): self
    {
        $this->onlyDatabaseNames = is_array($value)
            ? array_values($value)
            : [$value];
        return $this;
    }

    /**
     * Get whether a database name is allowed based on the 'onlyDatabaseNames' field.
     */
    public function allowsDatabase(string $dbName): bool
    {
        return
            $this->onlyDatabaseNames == null
            || in_array($dbName, $this->onlyDatabaseNames);
    }
}

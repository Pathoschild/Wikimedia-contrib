<?php
declare(strict_types=1);

/**
 * Provides metadata about an eligibility check.
 */
class ResultInfo
{
    ##########
    ## Accessors
    ##########
    /**
     * The eligibility check result (one of the {@see Result} values).
     */
    public string $result;

    /**
     * Whether the result is final (i.e. there's no need to check further wikis).
     */
    public bool $isFinal;

    /**
     * A human-readable message summarising the eligibility result.
     */
    public string $message;

    /**
     * Warning messages to append for this result.
     * @var string[]
     */
    public array $warnings = [];

    /**
     * Notes to append for this result.
     * @var string[]
     */
    public array $notes = [];


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $result The eligibility check result (one of the {@see Result} values).
     * @param string $message Whether the result is final (i.e. there's no need to check further wikis).
     * @param bool $isFinal A human-readable message summarising the eligibility result.
     */
    public function __construct(string $result, string $message, bool $isFinal = false)
    {
        $this->result = $result;
        $this->message = $message;
        $this->isFinal = $isFinal;
    }

    /**
     * Get whether the eligibility check passed.
     */
    public function isPass(): bool
    {
        return $this->result == Result::PASS;
    }

    /**
     * Get whether the eligibility check passed, but we should still check other wikis.
     */
    public function isSoftPass(): bool
    {
        return $this->result == Result::SOFT_PASS;
    }

    /**
     * Get whether the eligibility check failed.
     */
    public function isFail(): bool
    {
        return $this->result == Result::FAIL;
    }

    /**
     * Add a warning message for this result.
     * @param string $message The warning message.
     */
    public function addWarning(string $message): void
    {
        array_push($this->warnings, $message);
    }

    /**
     * Add a note about this result.
     * @param string $message The note message.
     */
    public function addNote(string $message): void
    {
        array_push($this->notes, $message);
    }
}

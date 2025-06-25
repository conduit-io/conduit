<?php

namespace App\Services;

/**
 * Value object for process execution results
 */
class ProcessResult
{
    public function __construct(
        private bool $successful,
        private string $output,
        private string $errorOutput
    ) {}

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getErrorOutput(): string
    {
        return $this->errorOutput;
    }

    public function hasError(): bool
    {
        return ! $this->successful;
    }
}

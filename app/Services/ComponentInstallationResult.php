<?php

namespace App\Services;

/**
 * Result object for component installation operations
 */
class ComponentInstallationResult
{
    private function __construct(
        private bool $successful,
        private string $message,
        private array $componentInfo = [],
        private array $commands = [],
        private ?ProcessResult $processResult = null
    ) {}

    public static function success(array $componentInfo, array $commands): self
    {
        return new self(
            successful: true,
            message: 'Component installed successfully',
            componentInfo: $componentInfo,
            commands: $commands
        );
    }

    public static function failed(string $message, ?ProcessResult $processResult = null): self
    {
        return new self(
            successful: false,
            message: $message,
            processResult: $processResult
        );
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getComponentInfo(): array
    {
        return $this->componentInfo;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getProcessResult(): ?ProcessResult
    {
        return $this->processResult;
    }
}

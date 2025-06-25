<?php

namespace App\Contracts;

interface ComponentManagerInterface
{
    /**
     * Check if a component is installed
     */
    public function isInstalled(string $name): bool;

    /**
     * Get all installed components
     */
    public function getInstalled(): array;

    /**
     * Get registry of available components
     */
    public function getRegistry(): array;

    /**
     * Register a component
     */
    public function register(string $name, array $componentInfo, ?string $version = null): void;

    /**
     * Unregister a component
     */
    public function unregister(string $name): void;

    /**
     * Discover available components from external sources
     */
    public function discoverComponents(): array;

    /**
     * Get a global setting value
     */
    public function getGlobalSetting(string $key, mixed $default = null): mixed;

    /**
     * Update a global setting value
     */
    public function updateGlobalSetting(string $key, mixed $value): void;

    /**
     * Get all global settings
     */
    public function getGlobalSettings(): array;

    /**
     * Get registered service providers
     */
    public function getServiceProviders(): array;

    /**
     * Migrate existing config data to database storage
     */
    public function migrateFromConfig(): array;
}
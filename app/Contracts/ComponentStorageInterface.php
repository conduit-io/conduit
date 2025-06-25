<?php

namespace App\Contracts;

interface ComponentStorageInterface
{
    /**
     * Get all installed components
     */
    public function getInstalled(): array;

    /**
     * Check if a component is installed
     */
    public function isInstalled(string $name): bool;

    /**
     * Register a new component
     */
    public function registerComponent(string $name, array $componentInfo, ?string $version = null): void;

    /**
     * Unregister a component
     */
    public function unregisterComponent(string $name): bool;

    /**
     * Get a component by name
     */
    public function getComponent(string $name): ?array;

    /**
     * Get all service providers
     */
    public function getServiceProviders(): array;

    /**
     * Get a global setting value
     */
    public function getSetting(string $key, mixed $default = null): mixed;

    /**
     * Set a global setting value
     */
    public function setSetting(string $key, mixed $value): void;

    /**
     * Get all global settings
     */
    public function getAllSettings(): array;

    /**
     * Check if database is initialized
     */
    public function isDatabaseInitialized(): bool;

    /**
     * Migrate existing config data to database storage
     */
    public function migrateFromConfig(): array;
}
<?php

namespace App\Actions;

trait DetectsServiceProviders
{
    /**
     * Detect service providers from an installed Composer package
     */
    protected function detectServiceProviders(string $packageName): array
    {
        try {
            $composerJsonPath = base_path("vendor/{$packageName}/composer.json");

            if (! file_exists($composerJsonPath)) {
                return [];
            }

            $composerJson = json_decode(file_get_contents($composerJsonPath), true);

            // Check Laravel extra.laravel.providers
            $providers = $composerJson['extra']['laravel']['providers'] ?? [];

            return is_array($providers) ? $providers : [];
        } catch (\Exception $e) {
            error_log("Error detecting service providers for {$packageName}: ".$e->getMessage());

            return [];
        }
    }

    /**
     * Detect aliases/facades from an installed package
     */
    protected function detectAliases(string $packageName): array
    {
        try {
            $composerJsonPath = base_path("vendor/{$packageName}/composer.json");

            if (! file_exists($composerJsonPath)) {
                return [];
            }

            $composerJson = json_decode(file_get_contents($composerJsonPath), true);

            // Check Laravel extra.laravel.aliases
            $aliases = $composerJson['extra']['laravel']['aliases'] ?? [];

            return is_array($aliases) ? $aliases : [];
        } catch (\Exception $e) {
            error_log("Error detecting aliases for {$packageName}: ".$e->getMessage());

            return [];
        }
    }

    /**
     * Detect custom Conduit extensions from package
     */
    protected function detectConduitExtensions(string $packageName): array
    {
        try {
            $composerJsonPath = base_path("vendor/{$packageName}/composer.json");

            if (! file_exists($composerJsonPath)) {
                return [];
            }

            $composerJson = json_decode(file_get_contents($composerJsonPath), true);

            // Check for Conduit-specific extensions
            $extensions = $composerJson['extra']['conduit'] ?? [];

            return is_array($extensions) ? $extensions : [];
        } catch (\Exception $e) {
            error_log("Error detecting Conduit extensions for {$packageName}: ".$e->getMessage());

            return [];
        }
    }

    /**
     * Get all Laravel package metadata
     */
    protected function getPackageMetadata(string $packageName): array
    {
        return [
            'providers' => $this->detectServiceProviders($packageName),
            'aliases' => $this->detectAliases($packageName),
            'conduit_extensions' => $this->detectConduitExtensions($packageName),
        ];
    }
}

<?php

namespace App\Services;

/**
 * Service for detecting service providers and commands from installed packages
 */
class ServiceProviderDetector
{
    /**
     * Detect service providers from an installed Composer package
     */
    public function detectServiceProviders(string $packageName): array
    {
        try {
            $vendorPath = base_path("vendor/{$packageName}/composer.json");

            if (! file_exists($vendorPath)) {
                return [];
            }

            $composerJson = json_decode(file_get_contents($vendorPath), true);
            $providers = $composerJson['extra']['laravel']['providers'] ?? [];

            return is_array($providers) ? $providers : [];
        } catch (\Exception $e) {
            error_log('Error detecting service providers: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Detect commands provided by service providers
     */
    public function detectCommands(array $serviceProviders): array
    {
        $commands = [];

        foreach ($serviceProviders as $provider) {
            try {
                $packageCommands = $this->discoverCommandsFromProvider($provider);
                $commands = array_merge($commands, $packageCommands);
            } catch (\Exception $e) {
                error_log("Error detecting commands from provider {$provider}: ".$e->getMessage());
            }
        }

        return array_unique($commands);
    }

    /**
     * Discover commands from a service provider by examining package structure
     */
    private function discoverCommandsFromProvider(string $provider): array
    {
        $parts = explode('\\', $provider);
        if (count($parts) < 2) {
            return [];
        }

        $vendor = strtolower($parts[0]);
        $package = strtolower($parts[1]);

        $possiblePaths = [
            base_path("vendor/{$vendor}/{$package}/src/Commands"),
            base_path("vendor/{$vendor}/{$package}/app/Commands"),
        ];

        $commands = [];
        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                $commands = array_merge($commands, $this->extractCommandsFromDirectory($path));
            }
        }

        return $commands;
    }

    /**
     * Extract command signatures from PHP command files
     */
    private function extractCommandsFromDirectory(string $directory): array
    {
        $commands = [];

        try {
            $files = glob($directory.'/*.php');
            foreach ($files as $file) {
                $content = file_get_contents($file);

                if (preg_match('/protected\\s+\\$signature\\s*=\\s*[\\\'"]([^\\\'"\\s]+)/', $content, $matches)) {
                    $signature = $matches[1];
                    $commandName = explode(' ', $signature)[0];
                    if (! empty($commandName)) {
                        $commands[] = $commandName;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error reading command directory {$directory}: ".$e->getMessage());
        }

        return $commands;
    }
}

<?php

namespace App\Actions;

trait DetectsCommands
{
    /**
     * Detect commands provided by service providers
     */
    protected function detectCommands(array $serviceProviders): array
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
    protected function discoverCommandsFromProvider(string $provider): array
    {
        // Extract package namespace to find vendor directory
        $parts = explode('\\', $provider);
        if (count($parts) < 2) {
            return [];
        }

        $vendor = strtolower($parts[0]);
        $package = strtolower($parts[1]);

        // Look for command files in common locations
        $possiblePaths = [
            base_path("vendor/{$vendor}/{$package}/src/Commands"),
            base_path("vendor/{$vendor}/{$package}/app/Commands"),
            base_path("vendor/{$vendor}/{$package}/Commands"),
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
    protected function extractCommandsFromDirectory(string $directory): array
    {
        $commands = [];

        try {
            $files = glob($directory.'/*.php');
            foreach ($files as $file) {
                $commandSignatures = $this->extractCommandSignaturesFromFile($file);
                $commands = array_merge($commands, $commandSignatures);
            }
        } catch (\Exception $e) {
            error_log("Error reading command directory {$directory}: ".$e->getMessage());
        }

        return $commands;
    }

    /**
     * Extract command signatures from a single PHP file
     */
    protected function extractCommandSignaturesFromFile(string $filePath): array
    {
        $commands = [];

        try {
            $content = file_get_contents($filePath);

            // Look for command signatures using multiple patterns
            $patterns = [
                '/protected\s+\$signature\s*=\s*[\'"]([^\'"\s]+)/', // Standard signature
                '/public\s+\$signature\s*=\s*[\'"]([^\'"\s]+)/',   // Public signature
                '/@Command\([\'"]([^\'"\s]+)[\'"]/',               // Annotation style
            ];

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $signature) {
                        // Extract just the command name (before any arguments/options)
                        $commandName = explode(' ', trim($signature))[0];
                        if (! empty($commandName) && ! str_contains($commandName, ':')) {
                            $commands[] = $commandName;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error parsing command file {$filePath}: ".$e->getMessage());
        }

        return $commands;
    }

    /**
     * Detect commands from package using multiple strategies
     */
    protected function detectCommandsFromPackage(string $packageName): array
    {
        $serviceProviders = $this->detectServiceProviders($packageName);
        $commands = $this->detectCommands($serviceProviders);

        // Add package-specific command detection logic
        $packageSpecificCommands = $this->detectPackageSpecificCommands($packageName);

        return array_unique(array_merge($commands, $packageSpecificCommands));
    }

    /**
     * Handle known packages with specific command patterns
     */
    protected function detectPackageSpecificCommands(string $packageName): array
    {
        $knownPackages = [
            'jordanpartridge/github-zero' => ['repos', 'clone'],
            // Add other known packages here
        ];

        return $knownPackages[$packageName] ?? [];
    }
}

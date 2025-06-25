<?php

namespace App\Services;

use App\Actions\DetectsCommands;
use App\Actions\DetectsServiceProviders;
use App\Actions\InstallsComposerPackage;
use Carbon\Carbon;

class ComponentInstaller
{
    use DetectsCommands;
    use DetectsServiceProviders;
    use InstallsComposerPackage;

    /**
     * Install a component package
     */
    public function install(array $component): array
    {
        $packageName = $component['full_name'];

        // Step 1: Check if already installed
        if ($this->isPackageInstalled($packageName)) {
            throw new \RuntimeException("Package {$packageName} is already installed");
        }

        // Step 2: Install via Composer
        $installResult = $this->installComposerPackage($packageName);

        if (! $installResult['success']) {
            throw new \RuntimeException(
                'Failed to install Composer package: '.$installResult['error']
            );
        }

        // Step 3: Detect package metadata
        $metadata = $this->getPackageMetadata($packageName);
        $commands = $this->detectCommandsFromPackage($packageName);
        $version = $this->getInstalledPackageVersion($packageName);

        // Step 4: Build component info
        return [
            'package' => $packageName,
            'version' => $version,
            'description' => $component['description'],
            'commands' => $commands,
            'service_providers' => $metadata['providers'],
            'aliases' => $metadata['aliases'],
            'conduit_extensions' => $metadata['conduit_extensions'],
            'topics' => $component['topics'] ?? [],
            'url' => $component['url'],
            'stars' => $component['stars'] ?? 0,
            'language' => $component['language'] ?? 'Unknown',
            'license' => $component['license'] ?? 'No license',
            'status' => 'active',
            'installed_at' => Carbon::now()->toISOString(),
            'install_output' => $installResult['output'],
        ];
    }

    /**
     * Uninstall a component package
     */
    public function uninstall(array $componentInfo): array
    {
        $packageName = $componentInfo['package'];

        // Step 1: Check if package is installed
        if (! $this->isPackageInstalled($packageName)) {
            throw new \RuntimeException("Package {$packageName} is not installed");
        }

        // Step 2: Remove via Composer
        $removeResult = $this->removeComposerPackage($packageName);

        if (! $removeResult['success']) {
            throw new \RuntimeException(
                'Failed to remove Composer package: '.$removeResult['error']
            );
        }

        return [
            'success' => true,
            'output' => $removeResult['output'],
            'uninstalled_at' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Validate component before installation
     */
    public function validateComponent(array $component): array
    {
        $issues = [];

        // Check required fields
        $requiredFields = ['name', 'full_name', 'description', 'url'];
        foreach ($requiredFields as $field) {
            if (empty($component[$field])) {
                $issues[] = "Missing required field: {$field}";
            }
        }

        // Validate package name format
        if (! empty($component['full_name']) && ! preg_match('/^[a-z0-9\-]+\/[a-z0-9\-]+$/', $component['full_name'])) {
            $issues[] = "Invalid package name format: {$component['full_name']}";
        }

        // Check if package exists on Packagist (optional validation)
        if (! empty($component['full_name']) && ! $this->packageExistsOnPackagist($component['full_name'])) {
            $issues[] = "Package not found on Packagist: {$component['full_name']}";
        }

        return $issues;
    }

    /**
     * Check if package exists on Packagist
     */
    protected function packageExistsOnPackagist(string $packageName): bool
    {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->get("https://packagist.org/packages/{$packageName}.json");

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log("Error checking Packagist for {$packageName}: ".$e->getMessage());

            return true; // Assume exists if we can't check
        }
    }

    /**
     * Get installation status of a component
     */
    public function getInstallationStatus(array $component): array
    {
        $packageName = $component['full_name'];
        $isInstalled = $this->isPackageInstalled($packageName);

        $status = [
            'installed' => $isInstalled,
            'package_name' => $packageName,
        ];

        if ($isInstalled) {
            $status['version'] = $this->getInstalledPackageVersion($packageName);
            $status['metadata'] = $this->getPackageMetadata($packageName);
        }

        return $status;
    }
}

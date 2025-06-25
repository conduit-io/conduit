<?php

namespace App\Services;

/**
 * High-level component installation orchestration service
 */
class ComponentInstallationService
{
    public function __construct(
        private SecurePackageInstaller $installer,
        private ComponentManager $manager,
        private ServiceProviderDetector $detector
    ) {}

    /**
     * Install a component with full lifecycle management
     */
    public function installComponent(string $componentName, array $component): ComponentInstallationResult
    {
        try {
            // Step 1: Secure package installation
            $installResult = $this->installer->install($component);

            if (! $installResult->isSuccessful()) {
                return ComponentInstallationResult::failed(
                    'Composer installation failed: '.$installResult->getErrorOutput(),
                    $installResult
                );
            }

            // Step 2: Detect service providers
            $serviceProviders = $this->detector->detectServiceProviders($component['full_name']);

            // Step 3: Detect commands
            $commands = $this->detector->detectCommands($serviceProviders);

            // Step 4: Register component
            $componentInfo = [
                'package' => $component['full_name'],
                'description' => $component['description'],
                'commands' => $commands,
                'env_vars' => [],
                'service_providers' => $serviceProviders,
                'topics' => $component['topics'],
                'url' => $component['url'],
                'stars' => $component['stars'],
            ];

            $this->manager->register($componentName, $componentInfo);

            return ComponentInstallationResult::success($componentInfo, $commands);

        } catch (\Exception $e) {
            return ComponentInstallationResult::failed($e->getMessage());
        }
    }

    /**
     * Uninstall a component
     */
    public function uninstallComponent(string $componentName): bool
    {
        try {
            $this->manager->unregister($componentName);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

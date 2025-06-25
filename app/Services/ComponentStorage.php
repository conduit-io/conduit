<?php

namespace App\Services;

use App\Contracts\ComponentStorageInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Database storage service for Conduit components and settings
 *
 * Replaces runtime config file mutations with proper database storage.
 * Provides clean separation between static configuration and dynamic data.
 */
class ComponentStorage implements ComponentStorageInterface
{
    /**
     * Get all installed components
     */
    public function getInstalled(): array
    {
        return DB::table('components')
            ->where('status', 'active')
            ->get()
            ->mapWithKeys(function ($component) {
                return [$component->name => [
                    'package' => $component->package,
                    'description' => $component->description,
                    'commands' => json_decode($component->commands, true) ?? [],
                    'env_vars' => json_decode($component->env_vars, true) ?? [],
                    'service_providers' => json_decode($component->service_providers, true) ?? [],
                    'topics' => json_decode($component->topics, true) ?? [],
                    'url' => $component->url,
                    'stars' => $component->stars,
                    'version' => $component->version,
                    'status' => $component->status,
                    'installed_at' => $component->installed_at,
                ]];
            })
            ->toArray();
    }

    /**
     * Check if a component is installed
     */
    public function isInstalled(string $name): bool
    {
        return DB::table('components')
            ->where('name', $name)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Register a new component
     */
    public function registerComponent(string $name, array $componentInfo, ?string $version = null): void
    {
        $data = [
            'name' => $name,
            'package' => $componentInfo['package'],
            'description' => $componentInfo['description'] ?? null,
            'commands' => json_encode($componentInfo['commands'] ?? []),
            'env_vars' => json_encode($componentInfo['env_vars'] ?? []),
            'service_providers' => json_encode($componentInfo['service_providers'] ?? []),
            'topics' => json_encode($componentInfo['topics'] ?? []),
            'url' => $componentInfo['url'] ?? null,
            'stars' => $componentInfo['stars'] ?? 0,
            'version' => $version,
            'status' => 'active',
            'installed_at' => $componentInfo['installed_at'] ?? Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        DB::table('components')->updateOrInsert(
            ['name' => $name],
            $data
        );

        // Register service providers
        $this->registerServiceProviders($name, $componentInfo['service_providers'] ?? []);
    }

    /**
     * Unregister a component
     */
    public function unregisterComponent(string $name): bool
    {
        $component = DB::table('components')->where('name', $name)->first();

        if (! $component) {
            return false;
        }

        // Remove service providers
        DB::table('service_providers')->where('component_name', $name)->delete();

        // Remove component
        DB::table('components')->where('name', $name)->delete();

        return true;
    }

    /**
     * Get a component by name
     */
    public function getComponent(string $name): ?array
    {
        $component = DB::table('components')->where('name', $name)->first();

        if (! $component) {
            return null;
        }

        return [
            'package' => $component->package,
            'description' => $component->description,
            'commands' => json_decode($component->commands, true) ?? [],
            'env_vars' => json_decode($component->env_vars, true) ?? [],
            'service_providers' => json_decode($component->service_providers, true) ?? [],
            'topics' => json_decode($component->topics, true) ?? [],
            'url' => $component->url,
            'stars' => $component->stars,
            'version' => $component->version,
            'status' => $component->status,
            'installed_at' => $component->installed_at,
        ];
    }

    /**
     * Get all registered service providers
     */
    public function getServiceProviders(): array
    {
        return DB::table('service_providers')
            ->where('enabled', true)
            ->pluck('provider_class')
            ->toArray();
    }

    /**
     * Get service providers for a specific component
     */
    public function getComponentServiceProviders(string $componentName): array
    {
        return DB::table('service_providers')
            ->where('component_name', $componentName)
            ->where('enabled', true)
            ->pluck('provider_class')
            ->toArray();
    }

    /**
     * Register service providers for a component
     */
    public function registerServiceProviders(string $componentName, array $providers): void
    {
        foreach ($providers as $provider) {
            DB::table('service_providers')->updateOrInsert(
                [
                    'provider_class' => $provider,
                    'component_name' => $componentName,
                ],
                [
                    'enabled' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );
        }
    }

    /**
     * Unregister service providers for a component
     */
    public function unregisterServiceProviders(string $componentName): void
    {
        DB::table('service_providers')
            ->where('component_name', $componentName)
            ->delete();
    }

    /**
     * Get a global setting value
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = DB::table('settings')->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return json_decode($setting->value, true);
    }

    /**
     * Set a global setting value
     */
    public function setSetting(string $key, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => json_encode($value),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Get all global settings
     */
    public function getAllSettings(): array
    {
        return DB::table('settings')
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => json_decode($setting->value, true)];
            })
            ->toArray();
    }

    /**
     * Delete a setting
     */
    public function deleteSetting(string $key): bool
    {
        return DB::table('settings')->where('key', $key)->delete() > 0;
    }

    /**
     * Migrate existing config data to database
     */
    public function migrateFromConfig(): array
    {
        $migrated = [
            'components' => 0,
            'settings' => 0,
            'service_providers' => 0,
        ];

        // Migrate installed components
        $installedComponents = config('components.installed', []);
        foreach ($installedComponents as $name => $componentInfo) {
            $this->registerComponent($name, $componentInfo, $componentInfo['version'] ?? null);
            $migrated['components']++;
        }

        // Migrate global settings
        $globalSettings = config('components.settings', []);
        foreach ($globalSettings as $key => $value) {
            $this->setSetting($key, $value);
            $migrated['settings']++;
        }

        return $migrated;
    }

    /**
     * Check if database is initialized
     */
    public function isDatabaseInitialized(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('components');
        } catch (\Exception $e) {
            return false;
        }
    }
}

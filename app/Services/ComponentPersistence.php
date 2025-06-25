<?php

namespace App\Services;

use App\Actions\ManagesConduitJson;
use Carbon\Carbon;

class ComponentPersistence
{
    use ManagesConduitJson;

    /**
     * Get all installed components
     */
    public function getInstalled(): array
    {
        $data = $this->loadConduitJson();

        return $data['installed'] ?? [];
    }

    /**
     * Check if a component is installed
     */
    public function isInstalled(string $name): bool
    {
        $installed = $this->getInstalled();

        return isset($installed[$name]) && ($installed[$name]['status'] ?? '') === 'active';
    }

    /**
     * Get a specific installed component
     */
    public function getComponent(string $name): ?array
    {
        $installed = $this->getInstalled();

        return $installed[$name] ?? null;
    }

    /**
     * Save/register a component
     */
    public function register(string $name, array $componentInfo): void
    {
        $backupPath = $this->backupConduitJson();

        try {
            $data = $this->loadConduitJson();

            // Add component to installed list
            $data['installed'][$name] = array_merge($componentInfo, [
                'status' => 'active',
                'installed_at' => $componentInfo['installed_at'] ?? Carbon::now()->toISOString(),
            ]);

            // Validate before saving
            $errors = $this->validateConduitJson($data);
            if (! empty($errors)) {
                throw new \RuntimeException('Validation failed: '.implode(', ', $errors));
            }

            $this->saveConduitJson($data);
        } catch (\Exception $e) {
            $this->restoreConduitJson($backupPath);
            throw $e;
        }
    }

    /**
     * Unregister/remove a component
     */
    public function unregister(string $name): void
    {
        $backupPath = $this->backupConduitJson();

        try {
            $data = $this->loadConduitJson();

            if (isset($data['installed'][$name])) {
                unset($data['installed'][$name]);
                $this->saveConduitJson($data);
            }
        } catch (\Exception $e) {
            $this->restoreConduitJson($backupPath);
            throw $e;
        }
    }

    /**
     * Update component status
     */
    public function updateStatus(string $name, string $status): void
    {
        $backupPath = $this->backupConduitJson();

        try {
            $data = $this->loadConduitJson();

            if (isset($data['installed'][$name])) {
                $data['installed'][$name]['status'] = $status;
                $data['installed'][$name]['updated_at'] = Carbon::now()->toISOString();
                $this->saveConduitJson($data);
            }
        } catch (\Exception $e) {
            $this->restoreConduitJson($backupPath);
            throw $e;
        }
    }

    /**
     * Get discovery settings
     */
    public function getDiscoverySettings(): array
    {
        $data = $this->loadConduitJson();

        return $data['discovery'] ?? [];
    }

    /**
     * Update discovery settings
     */
    public function updateDiscoverySettings(array $settings): void
    {
        $backupPath = $this->backupConduitJson();

        try {
            $data = $this->loadConduitJson();
            $data['discovery'] = array_merge($data['discovery'] ?? [], $settings);
            $this->saveConduitJson($data);
        } catch (\Exception $e) {
            $this->restoreConduitJson($backupPath);
            throw $e;
        }
    }

    /**
     * Get global settings
     */
    public function getSettings(): array
    {
        $data = $this->loadConduitJson();

        return $data['settings'] ?? [];
    }

    /**
     * Get a specific setting with default
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->getSettings();

        return $settings[$key] ?? $default;
    }

    /**
     * Update global settings
     */
    public function updateSettings(array $settings): void
    {
        $backupPath = $this->backupConduitJson();

        try {
            $data = $this->loadConduitJson();
            $data['settings'] = array_merge($data['settings'] ?? [], $settings);
            $this->saveConduitJson($data);
        } catch (\Exception $e) {
            $this->restoreConduitJson($backupPath);
            throw $e;
        }
    }

    /**
     * Get local registry
     */
    public function getRegistry(): array
    {
        $data = $this->loadConduitJson();

        return $data['registry'] ?? [];
    }

    /**
     * Update local registry
     */
    public function updateRegistry(array $registry): void
    {
        $backupPath = $this->backupConduitJson();

        try {
            $data = $this->loadConduitJson();
            $data['registry'] = $registry;
            $this->saveConduitJson($data);
        } catch (\Exception $e) {
            $this->restoreConduitJson($backupPath);
            throw $e;
        }
    }

    /**
     * Export components to array for backup/migration
     */
    public function export(): array
    {
        return $this->loadConduitJson();
    }

    /**
     * Import components from array (for migration/restore)
     */
    public function import(array $data): void
    {
        $backupPath = $this->backupConduitJson();

        try {
            $errors = $this->validateConduitJson($data);
            if (! empty($errors)) {
                throw new \RuntimeException('Import validation failed: '.implode(', ', $errors));
            }

            $this->saveConduitJson($data);
        } catch (\Exception $e) {
            $this->restoreConduitJson($backupPath);
            throw $e;
        }
    }

    /**
     * Initialize conduit.json with default structure
     */
    public function initialize(): void
    {
        if (! file_exists($this->conduitJsonPath)) {
            $this->saveConduitJson($this->getDefaultConduitStructure());
        }
    }

    /**
     * Migrate from config/components.php to conduit.json
     */
    public function migrateFromConfigFile(): bool
    {
        try {
            $migratedData = $this->migrateFromConfig();
            $this->saveConduitJson($migratedData);

            return true;
        } catch (\Exception $e) {
            error_log('Migration from config failed: '.$e->getMessage());

            return false;
        }
    }
}

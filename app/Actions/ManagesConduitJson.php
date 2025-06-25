<?php

namespace App\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

trait ManagesConduitJson
{
    protected string $conduitJsonPath;

    /**
     * Initialize the conduit.json path
     */
    protected function initializeConduitJsonPath(): void
    {
        $this->conduitJsonPath = base_path('conduit.json');
    }

    /**
     * Load conduit.json data
     */
    protected function loadConduitJson(): array
    {
        $this->initializeConduitJsonPath();

        if (! File::exists($this->conduitJsonPath)) {
            return $this->getDefaultConduitStructure();
        }

        try {
            $content = File::get($this->conduitJsonPath);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON in conduit.json: '.json_last_error_msg());
            }

            // Ensure structure exists
            return array_merge($this->getDefaultConduitStructure(), $data);
        } catch (\Exception $e) {
            error_log('Error loading conduit.json: '.$e->getMessage());

            return $this->getDefaultConduitStructure();
        }
    }

    /**
     * Save data to conduit.json
     */
    protected function saveConduitJson(array $data): void
    {
        $this->initializeConduitJsonPath();

        try {
            $data['_meta'] = [
                'schema_version' => '1.0',
                'last_modified' => Carbon::now()->toISOString(),
                'generated_by' => 'Conduit CLI',
            ];

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Failed to encode JSON: '.json_last_error_msg());
            }

            File::put($this->conduitJsonPath, $json);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to save conduit.json: '.$e->getMessage());
        }
    }

    /**
     * Get default conduit.json structure
     */
    protected function getDefaultConduitStructure(): array
    {
        return [
            'installed' => [],
            'discovery' => [
                'github_topic' => 'conduit-component',
                'auto_discover' => false,
                'fallback_to_local' => true,
            ],
            'settings' => [
                'interactive_mode' => true,
                'auto_register_providers' => false,
            ],
            'registry' => [],
        ];
    }

    /**
     * Backup conduit.json before making changes
     */
    protected function backupConduitJson(): string
    {
        $this->initializeConduitJsonPath();

        if (! File::exists($this->conduitJsonPath)) {
            return '';
        }

        $backupPath = $this->conduitJsonPath.'.backup.'.time();
        File::copy($this->conduitJsonPath, $backupPath);

        return $backupPath;
    }

    /**
     * Restore conduit.json from backup
     */
    protected function restoreConduitJson(string $backupPath): void
    {
        if (File::exists($backupPath)) {
            File::copy($backupPath, $this->conduitJsonPath);
            File::delete($backupPath);
        }
    }

    /**
     * Validate conduit.json structure
     */
    protected function validateConduitJson(array $data): array
    {
        $errors = [];

        // Check required sections
        $requiredSections = ['installed', 'discovery', 'settings'];
        foreach ($requiredSections as $section) {
            if (! isset($data[$section])) {
                $errors[] = "Missing required section: {$section}";
            }
        }

        // Validate installed components structure
        if (isset($data['installed']) && is_array($data['installed'])) {
            foreach ($data['installed'] as $name => $component) {
                if (! is_array($component)) {
                    $errors[] = "Component '{$name}' must be an object";

                    continue;
                }

                $requiredFields = ['package', 'status', 'installed_at'];
                foreach ($requiredFields as $field) {
                    if (! isset($component[$field])) {
                        $errors[] = "Component '{$name}' missing required field: {$field}";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Migrate data from old config format to conduit.json
     */
    protected function migrateFromConfig(): array
    {
        $configData = config('components', []);

        if (empty($configData)) {
            return $this->getDefaultConduitStructure();
        }

        return [
            'installed' => $configData['installed'] ?? [],
            'discovery' => $configData['discovery'] ?? [
                'github_topic' => 'conduit-component',
                'auto_discover' => false,
                'fallback_to_local' => true,
            ],
            'settings' => array_merge([
                'interactive_mode' => true,
                'auto_register_providers' => false,
            ], $configData['settings'] ?? []),
            'registry' => $configData['registry'] ?? [],
        ];
    }
}

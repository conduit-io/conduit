<?php

namespace Tests\Unit;

use App\Services\ComponentStorage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComponentStorageTest extends TestCase
{
    private ComponentStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure in-memory SQLite for testing
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        // Create tables manually for testing
        $this->createTables();

        $this->storage = new ComponentStorage;
    }

    private function createTables(): void
    {
        DB::statement('CREATE TABLE components (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) UNIQUE NOT NULL,
            package VARCHAR(255) NOT NULL,
            description TEXT,
            commands TEXT,
            env_vars TEXT,
            service_providers TEXT,
            topics TEXT,
            url VARCHAR(255),
            stars INTEGER DEFAULT 0,
            version VARCHAR(255),
            status VARCHAR(255) DEFAULT "active",
            installed_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('CREATE TABLE settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key VARCHAR(255) UNIQUE NOT NULL,
            value TEXT NOT NULL,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');

        DB::statement('CREATE TABLE service_providers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider_class VARCHAR(255) NOT NULL,
            component_name VARCHAR(255) NOT NULL,
            enabled BOOLEAN DEFAULT 1,
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            FOREIGN KEY (component_name) REFERENCES components(name) ON DELETE CASCADE,
            UNIQUE(provider_class, component_name)
        )');
    }

    public function test_component_registration_and_retrieval()
    {
        $componentInfo = [
            'package' => 'test/component',
            'description' => 'Test component',
            'commands' => ['test:command'],
            'service_providers' => ['Test\\ServiceProvider'],
            'topics' => ['conduit-component'],
            'url' => 'https://github.com/test/component',
            'stars' => 42,
        ];

        // Register component
        $this->storage->registerComponent('test-component', $componentInfo, '1.0.0');

        // Test component is installed
        $this->assertTrue($this->storage->isInstalled('test-component'));

        // Test component retrieval
        $component = $this->storage->getComponent('test-component');
        $this->assertNotNull($component);
        $this->assertEquals('test/component', $component['package']);
        $this->assertEquals('Test component', $component['description']);
        $this->assertEquals(['test:command'], $component['commands']);

        // Test all installed components
        $installed = $this->storage->getInstalled();
        $this->assertArrayHasKey('test-component', $installed);
    }

    public function test_component_unregistration()
    {
        $componentInfo = [
            'package' => 'test/component',
            'service_providers' => ['Test\\ServiceProvider'],
        ];

        // Register then unregister
        $this->storage->registerComponent('test-component', $componentInfo);
        $this->assertTrue($this->storage->isInstalled('test-component'));

        $this->storage->unregisterComponent('test-component');
        $this->assertFalse($this->storage->isInstalled('test-component'));
        $this->assertNull($this->storage->getComponent('test-component'));
    }

    public function test_service_provider_management()
    {
        $componentInfo = [
            'package' => 'test/component',
            'service_providers' => ['Test\\ServiceProvider', 'Test\\AnotherProvider'],
        ];

        $this->storage->registerComponent('test-component', $componentInfo);

        // Test service provider retrieval
        $providers = $this->storage->getComponentServiceProviders('test-component');
        $this->assertContains('Test\\ServiceProvider', $providers);
        $this->assertContains('Test\\AnotherProvider', $providers);

        // Test all service providers
        $allProviders = $this->storage->getServiceProviders();
        $this->assertContains('Test\\ServiceProvider', $allProviders);
    }

    public function test_settings_management()
    {
        // Test setting and getting
        $this->storage->setSetting('test.setting', 'test-value');
        $this->assertEquals('test-value', $this->storage->getSetting('test.setting'));

        // Test default value
        $this->assertEquals('default', $this->storage->getSetting('nonexistent', 'default'));

        // Test complex data
        $complexData = ['array' => ['nested' => true], 'number' => 42];
        $this->storage->setSetting('complex', $complexData);
        $this->assertEquals($complexData, $this->storage->getSetting('complex'));

        // Test all settings
        $allSettings = $this->storage->getAllSettings();
        $this->assertArrayHasKey('test.setting', $allSettings);
        $this->assertArrayHasKey('complex', $allSettings);

        // Test deletion
        $this->assertTrue($this->storage->deleteSetting('test.setting'));
        $this->assertNull($this->storage->getSetting('test.setting'));
    }

    public function test_database_initialization_check()
    {
        $this->assertTrue($this->storage->isDatabaseInitialized());
    }

    public function test_config_migration()
    {
        // Mock config data
        config([
            'components.installed' => [
                'legacy-component' => [
                    'package' => 'legacy/component',
                    'description' => 'Legacy component',
                    'version' => '0.9.0',
                ],
            ],
            'components.settings' => [
                'interactive' => true,
                'timeout' => 300,
            ],
        ]);

        // Run migration
        $migrated = $this->storage->migrateFromConfig();

        // Verify migration results
        $this->assertEquals(1, $migrated['components']);
        $this->assertEquals(2, $migrated['settings']);

        // Verify data was migrated
        $this->assertTrue($this->storage->isInstalled('legacy-component'));
        $this->assertEquals(true, $this->storage->getSetting('interactive'));
        $this->assertEquals(300, $this->storage->getSetting('timeout'));
    }
}

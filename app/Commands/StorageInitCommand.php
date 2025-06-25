<?php

namespace App\Commands;

use App\Services\ComponentStorage;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;

class StorageInitCommand extends Command
{
    protected $signature = 'storage:init {--migrate : Migrate existing config data to database}';

    protected $description = 'Initialize Conduit database storage';

    public function __construct(
        private ComponentStorage $storage
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🗄️ Initializing Conduit database storage...');

        // Ensure database directory exists
        $this->ensureDatabaseDirectory();

        // Configure SQLite database
        $this->configureSqliteDatabase();

        // Run migrations
        $this->info('📦 Running database migrations...');

        try {
            Artisan::call('migrate', [
                '--path' => 'database/migrations',
                '--force' => true,
            ]);

            $this->info('✅ Database migrations completed successfully');
        } catch (\Exception $e) {
            $this->error('❌ Migration failed: '.$e->getMessage());

            return self::FAILURE;
        }

        // Migrate existing config data if requested
        if ($this->option('migrate')) {
            $this->migrateConfigData();
        }

        $this->newLine();
        $this->info('🎉 Conduit database storage initialized successfully!');
        $this->info('📍 Database location: '.$this->getDatabasePath());

        return self::SUCCESS;
    }

    private function ensureDatabaseDirectory(): void
    {
        $dbDir = dirname($this->getDatabasePath());

        if (! File::exists($dbDir)) {
            File::makeDirectory($dbDir, 0755, true);
            $this->info("📁 Created database directory: {$dbDir}");
        }
    }

    private function configureSqliteDatabase(): void
    {
        $dbPath = $this->getDatabasePath();

        // Set Laravel database configuration for SQLite
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => $dbPath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        // Reconnect with new configuration
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->info("🔧 Configured SQLite database: {$dbPath}");
    }

    private function getDatabasePath(): string
    {
        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
        $conduitDir = $homeDir.'/.conduit';

        return $conduitDir.'/conduit.sqlite';
    }

    private function migrateConfigData(): void
    {
        $this->info('🔄 Migrating existing config data...');

        try {
            $migrated = $this->storage->migrateFromConfig();

            if ($migrated['components'] > 0) {
                $this->info("✅ Migrated {$migrated['components']} components");
            }

            if ($migrated['settings'] > 0) {
                $this->info("✅ Migrated {$migrated['settings']} settings");
            }

            if ($migrated['components'] === 0 && $migrated['settings'] === 0) {
                $this->info('ℹ️ No existing config data found to migrate');
            }

        } catch (\Exception $e) {
            $this->warn('⚠️ Config migration failed: '.$e->getMessage());
            $this->info('💡 You can continue without migration - new installs will use database storage');
        }
    }

    public function schedule(Schedule $schedule): void
    {
        // No scheduling needed for initialization command
    }
}

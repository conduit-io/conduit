<?php

namespace App\Providers;

use App\Contracts\ComponentManagerInterface;
use App\Contracts\ComponentStorageInterface;
use App\Contracts\PackageInstallerInterface;
use App\Services\ComponentInstallationService;
use App\Services\ComponentManager;
use App\Services\ComponentStorage;
use App\Services\SecurePackageInstaller;
use App\Services\ServiceProviderDetector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind interfaces to implementations
        $this->app->singleton(ComponentStorageInterface::class, ComponentStorage::class);
        $this->app->singleton(ComponentManagerInterface::class, ComponentManager::class);
        $this->app->singleton(PackageInstallerInterface::class, SecurePackageInstaller::class);

        // Register concrete services
        $this->app->singleton(ComponentStorage::class);
        $this->app->singleton(ComponentManager::class);
        $this->app->singleton(SecurePackageInstaller::class);
        $this->app->singleton(ServiceProviderDetector::class);
        $this->app->singleton(ComponentInstallationService::class);
    }
}

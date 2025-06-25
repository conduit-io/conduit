<?php

namespace App\Contracts;

use App\Services\ProcessResult;

interface PackageInstallerInterface
{
    /**
     * Install a package with full security validation
     */
    public function install(array $component): ProcessResult;

    /**
     * Remove a package safely
     */
    public function remove(string $packageName): ProcessResult;
}
<?php

namespace App\Actions;

use Symfony\Component\Process\Process;

trait InstallsComposerPackage
{
    /**
     * Install a Composer package with proper error handling
     */
    protected function installComposerPackage(string $packageName, int $timeout = 300): array
    {
        $process = Process::fromShellCommandline(
            "composer require {$packageName} --no-interaction --no-progress --prefer-dist"
        );

        $process->setTimeout($timeout);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
        ];
    }

    /**
     * Remove a Composer package
     */
    protected function removeComposerPackage(string $packageName): array
    {
        $process = Process::fromShellCommandline(
            "composer remove {$packageName} --no-interaction --no-progress"
        );

        $process->setTimeout(300);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
        ];
    }

    /**
     * Check if a package is installed via Composer
     */
    protected function isPackageInstalled(string $packageName): bool
    {
        $composerLock = base_path('composer.lock');

        if (! file_exists($composerLock)) {
            return false;
        }

        $lockData = json_decode(file_get_contents($composerLock), true);

        foreach (['packages', 'packages-dev'] as $section) {
            if (! isset($lockData[$section])) {
                continue;
            }

            foreach ($lockData[$section] as $package) {
                if ($package['name'] === $packageName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get installed package version
     */
    protected function getInstalledPackageVersion(string $packageName): ?string
    {
        $composerLock = base_path('composer.lock');

        if (! file_exists($composerLock)) {
            return null;
        }

        $lockData = json_decode(file_get_contents($composerLock), true);

        foreach (['packages', 'packages-dev'] as $section) {
            if (! isset($lockData[$section])) {
                continue;
            }

            foreach ($lockData[$section] as $package) {
                if ($package['name'] === $packageName) {
                    return $package['version'] ?? null;
                }
            }
        }

        return null;
    }
}

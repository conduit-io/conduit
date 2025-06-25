<?php

namespace App\Services;

use App\Contracts\PackageInstallerInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * Secure package installation service with comprehensive validation
 */
class SecurePackageInstaller implements PackageInstallerInterface
{
    private Client $httpClient;

    private int $timeout;

    public function __construct()
    {
        $this->httpClient = new Client(['timeout' => 10]);
        $this->timeout = 300; // 5 minutes
    }

    /**
     * Install a package with full security validation
     */
    public function install(array $component): ProcessResult
    {
        $this->validatePackageName($component['full_name']);
        $this->verifyPackageEligibility($component);

        return $this->executeComposerInstall($component['full_name']);
    }

    /**
     * Remove a package safely
     */
    public function remove(string $packageName): ProcessResult
    {
        $this->validatePackageName($packageName);

        $result = Process::timeout($this->timeout)
            ->path(base_path())
            ->run([
                'composer',
                'remove',
                $packageName,
                '--no-interaction',
            ]);

        return new ProcessResult(
            $result->successful(),
            $result->output(),
            $result->errorOutput()
        );
    }

    /**
     * Validate package name format for security
     */
    private function validatePackageName(string $packageName): void
    {
        // Composer package naming conventions
        if (! preg_match('/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9]([_.-]?[a-z0-9]+)*$/', $packageName)) {
            throw new InvalidArgumentException(
                "Invalid package name format: {$packageName}. ".
                'Must follow vendor/package naming convention.'
            );
        }

        if (strlen($packageName) > 100) {
            throw new InvalidArgumentException("Package name too long: {$packageName}");
        }
    }

    /**
     * Verify package exists on Packagist and has required topic
     */
    private function verifyPackageEligibility(array $component): void
    {
        try {
            $response = $this->httpClient->get("https://packagist.org/packages/{$component['full_name']}.json");

            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException("Package '{$component['full_name']}' not found on Packagist");
            }

            $packageData = json_decode($response->getBody()->getContents(), true);

            // Verify package has required topic
            $keywords = $packageData['package']['keywords'] ?? [];
            $requiredTopic = config('components.discovery.github_topic', 'conduit-component');

            if (! in_array($requiredTopic, $keywords)) {
                throw new RuntimeException(
                    "Package '{$component['full_name']}' does not have required topic '{$requiredTopic}'. ".
                    'Only verified Conduit components can be installed.'
                );
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new RuntimeException("Package '{$component['full_name']}' not found on Packagist");
            }
            throw new RuntimeException('Failed to verify package: '.$e->getMessage());
        } catch (\Exception $e) {
            throw new RuntimeException('Package verification failed: '.$e->getMessage());
        }
    }

    /**
     * Execute secure composer install
     */
    private function executeComposerInstall(string $packageName): ProcessResult
    {
        $result = Process::timeout($this->timeout)
            ->path(base_path())
            ->run([
                'composer',
                'require',
                $packageName,
                '--no-interaction',
                '--no-progress',
                '--prefer-dist',
            ]);

        return new ProcessResult(
            $result->successful(),
            $result->output(),
            $result->errorOutput()
        );
    }
}

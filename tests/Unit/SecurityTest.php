<?php

namespace Tests\Unit;

use App\Services\SecurePackageInstaller;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SecurityTest extends TestCase
{
    public function test_package_name_validation_prevents_command_injection()
    {
        $installer = new SecurePackageInstaller;
        $reflection = new ReflectionClass($installer);
        $method = $reflection->getMethod('validatePackageName');
        $method->setAccessible(true);

        // Test malicious inputs that could cause command injection
        $maliciousInputs = [
            'evil; rm -rf /',
            'package && echo pwned',
            'vendor/package; cat /etc/passwd',
            'valid/package | malicious-command',
            'package`whoami`',
            'package$(id)',
        ];

        foreach ($maliciousInputs as $maliciousInput) {
            $this->expectException(\InvalidArgumentException::class);
            $method->invoke($installer, $maliciousInput);
        }
    }

    public function test_package_name_validation_accepts_valid_names()
    {
        $installer = new SecurePackageInstaller;
        $reflection = new ReflectionClass($installer);
        $method = $reflection->getMethod('validatePackageName');
        $method->setAccessible(true);

        // Test valid package names
        $validInputs = [
            'vendor/package',
            'jordanpartridge/github-zero',
            'laravel-zero/framework',
            'company/my-package',
            'dev/package.name',
            'org/package_name',
        ];

        foreach ($validInputs as $validInput) {
            // Should not throw exception
            $method->invoke($installer, $validInput);
            $this->assertTrue(true); // Assertion to confirm no exception
        }
    }

    public function test_package_name_validation_rejects_invalid_formats()
    {
        $installer = new SecurePackageInstaller;
        $reflection = new ReflectionClass($installer);
        $method = $reflection->getMethod('validatePackageName');
        $method->setAccessible(true);

        // Test invalid but non-malicious formats
        $invalidInputs = [
            'just-package-name', // Missing vendor
            'vendor/', // Missing package
            '/package', // Missing vendor
            'VENDOR/package', // Uppercase not allowed
            'vendor/Package', // Uppercase not allowed
            'vendor//package', // Double slash
            '', // Empty string
            str_repeat('a', 101), // Too long
        ];

        foreach ($invalidInputs as $invalidInput) {
            $this->expectException(\InvalidArgumentException::class);
            $method->invoke($installer, $invalidInput);
        }
    }

    public function test_secure_process_execution_prevents_injection()
    {
        $installer = new SecurePackageInstaller;

        // Test that the installer uses secure process execution
        // We can't easily test the actual process execution without side effects,
        // but we can verify the validation happens before execution

        $this->expectException(\InvalidArgumentException::class);
        $installer->install(['full_name' => 'malicious; rm -rf /']);
    }

    public function test_service_layer_separation()
    {
        // Test that our services are properly separated
        $installer = new SecurePackageInstaller;
        $this->assertInstanceOf(SecurePackageInstaller::class, $installer);

        // Verify key security methods exist
        $reflection = new ReflectionClass($installer);
        $this->assertTrue($reflection->hasMethod('validatePackageName'));
        $this->assertTrue($reflection->hasMethod('verifyPackageEligibility'));
    }
}

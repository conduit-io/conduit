<?php

namespace App\Commands;

use App\Services\ComponentInstallationService;
use App\Services\ComponentManager;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

/**
 * Command for managing Conduit components
 *
 * This command provides a unified interface for discovering, installing, and managing
 * components in the Conduit microkernel architecture. Components are external packages
 * that extend Conduit's functionality through Laravel Zero service providers.
 */
class ComponentsCommand extends Command
{
    protected $signature = 'components 
                            {action? : Action to perform (list, discover, install, uninstall)}
                            {component? : Component name for install/uninstall operations}
                            {--non-interactive : Run in non-interactive mode}';

    protected $description = 'Manage Conduit components';

    public function handle(ComponentManager $manager, ComponentInstallationService $installer): int
    {
        try {
            $action = $this->argument('action');

            // If no action provided and interactive mode is enabled, show interactive menu
            if (! $action && $this->shouldBeInteractive($manager)) {
                $choice = select(
                    label: 'What would you like to do?',
                    options: [
                        'list' => 'List installed components',
                        'discover' => 'Discover available components',
                        'install' => 'Install a component',
                        'uninstall' => 'Uninstall a component',
                    ]
                );
                $action = $choice;
            }

            // Default to list if no action specified in non-interactive mode
            if (! $action) {
                $action = 'list';
            }

            // Validate action
            if (! in_array($action, ['list', 'discover', 'install', 'uninstall'])) {
                $this->error("Invalid action: {$action}. Valid actions are: list, discover, install, uninstall");

                return Command::FAILURE;
            }

            return match ($action) {
                'list' => $this->listInstalled($manager),
                'discover' => $this->discoverComponents($manager),
                'install' => $this->installComponent($manager, $installer),
                'uninstall' => $this->uninstallComponent($manager, $installer),
            };
        } catch (\Exception $e) {
            $this->error('An error occurred: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function listInstalled(ComponentManager $manager): int
    {
        $installed = $manager->getInstalled();

        if (empty($installed)) {
            $this->info('No components are currently installed.');
            $this->showInteractiveStatus($manager);

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->line('<fg=green>Installed Components</>');
        $this->newLine();

        $rows = collect($installed)->map(function ($component, $name) {
            return [
                'Name' => $name,
                'Package' => $component['package'] ?? 'N/A',
                'Version' => $component['version'] ?? 'N/A',
                'Status' => $component['status'] ?? 'unknown',
                'Installed' => isset($component['installed_at'])
                    ? \Carbon\Carbon::parse($component['installed_at'])->diffForHumans()
                    : 'Unknown',
            ];
        })->values()->toArray();

        table(['Name', 'Package', 'Version', 'Status', 'Installed'], $rows);

        $this->showInteractiveStatus($manager);

        return Command::SUCCESS;
    }

    protected function discoverComponents(ComponentManager $manager): int
    {
        $this->info('Discovering available components...');

        $discovered = $manager->discoverComponents();

        if (empty($discovered)) {
            $this->warn('No components found.');

            return Command::SUCCESS;
        }

        // Filter out already installed components
        $installed = $manager->getInstalled();
        $availableComponents = collect($discovered)
            ->filter(function ($component) use ($installed) {
                return ! isset($installed[$component['name']]);
            })
            ->toArray();

        if (empty($availableComponents)) {
            $this->info('All discovered components are already installed.');
            $this->info('Run `conduit components list` to see installed components.');

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->line('<fg=green>Available Components (Not Installed)</>');
        $this->newLine();

        $rows = collect($availableComponents)->map(function ($repo) {
            return [
                'Name' => $repo['name'],
                'Description' => \Illuminate\Support\Str::limit($repo['description'] ?? 'No description', 50),
                'Stars' => $repo['stars'],
                'Updated' => \Carbon\Carbon::parse($repo['updated_at'])->diffForHumans(),
            ];
        })->toArray();

        table(['Name', 'Description', 'Stars', 'Updated'], $rows);

        return Command::SUCCESS;
    }

    protected function installComponent(ComponentManager $manager, ComponentInstallationService $installer): int
    {
        try {
            $componentName = $this->argument('component');

            // Discover available components
            $this->info('Discovering available components...');
            $discovered = $manager->discoverComponents();

            if (empty($discovered)) {
                $this->warn('No components found for installation.');

                return Command::SUCCESS;
            }

            // Filter out already installed components
            $installed = $manager->getInstalled();
            $availableComponents = collect($discovered)
                ->filter(function ($component) use ($installed) {
                    return ! isset($installed[$component['name']]);
                })
                ->keyBy('name')
                ->toArray();

            if (empty($availableComponents)) {
                $this->info('All discovered components are already installed.');

                return Command::SUCCESS;
            }

            // If no component name provided, show selection menu
            if (! $componentName && $this->shouldBeInteractive($manager)) {
                $choices = [];
                foreach ($availableComponents as $component) {
                    $description = \Illuminate\Support\Str::limit($component['description'], 60);
                    $choices[$component['name']] = "{$component['name']} - {$description}";
                }

                $componentName = select(
                    label: 'Select a component to install:',
                    options: $choices
                );
            }

            if (! $componentName) {
                $this->error('Component name is required for installation.');

                return Command::FAILURE;
            }

            // Check if component exists in discovered components
            if (! isset($availableComponents[$componentName])) {
                $this->error("Component '{$componentName}' not found or already installed.");
                $this->info('Available components:');
                foreach (array_keys($availableComponents) as $name) {
                    $this->line("  - {$name}");
                }

                return Command::FAILURE;
            }

            // Confirm installation
            if ($this->shouldBeInteractive($manager)) {
                $component = $availableComponents[$componentName];
                $confirmed = confirm(
                    label: "Install '{$componentName}' from {$component['full_name']}?",
                    default: true
                );

                if (! $confirmed) {
                    $this->info('Installation cancelled.');

                    return Command::SUCCESS;
                }
            }

            // Install using the service
            $component = $availableComponents[$componentName];
            $this->info("Installing component '{$componentName}'...");
            $this->line("Package: {$component['full_name']}");
            $this->line("Description: {$component['description']}");

            $result = $installer->installComponent($componentName, $component);

            if (! $result->isSuccessful()) {
                $this->error('Failed to install component:');
                $this->line($result->getMessage());

                // Provide helpful guidance based on common errors
                if ($result->getProcessResult()) {
                    $errorOutput = $result->getProcessResult()->getErrorOutput();
                    if (str_contains($errorOutput, 'could not be found')) {
                        $this->warn('💡 The package may not exist or be misspelled.');
                    } elseif (str_contains($errorOutput, 'version constraints')) {
                        $this->warn('💡 There may be version compatibility issues.');
                    } elseif (str_contains($errorOutput, 'timeout')) {
                        $this->warn('💡 Network timeout - try again with a better connection.');
                    }
                }

                return Command::FAILURE;
            }

            $this->info("✅ Successfully installed and registered component '{$componentName}'.");

            if (! empty($result->getCommands())) {
                $this->info('📋 New commands available:');
                foreach ($result->getCommands() as $command) {
                    $this->line("  - conduit {$command}");
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Installation failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function uninstallComponent(ComponentManager $manager, ComponentInstallationService $installer): int
    {
        try {
            $componentName = $this->argument('component');

            if (! $componentName && $this->shouldBeInteractive($manager)) {
                $installed = $manager->getInstalled();
                if (empty($installed)) {
                    $this->info('No components are currently installed.');

                    return Command::SUCCESS;
                }

                $componentName = select(
                    label: 'Select a component to uninstall',
                    options: array_combine(array_keys($installed), array_keys($installed))
                );
            }

            if (! $componentName) {
                $this->error('Component name is required for uninstallation.');

                return Command::FAILURE;
            }

            // Check if component is installed
            if (! $manager->isInstalled($componentName)) {
                $this->error("Component '{$componentName}' is not installed.");

                return Command::FAILURE;
            }

            // Confirm uninstallation
            if ($this->shouldBeInteractive($manager)) {
                $confirmed = confirm(
                    label: "Are you sure you want to uninstall '{$componentName}'?",
                    default: false
                );

                if (! $confirmed) {
                    $this->info('Uninstallation cancelled.');

                    return Command::SUCCESS;
                }
            }

            // Uninstall the component
            if ($installer->uninstallComponent($componentName)) {
                $this->info("Successfully uninstalled component '{$componentName}'.");
            } else {
                $this->error("Failed to uninstall component '{$componentName}'.");

                return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Uninstallation failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Determine if commands should run in interactive mode
     */
    private function shouldBeInteractive(ComponentManager $manager): bool
    {
        // --non-interactive flag always overrides global setting
        if ($this->option('non-interactive')) {
            return false;
        }

        // Check global interactive mode setting (defaults to true)
        return $manager->getGlobalSetting('interactive_mode', true);
    }

    /**
     * Show contextual interactive mode guidance
     */
    private function showInteractiveStatus(ComponentManager $manager): void
    {
        $interactiveMode = $manager->getGlobalSetting('interactive_mode', true);
        $installed = $manager->getInstalled();

        $this->newLine();

        if (empty($installed)) {
            // No components installed - show discovery guidance
            if ($interactiveMode) {
                $this->line('<fg=cyan>💡 No components installed yet!</>');
                $this->line('   Run <fg=white>conduit components</> for interactive setup');
                $this->line('   Or <fg=white>conduit components discover</> to browse available components');
            } else {
                $this->line('<fg=cyan>💡 No components installed yet!</>');
                $this->line('   Run <fg=white>conduit components discover</> to browse available');
                $this->line('   Or <fg=white>conduit components install <name></> to install directly');
            }
        } else {
            // Components installed - show management guidance
            if ($interactiveMode) {
                $this->line('<fg=green>✨ Interactive mode:</> <fg=white>conduit components</> shows menu');
            } else {
                $this->line('<fg=yellow>🤖 Non-interactive mode:</> specify actions like <fg=white>conduit components discover</>');
            }
        }

        $this->line('<fg=gray>   Toggle: conduit interactive enable|disable</>');
    }
}

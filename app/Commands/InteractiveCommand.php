<?php

namespace App\Commands;

use App\Services\ComponentManager;
use LaravelZero\Framework\Commands\Command;

/**
 * Command for managing global interactive mode setting
 *
 * Controls whether interactive prompts are enabled by default for all Conduit commands.
 * This setting can be overridden per-command with the --non-interactive flag.
 */
class InteractiveCommand extends Command
{
    protected $signature = 'interactive 
                            {action : Action to perform (enable, disable, status)}';

    protected $description = 'Manage global interactive mode setting';

    public function handle(ComponentManager $manager): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['enable', 'disable', 'status'])) {
            $this->error("Invalid action: {$action}. Valid actions are: enable, disable, status");

            return Command::FAILURE;
        }

        return match ($action) {
            'enable' => $this->enableInteractive($manager),
            'disable' => $this->disableInteractive($manager),
            'status' => $this->showStatus($manager),
        };
    }

    protected function enableInteractive(ComponentManager $manager): int
    {
        $manager->updateGlobalSetting('interactive_mode', true);

        $this->info('✅ Interactive mode enabled globally');
        $this->line('   All commands will now prompt for user input by default');
        $this->line('   Use --non-interactive flag to override per command');

        return Command::SUCCESS;
    }

    protected function disableInteractive(ComponentManager $manager): int
    {
        $manager->updateGlobalSetting('interactive_mode', false);

        $this->info('🤖 Interactive mode disabled globally');
        $this->line('   All commands will now run in non-interactive mode by default');
        $this->line('   Perfect for CI/automation environments');

        return Command::SUCCESS;
    }

    protected function showStatus(ComponentManager $manager): int
    {
        $interactiveMode = $manager->getGlobalSetting('interactive_mode', true);

        $this->newLine();
        $this->line('<fg=cyan>Conduit Global Settings</>');
        $this->newLine();

        $status = $interactiveMode ? '<fg=green>ENABLED</>' : '<fg=yellow>DISABLED</>';
        $this->line("Interactive Mode: {$status}");

        if ($interactiveMode) {
            $this->line('  • Commands will prompt for user input by default');
            $this->line('  • Use --non-interactive to override individual commands');
        } else {
            $this->line('  • Commands will run silently by default');
            $this->line('  • Ideal for CI/automation environments');
        }

        $this->newLine();
        $this->line('<fg=gray>Toggle with: conduit interactive enable|disable</>');

        return Command::SUCCESS;
    }
}

<?php

namespace App\Commands;

use App\Services\ComponentManager;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;

/**
 * Enhanced summary command showing interactive mode status and contextual guidance
 *
 * Replaces the default Laravel Zero summary to provide better user experience
 * with prominent interactive mode status and actionable next steps.
 */
class SummaryCommand extends Command
{
    protected $signature = 'list {namespace? : The namespace name}
                            {--raw : To output raw command list}
                            {--format=txt : The output format (txt, xml, json, or md)}
                            {--short : To skip describing commands\' arguments}';

    protected $description = 'List commands with enhanced status information';

    public function handle(ComponentManager $manager): int
    {
        // Show standard command list first
        $this->showCommandList();

        // Add enhanced status section
        $this->showEnhancedStatus($manager);

        return Command::SUCCESS;
    }

    protected function showCommandList(): void
    {
        $helper = new DescriptorHelper;
        $helper->describe(
            $this->output,
            $this->getApplication(),
            [
                'format' => $this->option('format'),
                'raw_text' => $this->option('raw'),
                'namespace' => $this->argument('namespace'),
                'short' => $this->option('short'),
            ]
        );
    }

    protected function showEnhancedStatus(ComponentManager $manager): void
    {
        $interactiveMode = $manager->getGlobalSetting('interactive_mode', true);
        $installed = $manager->getInstalled();

        $this->newLine();
        $this->line('─────────────────────────────────────────────────────');

        // Interactive Mode Status (prominent)
        if ($interactiveMode) {
            $this->line('🎛️  <fg=green;options=bold>Interactive Mode: ENABLED</> <fg=gray>(conduit interactive disable to change)</>');
        } else {
            $this->line('🤖 <fg=yellow;options=bold>Interactive Mode: DISABLED</> <fg=gray>(conduit interactive enable to change)</>');
        }

        $this->newLine();

        // Component Status
        if (empty($installed)) {
            $this->line('📦 <fg=cyan;options=bold>Components:</> None installed');

            if ($interactiveMode) {
                $this->line('   💡 <fg=white>Quick start:</> conduit components <fg=gray>(interactive setup)</>');
                $this->line('   🔍 <fg=white>Browse:</> conduit components discover');
            } else {
                $this->line('   🔍 <fg=white>Browse:</> conduit components discover');
                $this->line('   📥 <fg=white>Install:</> conduit components install <name>');
            }
        } else {
            $componentCount = count($installed);
            $componentNames = implode(', ', array_keys($installed));

            $this->line("📦 <fg=green;options=bold>Components:</> {$componentCount} installed <fg=gray>({$componentNames})</>");

            if ($interactiveMode) {
                $this->line('   🎛️  <fg=white>Manage:</> conduit components <fg=gray>(shows interactive menu)</>');
            } else {
                $this->line('   📋 <fg=white>List:</> conduit components list');
                $this->line('   🔍 <fg=white>Discover:</> conduit components discover');
            }
        }

        // Quick Tips
        $this->newLine();
        $this->line('<fg=gray>💡 Tips:</> Run any command with <fg=white>--help</> for detailed usage');

        if ($interactiveMode) {
            $this->line('<fg=gray>   •</> Most commands will prompt for missing information');
        } else {
            $this->line('<fg=gray>   •</> Specify all required arguments for automated execution');
        }
    }
}

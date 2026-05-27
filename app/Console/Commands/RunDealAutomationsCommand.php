<?php

namespace App\Console\Commands;

use App\Services\DealAutomationService;
use Illuminate\Console\Command;

class RunDealAutomationsCommand extends Command
{
    protected $signature = 'automations:run';

    protected $description = 'Run active deal automation rules.';

    public function handle(DealAutomationService $automations): int
    {
        $summary = $automations->runAllActiveRules();

        $this->info(sprintf(
            'Automations processed: %d rules, %d success, %d skipped, %d failed.',
            $summary['rules'],
            $summary['success'],
            $summary['skipped'],
            $summary['failed'],
        ));

        return self::SUCCESS;
    }
}

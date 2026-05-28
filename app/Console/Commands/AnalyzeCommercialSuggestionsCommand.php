<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\AI\CommercialAgentService;
use Illuminate\Console\Command;

class AnalyzeCommercialSuggestionsCommand extends Command
{
    protected $signature = 'ai:analyze-commercial';

    protected $description = 'Analyze CRM data and create commercial AI suggestions.';

    public function handle(CommercialAgentService $agent): int
    {
        $totals = [
            'tenants' => 0,
            'deals' => 0,
            'created' => 0,
            'duplicates' => 0,
        ];

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) use ($agent, &$totals) {
            $result = $agent->analyzeTenant($tenant);

            $totals['tenants'] += $result['tenants'] ?? 1;
            $totals['deals'] += $result['deals'] ?? 0;
            $totals['created'] += $result['created'] ?? 0;
            $totals['duplicates'] += $result['duplicates'] ?? 0;
        });

        $this->info(sprintf(
            'Analyzed %d tenant(s), %d deal(s). Created %d suggestion(s), skipped %d duplicate(s).',
            $totals['tenants'],
            $totals['deals'],
            $totals['created'],
            $totals['duplicates'],
        ));

        return self::SUCCESS;
    }
}

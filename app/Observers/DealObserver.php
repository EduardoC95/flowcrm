<?php

namespace App\Observers;

use App\Models\Deal;
use App\Services\AI\CommercialAgentService;

class DealObserver
{
    public function created(Deal $deal): void
    {
        app(CommercialAgentService::class)->analyzeNewDeal($deal);
    }
}

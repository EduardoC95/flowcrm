<?php

namespace App\Observers;

use App\Models\Person;
use App\Services\AI\CommercialAgentService;

class PersonObserver
{
    public function created(Person $person): void
    {
        app(CommercialAgentService::class)->analyzeNewPerson($person);
    }
}

<?php

namespace App\Observers;

use App\Models\DealNote;
use App\Services\AI\CommercialAgentService;

class DealNoteObserver
{
    public function created(DealNote $note): void
    {
        $note->loadMissing('deal');

        if ($note->deal) {
            app(CommercialAgentService::class)->analyzeDeal($note->deal, 'realtime_note_created');
        }
    }
}

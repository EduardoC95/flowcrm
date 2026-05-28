<?php

namespace App\Services\AI;

use App\Services\OpenAI\OpenAIService;
use Illuminate\Support\Str;

class CRMQueryIntentService
{
    public const ALLOWED_INTENTS = [
        'deal_volume_by_stage',
        'deal_count_by_stage',
        'find_person_phone',
        'find_person_email',
        'find_entity_contacts',
        'deals_closing_soon',
        'inactive_deals',
        'top_products_by_value',
        'top_products_by_quantity',
        'open_deals_by_owner',
        'create_deal_note',
        'create_calendar_activity',
        'help',
    ];

    public function __construct(
        private readonly OpenAIService $openAI,
        private readonly LocalCRMIntentParser $localParser,
    ) {}

    /**
     * @return array{intent:string,confidence:float,parameters:array<string,mixed>,requires_action_confirmation:bool}
     */
    public function detect(string $question): array
    {
        $question = Str::limit(trim($question), 2000, '');
        $intent = $this->openAI->classifyIntent($question, self::ALLOWED_INTENTS);

        if (! $this->isAllowedIntent($intent)) {
            $intent = $this->localParser->parse($question);
        }

        if (! $this->isAllowedIntent($intent)) {
            return [
                'intent' => 'help',
                'confidence' => 0.5,
                'parameters' => [],
                'requires_action_confirmation' => false,
            ];
        }

        return [
            'intent' => $intent['intent'],
            'confidence' => (float) ($intent['confidence'] ?? 0.5),
            'parameters' => is_array($intent['parameters'] ?? null) ? $intent['parameters'] : [],
            'requires_action_confirmation' => (bool) ($intent['requires_action_confirmation'] ?? false),
        ];
    }

    /**
     * @param  mixed  $intent
     */
    private function isAllowedIntent($intent): bool
    {
        return is_array($intent)
            && isset($intent['intent'])
            && in_array($intent['intent'], self::ALLOWED_INTENTS, true);
    }
}

<?php

namespace App\Services\OpenAI;

use Generator;
use Illuminate\Support\Facades\Http;

class OpenAIService
{
    public function enabled(): bool
    {
        return (bool) config('openai.enabled') && filled(config('openai.api_key'));
    }

    /**
     * @param  array<int,string>  $allowedIntents
     * @return array<string,mixed>
     */
    public function classifyIntent(string $question, array $allowedIntents): array
    {
        if (! $this->enabled() || app()->environment('testing')) {
            return [];
        }

        try {
            $response = Http::withToken((string) config('openai.api_key'))
                ->acceptJson()
                ->timeout(10)
                ->post(rtrim((string) config('openai.base_url'), '/').'/responses', [
                    'model' => (string) config('openai.model'),
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => 'Classify the CRM question into exactly one allowed intent. Return only compact JSON with keys: intent, confidence, parameters, requires_action_confirmation. Never return SQL. Allowed intents: '.implode(', ', $allowedIntents).'.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $question,
                        ],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'crm_query_intent',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['intent', 'confidence', 'parameters', 'requires_action_confirmation'],
                                'properties' => [
                                    'intent' => ['type' => 'string', 'enum' => $allowedIntents],
                                    'confidence' => ['type' => 'number'],
                                    'parameters' => [
                                        'type' => 'object',
                                        'additionalProperties' => true,
                                    ],
                                    'requires_action_confirmation' => ['type' => 'boolean'],
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return [];
            }

            $content = $response->json('output_text')
                ?? $response->json('output.0.content.0.text')
                ?? $response->json('output.0.content.0.content');
            $decoded = json_decode((string) $content, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string,mixed>  $queryResult
     */
    public function generateNaturalAnswer(array $queryResult): string
    {
        return (string) ($queryResult['answer_text'] ?? 'Nao encontrei informacao suficiente para responder.');
    }

    /**
     * @param  array<string,mixed>  $queryResult
     * @return Generator<int,string>
     */
    public function streamNaturalAnswer(array $queryResult): Generator
    {
        $answer = $this->generateNaturalAnswer($queryResult);
        $chunks = preg_split('/(\s+)/', $answer, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$answer];
        $buffer = '';

        foreach ($chunks as $chunk) {
            $buffer .= $chunk;

            if (mb_strlen($buffer) >= 24) {
                yield $buffer;
                $buffer = '';
            }
        }

        if ($buffer !== '') {
            yield $buffer;
        }
    }
}

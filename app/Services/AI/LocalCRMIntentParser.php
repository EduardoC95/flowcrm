<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

class LocalCRMIntentParser
{
    /**
     * @return array{intent:string,confidence:float,parameters:array<string,mixed>,requires_action_confirmation:bool}
     */
    public function parse(string $question): array
    {
        $normalized = Str::of($question)->lower()->ascii()->squish()->toString();

        if (str_contains($normalized, 'criar') && str_contains($normalized, 'nota')) {
            return $this->intent('create_deal_note', 0.74, [
                'raw_request' => $question,
            ], true);
        }

        if (str_contains($normalized, 'criar') && (str_contains($normalized, 'tarefa') || str_contains($normalized, 'atividade') || str_contains($normalized, 'actividade'))) {
            return $this->intent('create_calendar_activity', 0.72, [
                'raw_request' => $question,
            ], true);
        }

        if ((str_contains($normalized, 'telemovel') || str_contains($normalized, 'telefone') || str_contains($normalized, 'contacto')) && (str_contains($normalized, ' do ') || str_contains($normalized, ' da '))) {
            return $this->intent('find_person_phone', 0.86, [
                'person_name' => $this->extractNameAfter($normalized, ['telemovel do', 'telefone do', 'contacto do', 'telemovel da', 'telefone da', 'contacto da']),
            ]);
        }

        if (str_contains($normalized, 'email') && (str_contains($normalized, ' do ') || str_contains($normalized, ' da '))) {
            return $this->intent('find_person_email', 0.84, [
                'person_name' => $this->extractNameAfter($normalized, ['email do', 'email da']),
            ]);
        }

        if ((str_contains($normalized, 'contactos') || str_contains($normalized, 'contatos')) && (str_contains($normalized, 'empresa') || str_contains($normalized, 'entidade'))) {
            return $this->intent('find_entity_contacts', 0.74, [
                'entity_name' => $this->extractNameAfter($normalized, ['empresa', 'entidade', 'de']),
            ]);
        }

        if (str_contains($normalized, 'produto') && (str_contains($normalized, 'quantidade') || str_contains($normalized, 'unidade'))) {
            return $this->intent('top_products_by_quantity', 0.79, [
                'limit' => 5,
            ]);
        }

        if (str_contains($normalized, 'produto')) {
            return $this->intent('top_products_by_value', 0.8, [
                'limit' => 5,
            ]);
        }

        if ((str_contains($normalized, 'volume') || str_contains($normalized, 'valor')) && (str_contains($normalized, 'estado') || str_contains($normalized, 'etapa') || str_contains($normalized, 'pipeline'))) {
            return $this->intent('deal_volume_by_stage', 0.88, [
                'stage' => $this->extractStage($normalized),
            ]);
        }

        if ((str_contains($normalized, 'quantos') || str_contains($normalized, 'numero') || str_contains($normalized, 'número')) && str_contains($normalized, 'negocio')) {
            return $this->intent('deal_count_by_stage', 0.82, [
                'stage' => $this->extractStage($normalized),
            ]);
        }

        if (str_contains($normalized, 'fech') || str_contains($normalized, 'data prevista') || str_contains($normalized, 'proximos')) {
            return $this->intent('deals_closing_soon', 0.78, [
                'days' => $this->extractDays($normalized, 14),
            ]);
        }

        if (str_contains($normalized, 'parado') || str_contains($normalized, 'sem atividade') || str_contains($normalized, 'inativo')) {
            return $this->intent('inactive_deals', 0.82, [
                'days' => $this->extractDays($normalized, 7),
            ]);
        }

        if (str_contains($normalized, 'responsavel') || str_contains($normalized, 'owner')) {
            return $this->intent('open_deals_by_owner', 0.7, []);
        }

        return $this->intent('help', 0.55, []);
    }

    /**
     * @param  array<string>  $phrases
     */
    private function extractNameAfter(string $question, array $phrases): string
    {
        foreach ($phrases as $phrase) {
            $position = mb_strpos($question, $phrase);

            if ($position !== false) {
                return trim(Str::of(mb_substr($question, $position + mb_strlen($phrase)))
                    ->replace(['?', '.', '!'], '')
                    ->squish()
                    ->title()
                    ->toString());
            }
        }

        return '';
    }

    private function extractStage(string $question): ?string
    {
        $stages = [
            'negociacao' => 'Negociação',
            'em negociacao' => 'Negociação',
            'proposta' => 'Proposta',
            'follow up' => 'Follow Up',
            'follow-up' => 'Follow Up',
            'lead' => 'Lead',
            'ganho' => 'Ganho',
            'perdido' => 'Perdido',
        ];

        foreach ($stages as $needle => $stage) {
            if (str_contains($question, $needle)) {
                return $stage;
            }
        }

        return null;
    }

    private function extractDays(string $question, int $default): int
    {
        preg_match('/(\d+)\s+dias?/', $question, $matches);

        return isset($matches[1]) ? max(1, (int) $matches[1]) : $default;
    }

    /**
     * @param  array<string,mixed>  $parameters
     * @return array{intent:string,confidence:float,parameters:array<string,mixed>,requires_action_confirmation:bool}
     */
    private function intent(string $intent, float $confidence, array $parameters = [], bool $requiresConfirmation = false): array
    {
        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'parameters' => $parameters,
            'requires_action_confirmation' => $requiresConfirmation,
        ];
    }
}

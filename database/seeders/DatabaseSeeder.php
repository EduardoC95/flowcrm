<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealProposal;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'FlowCRM Demo',
            'slug' => 'flowcrm-demo',
            'settings' => [
                'locale' => 'pt',
            ],
        ]);

        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@flowcrm.test',
            'password' => Hash::make('password'),
            'current_tenant_id' => $tenant->id,
        ]);

        $user->tenants()->attach($tenant->id, [
            'role' => Tenant::ROLE_OWNER,
        ]);

        DealStage::ensureDefaultStages($tenant);

        $entities = collect([
            ['name' => 'Acme Portugal', 'vat' => 'PT509999001', 'status' => Entity::STATUS_CLIENT, 'email' => 'hello@acme.test'],
            ['name' => 'Northwind Labs', 'vat' => 'PT509999002', 'status' => Entity::STATUS_ACTIVE, 'email' => 'sales@northwind.test'],
            ['name' => 'Blue Peak Retail', 'vat' => 'PT509999003', 'status' => Entity::STATUS_PROSPECT, 'email' => 'contact@bluepeak.test'],
            ['name' => 'Lisbon Solar Group', 'vat' => 'PT509999004', 'status' => Entity::STATUS_LEAD, 'email' => 'hello@lisbonsolar.test'],
            ['name' => 'Old Harbor Services', 'vat' => 'PT509999005', 'status' => Entity::STATUS_INACTIVE, 'email' => 'office@oldharbor.test'],
        ])->map(fn (array $attributes) => Entity::factory()->create([
            ...$attributes,
            'tenant_id' => $tenant->id,
        ]));

        $people = collect([
            ['entity' => 0, 'name' => 'Maria Silva', 'email' => 'maria@acme.test', 'phone' => '+351 910 100 001', 'position' => 'Commercial Director', 'status' => Person::STATUS_CLIENT],
            ['entity' => 0, 'name' => 'Joao Pereira', 'email' => 'joao@acme.test', 'phone' => '+351 910 100 002', 'position' => 'Operations Manager', 'status' => Person::STATUS_ACTIVE],
            ['entity' => 1, 'name' => 'Ana Costa', 'email' => 'ana@northwind.test', 'phone' => '+351 910 100 003', 'position' => 'Head of Sales', 'status' => Person::STATUS_PROSPECT],
            ['entity' => 2, 'name' => 'Rui Martins', 'email' => 'rui@bluepeak.test', 'phone' => '+351 910 100 004', 'position' => 'Managing Partner', 'status' => Person::STATUS_LEAD],
            ['entity' => null, 'name' => 'Sofia Almeida', 'email' => 'sofia.independent@test', 'phone' => '+351 910 100 005', 'position' => 'Consultant', 'status' => Person::STATUS_ACTIVE],
            ['entity' => null, 'name' => 'Miguel Santos', 'email' => 'miguel.freelance@test', 'phone' => '+351 910 100 006', 'position' => 'Advisor', 'status' => Person::STATUS_INACTIVE],
        ])->map(function (array $attributes) use ($entities, $tenant) {
            $entity = is_int($attributes['entity']) ? $entities->get($attributes['entity']) : null;

            return Person::factory()->create([
                'tenant_id' => $tenant->id,
                'entity_id' => $entity?->id,
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'],
                'position' => $attributes['position'],
                'job_title' => $attributes['position'],
                'status' => $attributes['status'],
            ]);
        });

        $stages = $tenant->dealStages()->orderBy('position')->get()->keyBy('slug');

        $deals = collect([
            ['stage' => DealStage::SLUG_LEAD, 'entity' => 3, 'person' => 3, 'title' => 'Qualificação para expansão solar', 'value' => 8500, 'probability' => 15, 'days' => 12, 'priority' => Deal::PRIORITY_MEDIUM],
            ['stage' => DealStage::SLUG_PROPOSAL, 'entity' => 0, 'person' => 0, 'title' => 'CRM rollout', 'value' => 12500, 'probability' => 45, 'days' => 20, 'priority' => Deal::PRIORITY_HIGH],
            ['stage' => DealStage::SLUG_NEGOTIATION, 'entity' => 1, 'person' => 2, 'title' => 'Automação comercial anual', 'value' => 32000, 'probability' => 65, 'days' => 30, 'priority' => Deal::PRIORITY_HIGH],
            ['stage' => DealStage::SLUG_FOLLOW_UP, 'entity' => 2, 'person' => 3, 'title' => 'Acompanhamento pós-proposta', 'value' => 7800, 'probability' => 55, 'days' => 7, 'priority' => Deal::PRIORITY_URGENT],
            ['stage' => DealStage::SLUG_WON, 'entity' => 0, 'person' => 1, 'title' => 'Serviços de onboarding', 'value' => 5400, 'probability' => 100, 'days' => -5, 'priority' => Deal::PRIORITY_LOW],
            ['stage' => DealStage::SLUG_LOST, 'entity' => 4, 'person' => null, 'title' => 'Projeto suspenso', 'value' => 4200, 'probability' => 0, 'days' => -10, 'priority' => Deal::PRIORITY_LOW],
        ])->map(function (array $attributes) use ($entities, $people, $stages, $tenant, $user) {
            $entity = is_int($attributes['entity']) ? $entities->get($attributes['entity']) : null;
            $person = is_int($attributes['person']) ? $people->get($attributes['person']) : null;
            $stage = $stages->get($attributes['stage']);

            return Deal::factory()->create([
                'tenant_id' => $tenant->id,
                'entity_id' => $entity?->id ?? $person?->entity_id,
                'person_id' => $person?->id,
                'owner_id' => $user->id,
                'deal_stage_id' => $stage?->id,
                'title' => $attributes['title'],
                'stage' => $stage?->slug ?? DealStage::SLUG_LEAD,
                'value' => $attributes['value'],
                'probability' => $attributes['probability'],
                'expected_close_date' => now()->addDays($attributes['days']),
                'priority' => $attributes['priority'],
                'description' => 'Negócio demo para validar pipeline, filtros e histórico comercial.',
            ]);
        });

        collect([
            ['type' => CalendarEvent::TYPE_MEETING, 'status' => CalendarEvent::STATUS_PENDING, 'title' => 'Discovery call', 'target' => 'deal', 'index' => 1, 'days' => 1, 'priority' => CalendarEvent::PRIORITY_HIGH, 'location' => 'Remote'],
            ['type' => CalendarEvent::TYPE_CALL, 'status' => CalendarEvent::STATUS_PENDING, 'title' => 'Chamada de qualificação', 'target' => 'person', 'index' => 3, 'days' => 2, 'priority' => CalendarEvent::PRIORITY_MEDIUM, 'location' => 'Telefone'],
            ['type' => CalendarEvent::TYPE_TASK, 'status' => CalendarEvent::STATUS_PENDING, 'title' => 'Preparar proposta comercial', 'target' => 'entity', 'index' => 1, 'days' => 3, 'priority' => CalendarEvent::PRIORITY_URGENT, 'location' => null],
            ['type' => CalendarEvent::TYPE_NOTE, 'status' => CalendarEvent::STATUS_COMPLETED, 'title' => 'Nota de reunião com operações', 'target' => 'person', 'index' => 1, 'days' => -1, 'priority' => CalendarEvent::PRIORITY_LOW, 'location' => 'Lisboa'],
            ['type' => CalendarEvent::TYPE_REMINDER, 'status' => CalendarEvent::STATUS_PENDING, 'title' => 'Follow-up pós-proposta', 'target' => 'deal', 'index' => 3, 'days' => 5, 'priority' => CalendarEvent::PRIORITY_HIGH, 'location' => null],
            ['type' => CalendarEvent::TYPE_TASK, 'status' => CalendarEvent::STATUS_CANCELLED, 'title' => 'Tarefa cancelada de validação', 'target' => 'entity', 'index' => 4, 'days' => -2, 'priority' => CalendarEvent::PRIORITY_LOW, 'location' => null],
        ])->each(function (array $attributes) use ($deals, $entities, $people, $tenant, $user) {
            $target = match ($attributes['target']) {
                'deal' => $deals->values()->get($attributes['index']),
                'person' => $people->values()->get($attributes['index']),
                default => $entities->values()->get($attributes['index']),
            };

            $startAt = now()->addDays($attributes['days'])->setTime(10, 0);

            $factory = match (true) {
                $target instanceof Deal => CalendarEvent::factory()->forDeal($target),
                $target instanceof Person => CalendarEvent::factory()->forPerson($target),
                default => CalendarEvent::factory()->forEntity($target),
            };

            $factory->ownedBy($user)
                ->create([
                    'tenant_id' => $tenant->id,
                    'title' => $attributes['title'],
                    'type' => $attributes['type'],
                    'status' => $attributes['status'],
                    'priority' => $attributes['priority'],
                    'start_at' => $startAt,
                    'end_at' => $startAt->copy()->addHour(),
                    'starts_at' => $startAt,
                    'ends_at' => $startAt->copy()->addHour(),
                    'location' => $attributes['location'],
                    'description' => 'Atividade demo para validar calendário, histórico e lembretes.',
                    'notes' => 'Atividade demo para validar calendário, histórico e lembretes.',
                    'reminder_at' => $attributes['status'] === CalendarEvent::STATUS_PENDING ? $startAt->copy()->subHour() : null,
                ]);
        });

        $proposalDeal = $deals->firstWhere('title', 'CRM rollout') ?? $deals->first();
        $proposalPath = 'deal-proposals/demo-proposal.txt';
        Storage::disk('local')->put($proposalPath, 'Proposta comercial demo FlowCRM.');

        DealProposal::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $proposalDeal->id,
            'uploaded_by' => $user->id,
            'original_name' => 'proposta-demo.txt',
            'path' => $proposalPath,
            'mime_type' => 'text/plain',
            'size' => strlen('Proposta comercial demo FlowCRM.'),
            'status' => DealProposal::STATUS_DRAFT,
        ]);
    }
}

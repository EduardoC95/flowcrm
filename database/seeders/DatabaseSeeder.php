<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AIChatConversation;
use App\Models\AIChatMessage;
use App\Models\AISuggestion;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealFollowUp;
use App\Models\DealFollowUpEmail;
use App\Models\DealProposal;
use App\Models\DealStage;
use App\Models\DealProduct;
use App\Models\DealNote;
use App\Models\Entity;
use App\Models\FollowUpTemplate;
use App\Models\InternalNotification;
use App\Models\LeadForm;
use App\Models\LeadFormSubmission;
use App\Models\Person;
use App\Models\Product;
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

        $followUpTemplates = collect([
            ['name' => 'Follow-up 01', 'subject' => 'Acompanhamento da proposta - {deal_title}', 'body' => "Olá {client_name},\n\nQueria saber se teve oportunidade de analisar a proposta relativa a {deal_title}.\n\nPosso ajudar com alguma informação adicional?\n\nObrigado,\n{user_name}"],
            ['name' => 'Follow-up 02', 'subject' => 'Dúvidas sobre {deal_title}', 'body' => "Olá {client_name},\n\nFicou alguma dúvida sobre a proposta que enviámos para {company_name}?\n\nEstou disponível para esclarecer qualquer ponto.\n\nObrigado,\n{user_name}"],
            ['name' => 'Follow-up 03', 'subject' => 'Podemos ajudar com a proposta?', 'body' => "Olá {client_name},\n\nPasso só para confirmar se precisa de ajuda com a proposta de {deal_title}.\n\nSe fizer sentido, posso alinhar os próximos passos consigo.\n\nObrigado,\n{user_name}"],
            ['name' => 'Follow-up 04', 'subject' => 'Novidades sobre {deal_title}', 'body' => "Olá {client_name},\n\nTem alguma novidade sobre a proposta relativa a {deal_title}?\n\nFico totalmente disponível para ajudar no que for necessário.\n\nObrigado,\n{user_name}"],
            ['name' => 'Follow-up 05', 'subject' => 'Acompanhamento comercial', 'body' => "Olá {client_name},\n\nEstou a acompanhar a proposta enviada e queria perceber se existe algum ponto que possamos ajustar.\n\nObrigado,\n{user_name}"],
            ['name' => 'Follow-up 06', 'subject' => 'Próximo passo para {deal_title}', 'body' => "Olá {client_name},\n\nGostava de confirmar consigo qual será o melhor próximo passo relativamente a {deal_title}.\n\nPosso ajudar com mais contexto ou informação.\n\nObrigado,\n{user_name}"],
            ['name' => 'Follow-up 07', 'subject' => 'Seguimento da nossa proposta', 'body' => "Olá {client_name},\n\nEspero que se encontre bem.\n\nQueria saber se a proposta enviada responde às necessidades de {company_name} ou se há algo que devamos rever.\n\nObrigado,\n{user_name}"],
            ['name' => 'Follow-up 08', 'subject' => 'Disponível para esclarecer a proposta', 'body' => "Olá {client_name},\n\nDeixo uma nota rápida para dizer que estou disponível para esclarecer qualquer detalhe da proposta de {deal_title}.\n\nObrigado,\n{user_name}"],
            ['name' => 'Follow-up 09', 'subject' => 'Atualização sobre a proposta', 'body' => "Olá {client_name},\n\nConseguiu avançar na análise da proposta?\n\nSe houver alguma questão em aberto, terei todo o gosto em ajudar.\n\nObrigado,\n{user_name}"],
            ['name' => 'Follow-up 10', 'subject' => 'Fecho de próximos passos', 'body' => "Olá {client_name},\n\nQueria confirmar se ainda faz sentido avançarmos com os próximos passos para {deal_title}.\n\nFico atento ao seu feedback.\n\nObrigado,\n{user_name}"],
        ])->map(fn (array $attributes, int $index) => FollowUpTemplate::create([
            ...$attributes,
            'tenant_id' => null,
            'active' => true,
            'position' => $index + 1,
        ]));

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

        $deals->take(3)->each(fn (Deal $deal, int $index) => $deal->forceFill([
            'last_activity_at' => now()->subDays(6 + ($index * 3)),
        ])->save());

        $automationRules = collect([
            [
                'name' => 'Follow-up de negócios parados há 5 dias',
                'description' => 'Cria uma tarefa para rever oportunidades sem contacto recente.',
                'inactivity_days' => 5,
                'activity_type' => CalendarEvent::TYPE_TASK,
                'title' => 'Follow-up automático: {deal_title}',
                'description_template' => 'O negócio está sem atividade há {inactivity_days} dias. Rever próximos passos com {owner_name}.',
                'due_in_days' => 1,
                'priority' => 'inherit',
            ],
            [
                'name' => 'Chamada para oportunidades sem atividade há 10 dias',
                'description' => 'Agenda uma chamada comercial quando uma oportunidade fica demasiado tempo parada.',
                'inactivity_days' => 10,
                'activity_type' => CalendarEvent::TYPE_CALL,
                'title' => 'Chamada de recuperação: {deal_title}',
                'description_template' => 'Contactar o cliente e perceber se precisa de apoio adicional.',
                'due_in_days' => 2,
                'priority' => Deal::PRIORITY_HIGH,
            ],
        ])->map(fn (array $attributes) => AutomationRule::create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['name'],
            'description' => $attributes['description'],
            'trigger_type' => AutomationRule::TRIGGER_DEAL_INACTIVITY,
            'inactivity_days' => $attributes['inactivity_days'],
            'action_type' => AutomationRule::ACTION_CREATE_CALENDAR_ACTIVITY,
            'action_payload' => [
                'activity_type' => $attributes['activity_type'],
                'activity_title_template' => $attributes['title'],
                'activity_description_template' => $attributes['description_template'],
                'due_in_days' => $attributes['due_in_days'],
                'priority' => $attributes['priority'],
            ],
            'notify_owner' => true,
            'active' => true,
            'created_by' => $user->id,
        ]));

        $automationDeal = $deals->first();
        $automationEvent = CalendarEvent::factory()->forDeal($automationDeal)->ownedBy($user)->create([
            'tenant_id' => $tenant->id,
            'title' => 'Atividade demo criada por automação',
            'description' => 'Exemplo de atividade criada por uma regra de negócio sem atividade.',
            'notes' => 'Exemplo de atividade criada por uma regra de negócio sem atividade.',
            'type' => CalendarEvent::TYPE_TASK,
            'status' => CalendarEvent::STATUS_PENDING,
            'priority' => $automationDeal?->priority ?? CalendarEvent::PRIORITY_MEDIUM,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(10, 0),
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(10, 0),
        ]);

        AutomationRun::create([
            'tenant_id' => $tenant->id,
            'automation_rule_id' => $automationRules->first()->id,
            'deal_id' => $automationDeal?->id,
            'calendar_event_id' => $automationEvent->id,
            'status' => AutomationRun::STATUS_SUCCESS,
            'result' => 'Atividade demo criada automaticamente.',
            'metadata' => ['source' => 'seeder'],
            'ran_at' => now()->subDay(),
        ]);

        InternalNotification::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'title' => 'Nova atividade criada por automação',
            'body' => 'A automação de demonstração criou uma tarefa para um negócio sem atividade.',
            'type' => 'automation',
            'notifiable_type' => CalendarEvent::class,
            'notifiable_id' => $automationEvent->id,
        ]);

        $leadForms = collect([
            [
                'name' => 'Pedido de contacto',
                'slug' => 'pedido-contacto',
                'description' => 'Formulário público para pedidos rápidos de contacto.',
                'confirmation_message' => 'Obrigado pelo contacto. A nossa equipa vai responder em breve.',
            ],
            [
                'name' => 'Pedido de proposta',
                'slug' => 'pedido-proposta',
                'description' => 'Formulário público para pedidos de proposta comercial.',
                'confirmation_message' => 'Obrigado. Recebemos o pedido de proposta e vamos analisar os dados enviados.',
            ],
        ])->map(fn (array $attributes) => LeadForm::create([
            ...$attributes,
            'tenant_id' => $tenant->id,
            'fields' => [
                ['key' => 'name', 'label' => 'Nome', 'type' => LeadForm::FIELD_TEXT, 'required' => true, 'placeholder' => 'O seu nome'],
                ['key' => 'email', 'label' => 'Email', 'type' => LeadForm::FIELD_EMAIL, 'required' => true, 'placeholder' => 'email@empresa.pt'],
                ['key' => 'phone', 'label' => 'Telefone', 'type' => LeadForm::FIELD_PHONE, 'required' => false, 'placeholder' => '+351 ...'],
                ['key' => 'company', 'label' => 'Empresa', 'type' => LeadForm::FIELD_TEXT, 'required' => false, 'placeholder' => 'Nome da empresa'],
                ['key' => 'message', 'label' => 'Mensagem', 'type' => LeadForm::FIELD_TEXTAREA, 'required' => false, 'placeholder' => 'Como podemos ajudar?'],
            ],
            'active' => true,
            'require_captcha' => true,
            'created_by' => $user->id,
        ]));

        $leadForms->each(function (LeadForm $leadForm, int $index) use ($tenant, $user, $stages) {
            $payload = [
                'name' => $index === 0 ? 'Carla Mendes' : 'Pedro Rocha',
                'email' => $index === 0 ? 'carla.lead@example.test' : 'pedro.proposta@example.test',
                'phone' => '+351 930 200 00'.$index,
                'company' => $index === 0 ? 'Mendes Consulting' : 'Rocha Energia',
                'message' => $index === 0 ? 'Gostava de agendar uma chamada.' : 'Precisamos de uma proposta para automação comercial.',
            ];

            $person = Person::create([
                'tenant_id' => $tenant->id,
                'name' => $payload['name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'status' => Person::STATUS_LEAD,
                'notes' => 'Origem: Formulário público: '.$leadForm->name."\nMensagem: ".$payload['message'],
            ]);

            $stage = $stages->get(DealStage::SLUG_LEAD);
            $deal = Deal::create([
                'tenant_id' => $tenant->id,
                'person_id' => $person->id,
                'owner_id' => $user->id,
                'deal_stage_id' => $stage?->id,
                'stage' => $stage?->slug ?? DealStage::SLUG_LEAD,
                'title' => 'Lead via '.$leadForm->name.' - '.$person->name,
                'value' => 0,
                'probability' => 0,
                'priority' => Deal::PRIORITY_MEDIUM,
                'description' => 'Lead demo criada através de formulário público.',
                'last_activity_at' => now(),
            ]);

            LeadFormSubmission::create([
                'tenant_id' => $tenant->id,
                'lead_form_id' => $leadForm->id,
                'payload' => $payload,
                'source_url' => 'https://site-demo.example/'.$leadForm->slug,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'FlowCRM Seeder',
                'created_person_id' => $person->id,
                'created_deal_id' => $deal->id,
                'captcha_passed' => true,
                'submitted_at' => now()->subHours($index + 1),
            ]);
        });

        $products = collect([
            ['name' => 'Licença FlowCRM Core', 'sku' => 'FLOW-CORE', 'unit_price' => 1200],
            ['name' => 'Automação Comercial', 'sku' => 'AUTO-SALES', 'unit_price' => 2400],
            ['name' => 'Onboarding Premium', 'sku' => 'ONB-PREM', 'unit_price' => 1800],
            ['name' => 'Integração ERP', 'sku' => 'INT-ERP', 'unit_price' => 3500],
            ['name' => 'Pack AI Comercial', 'sku' => 'AI-SALES', 'unit_price' => 2100],
            ['name' => 'Suporte Mensal', 'sku' => 'SUP-MONTH', 'unit_price' => 450],
        ])->map(fn (array $attributes) => Product::create([
            ...$attributes,
            'tenant_id' => $tenant->id,
            'description' => 'Produto demo para análise de presença e valor nos negócios.',
            'active' => true,
        ]));

        $deals->values()->each(function (Deal $deal, int $dealIndex) use ($products, $tenant) {
            $products->take(3)->each(function (Product $product, int $productIndex) use ($deal, $dealIndex, $tenant) {
                if (($dealIndex + $productIndex) % 2 !== 0) {
                    return;
                }

                $quantity = $productIndex + 1;
                $unitPrice = (float) $product->unit_price;

                DealProduct::create([
                    'tenant_id' => $tenant->id,
                    'deal_id' => $deal->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => round($quantity * $unitPrice, 2),
                ]);
            });
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
        $followUpDeal = $deals->firstWhere('stage', DealStage::SLUG_FOLLOW_UP) ?? $deals->first();
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

        if ($followUpDeal instanceof Deal && $followUpDeal->id !== $proposalDeal?->id) {
            DealProposal::create([
                'tenant_id' => $tenant->id,
                'deal_id' => $followUpDeal->id,
                'uploaded_by' => $user->id,
                'original_name' => 'proposta-follow-up-demo.txt',
                'path' => $proposalPath,
                'mime_type' => 'text/plain',
                'size' => strlen('Proposta comercial demo FlowCRM.'),
                'status' => DealProposal::STATUS_SENT,
                'sent_at' => now()->subDays(2)->setTime(11, 0),
                'sent_by' => $user->id,
                'recipient_email' => $followUpDeal->person?->email ?? $followUpDeal->entity?->email,
                'email_subject' => 'Proposta comercial - '.$followUpDeal->title,
                'email_body' => 'Email demo enviado com a proposta comercial.',
            ]);
        }

        $deals->each(fn (Deal $deal) => DealNote::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'user_id' => $user->id,
            'body' => 'Nota demo sobre o contexto comercial e próximos passos deste negócio.',
        ]));

        if ($followUpDeal instanceof Deal) {
            ActivityLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'action' => 'demo.timeline.seeded',
                'module' => 'deals',
                'subject_type' => Deal::class,
                'subject_id' => $followUpDeal->id,
                'description' => 'Demo timeline interaction created.',
                'properties' => ['source' => 'seeder'],
            ]);

            $followUp = DealFollowUp::create([
                'tenant_id' => $tenant->id,
                'deal_id' => $followUpDeal->id,
                'status' => DealFollowUp::STATUS_ACTIVE,
                'next_send_at' => now()->addDay()->setTime(10, 0),
                'last_sent_at' => now()->subDay()->setTime(10, 0),
                'sent_count' => 1,
            ]);

            $template = $followUpTemplates->first();

            DealFollowUpEmail::create([
                'tenant_id' => $tenant->id,
                'deal_id' => $followUpDeal->id,
                'deal_follow_up_id' => $followUp->id,
                'follow_up_template_id' => $template?->id,
                'sent_by' => $user->id,
                'recipient_email' => $followUpDeal->person?->email ?? $followUpDeal->entity?->email ?? 'cliente@example.test',
                'subject' => 'Acompanhamento da proposta - '.$followUpDeal->title,
                'body' => "Olá,\n\nQueria saber se teve oportunidade de analisar a proposta.\n\nObrigado,\n{$user->name}",
                'sent_at' => now()->subDay()->setTime(10, 0),
            ]);
        }

        $chatConversation = AIChatConversation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'title' => 'Volume em negociacao',
            'last_message_at' => now()->subMinutes(10),
        ]);

        AIChatMessage::create([
            'tenant_id' => $tenant->id,
            'ai_chat_conversation_id' => $chatConversation->id,
            'user_id' => $user->id,
            'role' => AIChatMessage::ROLE_USER,
            'content' => 'Qual o volume de negocios no estado Negociacao?',
        ]);

        AIChatMessage::create([
            'tenant_id' => $tenant->id,
            'ai_chat_conversation_id' => $chatConversation->id,
            'role' => AIChatMessage::ROLE_ASSISTANT,
            'content' => 'O volume de negocios no estado Negociacao e 32.000,00 EUR em 1 negocio.',
            'intent' => 'deal_volume_by_stage',
            'metadata' => [
                'records' => [
                    [
                        'type' => 'deal',
                        'id' => $deals->firstWhere('stage', DealStage::SLUG_NEGOTIATION)?->id,
                        'title' => 'Automacao comercial anual',
                        'url' => '/deals/'.$deals->firstWhere('stage', DealStage::SLUG_NEGOTIATION)?->id,
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'open_record',
                        'label' => 'Abrir negocios',
                        'url' => '/deals',
                    ],
                ],
            ],
        ]);

        $suggestionDeals = $deals->values();
        collect([
            [
                'deal' => $suggestionDeals->get(2),
                'type' => AISuggestion::TYPE_HIGH_VALUE_STALLED,
                'title' => 'Desbloquear negocio de alto valor',
                'reason' => 'Este negocio tem valor relevante e esta sem atividade recente.',
                'action' => 'Contactar cliente prioritariamente',
                'priority' => AISuggestion::PRIORITY_URGENT,
                'status' => AISuggestion::STATUS_PENDING,
                'score' => 92,
                'source' => 'daily_analysis',
            ],
            [
                'deal' => $proposalDeal,
                'type' => AISuggestion::TYPE_PROPOSAL_SENT_NO_FOLLOWUP,
                'title' => 'Acompanhar proposta enviada',
                'reason' => 'Foi enviada uma proposta, mas ainda nao houve acompanhamento recente.',
                'action' => 'Telefonar ou enviar follow-up da proposta',
                'priority' => AISuggestion::PRIORITY_HIGH,
                'status' => AISuggestion::STATUS_POSTPONED,
                'score' => 78,
                'source' => 'daily_analysis',
                'postponed_until' => now()->addDay(),
            ],
            [
                'deal' => $suggestionDeals->get(1),
                'type' => AISuggestion::TYPE_CLOSING_DATE_NEAR,
                'title' => 'Validar fecho previsto',
                'reason' => 'A data prevista de fecho esta proxima.',
                'action' => 'Marcar chamada de validacao antes do fecho',
                'priority' => AISuggestion::PRIORITY_HIGH,
                'status' => AISuggestion::STATUS_ACCEPTED,
                'score' => 74,
                'source' => 'daily_analysis',
                'accepted_at' => now()->subDay(),
                'accepted_by' => $user->id,
            ],
            [
                'deal' => $suggestionDeals->get(0),
                'type' => AISuggestion::TYPE_NEW_LEAD_NEEDS_FIRST_CONTACT,
                'title' => 'Fazer primeiro contacto com lead',
                'reason' => 'Esta lead ainda precisa de primeiro contacto.',
                'action' => 'Criar tarefa de primeiro contacto',
                'priority' => AISuggestion::PRIORITY_MEDIUM,
                'status' => AISuggestion::STATUS_ARCHIVED,
                'score' => 64,
                'source' => 'realtime_deal_created',
                'archived_at' => now()->subHours(6),
                'archived_by' => $user->id,
            ],
            [
                'deal' => $followUpDeal,
                'type' => AISuggestion::TYPE_NO_ACTIVITY,
                'title' => 'Retomar contacto comercial',
                'reason' => 'Este negocio nao tem atividade recente ha varios dias.',
                'action' => 'Criar tarefa de follow-up',
                'priority' => AISuggestion::PRIORITY_HIGH,
                'status' => AISuggestion::STATUS_PENDING,
                'score' => 83,
                'source' => 'daily_analysis',
            ],
        ])->each(function (array $attributes) use ($tenant, $user) {
            $deal = $attributes['deal'];

            if (! $deal instanceof Deal) {
                return;
            }

            AISuggestion::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'deal_id' => $deal->id,
                'person_id' => $deal->person_id,
                'entity_id' => $deal->entity_id,
                'type' => $attributes['type'],
                'title' => $attributes['title'],
                'reason' => $attributes['reason'],
                'suggested_action' => $attributes['action'],
                'suggested_due_at' => now()->addDay()->setTime(10, 0),
                'priority' => $attributes['priority'],
                'status' => $attributes['status'],
                'source' => $attributes['source'],
                'score' => $attributes['score'],
                'metadata' => ['source' => 'seeder'],
                'postponed_until' => $attributes['postponed_until'] ?? null,
                'accepted_at' => $attributes['accepted_at'] ?? null,
                'accepted_by' => $attributes['accepted_by'] ?? null,
                'archived_at' => $attributes['archived_at'] ?? null,
                'archived_by' => $attributes['archived_by'] ?? null,
            ]);
        });
    }
}

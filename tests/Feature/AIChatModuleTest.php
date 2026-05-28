<?php

namespace Tests\Feature;

use App\Models\AIChatConversation;
use App\Models\AIChatMessage;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealProduct;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AIChatModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'openai.enabled' => false,
            'openai.api_key' => null,
        ]);
    }

    public function test_chat_page_loads(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);

        $this->actingAs($user)
            ->get(route('ai-chat.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ai-chat/Index')
                ->has('suggestions')
                ->etc());
    }

    public function test_first_message_creates_conversation_and_assistant_response_with_local_fallback(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $stage = $this->stage($tenant, DealStage::SLUG_NEGOTIATION);
        $this->dealForTenant($tenant, $user, [
            'deal_stage_id' => $stage->id,
            'stage' => $stage->slug,
            'value' => 32000,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('ai-chat.store'), [
                'message' => 'Qual o volume de negocios no estado Negociacao?',
            ]);

        $response->assertOk()
            ->assertJsonPath('result.intent.intent', 'deal_volume_by_stage')
            ->assertJsonPath('result.metadata.total_value', 32000);

        $this->assertDatabaseCount('ai_chat_conversations', 1);
        $this->assertDatabaseHas('ai_chat_messages', ['role' => AIChatMessage::ROLE_USER]);
        $this->assertDatabaseHas('ai_chat_messages', ['role' => AIChatMessage::ROLE_ASSISTANT, 'intent' => 'deal_volume_by_stage']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ai_chat.response_generated', 'tenant_id' => $tenant->id]);
    }

    public function test_chat_answers_count_phone_email_inactive_closing_and_top_products(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme']);
        $person = Person::factory()->forEntity($entity)->create([
            'name' => 'Maria Silva',
            'email' => 'maria@example.test',
            'phone' => '+351 910 100 001',
        ]);
        $stage = $this->stage($tenant, DealStage::SLUG_NEGOTIATION);
        $deal = $this->dealForTenant($tenant, $user, [
            'entity_id' => $entity->id,
            'person_id' => $person->id,
            'deal_stage_id' => $stage->id,
            'stage' => $stage->slug,
            'title' => 'Automation rollout',
            'expected_close_date' => now()->addDays(3),
            'last_activity_at' => now()->subDays(10),
        ]);
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'FlowCRM Core',
            'sku' => 'FLOW-CORE',
            'unit_price' => 1000,
            'active' => true,
        ]);
        DealProduct::create([
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 1000,
            'total' => 2000,
        ]);

        $conversationId = $this->ask($user, 'Quantos negocios tenho no estado Negociacao?')
            ->assertJsonPath('result.intent.intent', 'deal_count_by_stage')
            ->json('conversation.id');

        $this->ask($user, 'Qual o telefone da Maria Silva?', $conversationId)
            ->assertJsonPath('result.intent.intent', 'find_person_phone')
            ->assertJsonFragment(['title' => 'Maria Silva']);

        $this->ask($user, 'Qual o email da Maria Silva?', $conversationId)
            ->assertJsonPath('result.intent.intent', 'find_person_email');

        $this->ask($user, 'Quais os negocios sem atividade ha mais de 7 dias?', $conversationId)
            ->assertJsonPath('result.intent.intent', 'inactive_deals')
            ->assertJsonFragment(['title' => 'Automation rollout']);

        $this->ask($user, 'Que negocios fecham nos proximos 14 dias?', $conversationId)
            ->assertJsonPath('result.intent.intent', 'deals_closing_soon')
            ->assertJsonFragment(['title' => 'Automation rollout']);

        $this->ask($user, 'Quais sao os produtos com maior valor no pipeline?', $conversationId)
            ->assertJsonPath('result.intent.intent', 'top_products_by_value')
            ->assertJsonFragment(['title' => 'FlowCRM Core']);
    }

    public function test_chat_respects_tenant_isolation(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [$otherUser, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        Person::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Hidden Contact',
            'phone' => '+351 999 999 999',
        ]);
        $this->dealForTenant($otherTenant, $otherUser, ['title' => 'Hidden deal']);

        $this->actingAs($user)
            ->postJson(route('ai-chat.store'), ['message' => 'Qual o telefone da Hidden Contact?'])
            ->assertOk()
            ->assertJsonMissing(['title' => 'Hidden Contact'])
            ->assertJsonMissing(['title' => 'Hidden deal']);
    }

    public function test_suggested_questions_endpoint_works(): void
    {
        [$user] = $this->userWithTenant(Tenant::ROLE_VIEWER);

        $this->actingAs($user)
            ->getJson(route('ai-chat.suggestions'))
            ->assertOk()
            ->assertJsonStructure(['suggestions']);
    }

    public function test_stream_endpoint_returns_event_stream(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $conversation = AIChatConversation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'title' => 'Stream',
            'last_message_at' => now(),
        ]);
        AIChatMessage::create([
            'tenant_id' => $tenant->id,
            'ai_chat_conversation_id' => $conversation->id,
            'role' => AIChatMessage::ROLE_ASSISTANT,
            'content' => 'Resposta em streaming.',
        ]);

        $this->actingAs($user)
            ->get(route('ai-chat.stream', $conversation))
            ->assertOk()
            ->assertHeader('content-type', 'text/event-stream; charset=UTF-8');
    }

    public function test_quick_actions_create_note_and_activity_only_after_confirmation(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $deal = $this->dealForTenant($tenant, $user);
        $conversation = AIChatConversation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'title' => 'Actions',
            'last_message_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('ai-chat.actions', $conversation), [
                'type' => 'create_note',
                'payload' => [
                    'deal_id' => $deal->id,
                    'body' => 'Nota confirmada pelo utilizador.',
                ],
            ])
            ->assertOk()
            ->assertJsonFragment(['answer_text' => 'Nota adicionada ao negocio '.$deal->title.'.']);

        $this->assertDatabaseHas('deal_notes', [
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'body' => 'Nota confirmada pelo utilizador.',
        ]);

        $this->actingAs($user)
            ->postJson(route('ai-chat.actions', $conversation), [
                'type' => 'create_activity',
                'payload' => [
                    'deal_id' => $deal->id,
                    'title' => 'Follow-up criado pelo chat',
                    'activity_type' => CalendarEvent::TYPE_TASK,
                    'start_at' => now()->addDay()->toDateTimeString(),
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('calendar_events', [
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'title' => 'Follow-up criado pelo chat',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'ai_chat.action_created']);
    }

    public function test_viewer_cannot_execute_write_actions(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_VIEWER);
        $owner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $owner->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_OWNER]);
        $deal = $this->dealForTenant($tenant, $owner);
        $conversation = AIChatConversation::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'title' => 'Viewer',
            'last_message_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('ai-chat.actions', $conversation), [
                'type' => 'create_note',
                'payload' => [
                    'deal_id' => $deal->id,
                    'body' => 'Not allowed',
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('deal_notes', ['body' => 'Not allowed']);
    }

    public function test_chat_routes_are_rate_limited(): void
    {
        $this->assertTrue(collect(Route::getRoutes()->getByName('ai-chat.store')?->middleware() ?? [])->contains('throttle:30,1'));
        $this->assertTrue(collect(Route::getRoutes()->getByName('ai-chat.actions')?->middleware() ?? [])->contains('throttle:20,1'));
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function userWithTenant(string $role): array
    {
        $tenant = Tenant::factory()->create();
        DealStage::ensureDefaultStages($tenant);
        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
        ]);

        $user->tenants()->attach($tenant->id, [
            'role' => $role,
        ]);

        return [$user->refresh(), $tenant];
    }

    private function stage(Tenant $tenant, string $slug): DealStage
    {
        return DealStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function dealForTenant(Tenant $tenant, User $owner, array $attributes = []): Deal
    {
        $entity = $attributes['entity'] ?? Entity::factory()->create(['tenant_id' => $tenant->id]);
        $stage = $attributes['deal_stage_id'] ?? $this->stage($tenant, DealStage::SLUG_LEAD)->id;

        return Deal::factory()->create([
            'tenant_id' => $tenant->id,
            'entity_id' => $entity instanceof Entity ? $entity->id : $entity,
            'owner_id' => $owner->id,
            'deal_stage_id' => $stage,
            'stage' => $attributes['stage'] ?? DealStage::withoutGlobalScopes()->find($stage)?->slug ?? DealStage::SLUG_LEAD,
            ...$attributes,
        ]);
    }

    private function ask(User $user, string $message, ?int $conversationId = null)
    {
        $route = $conversationId
            ? route('ai-chat.messages.store', $conversationId)
            : route('ai-chat.store');

        return $this->actingAs($user)->postJson($route, ['message' => $message])->assertOk();
    }
}

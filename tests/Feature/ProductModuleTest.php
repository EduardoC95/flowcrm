<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Deal;
use App\Models\DealProduct;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_product_crud_search_and_active_filter(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_SALES);
        $visible = $this->product($tenant, ['name' => 'Consultoria CRM', 'sku' => 'CRM-CONS', 'active' => true]);
        $inactive = $this->product($tenant, ['name' => 'Produto antigo', 'sku' => 'OLD', 'active' => false]);

        $this->actingAs($user)
            ->get(route('products.index', ['search' => 'CRM-CONS']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/Index')
                ->has('products.data', 1)
                ->where('products.data.0.id', $visible->id)
                ->etc());

        $this->actingAs($user)
            ->get(route('products.index', ['active' => 'inactive']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $inactive->id)
                ->etc());

        $response = $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Pack AI',
            'sku' => 'AI',
            'description' => 'Apoio comercial com AI.',
            'unit_price' => 1500,
            'active' => true,
        ]);

        $product = Product::firstWhere('name', 'Pack AI');
        $response->assertRedirect(route('products.show', $product));

        $this->actingAs($user)
            ->put(route('products.update', $product), [
                'name' => 'Pack AI Plus',
                'sku' => 'AI-PLUS',
                'description' => 'Atualizado.',
                'unit_price' => 1750,
                'active' => false,
            ])
            ->assertRedirect(route('products.show', $product));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Pack AI Plus',
            'active' => false,
        ]);

        $this->actingAs($user)->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'product.created', 'subject_id' => $product->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'product.updated', 'subject_id' => $product->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'product.deleted', 'subject_id' => $product->id]);
    }

    public function test_viewer_cannot_manage_products(): void
    {
        [$viewer, $tenant] = $this->userWithTenant(Tenant::ROLE_VIEWER);
        $product = $this->product($tenant);

        $this->actingAs($viewer)->get(route('products.index'))->assertOk();
        $this->actingAs($viewer)->get(route('products.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('products.store'), ['name' => 'Hidden'])->assertForbidden();
        $this->actingAs($viewer)->put(route('products.update', $product), ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('products.destroy', $product))->assertForbidden();
    }

    public function test_deal_products_can_be_added_updated_removed_and_logged(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_MANAGER);
        $deal = $this->deal($tenant, $user);
        $product = $this->product($tenant, ['unit_price' => 100]);

        $this->actingAs($user)
            ->post(route('deals.products.store', $deal), [
                'product_id' => $product->id,
                'quantity' => 3,
            ])
            ->assertRedirect();

        $dealProduct = DealProduct::firstOrFail();
        $this->assertDatabaseHas('deal_products', [
            'id' => $dealProduct->id,
            'tenant_id' => $tenant->id,
            'deal_id' => $deal->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 100,
            'total' => 300,
        ]);

        $this->actingAs($user)
            ->patch(route('deals.products.update', [$deal, $dealProduct]), [
                'quantity' => 2,
                'unit_price' => 125,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('deal_products', [
            'id' => $dealProduct->id,
            'quantity' => 2,
            'unit_price' => 125,
            'total' => 250,
        ]);

        $this->actingAs($user)
            ->delete(route('deals.products.destroy', [$deal, $dealProduct]))
            ->assertRedirect();

        $this->assertDatabaseMissing('deal_products', ['id' => $dealProduct->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'deal_product.added', 'subject_id' => $deal->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'deal_product.updated', 'subject_id' => $deal->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'deal_product.removed', 'subject_id' => $deal->id]);
    }

    public function test_cannot_add_product_from_another_tenant_or_access_cross_tenant_product(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        [, $otherTenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->deal($tenant, $user);
        $otherProduct = $this->product($otherTenant);

        $this->actingAs($user)
            ->from(route('deals.show', $deal))
            ->post(route('deals.products.store', $deal), [
                'product_id' => $otherProduct->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('deals.show', $deal))
            ->assertSessionHasErrors('product_id');

        $this->actingAs($user)->get(route('products.show', $otherProduct))->assertNotFound();
    }

    public function test_product_detail_shows_deals_where_it_appears(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $deal = $this->deal($tenant, $user, ['title' => 'CRM rollout']);
        $product = $this->product($tenant);
        $this->attachProduct($deal, $product, 4, 50);

        $this->actingAs($user)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/Show')
                ->where('product.stats.total_quantity', 4)
                ->where('product.stats.total_value', 200)
                ->where('product.stats.deals_count', 1)
                ->where('product.deals.0.deal.title', 'CRM rollout')
                ->etc());
    }

    public function test_product_stats_calculate_totals_and_filters(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $otherOwner = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $otherOwner->tenants()->attach($tenant->id, ['role' => Tenant::ROLE_SALES]);
        $lead = $this->stage($tenant, DealStage::SLUG_LEAD);
        $won = $this->stage($tenant, DealStage::SLUG_WON);
        $product = $this->product($tenant, ['name' => 'Licenca']);
        $otherProduct = $this->product($tenant, ['name' => 'Suporte']);
        $visibleDeal = $this->deal($tenant, $user, [
            'deal_stage_id' => $lead->id,
            'stage' => $lead->slug,
            'created_at' => '2026-05-10 10:00:00',
        ]);
        $hiddenDeal = $this->deal($tenant, $otherOwner, [
            'deal_stage_id' => $won->id,
            'stage' => $won->slug,
            'created_at' => '2026-06-10 10:00:00',
        ]);

        $this->attachProduct($visibleDeal, $product, 2, 100);
        $this->attachProduct($visibleDeal, $otherProduct, 1, 50);
        $this->attachProduct($hiddenDeal, $product, 10, 100);

        $this->actingAs($user)
            ->get(route('product-stats.index', [
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
                'deal_stage_id' => $lead->id,
                'owner_id' => $user->id,
                'sort' => 'quantity',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('product-stats/Index')
                ->where('summary.products_count', 2)
                ->where('summary.total_quantity', 3)
                ->where('summary.total_value', 250)
                ->where('summary.deals_count', 1)
                ->where('rows.0.product_name', 'Licenca')
                ->where('rows.0.total_quantity', 2)
                ->where('rows.0.total_value', 200)
                ->where('rows.0.deals_count', 1)
                ->etc());
    }

    public function test_product_stats_export_csv_respects_filters_and_logs(): void
    {
        [$user, $tenant] = $this->userWithTenant(Tenant::ROLE_OWNER);
        $lead = $this->stage($tenant, DealStage::SLUG_LEAD);
        $won = $this->stage($tenant, DealStage::SLUG_WON);
        $product = $this->product($tenant, ['name' => 'Licenca', 'sku' => 'LIC']);
        $otherProduct = $this->product($tenant, ['name' => 'Suporte', 'sku' => 'SUP']);
        $deal = $this->deal($tenant, $user, ['deal_stage_id' => $lead->id, 'stage' => $lead->slug, 'created_at' => '2026-05-10 10:00:00']);
        $otherDeal = $this->deal($tenant, $user, ['deal_stage_id' => $won->id, 'stage' => $won->slug, 'created_at' => '2026-05-10 10:00:00']);
        $this->attachProduct($deal, $product, 2, 100);
        $this->attachProduct($otherDeal, $otherProduct, 1, 50);

        $response = $this->actingAs($user)->get(route('product-stats.export', ['deal_stage_id' => $lead->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Licenca,LIC,2.00,200.00,1', $content);
        $this->assertStringNotContainsString('Suporte', $content);
        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'product_stats.exported',
        ]);
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function userWithTenant(string $role): array
    {
        $tenant = Tenant::factory()->create();
        DealStage::ensureDefaultStages($tenant);
        $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id, ['role' => $role]);

        return [$user->refresh(), $tenant];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(Tenant $tenant, array $attributes = []): Product
    {
        return Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Produto Demo',
            'sku' => 'SKU-DEMO',
            'unit_price' => 100,
            'active' => true,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deal(Tenant $tenant, User $owner, array $attributes = []): Deal
    {
        $stageId = $attributes['deal_stage_id'] ?? $this->stage($tenant, DealStage::SLUG_LEAD)->id;
        $stageSlug = $attributes['stage'] ?? DealStage::withoutGlobalScopes()->find($stageId)?->slug ?? DealStage::SLUG_LEAD;
        $entity = Entity::factory()->create(['tenant_id' => $tenant->id]);

        return Deal::factory()->create([
            'tenant_id' => $tenant->id,
            'entity_id' => $entity->id,
            'owner_id' => $owner->id,
            'deal_stage_id' => $stageId,
            'stage' => $stageSlug,
            ...$attributes,
        ]);
    }

    private function stage(Tenant $tenant, string $slug): DealStage
    {
        return DealStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function attachProduct(Deal $deal, Product $product, float $quantity, float $unitPrice): DealProduct
    {
        return DealProduct::create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
        ]);
    }
}

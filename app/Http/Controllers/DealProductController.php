<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDealProductRequest;
use App\Http\Requests\UpdateDealProductRequest;
use App\Models\Deal;
use App\Models\DealProduct;
use App\Models\Product;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DealProductController extends Controller
{
    public function store(StoreDealProductRequest $request, Deal $deal, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $product = Product::findOrFail($request->validated('product_id'));
        $quantity = (float) $request->validated('quantity');
        $unitPrice = (float) ($request->validated('unit_price') ?? $product->unit_price);

        $dealProduct = DealProduct::create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
        ]);

        $deal->forceFill(['last_activity_at' => now()])->save();
        $this->log($logger, 'deal_product.added', $deal, $dealProduct);

        return back()->with('success', 'Produto adicionado ao negócio.');
    }

    public function update(UpdateDealProductRequest $request, Deal $deal, DealProduct $dealProduct, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $deal);
        $this->ensureDealProductMatchesDeal($deal, $dealProduct);

        $quantity = (float) $request->validated('quantity');
        $unitPrice = (float) ($request->validated('unit_price') ?? $dealProduct->unit_price);

        $dealProduct->update([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
        ]);

        $deal->forceFill(['last_activity_at' => now()])->save();
        $this->log($logger, 'deal_product.updated', $deal, $dealProduct);

        return back()->with('success', 'Produto do negócio atualizado.');
    }

    public function destroy(Deal $deal, DealProduct $dealProduct, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $deal);
        $this->ensureDealProductMatchesDeal($deal, $dealProduct);

        $properties = $this->properties($dealProduct);
        $dealProduct->delete();
        $deal->forceFill(['last_activity_at' => now()])->save();

        $logger->log(
            'deal_product.removed',
            'deal_products',
            $deal->tenant_id,
            $deal,
            'Product removed from deal.',
            $properties,
        );

        return back()->with('success', 'Produto removido do negócio.');
    }

    private function ensureDealProductMatchesDeal(Deal $deal, DealProduct $dealProduct): void
    {
        abort_unless((int) $dealProduct->deal_id === (int) $deal->id, 404);
        abort_unless((int) $dealProduct->tenant_id === (int) $deal->tenant_id, 404);
    }

    private function log(ActivityLogger $logger, string $action, Deal $deal, DealProduct $dealProduct): void
    {
        $logger->log(
            $action,
            'deal_products',
            $deal->tenant_id,
            $deal,
            'Deal product changed.',
            $this->properties($dealProduct),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(DealProduct $dealProduct): array
    {
        return [
            'deal_product_id' => $dealProduct->id,
            'product_id' => $dealProduct->product_id,
            'quantity' => (float) $dealProduct->quantity,
            'unit_price' => (float) $dealProduct->unit_price,
            'total' => (float) $dealProduct->total,
        ];
    }
}

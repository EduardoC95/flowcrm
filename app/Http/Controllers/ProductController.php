<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Product::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'in:active,inactive'],
            'sort' => ['nullable', 'in:name,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = $filters['search'] ?? null;
        $active = $filters['active'] ?? null;
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        $products = Product::query()
            ->withCount('dealProducts')
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($active, fn ($query, string $active) => $query->where('active', $active === 'active'))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Product $product) => $this->productRow($product));

        return Inertia::render('products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'active' => $active,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'can' => [
                'create' => $request->user()->can('create', Product::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('products/Create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = Product::create([
            ...$request->validated(),
            'unit_price' => $request->validated('unit_price') ?? 0,
            'active' => $request->boolean('active', true),
        ]);

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Produto criado com sucesso.');
    }

    public function show(Product $product): Response
    {
        Gate::authorize('view', $product);

        $product->load([
            'dealProducts' => fn ($query) => $query
                ->latest()
                ->with(['deal.stage:id,name,slug,color', 'deal.owner:id,name']),
        ]);

        $dealProducts = $product->dealProducts;

        return Inertia::render('products/Show', [
            'product' => [
                ...$this->productRow($product),
                'description' => $product->description,
                'created_at' => $product->created_at?->toDateTimeString(),
                'updated_at' => $product->updated_at?->toDateTimeString(),
                'stats' => [
                    'total_quantity' => (float) $dealProducts->sum('quantity'),
                    'total_value' => (float) $dealProducts->sum('total'),
                    'deals_count' => $dealProducts->pluck('deal_id')->unique()->count(),
                ],
                'deals' => $dealProducts->map(function ($dealProduct) {
                    $deal = $dealProduct->deal;
                    $stage = $deal?->relationLoaded('stage') ? $deal->getRelation('stage') : $deal?->stage()->first();

                    return [
                        'id' => $dealProduct->id,
                        'quantity' => (float) $dealProduct->quantity,
                        'unit_price' => (float) $dealProduct->unit_price,
                        'total' => (float) $dealProduct->total,
                        'deal' => $deal ? [
                            'id' => $deal->id,
                            'title' => $deal->title,
                            'expected_close_date' => $deal->expected_close_date?->toDateString(),
                            'stage' => $stage?->only(['id', 'name', 'slug', 'color']),
                            'owner' => $deal->owner?->only(['id', 'name']),
                        ] : null,
                    ];
                }),
            ],
            'can' => [
                'update' => request()->user()->can('update', $product),
                'delete' => request()->user()->can('delete', $product),
            ],
        ]);
    }

    public function edit(Product $product): Response
    {
        Gate::authorize('update', $product);

        return Inertia::render('products/Edit', [
            'product' => $product->only(['id', 'name', 'sku', 'description', 'unit_price', 'active']),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update([
            ...$request->validated(),
            'unit_price' => $request->validated('unit_price') ?? 0,
            'active' => $request->boolean('active'),
        ]);

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Produto atualizado com sucesso.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Produto apagado com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function productRow(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'unit_price' => (float) $product->unit_price,
            'active' => $product->active,
            'deal_products_count' => $product->deal_products_count ?? null,
        ];
    }
}

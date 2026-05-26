<?php

namespace App\Http\Controllers;

use App\Models\DealStage;
use App\Models\Product;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ProductStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductStatsController extends Controller
{
    public function index(Request $request, ProductStatsService $stats): Response
    {
        Gate::authorize('viewAny', Product::class);

        $filters = $this->filters($request);
        $tenantId = (int) $request->user()->current_tenant_id;

        return Inertia::render('product-stats/Index', [
            'rows' => $stats->rows($tenantId, $filters)->map(fn ($row) => $this->statsRow($row)),
            'summary' => $stats->summary($tenantId, $filters),
            'filters' => $filters,
            'stages' => DealStage::query()->orderBy('position')->get(['id', 'name']),
            'owners' => $this->ownerOptions($request),
        ]);
    }

    public function export(Request $request, ProductStatsService $stats, ActivityLogger $logger): StreamedResponse
    {
        Gate::authorize('viewAny', Product::class);

        $filters = $this->filters($request);
        $tenantId = (int) $request->user()->current_tenant_id;
        $rows = $stats->rows($tenantId, $filters);

        $logger->log(
            'product_stats.exported',
            'product_stats',
            $tenantId,
            null,
            'Product statistics exported.',
            ['filters' => $filters, 'rows_count' => $rows->count()],
        );

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Produto', 'SKU', 'Quantidade total', 'Valor total', 'Nº de negócios']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->product_name,
                    $row->sku,
                    number_format((float) $row->total_quantity, 2, '.', ''),
                    number_format((float) $row->total_value, 2, '.', ''),
                    $row->deals_count,
                ]);
            }

            fclose($handle);
        }, 'product-stats.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            ...[
                'date_from' => null,
                'date_to' => null,
                'deal_stage_id' => null,
                'owner_id' => null,
                'product_id' => null,
                'sort' => 'value',
            ],
            ...$request->validate([
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date'],
                'deal_stage_id' => ['nullable', 'integer'],
                'owner_id' => ['nullable', 'integer'],
                'product_id' => ['nullable', 'integer'],
                'sort' => ['nullable', 'in:value,quantity'],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statsRow(object $row): array
    {
        return [
            'product_id' => $row->product_id,
            'product_name' => $row->product_name,
            'sku' => $row->sku,
            'total_quantity' => (float) $row->total_quantity,
            'total_value' => (float) $row->total_value,
            'deals_count' => (int) $row->deals_count,
            'average_value_per_deal' => (float) $row->average_value_per_deal,
        ];
    }

    private function ownerOptions(Request $request)
    {
        $tenant = $request->user()->currentTenant;

        return $tenant?->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $user) => $user->only(['id', 'name'])) ?? collect();
    }
}

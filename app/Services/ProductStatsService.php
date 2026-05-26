<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductStatsService
{
    /**
     * Date filters use deals.created_at so the report answers:
     * "products included in deals created during this period".
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function rows(int $tenantId, array $filters): Collection
    {
        return $this->baseQuery($tenantId, $filters)
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy($filters['sort'] === 'quantity' ? 'total_quantity' : 'total_value', 'desc')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int>
     */
    public function summary(int $tenantId, array $filters): array
    {
        $rows = $this->rows($tenantId, $filters);
        $dealsCount = DB::table('deal_products')
            ->join('products', 'products.id', '=', 'deal_products.product_id')
            ->join('deals', 'deals.id', '=', 'deal_products.deal_id')
            ->where('deal_products.tenant_id', $tenantId)
            ->where('products.tenant_id', $tenantId)
            ->where('deals.tenant_id', $tenantId)
            ->whereNull('deals.deleted_at')
            ->whereNull('products.deleted_at')
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('deals.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('deals.created_at', '<=', $date))
            ->when($filters['deal_stage_id'] ?? null, fn ($query, int|string $stageId) => $query->where('deals.deal_stage_id', $stageId))
            ->when($filters['owner_id'] ?? null, fn ($query, int|string $ownerId) => $query->where('deals.owner_id', $ownerId))
            ->when($filters['product_id'] ?? null, fn ($query, int|string $productId) => $query->where('products.id', $productId))
            ->distinct('deals.id')
            ->count('deals.id');

        return [
            'products_count' => $rows->count(),
            'total_quantity' => (float) $rows->sum('total_quantity'),
            'total_value' => (float) $rows->sum('total_value'),
            'deals_count' => (int) $dealsCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(int $tenantId, array $filters): Builder
    {
        return DB::table('deal_products')
            ->join('products', 'products.id', '=', 'deal_products.product_id')
            ->join('deals', 'deals.id', '=', 'deal_products.deal_id')
            ->where('deal_products.tenant_id', $tenantId)
            ->where('products.tenant_id', $tenantId)
            ->where('deals.tenant_id', $tenantId)
            ->whereNull('deals.deleted_at')
            ->whereNull('products.deleted_at')
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('deals.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('deals.created_at', '<=', $date))
            ->when($filters['deal_stage_id'] ?? null, fn ($query, int|string $stageId) => $query->where('deals.deal_stage_id', $stageId))
            ->when($filters['owner_id'] ?? null, fn ($query, int|string $ownerId) => $query->where('deals.owner_id', $ownerId))
            ->when($filters['product_id'] ?? null, fn ($query, int|string $productId) => $query->where('products.id', $productId))
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                'products.sku',
                DB::raw('SUM(deal_products.quantity) as total_quantity'),
                DB::raw('SUM(deal_products.total) as total_value'),
                DB::raw('COUNT(DISTINCT deals.id) as deals_count'),
                DB::raw('CASE WHEN COUNT(DISTINCT deals.id) = 0 THEN 0 ELSE SUM(deal_products.total) / COUNT(DISTINCT deals.id) END as average_value_per_deal'),
            ]);
    }
}

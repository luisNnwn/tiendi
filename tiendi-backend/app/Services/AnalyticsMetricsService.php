<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(int $supplierId, string $fromDate, string $toDate): array
    {
        $current = $this->aggregatePeriod($supplierId, $fromDate, $toDate);
        $previousRange = $this->previousRange($fromDate, $toDate);
        $previous = $this->aggregatePeriod($supplierId, $previousRange['from'], $previousRange['to']);

        $ordersCount = $current['orders_count'];
        $revenue = $current['revenue'];
        $avgTicket = $ordersCount > 0 ? $revenue / $ordersCount : 0.0;

        $prevOrdersCount = $previous['orders_count'];
        $prevRevenue = $previous['revenue'];
        $prevAvgTicket = $prevOrdersCount > 0 ? $prevRevenue / $prevOrdersCount : 0.0;

        $clientsCurrent = $current['clients_count'];
        $clientsPrevious = $previous['clients_count'];

        $totalActiveStores = (int) DB::table('stores')
            ->where('active', true)
            ->count();

        $activeProducts = (int) DB::table('products')
            ->where('supplier_id', $supplierId)
            ->where('active', true)
            ->count();

        $productsSold = (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.supplier_id', $supplierId)
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$fromDate, $toDate])
            ->distinct()
            ->count('products.id');

        $catalogCoverage = $activeProducts > 0 ? ($productsSold / $activeProducts) * 100 : 0.0;

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.supplier_id', $supplierId)
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$fromDate, $toDate])
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.id, products.name, SUM(order_items.quantity) as quantity, COALESCE(SUM(order_items.subtotal), 0) as sales')
            ->orderByDesc('sales')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'quantity' => (int) $row->quantity,
                'sales' => number_format((float) $row->sales, 2, '.', ''),
            ])->all();

        $topStores = DB::table('orders')
            ->join('stores', 'stores.id', '=', 'orders.store_id')
            ->where('orders.supplier_id', $supplierId)
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$fromDate, $toDate])
            ->groupBy('stores.id', 'stores.name')
            ->selectRaw('stores.id, stores.name, COUNT(orders.id) as orders_count, COALESCE(SUM(orders.total), 0) as sales')
            ->orderByDesc('sales')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'orders_count' => (int) $row->orders_count,
                'sales' => number_format((float) $row->sales, 2, '.', ''),
            ])->all();

        $salesByCategory = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.supplier_id', $supplierId)
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$fromDate, $toDate])
            ->groupBy('products.category')
            ->selectRaw('COALESCE(products.category, ?) as category, COALESCE(SUM(order_items.subtotal), 0) as sales', ['Sin categoría'])
            ->orderByDesc('sales')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'sales' => number_format((float) $row->sales, 2, '.', ''),
            ])->all();

        $statusCountsRaw = DB::table('orders')
            ->where('supplier_id', $supplierId)
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->selectRaw('status, COUNT(*) as total')
            ->whereIn('status', ['pending', 'confirmed', 'delivered'])
            ->groupBy('status')
            ->pluck('total', 'status');

        $pendingCount = (int) ($statusCountsRaw['pending'] ?? 0);
        $confirmedCount = (int) ($statusCountsRaw['confirmed'] ?? 0);
        $deliveredCount = (int) ($statusCountsRaw['delivered'] ?? 0);
        $statusBase = max($ordersCount, 1);

        $monthlySales = $this->monthlySales($supplierId, 6, $toDate);
        $unsoldStores = $this->unsoldStores($supplierId, $fromDate, $toDate);

        return [
            'range' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'previous_range' => $previousRange,
            'kpis' => [
                'orders_count' => $ordersCount,
                'revenue' => number_format($revenue, 2, '.', ''),
                'avg_ticket' => number_format($avgTicket, 2, '.', ''),
                'active_stores' => $clientsCurrent,
                'total_active_stores' => $totalActiveStores,
                'clients_current' => $clientsCurrent.'/'.$totalActiveStores,
                'clients_previous' => $clientsPrevious,
                'orders_growth_pct' => $this->growthPercent($ordersCount, $prevOrdersCount),
                'revenue_growth_pct' => $this->growthPercent($revenue, $prevRevenue),
                'avg_ticket_growth_pct' => $this->growthPercent($avgTicket, $prevAvgTicket),
                'clients_growth_pct' => $this->growthPercent($clientsCurrent, $clientsPrevious),
                'catalog_coverage_pct' => round($catalogCoverage, 2),
                'products_sold' => $productsSold,
                'products_active' => $activeProducts,
                'unsold_stores_count' => count($unsoldStores),
                'status_ratio' => [
                    'pending' => [
                        'count' => $pendingCount,
                        'pct' => round(($pendingCount / $statusBase) * 100, 2),
                    ],
                    'confirmed' => [
                        'count' => $confirmedCount,
                        'pct' => round(($confirmedCount / $statusBase) * 100, 2),
                    ],
                    'delivered' => [
                        'count' => $deliveredCount,
                        'pct' => round(($deliveredCount / $statusBase) * 100, 2),
                    ],
                ],
            ],
            'top_products' => $topProducts,
            'top_stores' => $topStores,
            'sales_by_category' => $salesByCategory,
            'monthly_sales' => $monthlySales,
            'unsold_stores' => $unsoldStores,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, phone_number: string|null, phone_display: string|null}>
     */
    public function unsoldStores(int $supplierId, string $fromDate, string $toDate): array
    {
        $soldStoreIds = DB::table('orders')
            ->where('supplier_id', $supplierId)
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->pluck('store_id')
            ->unique()
            ->values()
            ->all();

        return DB::table('stores')
            ->where('active', true)
            ->whereNotIn('id', $soldStoreIds)
            ->orderBy('name')
            ->get(['id', 'name', 'phone_number'])
            ->map(fn ($store) => [
                'id' => (int) $store->id,
                'name' => $store->name,
                'phone_number' => $store->phone_number,
                'phone_display' => \App\Support\PhoneNumber::formatDisplay($store->phone_number),
            ])
            ->all();
    }

    /**
     * @return array{orders_count: int, revenue: float, clients_count: int}
     */
    private function aggregatePeriod(int $supplierId, string $fromDate, string $toDate): array
    {
        $aggregate = DB::table('orders')
            ->where('supplier_id', $supplierId)
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as revenue, COUNT(DISTINCT store_id) as clients_count')
            ->first();

        return [
            'orders_count' => (int) ($aggregate->orders_count ?? 0),
            'revenue' => (float) ($aggregate->revenue ?? 0),
            'clients_count' => (int) ($aggregate->clients_count ?? 0),
        ];
    }

    /**
     * @return array{from: string, to: string}
     */
    private function previousRange(string $fromDate, string $toDate): array
    {
        $from = CarbonImmutable::parse($fromDate);
        $to = CarbonImmutable::parse($toDate);
        $days = $from->diffInDays($to) + 1;

        return [
            'from' => $from->subDays($days)->toDateString(),
            'to' => $from->subDay()->toDateString(),
        ];
    }

    /**
     * @return array<int, array{month: string, sales: string, orders_count: int}>
     */
    private function monthlySales(int $supplierId, int $months, string $toDate): array
    {
        $to = CarbonImmutable::parse($toDate)->endOfMonth();
        $start = $to->subMonths($months - 1)->startOfMonth();
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'pgsql'
            ? "TO_CHAR(created_at, 'YYYY-MM')"
            : "strftime('%Y-%m', created_at)";

        $rawRows = DB::table('orders')
            ->where('supplier_id', $supplierId)
            ->whereBetween(DB::raw('DATE(created_at)'), [$start->toDateString(), $to->toDateString()])
            ->selectRaw("{$monthExpr} as month, COALESCE(SUM(total), 0) as sales, COUNT(*) as orders_count")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $series = [];
        for ($cursor = $start; $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addMonth()) {
            $monthKey = $cursor->format('Y-m');
            $row = $rawRows->get($monthKey);
            $series[] = [
                'month' => $monthKey,
                'sales' => number_format((float) ($row->sales ?? 0), 2, '.', ''),
                'orders_count' => (int) ($row->orders_count ?? 0),
            ];
        }

        return $series;
    }

    private function growthPercent(float|int $current, float|int $previous): ?float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}


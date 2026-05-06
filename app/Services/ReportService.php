<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ReportService
{
    public function clearCache(Carbon $startDate, Carbon $endDate): void
    {
        $s = $startDate->format('Ymd');
        $e = $endDate->format('Ymd');

        Cache::forget("report_customer_summary_{$s}_{$e}");
        Cache::forget("report_product_summary_{$s}_{$e}");
        Cache::forget("report_customer_nominal_{$s}_{$e}");
    }

    /**
     * Per-customer transaction summary:
     * invoice count, total qty, total nominal, last purchase date.
     */
    public function getCustomerSummary(Carbon $startDate, Carbon $endDate, ?string $search = null): Collection
    {
        $cacheKey = "report_customer_summary_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        $rows = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($startDate, $endDate) {
            return Sale::query()
                ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
                ->leftJoin('sale_items', 'sale_items.sale_id', '=', 'sales.id')
                ->whereBetween('sales.sale_date', [$startDate, $endDate])
                ->where('sales.status', 'completed')
                ->selectRaw("
                    COALESCE(customers.id, 0) as customer_id,
                    COALESCE(customers.name, 'Walk-in') as customer_name,
                    COALESCE(customers.phone, '-') as customer_phone,
                    COUNT(DISTINCT sales.id) as invoice_count,
                    COALESCE(SUM(sale_items.quantity), 0) as total_qty,
                    COALESCE(SUM(DISTINCT sales.total), 0) as total_nominal,
                    MAX(sales.sale_date) as last_purchase
                ")
                ->groupBy('customers.id', 'customers.name', 'customers.phone')
                ->orderByDesc('total_nominal')
                ->get();
        });

        if ($search) {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r->customer_name), $needle)
                || str_contains(mb_strtolower($r->customer_phone ?? ''), $needle));
        }

        return $rows->values();
    }

    /**
     * Per-product sales summary:
     * qty sold, revenue, profit, current stock.
     */
    public function getProductSummary(Carbon $startDate, Carbon $endDate, ?string $search = null): Collection
    {
        $cacheKey = "report_product_summary_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        $rows = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($startDate, $endDate) {
            return SaleItem::query()
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->whereBetween('sales.sale_date', [$startDate, $endDate])
                ->where('sales.status', 'completed')
                ->selectRaw('
                    products.id as product_id,
                    products.sku as sku,
                    products.name as product_name,
                    products.quantity as current_stock,
                    SUM(sale_items.quantity) as total_qty,
                    SUM(sale_items.subtotal) as total_revenue,
                    SUM(sale_items.subtotal - (sale_items.quantity * sale_items.cost_price)) as total_profit
                ')
                ->groupBy('products.id', 'products.sku', 'products.name', 'products.quantity')
                ->orderByDesc('total_revenue')
                ->get();
        });

        if ($search) {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r->product_name), $needle)
                || str_contains(mb_strtolower($r->sku ?? ''), $needle));
        }

        return $rows->values();
    }

    /**
     * Customer nominal contribution: total spent + percentage share.
     */
    public function getCustomerNominal(Carbon $startDate, Carbon $endDate, ?string $search = null): Collection
    {
        $cacheKey = "report_customer_nominal_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        $rows = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($startDate, $endDate) {
            return Sale::query()
                ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
                ->whereBetween('sales.sale_date', [$startDate, $endDate])
                ->where('sales.status', 'completed')
                ->selectRaw("
                    COALESCE(customers.id, 0) as customer_id,
                    COALESCE(customers.name, 'Walk-in') as customer_name,
                    COALESCE(customers.phone, '-') as customer_phone,
                    COUNT(sales.id) as invoice_count,
                    SUM(sales.total) as total_spent
                ")
                ->groupBy('customers.id', 'customers.name', 'customers.phone')
                ->orderByDesc('total_spent')
                ->get();
        });

        $grandTotal = (float) $rows->sum('total_spent');

        $rows = $rows->map(function ($r) use ($grandTotal) {
            $r->percentage = $grandTotal > 0 ? round(((float) $r->total_spent / $grandTotal) * 100, 2) : 0;
            return $r;
        });

        if ($search) {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r->customer_name), $needle)
                || str_contains(mb_strtolower($r->customer_phone ?? ''), $needle));
        }

        return $rows->values();
    }
}

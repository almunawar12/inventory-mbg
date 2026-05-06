<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Sale;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\PurchaseItem;
use App\Enums\DatePeriod;
use Illuminate\Support\Facades\DB;
use App\Models\FinanceTransaction;
use Illuminate\Support\Facades\Cache;

class DashboardStatsService
{
    public function clearCache(Carbon $startDate, Carbon $endDate, string $periodKey): void
    {
        $s = $startDate->format('Ymd');
        $e = $endDate->format('Ymd');

        Cache::forget("dashboard_sales_{$periodKey}_{$s}_{$e}");
        Cache::forget("dashboard_cashflow_{$periodKey}_{$s}_{$e}");
        Cache::forget("dashboard_sales_trend_{$s}_{$e}");
        Cache::forget("dashboard_sales_trend_{$s}_{$e}_hourly");
        Cache::forget("dashboard_cashflow_trend_{$s}_{$e}");
        Cache::forget("dashboard_expense_breakdown_{$s}_{$e}");
        Cache::forget("dashboard_top_products_{$s}_{$e}");
        Cache::forget("dashboard_top_customers_{$s}_{$e}");
        Cache::forget('dashboard_low_stock');
        Cache::forget('dashboard_stock');
        Cache::forget('dashboard_recent_sales');
    }

    /**
     * Get Sales Statistics (Total Revenue, Net Profit, Count)
     */
    public function getSalesStats(Carbon $startDate, Carbon $endDate, string $periodKey): array
    {
        $cacheKey = "dashboard_sales_{$periodKey}_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($startDate, $endDate) {
            // Optimize: Use aggregate queries instead of loading all models into memory
            $salesData = Sale::whereBetween('sale_date', [$startDate, $endDate])
                ->where('status', 'completed')
                ->selectRaw('COUNT(*) as count, SUM(total) as total_revenue')
                ->first();

            $totalRevenue = $salesData->total_revenue ?? 0;
            $count = $salesData->count ?? 0;

            // COGS = Sum of (Sales Item Quantity * Recorded Cost Price)
            // Using `cost_price` from sale_items ensures we use the HPP at the time of sale.
            $cogs = SaleItem::whereHas('sale', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('sale_date', [$startDate, $endDate])
                          ->where('status', 'completed');
                })
                ->sum(DB::raw('quantity * cost_price'));

            $grossProfit = $totalRevenue - $cogs;

            return [
                'total_revenue' => (float) $totalRevenue,
                'count' => (int) $count,
                'gross_profit' => (float) $grossProfit,
            ];
        });
    }

    /**
     * Get Cash Flow Statistics (Income, Expense, Net)
     */
    public function getCashFlowStats(Carbon $startDate, Carbon $endDate, string $periodKey): array
    {
        $cacheKey = "dashboard_cashflow_{$periodKey}_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($startDate, $endDate) {
            // Optimize: Calculate Income and Expense directly in the DB using joins
            $totals = FinanceTransaction::join('finance_categories', 'finance_transactions.finance_category_id', '=', 'finance_categories.id')
                ->whereBetween('finance_transactions.transaction_date', [$startDate, $endDate])
                ->selectRaw('finance_categories.type, SUM(finance_transactions.amount) as total')
                ->groupBy('finance_categories.type')
                ->pluck('total', 'finance_categories.type');

            $income = (float) ($totals['income'] ?? 0);
            $expense = (float) ($totals['expense'] ?? 0);

            return [
                'income' => $income,
                'expense' => $expense,
                'net_cash_flow' => $income - $expense,
            ];
        });
    }

    /**
     * Get Low Stock Products
     */
    

    public function getLowStockProducts(): array
    {
        // Cache for 5 minutes as stock levels change frequently.
        return Cache::remember('dashboard_low_stock', now()->addMinutes(5), function () {
            return Product::whereColumn('quantity', '<=', 'min_stock')
                ->where('is_active', true)
                ->orderBy('quantity', 'asc')
                ->get()
                ->toArray();
        });
    }

    public function getStockProducts(): array
    {
        // Cache for 5 minutes as stock levels change frequently.
        return Cache::remember('dashboard_stock', now()->addMinutes(5), function () {
            return Product::select('id')
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        });
    }

    

     public function getQtySuppliers(): array
    {
        // Cache for 5 minutes as stock levels change frequently.
        return Cache::remember('dashboard_stock', now()->addMinutes(5), function () {
            return Supplier::select('id')
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        });
    }



    /**
     * Get Top Selling Products
     */
    public function getTopProducts(Carbon $startDate, Carbon $endDate, int $limit = 5): array
    {
         $cacheKey = "dashboard_top_products_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

         return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($startDate, $endDate, $limit) {
            return SaleItem::select('product_id', DB::raw('SUM(quantity) as total_qty'))
                ->whereHas('sale', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('sale_date', [$startDate, $endDate])
                          ->where('status', 'completed');
                })
                ->with('product:id,name,sku')
                ->groupBy('product_id')
                ->orderByDesc('total_qty')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                     return [
                         'product_name' => $item->product->name,
                         'sku' => $item->product->sku,
                         'total_sold' => $item->total_qty
                     ];
                })
                ->toArray();
         });
    }

    

   
    

    /**
     * Get Recent Sales
     */
    public function getRecentSales(): array
    {
        return Cache::remember('dashboard_recent_sales', now()->addMinutes(1), function ()  {
            return Sale::with('customer:id,name')
                ->orderByDesc('sale_date')
                ->get()
                ->toArray();
        });
    }

    public function getSalesTrend(Carbon $startDate, Carbon $endDate, bool $hourly = false): array
    {
        $cacheKey = "dashboard_sales_trend_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}" . ($hourly ? '_hourly' : '');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($startDate, $endDate, $hourly) {
            if ($hourly) {
                $data = Sale::selectRaw("HOUR(CONVERT_TZ(sale_date, '+00:00', '+07:00')) as hour, SUM(total) as total")
                    ->whereBetween('sale_date', [$startDate, $endDate])
                    ->where('status', 'completed')
                    ->groupBy('hour')
                    ->orderBy('hour')
                    ->get()
                    ->pluck('total', 'hour')
                    ->toArray();

                $chartData = [];
                for ($h = 0; $h <= 23; $h++) {
                    $label = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                    $chartData[$label] = (float) ($data[$h] ?? 0);
                }

                return $chartData;
            }

            $data = Sale::selectRaw('DATE(sale_date) as date, SUM(total) as total')
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->where('status', 'completed')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('total', 'date')
                ->toArray();

            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            $chartData = [];

            foreach ($period as $date) {
                $formattedDate = $date->format('Y-m-d');
                $chartData[$formattedDate] = (float) ($data[$formattedDate] ?? 0);
            }

            return $chartData;
        });
    }

     

       
        
    /**
     * Get per-product stock comparison across last N days.
     * Derives historical stock from current quantity and reverses sale/purchase
     * transactions that occurred after the end of each target day.
     */
    public function getStockComparison(int $productId, int $days = 2): array
    {
        $days = max(2, min(30, $days));
        $today = Carbon::now()->startOfDay();
        $cacheKey = "dashboard_stock_compare_{$productId}_{$days}_{$today->format('Ymd')}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($productId, $days, $today) {
            $product = Product::find($productId);
            if (!$product) {
                return ['labels' => [], 'data' => [], 'product_name' => null];
            }

            $currentQty = (int) $product->quantity;
            $labels = [];
            $data = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                $day = $today->copy()->subDays($i);
                $endOfDay = $day->copy()->endOfDay();

                $salesAfter = (int) SaleItem::where('product_id', $productId)
                    ->whereHas('sale', function ($q) use ($endOfDay) {
                        $q->where('status', 'completed')
                          ->where('sale_date', '>', $endOfDay);
                    })
                    ->sum('quantity');

                $purchasesAfter = (int) PurchaseItem::where('product_id', $productId)
                    ->whereHas('purchase', function ($q) use ($endOfDay) {
                        $q->whereIn('status', ['received', 'paid'])
                          ->where('purchase_date', '>', $endOfDay);
                    })
                    ->sum('quantity');

                $labels[] = $day->format('Y-m-d');
                $data[] = $currentQty + $salesAfter - $purchasesAfter;
            }

            return [
                'labels' => $labels,
                'data' => $data,
                'product_name' => $product->name,
            ];
        });
    }

    /**
     * Get Cash Flow Chart Data (Income vs Expense)
     */
    public function getCashFlowTrend(Carbon $startDate, Carbon $endDate): array
    {
         $cacheKey = "dashboard_cashflow_trend_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

         return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($startDate, $endDate) {
            // Optimize: Group by date and type at the database level instead of memory
            $transactions = FinanceTransaction::join('finance_categories', 'finance_transactions.finance_category_id', '=', 'finance_categories.id')
                ->whereBetween('finance_transactions.transaction_date', [$startDate, $endDate])
                ->selectRaw('DATE(finance_transactions.transaction_date) as date, finance_categories.type, SUM(finance_transactions.amount) as total')
                ->groupBy('date', 'finance_categories.type')
                ->get();

            // Structure data
            $grouped = [];
            foreach ($transactions as $t) {
                $grouped[$t->date][$t->type] = $t->total;
            }

            // Fill missing dates
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
            $incomeData = [];
            $expenseData = [];

            foreach ($period as $date) {
                $formattedDate = $date->format('Y-m-d');
                $incomeData[$formattedDate] = (float) ($grouped[$formattedDate]['income'] ?? 0);
                $expenseData[$formattedDate] = (float) ($grouped[$formattedDate]['expense'] ?? 0);
            }

            return [
                'income' => $incomeData,
                'expense' => $expenseData,
            ];
         });
    }

    /**
     * Get Top Customers by Revenue
     */
    public function getTopCustomers(Carbon $startDate, Carbon $endDate, int $limit = 5): array
    {
         $cacheKey = "dashboard_top_customers_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

         return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($startDate, $endDate, $limit) {
            return Sale::select('customer_id', DB::raw('SUM(total) as total_spent'))
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->where('status', 'completed')
                ->whereNotNull('customer_id')
                ->with('customer:id,name,phone')
                ->groupBy('customer_id')
                ->orderByDesc('total_spent')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                     return [
                         'customer_name' => $item->customer->name ?? 'Unknown',
                         'phone' => $item->customer->phone ?? '-',
                         'total_spent' => $item->total_spent
                     ];
                })
                ->toArray();
         });
    }

    /**
     * Get Expense Breakdown by Category
     */
    public function getExpenseBreakdown(Carbon $startDate, Carbon $endDate): array
    {
         $cacheKey = "dashboard_expense_breakdown_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}";

         return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($startDate, $endDate) {
            return FinanceTransaction::select('finance_category_id', DB::raw('SUM(amount) as total_amount'))
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->whereHas('category', function ($query) {
                    $query->where('type', 'expense');
                })
                ->with('category:id,name,type')
                ->groupBy('finance_category_id')
                ->orderByDesc('total_amount')
                ->get()
                ->map(function ($item) {
                     return [
                         'category_name' => $item->category->name ?? 'Uncategorized',
                         'total_amount' => $item->total_amount
                     ];
                })
                ->toArray();
         });
    }
}

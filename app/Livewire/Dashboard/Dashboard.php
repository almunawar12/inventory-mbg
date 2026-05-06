<?php

namespace App\Livewire\Dashboard;

use Carbon\Carbon;
use Livewire\Component;
use App\Enums\DatePeriod;
use App\Services\ProductService;
use App\Services\DashboardStatsService;
use App\Models\Product;

class Dashboard extends Component
{
    public string $dateFilter = DatePeriod::TODAY->value;
    public ?string $customStartDate = null;
    public ?string $customEndDate = null;

    public array $stats = [];
    public array $lowStockProducts = [];
    public array $recentSales = [];
    public array $stockProducts = [];
    public array $qtySuppliers = [];
    public array $topProducts = [];
    public array $topCustomers = [];
    public array $minimProducts = [];
    public array $productOptions = [];

    // Stock comparison chart (per product)
    public ?int $selectedProductId = null;
    public int $compareDays = 2;
    public array $stockCompareChart = [];

    // Charts Data
    public array $salesChart = [];
    public array $cashFlowChart = [];
    public array $expenseChart = [];

    public function mount(DashboardStatsService $service)
    {
        $this->productOptions = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->toArray();

        $this->loadStats($service);
        $this->stockCompareChart = ['labels' => [], 'data' => [], 'product_name' => null];
    }

    public function updatedSelectedProductId()
    {
        $this->loadStockCompare(app(DashboardStatsService::class));
    }

    public function updatedCompareDays()
    {
        $this->loadStockCompare(app(DashboardStatsService::class));
    }

    protected function loadStockCompare(DashboardStatsService $service): void
    {
        if (!$this->selectedProductId) {
            $this->stockCompareChart = ['labels' => [], 'data' => [], 'product_name' => null];
            return;
        }
        $this->stockCompareChart = $service->getStockComparison(
            (int) $this->selectedProductId,
            (int) $this->compareDays
        );
        $this->dispatch('stock-compare-updated', $this->stockCompareChart);
    }

    public function refresh()
    {
        $service = app(DashboardStatsService::class);
        [$startDate, $endDate] = $this->getDateRange();
        $service->clearCache($startDate, $endDate, $this->dateFilter);
        $this->loadStats($service);
    }

    public function updatedDateFilter()
    {
        if ($this->dateFilter !== DatePeriod::CUSTOM->value) {
            $service = app(DashboardStatsService::class);
            [$startDate, $endDate] = $this->getDateRange();
            $service->clearCache($startDate, $endDate, $this->dateFilter);
            $this->loadStats($service);
        }
    }

    public function updateCustomRange($startDate, $endDate)
    {
        $this->customStartDate = $startDate;
        $this->customEndDate = $endDate;

        if ($this->dateFilter === DatePeriod::CUSTOM->value) {
            $this->loadStats(app(DashboardStatsService::class));
        }
    }

   

  



    public function loadStats(DashboardStatsService $service)
    {
        [$startDate, $endDate] = $this->getDateRange();

        // 1. Sales Stats
        $salesStats = $service->getSalesStats($startDate, $endDate, $this->dateFilter);

        // 2. Cash Flow Stats
        $cashFlowStats = $service->getCashFlowStats($startDate, $endDate, $this->dateFilter);
        
      
        $this->stats = [
            'total_sales' => $salesStats['total_revenue'],
            'sales_count' => $salesStats['count'],
            'gross_profit' => $salesStats['gross_profit'],
            'income' => $cashFlowStats['income'],
            'expense' => $cashFlowStats['expense'],
            'net_cash_flow' => $cashFlowStats['net_cash_flow'],
        ];

        // 3. Lists
        $this->stockProducts = $service->getStockProducts(5);
        $this->qtySuppliers = $service->getQtySuppliers(5);
        $this->lowStockProducts = $service->getLowStockProducts(10);
        $this->topProducts = $service->getTopProducts($startDate, $endDate, 5);
        $this->recentSales = $service->getRecentSales(5);
        $this->topCustomers = $service->getTopCustomers($startDate, $endDate, 5);

        // 4. Prepare Chart Data
        $hourly = in_array($this->dateFilter, [DatePeriod::TODAY->value, DatePeriod::YESTERDAY->value]);
        $salesTrend = $service->getSalesTrend($startDate, $endDate, $hourly);

        $this->salesChart = [
            'labels' => array_keys($salesTrend),
            'data' => array_values($salesTrend),
            'hourly' => $hourly,
        ];

        $cashFlowTrend = $service->getCashFlowTrend($startDate, $endDate);

        $this->cashFlowChart = [
            'labels' => array_keys($cashFlowTrend['income']),
            'income' => array_values($cashFlowTrend['income']),
            'expense' => array_values($cashFlowTrend['expense']),
        ];

        $expenseBreakdown = $service->getExpenseBreakdown($startDate, $endDate);
        $this->expenseChart = [
            'labels' => array_column($expenseBreakdown, 'category_name'),
            'series' => array_column($expenseBreakdown, 'total_amount'),
        ];

        $this->dispatch('stats-updated', [
            'sales' => $this->salesChart,
            'cashFlow' => $this->cashFlowChart,
            'expense' => $this->expenseChart,
        ]);
    }

    protected function getDateRange(): array
    {
        $now = Carbon::now('Asia/Jakarta');

        return match(DatePeriod::tryFrom($this->dateFilter)) {
            DatePeriod::TODAY => [$now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc()],
            DatePeriod::YESTERDAY => [$now->copy()->subDay()->startOfDay()->utc(), $now->copy()->subDay()->endOfDay()->utc()],
            DatePeriod::THIS_WEEK => [$now->copy()->startOfWeek()->utc(), $now->copy()->endOfWeek()->utc()],
            DatePeriod::THIS_MONTH => [$now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc()],
            DatePeriod::LAST_MONTH => [$now->copy()->subMonth()->startOfMonth()->utc(), $now->copy()->subMonth()->endOfMonth()->utc()],
            DatePeriod::CUSTOM => [
                Carbon::parse($this->customStartDate, 'Asia/Jakarta')->startOfDay()->utc(),
                Carbon::parse($this->customEndDate, 'Asia/Jakarta')->endOfDay()->utc()
            ],
            default => [$now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc()],
        };
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard');
    }
}

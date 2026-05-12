# Stock Movements History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add super-admin "Riwayat Stok" report with a global IN/OUT movement log and a per-product stock card, both filterable and exportable to Excel/PDF.

**Architecture:** Read-only reporting over existing tables (`purchases`, `purchase_items`, `sales`, `sale_items`, `sale_returns`, `sale_return_items`). A single `StockMovementService` unions three legs (purchase received/paid, sale completed, sale return) into a uniform movement row shape. Two Livewire 3 components (`StockMovementReport`, `ProductStockCard`) consume the service, mirror the existing `ProductReport` pattern, and reuse the shared `filter-bar` partial.

**Tech Stack:** Laravel 11, Livewire 3, Maatwebsite Excel, barryvdh/laravel-dompdf, TailwindCSS, Pest/PHPUnit.

**Spec:** `docs/superpowers/specs/2026-05-12-stock-movements-history-design.md`

---

## File Structure

**Create:**
- `app/Services/StockMovementService.php` — UNION query builder, totals, per-product ledger.
- `app/Livewire/Reports/StockMovementReport.php` — global log component.
- `app/Livewire/Reports/ProductStockCard.php` — per-product ledger component.
- `app/Exports/StockMovementExport.php` — Maatwebsite export for the global log.
- `app/Exports/ProductStockCardExport.php` — Maatwebsite export for the product ledger.
- `resources/views/livewire/reports/stock-movement-report.blade.php`
- `resources/views/livewire/reports/product-stock-card.blade.php`
- `resources/views/exports/pdf/stock-movement.blade.php`
- `resources/views/exports/pdf/product-stock-card.blade.php`
- `tests/Feature/Reports/StockMovementReportTest.php`
- `tests/Feature/Reports/ProductStockCardTest.php`
- `tests/Unit/Services/StockMovementServiceTest.php`

**Modify:**
- `routes/web.php` — add two routes inside the existing super-admin `reports` group.
- `resources/views/layouts/navigation.blade.php` — add nav entry to Reports dropdown and mobile accordion.

---

## Task 1: `StockMovementService` query shape

**Files:**
- Create: `app/Services/StockMovementService.php`
- Test: `tests/Unit/Services/StockMovementServiceTest.php`

- [ ] **Step 1: Write the failing test for query row shape and source filtering**

Create `tests/Unit/Services/StockMovementServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\Sale;
use App\Models\User;
use App\Models\Unit;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SaleItem;
use App\Models\Purchase;
use App\Models\SaleReturn;
use App\Models\PurchaseItem;
use App\Models\SaleReturnItem;
use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $stock = 0): Product
    {
        return Product::factory()->create([
            'quantity'       => $stock,
            'purchase_price' => 5000,
            'selling_price'  => 10000,
            'category_id'    => Category::factory()->create()->id,
            'unit_id'        => Unit::factory()->create()->id,
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_query_returns_three_legs_with_uniform_shape(): void
    {
        $user     = $this->makeUser();
        $product  = $this->makeProduct(stock: 50);
        $supplier = Supplier::factory()->create();

        $purchase = Purchase::create([
            'invoice_number' => 'PO-001',
            'supplier_id'    => $supplier->id,
            'purchase_date'  => '2026-05-01',
            'total'          => 50000,
            'status'         => PurchaseStatus::RECEIVED,
            'created_by'     => $user->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id'  => $product->id,
            'quantity'    => 10,
            'unit_price'  => 5000,
            'subtotal'    => 50000,
        ]);

        $sale = Sale::create([
            'invoice_number'  => 'SO-001',
            'created_by'      => $user->id,
            'sale_date'       => '2026-05-05',
            'status'          => SaleStatus::COMPLETED,
            'subtotal'        => 30000,
            'total_discount'  => 0,
            'total'           => 30000,
            'cash_received'   => 30000,
            'change'          => 0,
            'payment_method'  => PaymentMethod::CASH,
        ]);
        $saleItem = SaleItem::create([
            'sale_id'     => $sale->id,
            'product_id'  => $product->id,
            'quantity'    => 3,
            'cost_price'  => 5000,
            'unit_price'  => 10000,
            'discount'    => 0,
            'final_price' => 10000,
            'subtotal'    => 30000,
        ]);

        $return = SaleReturn::create([
            'return_number' => 'RT-001',
            'sale_id'       => $sale->id,
            'created_by'    => $user->id,
            'return_date'   => '2026-05-07',
            'total_refund'  => 10000,
        ]);
        SaleReturnItem::create([
            'sale_return_id' => $return->id,
            'sale_item_id'   => $saleItem->id,
            'product_id'     => $product->id,
            'quantity'       => 1,
            'unit_price'     => 10000,
            'subtotal'       => 10000,
        ]);

        $rows = (new StockMovementService())->collect([
            'start'  => Carbon::parse('2026-04-01')->startOfDay(),
            'end'    => Carbon::parse('2026-05-31')->endOfDay(),
            'type'   => 'all',
            'source' => 'all',
        ]);

        $this->assertCount(3, $rows);

        $sources = $rows->pluck('source')->sort()->values()->all();
        $this->assertSame(['purchase', 'return', 'sale'], $sources);

        $first = $rows->first();
        foreach (['occurred_at', 'type', 'source', 'reference_id', 'reference_no',
                  'product_id', 'sku', 'product_name', 'qty', 'unit_price'] as $col) {
            $this->assertTrue(property_exists($first, $col), "missing column {$col}");
        }
    }
}
```

- [ ] **Step 2: Run the test, verify it fails**

Run: `vendor/bin/phpunit --filter test_query_returns_three_legs_with_uniform_shape`
Expected: FAIL with "Class App\Services\StockMovementService not found".

- [ ] **Step 3: Implement `StockMovementService`**

Create `app/Services/StockMovementService.php`:

```php
<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StockMovementService
{
    /**
     * Build the unioned movements query, filtered.
     *
     * @param array $filters {
     *     start: CarbonInterface,
     *     end: CarbonInterface,
     *     type: 'all'|'in'|'out',
     *     source: 'all'|'purchase'|'sale'|'return',
     *     product_search?: ?string,
     *     product_id?: ?int,
     * }
     */
    public function query(array $filters): Builder
    {
        $start = $filters['start'];
        $end   = $filters['end'];
        $productId = $filters['product_id'] ?? null;

        $purchases = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->whereIn('purchases.status', ['received', 'paid'])
            ->whereBetween('purchases.purchase_date', [$start, $end])
            ->when($productId, fn ($q, $id) => $q->where('purchase_items.product_id', $id))
            ->selectRaw("
                purchases.purchase_date           as occurred_at,
                'IN'                              as type,
                'purchase'                        as source,
                purchases.id                      as reference_id,
                COALESCE(purchases.invoice_number, CONCAT('PO#', purchases.id)) as reference_no,
                products.id                       as product_id,
                products.sku                      as sku,
                products.name                     as product_name,
                purchase_items.quantity           as qty,
                purchase_items.unit_price         as unit_price,
                purchase_items.subtotal           as line_total,
                purchase_items.id                 as item_id
            ");

        $sales = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->when($productId, fn ($q, $id) => $q->where('sale_items.product_id', $id))
            ->selectRaw("
                sales.sale_date                   as occurred_at,
                'OUT'                             as type,
                'sale'                            as source,
                sales.id                          as reference_id,
                sales.invoice_number              as reference_no,
                products.id                       as product_id,
                products.sku                      as sku,
                products.name                     as product_name,
                sale_items.quantity               as qty,
                sale_items.final_price            as unit_price,
                sale_items.subtotal               as line_total,
                sale_items.id                     as item_id
            ");

        $returns = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
            ->join('products', 'sale_return_items.product_id', '=', 'products.id')
            ->whereBetween('sale_returns.return_date', [$start, $end])
            ->when($productId, fn ($q, $id) => $q->where('sale_return_items.product_id', $id))
            ->selectRaw("
                sale_returns.return_date          as occurred_at,
                'IN'                              as type,
                'return'                          as source,
                sale_returns.id                   as reference_id,
                sale_returns.return_number        as reference_no,
                products.id                       as product_id,
                products.sku                      as sku,
                products.name                     as product_name,
                sale_return_items.quantity        as qty,
                sale_return_items.unit_price      as unit_price,
                sale_return_items.subtotal        as line_total,
                sale_return_items.id              as item_id
            ");

        $union = $purchases->unionAll($sales)->unionAll($returns);

        $wrapped = DB::query()->fromSub($union, 'm');

        $type   = $filters['type']   ?? 'all';
        $source = $filters['source'] ?? 'all';
        $search = $filters['product_search'] ?? null;

        if ($type === 'in')  { $wrapped->where('m.type', 'IN'); }
        if ($type === 'out') { $wrapped->where('m.type', 'OUT'); }
        if (in_array($source, ['purchase', 'sale', 'return'], true)) {
            $wrapped->where('m.source', $source);
        }
        if ($search) {
            $needle = '%' . mb_strtolower($search) . '%';
            $wrapped->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(m.sku) LIKE ?', [$needle])
                  ->orWhereRaw('LOWER(m.product_name) LIKE ?', [$needle]);
            });
        }

        return $wrapped->orderByDesc('m.occurred_at')
            ->orderBy('m.source')
            ->orderBy('m.reference_id')
            ->orderBy('m.item_id');
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage);
    }

    public function collect(array $filters): Collection
    {
        return collect($this->query($filters)->get());
    }

    /**
     * @return array{in:int,out:int,net:int}
     */
    public function totals(array $filters): array
    {
        $row = $this->query($filters)
            ->reorder()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN m.type = 'IN'  THEN m.qty ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN m.type = 'OUT' THEN m.qty ELSE 0 END), 0) as total_out
            ")
            ->first();

        $in  = (int) ($row->total_in ?? 0);
        $out = (int) ($row->total_out ?? 0);

        return ['in' => $in, 'out' => $out, 'net' => $in - $out];
    }

    /**
     * Per-product ledger, ascending order, with opening + running balance.
     *
     * @return array{opening:int,rows:Collection,closing:int}
     */
    public function productLedger(int $productId, CarbonInterface $start, CarbonInterface $end): array
    {
        $rowsAsc = collect(
            $this->query([
                'start'      => $start,
                'end'        => $end,
                'type'       => 'all',
                'source'     => 'all',
                'product_id' => $productId,
            ])->reorder()
              ->orderBy('m.occurred_at')
              ->orderBy('m.source')
              ->orderBy('m.reference_id')
              ->orderBy('m.item_id')
              ->get()
        );

        $product = DB::table('products')->where('id', $productId)->first();
        $currentStock = (int) ($product->quantity ?? 0);

        $netInWindow = $rowsAsc->reduce(
            fn ($carry, $r) => $carry + ($r->type === 'IN' ? (int) $r->qty : -(int) $r->qty),
            0
        );

        $netAfterEnd = $this->netBetween($productId, $end->copy()->addSecond(), null);

        $opening = $currentStock - $netInWindow - $netAfterEnd;

        $running = $opening;
        $rowsAsc = $rowsAsc->map(function ($r) use (&$running) {
            $delta = $r->type === 'IN' ? (int) $r->qty : -(int) $r->qty;
            $running += $delta;
            $r->running_balance = $running;
            return $r;
        });

        return [
            'opening' => $opening,
            'rows'    => $rowsAsc,
            'closing' => $running,
        ];
    }

    private function netBetween(int $productId, CarbonInterface $start, ?CarbonInterface $end): int
    {
        $end = $end ?? now()->addCentury();

        $row = $this->query([
            'start'      => $start,
            'end'        => $end,
            'type'       => 'all',
            'source'     => 'all',
            'product_id' => $productId,
        ])->reorder()
          ->selectRaw("
            COALESCE(SUM(CASE WHEN m.type = 'IN'  THEN m.qty ELSE 0 END), 0) as in_qty,
            COALESCE(SUM(CASE WHEN m.type = 'OUT' THEN m.qty ELSE 0 END), 0) as out_qty
          ")
          ->first();

        return (int) ($row->in_qty ?? 0) - (int) ($row->out_qty ?? 0);
    }
}
```

- [ ] **Step 4: Run the unit test, verify it passes**

Run: `vendor/bin/phpunit --filter test_query_returns_three_legs_with_uniform_shape`
Expected: PASS.

- [ ] **Step 5: Add filter test cases**

Append to `tests/Unit/Services/StockMovementServiceTest.php` inside the class:

```php
public function test_type_filter_narrows_in_or_out(): void
{
    $this->seedThreeMovements();

    $svc = new StockMovementService();

    $window = ['start' => Carbon::parse('2026-04-01')->startOfDay(),
               'end'   => Carbon::parse('2026-05-31')->endOfDay(),
               'source'=> 'all'];

    $this->assertCount(2, $svc->collect($window + ['type' => 'in']));
    $this->assertCount(1, $svc->collect($window + ['type' => 'out']));
}

public function test_source_filter_narrows_by_origin(): void
{
    $this->seedThreeMovements();
    $svc = new StockMovementService();

    $window = ['start' => Carbon::parse('2026-04-01')->startOfDay(),
               'end'   => Carbon::parse('2026-05-31')->endOfDay(),
               'type'  => 'all'];

    $this->assertCount(1, $svc->collect($window + ['source' => 'purchase']));
    $this->assertCount(1, $svc->collect($window + ['source' => 'sale']));
    $this->assertCount(1, $svc->collect($window + ['source' => 'return']));
}

public function test_totals_in_minus_out(): void
{
    $this->seedThreeMovements();
    $svc = new StockMovementService();

    $totals = $svc->totals([
        'start'  => Carbon::parse('2026-04-01')->startOfDay(),
        'end'    => Carbon::parse('2026-05-31')->endOfDay(),
        'type'   => 'all',
        'source' => 'all',
    ]);

    $this->assertSame(11, $totals['in']);  // 10 purchase + 1 return
    $this->assertSame(3,  $totals['out']); // 3 sale
    $this->assertSame(8,  $totals['net']);
}

private function seedThreeMovements(): void
{
    $user     = $this->makeUser();
    $product  = $this->makeProduct(stock: 8); // 50 in - 3 out doesn't matter for these tests
    $supplier = Supplier::factory()->create();

    $purchase = Purchase::create([
        'invoice_number' => 'PO-002',
        'supplier_id'    => $supplier->id,
        'purchase_date'  => '2026-05-01',
        'total'          => 50000,
        'status'         => PurchaseStatus::RECEIVED,
        'created_by'     => $user->id,
    ]);
    PurchaseItem::create([
        'purchase_id' => $purchase->id, 'product_id' => $product->id,
        'quantity' => 10, 'unit_price' => 5000, 'subtotal' => 50000,
    ]);

    $sale = Sale::create([
        'invoice_number' => 'SO-002', 'created_by' => $user->id,
        'sale_date' => '2026-05-05', 'status' => SaleStatus::COMPLETED,
        'subtotal' => 30000, 'total_discount' => 0, 'total' => 30000,
        'cash_received' => 30000, 'change' => 0, 'payment_method' => PaymentMethod::CASH,
    ]);
    $saleItem = SaleItem::create([
        'sale_id' => $sale->id, 'product_id' => $product->id,
        'quantity' => 3, 'cost_price' => 5000, 'unit_price' => 10000,
        'discount' => 0, 'final_price' => 10000, 'subtotal' => 30000,
    ]);

    $return = SaleReturn::create([
        'return_number' => 'RT-002', 'sale_id' => $sale->id,
        'created_by' => $user->id, 'return_date' => '2026-05-07',
        'total_refund' => 10000,
    ]);
    SaleReturnItem::create([
        'sale_return_id' => $return->id, 'sale_item_id' => $saleItem->id,
        'product_id' => $product->id, 'quantity' => 1,
        'unit_price' => 10000, 'subtotal' => 10000,
    ]);
}
```

- [ ] **Step 6: Run all three tests, verify PASS**

Run: `vendor/bin/phpunit tests/Unit/Services/StockMovementServiceTest.php`
Expected: 3 tests pass.

- [ ] **Step 7: Add product ledger test**

Append:

```php
public function test_product_ledger_running_balance_anchors_to_current_stock(): void
{
    // After seed: 10 in - 3 out + 1 in = +8 net. Set current stock = 8.
    $this->seedThreeMovements();
    $product = Product::first();
    $product->update(['quantity' => 8]);

    $ledger = (new StockMovementService())->productLedger(
        $product->id,
        Carbon::parse('2026-04-01')->startOfDay(),
        Carbon::parse('2026-05-31')->endOfDay(),
    );

    $this->assertSame(0, $ledger['opening']);
    $this->assertSame(8, $ledger['closing']);
    $this->assertCount(3, $ledger['rows']);

    $first  = $ledger['rows']->first();
    $last   = $ledger['rows']->last();
    $this->assertSame(10, $first->running_balance);
    $this->assertSame(8,  $last->running_balance);
}
```

- [ ] **Step 8: Run ledger test, verify PASS**

Run: `vendor/bin/phpunit --filter test_product_ledger_running_balance_anchors_to_current_stock`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Services/StockMovementService.php tests/Unit/Services/StockMovementServiceTest.php
git commit -m "feat(reports): add StockMovementService with unioned movements query"
```

---

## Task 2: Routes + StockMovementReport Livewire

**Files:**
- Create: `app/Livewire/Reports/StockMovementReport.php`
- Create: `resources/views/livewire/reports/stock-movement-report.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Reports/StockMovementReportTest.php`

- [ ] **Step 1: Write the failing route guard test**

Create `tests/Feature/Reports/StockMovementReportTest.php`:

```php
<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StockMovementReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_is_forbidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('reports.stock-movements'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->get(route('reports.stock-movements'))
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run the route test, verify it fails**

Run: `vendor/bin/phpunit --filter test_non_super_admin_is_forbidden`
Expected: FAIL with "Route [reports.stock-movements] not defined".

- [ ] **Step 3: Add the routes**

Edit `routes/web.php`. Inside the existing `Route::prefix('reports')->name('reports.')->group(...)` (currently containing `customers`, `products`, `customer-nominal`), add:

```php
Route::get('stock-movements', \App\Livewire\Reports\StockMovementReport::class)
    ->name('stock-movements');
Route::get('stock-movements/products/{product}', \App\Livewire\Reports\ProductStockCard::class)
    ->name('stock-movements.product');
```

- [ ] **Step 4: Create the `StockMovementReport` Livewire component**

Create `app/Livewire/Reports/StockMovementReport.php`:

```php
<?php

namespace App\Livewire\Reports;

use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use App\Enums\DatePeriod;
use App\Services\StockMovementService;
use App\Exports\StockMovementExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class StockMovementReport extends Component
{
    use WithPagination;

    public string $dateFilter = DatePeriod::THIS_MONTH->value;
    public ?string $customStartDate = null;
    public ?string $customEndDate = null;
    public string $search = '';
    public string $type = 'all';
    public string $source = 'all';

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->customStartDate = Carbon::now('Asia/Jakarta')->startOfMonth()->format('Y-m-d');
        $this->customEndDate   = Carbon::now('Asia/Jakarta')->endOfMonth()->format('Y-m-d');
    }

    public function updatingDateFilter(): void { $this->resetPage(); }
    public function updatingType(): void      { $this->resetPage(); }
    public function updatingSource(): void    { $this->resetPage(); }
    public function updatingSearch(): void    { $this->resetPage(); }

    public function updateCustomRange(string $start, string $end): void
    {
        $this->customStartDate = $start;
        $this->customEndDate   = $end;
        $this->resetPage();
    }

    public function refresh(): void
    {
        $this->resetPage();
    }

    public function exportExcel()
    {
        [$start, $end] = $this->getDateRange();
        $rows = app(StockMovementService::class)->collect($this->filters($start, $end));
        $file = 'stock-movement-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.xlsx';

        return Excel::download(new StockMovementExport($rows, $start, $end), $file);
    }

    public function exportPdf()
    {
        [$start, $end] = $this->getDateRange();
        $svc    = app(StockMovementService::class);
        $rows   = $svc->collect($this->filters($start, $end));
        $totals = $svc->totals($this->filters($start, $end));

        $pdf = Pdf::loadView('exports.pdf.stock-movement', compact('rows', 'totals', 'start', 'end'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'stock-movement-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.pdf'
        );
    }

    protected function filters(Carbon $start, Carbon $end): array
    {
        return [
            'start'          => $start,
            'end'            => $end,
            'type'           => $this->type,
            'source'         => $this->source,
            'product_search' => $this->search ?: null,
        ];
    }

    protected function getDateRange(): array
    {
        $now = Carbon::now('Asia/Jakarta');

        return match (DatePeriod::tryFrom($this->dateFilter)) {
            DatePeriod::TODAY      => [$now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc()],
            DatePeriod::YESTERDAY  => [$now->copy()->subDay()->startOfDay()->utc(), $now->copy()->subDay()->endOfDay()->utc()],
            DatePeriod::THIS_WEEK  => [$now->copy()->startOfWeek()->utc(), $now->copy()->endOfWeek()->utc()],
            DatePeriod::THIS_MONTH => [$now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc()],
            DatePeriod::LAST_MONTH => [$now->copy()->subMonth()->startOfMonth()->utc(), $now->copy()->subMonth()->endOfMonth()->utc()],
            DatePeriod::CUSTOM     => [
                Carbon::parse($this->customStartDate, 'Asia/Jakarta')->startOfDay()->utc(),
                Carbon::parse($this->customEndDate,   'Asia/Jakarta')->endOfDay()->utc(),
            ],
            default                => [$now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc()],
        };
    }

    public function render()
    {
        [$start, $end] = $this->getDateRange();
        $svc     = app(StockMovementService::class);
        $filters = $this->filters($start, $end);

        $rows   = $svc->paginate($filters, 25);
        $totals = $svc->totals($filters);

        return view('livewire.reports.stock-movement-report', [
            'rows'      => $rows,
            'totals'    => $totals,
            'startDate' => $start,
            'endDate'   => $end,
        ]);
    }
}
```

- [ ] **Step 5: Create the blade view**

Create `resources/views/livewire/reports/stock-movement-report.blade.php`:

```blade
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
    @include('livewire.reports.partials.filter-bar', [
        'title' => 'Riwayat Stok',
        'subtitle' => 'Pergerakan barang masuk dan keluar.',
    ])

    <div class="flex flex-wrap gap-2 print:hidden">
        <select wire:model.live="type"
            class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm">
            <option value="all">Semua Jenis</option>
            <option value="in">Masuk</option>
            <option value="out">Keluar</option>
        </select>
        <select wire:model.live="source"
            class="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm">
            <option value="all">Semua Sumber</option>
            <option value="purchase">Pembelian</option>
            <option value="sale">Penjualan</option>
            <option value="return">Retur</option>
        </select>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border bg-green-200 p-4">
            <h3 class="text-sm font-medium">Total Masuk</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1">{{ number_format($totals['in']) }}</div>
        </div>
        <div class="rounded-xl border bg-red-200 p-4">
            <h3 class="text-sm font-medium">Total Keluar</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1">{{ number_format($totals['out']) }}</div>
        </div>
        <div class="rounded-xl border bg-purple-200 p-4">
            <h3 class="text-sm font-medium">Net</h3>
            <div class="text-xl sm:text-2xl font-bold mt-1 {{ $totals['net'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                {{ number_format($totals['net']) }}
            </div>
        </div>
    </div>

    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-muted/40">
            <h3 class="text-sm font-semibold">
                Periode: {{ \Carbon\Carbon::parse($startDate)->setTimezone('Asia/Jakarta')->format('d M Y') }} —
                {{ \Carbon\Carbon::parse($endDate)->setTimezone('Asia/Jakarta')->format('d M Y') }}
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Tanggal</th>
                        <th class="px-4 py-2 font-semibold">Jenis</th>
                        <th class="px-4 py-2 font-semibold">Sumber</th>
                        <th class="px-4 py-2 font-semibold">No. Ref</th>
                        <th class="px-4 py-2 font-semibold">SKU</th>
                        <th class="px-4 py-2 font-semibold">Produk</th>
                        <th class="px-4 py-2 font-semibold text-right">Qty</th>
                        <th class="px-4 py-2 font-semibold text-right">Harga</th>
                        <th class="px-4 py-2 font-semibold text-right">Total</th>
                        <th class="px-4 py-2 font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t hover:bg-muted/30">
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($row->occurred_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold
                                    {{ $row->type === 'IN' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $row->type === 'IN' ? 'Masuk' : 'Keluar' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 capitalize">{{ $row->source }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $row->reference_no }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $row->sku }}</td>
                            <td class="px-4 py-2 font-medium">{{ $row->product_name }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($row->qty) }}</td>
                            <td class="px-4 py-2 text-right">@money($row->unit_price)</td>
                            <td class="px-4 py-2 text-right font-semibold">@money($row->line_total)</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('reports.stock-movements.product', $row->product_id) }}"
                                   class="text-sky-600 hover:underline text-xs">Kartu Stok</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-muted-foreground">
                                Tidak ada pergerakan stok pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t">
            {{ $rows->links() }}
        </div>
    </div>
</div>
```

Note: `StockMovementExport` and `Pdf::loadView('exports.pdf.stock-movement', ...)` are referenced from the component but not yet implemented. The view will still render and pagination will work — the export buttons will error until Task 4 and Task 5 land. Skip clicking them in manual checks until then.

- [ ] **Step 6: Create a placeholder `ProductStockCard` class so the route doesn't 500**

The route `reports.stock-movements.product` references `App\Livewire\Reports\ProductStockCard`. Add a minimal stub now; Task 3 fleshes it out.

Create `app/Livewire/Reports/ProductStockCard.php`:

```php
<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use App\Models\Product;

class ProductStockCard extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    public function render()
    {
        return view('livewire.reports.product-stock-card', [
            'product' => $this->product,
        ]);
    }
}
```

Create matching stub `resources/views/livewire/reports/product-stock-card.blade.php`:

```blade
<div class="max-w-7xl mx-auto px-4 py-6">
    <h1 class="text-lg font-semibold">Kartu Stok — {{ $product->name }}</h1>
    <p class="text-sm text-muted-foreground">Coming soon.</p>
</div>
```

- [ ] **Step 7: Run the route guard tests, verify PASS**

Run: `vendor/bin/phpunit tests/Feature/Reports/StockMovementReportTest.php`
Expected: 2 tests pass.

- [ ] **Step 8: Commit**

```bash
git add routes/web.php app/Livewire/Reports/StockMovementReport.php app/Livewire/Reports/ProductStockCard.php resources/views/livewire/reports/stock-movement-report.blade.php resources/views/livewire/reports/product-stock-card.blade.php tests/Feature/Reports/StockMovementReportTest.php
git commit -m "feat(reports): add stock movement report Livewire + routes"
```

---

## Task 3: ProductStockCard ledger view

**Files:**
- Modify: `app/Livewire/Reports/ProductStockCard.php`
- Modify: `resources/views/livewire/reports/product-stock-card.blade.php`
- Test: `tests/Feature/Reports/ProductStockCardTest.php`

- [ ] **Step 1: Write the failing ledger feature test**

Create `tests/Feature/Reports/ProductStockCardTest.php`:

```php
<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Unit;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SaleItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductStockCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_ledger_with_running_balance(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $product = Product::factory()->create([
            'quantity'       => 7,
            'purchase_price' => 5000,
            'selling_price'  => 10000,
            'category_id'    => Category::factory()->create()->id,
            'unit_id'        => Unit::factory()->create()->id,
        ]);

        $supplier = Supplier::factory()->create();
        $purchase = Purchase::create([
            'invoice_number' => 'PO-X', 'supplier_id' => $supplier->id,
            'purchase_date'  => '2026-05-01', 'total' => 50000,
            'status' => PurchaseStatus::RECEIVED, 'created_by' => $user->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $purchase->id, 'product_id' => $product->id,
            'quantity' => 10, 'unit_price' => 5000, 'subtotal' => 50000,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'SO-X', 'created_by' => $user->id,
            'sale_date' => '2026-05-05', 'status' => SaleStatus::COMPLETED,
            'subtotal' => 30000, 'total_discount' => 0, 'total' => 30000,
            'cash_received' => 30000, 'change' => 0, 'payment_method' => PaymentMethod::CASH,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id,
            'quantity' => 3, 'cost_price' => 5000, 'unit_price' => 10000,
            'discount' => 0, 'final_price' => 10000, 'subtotal' => 30000,
        ]);

        $this->actingAs($user)
            ->get(route('reports.stock-movements.product', $product))
            ->assertOk()
            ->assertSee('PO-X')
            ->assertSee('SO-X')
            ->assertSeeText('Saldo Berjalan');
    }
}
```

- [ ] **Step 2: Run the test, verify it fails on `Saldo Berjalan` not visible**

Run: `vendor/bin/phpunit --filter test_super_admin_sees_ledger_with_running_balance`
Expected: FAIL (stub view has no ledger).

- [ ] **Step 3: Replace `ProductStockCard` with the full implementation**

Replace `app/Livewire/Reports/ProductStockCard.php`:

```php
<?php

namespace App\Livewire\Reports;

use Carbon\Carbon;
use Livewire\Component;
use App\Models\Product;
use App\Enums\DatePeriod;
use App\Services\StockMovementService;
use App\Exports\ProductStockCardExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ProductStockCard extends Component
{
    public Product $product;
    public string $dateFilter = DatePeriod::THIS_MONTH->value;
    public ?string $customStartDate = null;
    public ?string $customEndDate = null;
    public string $search = ''; // unused; satisfies shared filter-bar partial

    public function mount(Product $product): void
    {
        $this->product = $product;
        $this->customStartDate = Carbon::now('Asia/Jakarta')->startOfMonth()->format('Y-m-d');
        $this->customEndDate   = Carbon::now('Asia/Jakarta')->endOfMonth()->format('Y-m-d');
    }

    public function updateCustomRange(string $start, string $end): void
    {
        $this->customStartDate = $start;
        $this->customEndDate   = $end;
    }

    public function refresh(): void
    {
        // forces re-render
    }

    public function exportExcel()
    {
        [$start, $end] = $this->getDateRange();
        $ledger = app(StockMovementService::class)->productLedger($this->product->id, $start, $end);
        $file = 'kartu-stok-' . $this->product->sku . '-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.xlsx';

        return Excel::download(
            new ProductStockCardExport($this->product, $ledger, $start, $end),
            $file
        );
    }

    public function exportPdf()
    {
        [$start, $end] = $this->getDateRange();
        $ledger = app(StockMovementService::class)->productLedger($this->product->id, $start, $end);

        $pdf = Pdf::loadView('exports.pdf.product-stock-card', [
            'product' => $this->product,
            'ledger'  => $ledger,
            'start'   => $start,
            'end'     => $end,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'kartu-stok-' . $this->product->sku . '-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.pdf'
        );
    }

    protected function getDateRange(): array
    {
        $now = Carbon::now('Asia/Jakarta');

        return match (DatePeriod::tryFrom($this->dateFilter)) {
            DatePeriod::TODAY      => [$now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc()],
            DatePeriod::YESTERDAY  => [$now->copy()->subDay()->startOfDay()->utc(), $now->copy()->subDay()->endOfDay()->utc()],
            DatePeriod::THIS_WEEK  => [$now->copy()->startOfWeek()->utc(), $now->copy()->endOfWeek()->utc()],
            DatePeriod::THIS_MONTH => [$now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc()],
            DatePeriod::LAST_MONTH => [$now->copy()->subMonth()->startOfMonth()->utc(), $now->copy()->subMonth()->endOfMonth()->utc()],
            DatePeriod::CUSTOM     => [
                Carbon::parse($this->customStartDate, 'Asia/Jakarta')->startOfDay()->utc(),
                Carbon::parse($this->customEndDate,   'Asia/Jakarta')->endOfDay()->utc(),
            ],
            default                => [$now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc()],
        };
    }

    public function render()
    {
        [$start, $end] = $this->getDateRange();
        $ledger = app(StockMovementService::class)->productLedger($this->product->id, $start, $end);

        return view('livewire.reports.product-stock-card', [
            'product'   => $this->product,
            'ledger'    => $ledger,
            'startDate' => $start,
            'endDate'   => $end,
        ]);
    }
}
```

- [ ] **Step 4: Replace the stub blade with the ledger view**

Replace `resources/views/livewire/reports/product-stock-card.blade.php`:

```blade
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
    @include('livewire.reports.partials.filter-bar', [
        'title' => 'Kartu Stok — ' . $product->name,
        'subtitle' => 'SKU: ' . $product->sku . ' • Stok saat ini: ' . number_format($product->quantity),
        'showSearch' => false,
    ])

    <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-muted/40 flex items-center justify-between">
            <h3 class="text-sm font-semibold">
                Periode: {{ \Carbon\Carbon::parse($startDate)->setTimezone('Asia/Jakarta')->format('d M Y') }} —
                {{ \Carbon\Carbon::parse($endDate)->setTimezone('Asia/Jakarta')->format('d M Y') }}
            </h3>
            <div class="text-sm">
                Saldo Awal: <span class="font-semibold">{{ number_format($ledger['opening']) }}</span> •
                Saldo Akhir: <span class="font-semibold">{{ number_format($ledger['closing']) }}</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Tanggal</th>
                        <th class="px-4 py-2 font-semibold">Jenis</th>
                        <th class="px-4 py-2 font-semibold">Sumber</th>
                        <th class="px-4 py-2 font-semibold">No. Ref</th>
                        <th class="px-4 py-2 font-semibold text-right">Qty</th>
                        <th class="px-4 py-2 font-semibold text-right">Saldo Berjalan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-sky-50 border-t">
                        <td colspan="5" class="px-4 py-2 font-semibold">Saldo Awal</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ number_format($ledger['opening']) }}</td>
                    </tr>
                    @forelse ($ledger['rows'] as $row)
                        <tr class="border-t">
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($row->occurred_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold
                                    {{ $row->type === 'IN' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $row->type === 'IN' ? 'Masuk' : 'Keluar' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 capitalize">{{ $row->source }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $row->reference_no }}</td>
                            <td class="px-4 py-2 text-right {{ $row->type === 'IN' ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ ($row->type === 'IN' ? '+' : '-') . number_format($row->qty) }}
                            </td>
                            <td class="px-4 py-2 text-right font-semibold">{{ number_format($row->running_balance) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                Tidak ada pergerakan pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                    <tr class="bg-sky-50 border-t">
                        <td colspan="5" class="px-4 py-2 font-semibold">Saldo Akhir</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ number_format($ledger['closing']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
```

- [ ] **Step 5: Run the ledger feature test, verify PASS**

Run: `vendor/bin/phpunit tests/Feature/Reports/ProductStockCardTest.php`
Expected: 1 test passes.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Reports/ProductStockCard.php resources/views/livewire/reports/product-stock-card.blade.php tests/Feature/Reports/ProductStockCardTest.php
git commit -m "feat(reports): add product stock card with running balance"
```

---

## Task 4: Excel exports

**Files:**
- Create: `app/Exports/StockMovementExport.php`
- Create: `app/Exports/ProductStockCardExport.php`

- [ ] **Step 1: Create `StockMovementExport`**

Create `app/Exports/StockMovementExport.php`:

```php
<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StockMovementExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithEvents
{
    use Exportable;

    public function __construct(
        protected Collection $rows,
        protected Carbon $start,
        protected Carbon $end,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Tanggal', 'Jenis', 'Sumber', 'No. Ref', 'SKU', 'Produk', 'Qty', 'Harga', 'Total'];
    }

    public function map($row): array
    {
        return [
            Carbon::parse($row->occurred_at)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
            $row->type === 'IN' ? 'Masuk' : 'Keluar',
            ucfirst($row->source),
            $row->reference_no,
            $row->sku,
            $row->product_name,
            (int) $row->qty,
            (int) $row->unit_price,
            (int) $row->line_total,
        ];
    }

    public function title(): string
    {
        return 'Stock Movement';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:I1')->getFont()->setBold(true);
                $sheet->getStyle('A1:I1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0F2FE');
                $sheet->freezePane('A2');
            },
        ];
    }
}
```

- [ ] **Step 2: Create `ProductStockCardExport`**

Create `app/Exports/ProductStockCardExport.php`:

```php
<?php

namespace App\Exports;

use Carbon\Carbon;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductStockCardExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    use Exportable;

    /**
     * @param array{opening:int,rows:Collection,closing:int} $ledger
     */
    public function __construct(
        protected Product $product,
        protected array $ledger,
        protected Carbon $start,
        protected Carbon $end,
    ) {}

    public function collection(): Collection
    {
        $out = collect([
            ['', '', '', 'Saldo Awal', '', $this->ledger['opening']],
        ]);

        foreach ($this->ledger['rows'] as $row) {
            $out->push([
                Carbon::parse($row->occurred_at)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
                $row->type === 'IN' ? 'Masuk' : 'Keluar',
                ucfirst($row->source),
                $row->reference_no,
                ($row->type === 'IN' ? '+' : '-') . (int) $row->qty,
                (int) $row->running_balance,
            ]);
        }

        $out->push(['', '', '', 'Saldo Akhir', '', $this->ledger['closing']]);

        return $out;
    }

    public function headings(): array
    {
        return ['Tanggal', 'Jenis', 'Sumber', 'No. Ref', 'Qty', 'Saldo Berjalan'];
    }

    public function title(): string
    {
        return 'Kartu Stok ' . $this->product->sku;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:F1')->getFont()->setBold(true);
                $sheet->getStyle('A1:F1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E0F2FE');
                $sheet->freezePane('A2');
            },
        ];
    }
}
```

- [ ] **Step 3: Smoke-test the Excel exports manually**

Boot the dev server and download both exports.

```bash
php artisan serve
```

Sign in as a super admin, visit `/reports/stock-movements`, click "Excel". Open the resulting `.xlsx` and verify 9 columns, header bold, data rows present. Repeat from a product's kartu stok page; verify 6 columns plus Saldo Awal / Saldo Akhir rows.

- [ ] **Step 4: Commit**

```bash
git add app/Exports/StockMovementExport.php app/Exports/ProductStockCardExport.php
git commit -m "feat(reports): add stock movement Excel exports"
```

---

## Task 5: PDF exports

**Files:**
- Create: `resources/views/exports/pdf/stock-movement.blade.php`
- Create: `resources/views/exports/pdf/product-stock-card.blade.php`

- [ ] **Step 1: Create the global PDF template**

Create `resources/views/exports/pdf/stock-movement.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Riwayat Stok</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { margin: 0 0 4px 0; font-size: 16px; }
        .meta { color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; }
        th { background: #e0f2fe; text-align: left; }
        .num { text-align: right; }
        .in { color: #047857; font-weight: 600; }
        .out { color: #b91c1c; font-weight: 600; }
        .totals { margin-top: 12px; }
    </style>
</head>
<body>
    <h1>Riwayat Stok</h1>
    <div class="meta">
        Periode: {{ $start->copy()->setTimezone('Asia/Jakarta')->format('d M Y') }}
        &mdash;
        {{ $end->copy()->setTimezone('Asia/Jakarta')->format('d M Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Sumber</th>
                <th>No. Ref</th>
                <th>SKU</th>
                <th>Produk</th>
                <th class="num">Qty</th>
                <th class="num">Harga</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->occurred_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                    <td class="{{ $row->type === 'IN' ? 'in' : 'out' }}">{{ $row->type === 'IN' ? 'Masuk' : 'Keluar' }}</td>
                    <td>{{ ucfirst($row->source) }}</td>
                    <td>{{ $row->reference_no }}</td>
                    <td>{{ $row->sku }}</td>
                    <td>{{ $row->product_name }}</td>
                    <td class="num">{{ number_format($row->qty) }}</td>
                    <td class="num">{{ number_format((int) $row->unit_price) }}</td>
                    <td class="num">{{ number_format((int) $row->line_total) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:16px;">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        Total Masuk: <strong>{{ number_format($totals['in']) }}</strong> &nbsp;|&nbsp;
        Total Keluar: <strong>{{ number_format($totals['out']) }}</strong> &nbsp;|&nbsp;
        Net: <strong>{{ number_format($totals['net']) }}</strong>
    </div>
</body>
</html>
```

- [ ] **Step 2: Create the product stock card PDF template**

Create `resources/views/exports/pdf/product-stock-card.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kartu Stok {{ $product->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { margin: 0 0 4px 0; font-size: 16px; }
        .meta { color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; }
        th { background: #e0f2fe; text-align: left; }
        .num { text-align: right; }
        .saldo { background: #f0f9ff; font-weight: 600; }
        .in { color: #047857; }
        .out { color: #b91c1c; }
    </style>
</head>
<body>
    <h1>Kartu Stok &mdash; {{ $product->name }}</h1>
    <div class="meta">
        SKU: {{ $product->sku }} &nbsp;|&nbsp;
        Stok saat ini: {{ number_format($product->quantity) }} &nbsp;|&nbsp;
        Periode: {{ $start->copy()->setTimezone('Asia/Jakarta')->format('d M Y') }} &mdash;
        {{ $end->copy()->setTimezone('Asia/Jakarta')->format('d M Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Sumber</th>
                <th>No. Ref</th>
                <th class="num">Qty</th>
                <th class="num">Saldo Berjalan</th>
            </tr>
        </thead>
        <tbody>
            <tr class="saldo"><td colspan="5">Saldo Awal</td><td class="num">{{ number_format($ledger['opening']) }}</td></tr>
            @forelse ($ledger['rows'] as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->occurred_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                    <td class="{{ $row->type === 'IN' ? 'in' : 'out' }}">{{ $row->type === 'IN' ? 'Masuk' : 'Keluar' }}</td>
                    <td>{{ ucfirst($row->source) }}</td>
                    <td>{{ $row->reference_no }}</td>
                    <td class="num {{ $row->type === 'IN' ? 'in' : 'out' }}">
                        {{ ($row->type === 'IN' ? '+' : '-') . number_format($row->qty) }}
                    </td>
                    <td class="num">{{ number_format($row->running_balance) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:16px;">Tidak ada pergerakan.</td></tr>
            @endforelse
            <tr class="saldo"><td colspan="5">Saldo Akhir</td><td class="num">{{ number_format($ledger['closing']) }}</td></tr>
        </tbody>
    </table>
</body>
</html>
```

- [ ] **Step 3: Smoke-test the PDFs manually**

```bash
php artisan serve
```

As super admin, click the "PDF" button on both report pages. Open each file and verify columns and totals.

- [ ] **Step 4: Commit**

```bash
git add resources/views/exports/pdf/stock-movement.blade.php resources/views/exports/pdf/product-stock-card.blade.php
git commit -m "feat(reports): add stock movement PDF exports"
```

---

## Task 6: Sidebar navigation

**Files:**
- Modify: `resources/views/layouts/navigation.blade.php`

- [ ] **Step 1: Add the desktop nav entry**

In `resources/views/layouts/navigation.blade.php`, inside the Reports `<x-slot name="content">` block (currently between `customer-nominal` link and the closing `</x-slot>`), add immediately after the existing entries:

Find:

```blade
<x-dropdown-link :href="route('reports.customer-nominal')" :active="request()->routeIs('reports.customer-nominal')">
    Customer Nominal
</x-dropdown-link>
```

Append after it (still inside `<x-slot name="content">`):

```blade
<x-dropdown-link :href="route('reports.stock-movements')" :active="request()->routeIs('reports.stock-movements*')">
    Riwayat Stok
</x-dropdown-link>
```

- [ ] **Step 2: Add the mobile accordion entry**

In the same file, locate the mobile accordion block for reports (around line 280):

```blade
<a class="text-sm font-medium hover:underline py-1 {{ request()->routeIs('reports.customer-nominal') ? 'text-primary' : '' }}" href="{{ route('reports.customer-nominal') }}">Customer Nominal</a>
```

Append immediately after it:

```blade
<a class="text-sm font-medium hover:underline py-1 {{ request()->routeIs('reports.stock-movements*') ? 'text-primary' : '' }}" href="{{ route('reports.stock-movements') }}">Riwayat Stok</a>
```

- [ ] **Step 3: Manual smoke test**

```bash
php artisan serve
```

Log in as super admin. Verify "Riwayat Stok" appears in the Reports dropdown (desktop) and Reports accordion (mobile). Click — page loads. Active state highlights when on the page.

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/navigation.blade.php
git commit -m "feat(reports): add Riwayat Stok nav entry"
```

---

## Task 7: Round-trip feature tests + cleanup

**Files:**
- Modify: `tests/Feature/Reports/StockMovementReportTest.php`

- [ ] **Step 1: Add filter + content assertions to the report test**

Append to `tests/Feature/Reports/StockMovementReportTest.php` inside the class:

```php
public function test_global_log_renders_rows_with_filters(): void
{
    $user = User::factory()->create(['role' => 'super_admin']);

    $product = \App\Models\Product::factory()->create([
        'quantity'       => 8,
        'purchase_price' => 5000,
        'selling_price'  => 10000,
        'category_id'    => \App\Models\Category::factory()->create()->id,
        'unit_id'        => \App\Models\Unit::factory()->create()->id,
    ]);

    $supplier = \App\Models\Supplier::factory()->create();
    $purchase = \App\Models\Purchase::create([
        'invoice_number' => 'PO-NAV', 'supplier_id' => $supplier->id,
        'purchase_date' => now()->format('Y-m-d'), 'total' => 50000,
        'status' => \App\Enums\PurchaseStatus::RECEIVED, 'created_by' => $user->id,
    ]);
    \App\Models\PurchaseItem::create([
        'purchase_id' => $purchase->id, 'product_id' => $product->id,
        'quantity' => 10, 'unit_price' => 5000, 'subtotal' => 50000,
    ]);

    $sale = \App\Models\Sale::create([
        'invoice_number' => 'SO-NAV', 'created_by' => $user->id,
        'sale_date' => now(), 'status' => \App\Enums\SaleStatus::COMPLETED,
        'subtotal' => 20000, 'total_discount' => 0, 'total' => 20000,
        'cash_received' => 20000, 'change' => 0,
        'payment_method' => \App\Enums\PaymentMethod::CASH,
    ]);
    \App\Models\SaleItem::create([
        'sale_id' => $sale->id, 'product_id' => $product->id,
        'quantity' => 2, 'cost_price' => 5000, 'unit_price' => 10000,
        'discount' => 0, 'final_price' => 10000, 'subtotal' => 20000,
    ]);

    $this->actingAs($user)
        ->get(route('reports.stock-movements'))
        ->assertOk()
        ->assertSee('PO-NAV')
        ->assertSee('SO-NAV')
        ->assertSeeText('Total Masuk');
}
```

- [ ] **Step 2: Run the full feature + unit suite**

Run:
```bash
vendor/bin/phpunit tests/Feature/Reports tests/Unit/Services/StockMovementServiceTest.php
```

Expected: all tests pass.

- [ ] **Step 3: Run the full test suite for regressions**

Run: `vendor/bin/phpunit`
Expected: full suite green (no regressions in existing Sale Returns / Auth tests).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Reports/StockMovementReportTest.php
git commit -m "test(reports): cover stock movement filters and rendering"
```

---

## Self-Review Notes

- Spec section 2 (in/out of scope) — covered by Tasks 1-7.
- Spec section 3 (data sources, three legs) — Task 1 step 3.
- Spec section 4.1 (service API: query/paginate/collect/totals/productLedger) — Task 1 steps 3-7.
- Spec section 4.2 (Livewire components, state, methods, columns) — Tasks 2 and 3.
- Spec section 4.3 (routes) — Task 2 step 3.
- Spec section 4.4 (Excel + PDF exports) — Tasks 4 and 5.
- Spec section 4.5 (sidebar nav) — Task 6.
- Spec section 5 (edge cases: status filtering, timezone, stable sort) — Task 1 step 3 enforces; the additional same-second sort uses `source` then `reference_id` then `item_id`.
- Spec section 6 (tests) — Tasks 1, 2, 3, 7.
- Type consistency — `StockMovementService::query()` always returns `Illuminate\Database\Query\Builder`; consumers call `paginate`, `get`, `selectRaw`, `reorder` consistently. Column names (`occurred_at`, `type`, `source`, `reference_id`, `reference_no`, `product_id`, `sku`, `product_name`, `qty`, `unit_price`, `line_total`, `item_id`) are identical across legs and consumed identically by views, exports, and tests.
- Pagination caveat: `paginate()` on a `fromSub` builder works in Laravel 10+; if the project is on a version where the count subquery breaks, swap to manual count (`->getCountForPagination()` and `->forPage()`). Verify on first run.

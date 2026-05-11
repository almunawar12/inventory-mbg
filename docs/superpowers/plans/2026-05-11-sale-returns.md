# Sale Returns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add refund-based partial-return feature tied to existing COMPLETED sale invoices, auto-recording stock restore and Finance expense.

**Architecture:** New `SaleReturn` + `SaleReturnItem` tables referencing `sales` / `sale_items`. New `SaleReturnService` orchestrates the create/delete transaction (stock + finance). `SaleReturnController` exposes resource routes under the `super_admin` middleware group. UI: button on Sale Detail + standalone Returns index/create/show/print views, with a Livewire PowerGrid table.

**Tech Stack:** PHP 8 / Laravel, Livewire 3, PowerGrid, Alpine.js, Blade, Tailwind, PHPUnit.

**Spec:** [docs/superpowers/specs/2026-05-11-sale-returns-design.md](../specs/2026-05-11-sale-returns-design.md)

---

## File Structure

**Create**
- `database/migrations/2026_05_11_000001_create_sale_returns_table.php`
- `database/migrations/2026_05_11_000002_create_sale_return_items_table.php`
- `app/Models/SaleReturn.php`
- `app/Models/SaleReturnItem.php`
- `app/DTOs/SaleReturnData.php`
- `app/DTOs/SaleReturnItemData.php`
- `app/Exceptions/SaleReturnException.php`
- `app/Services/SaleReturnService.php`
- `app/Http/Requests/StoreSaleReturnRequest.php`
- `app/Http/Controllers/SaleReturnController.php`
- `app/Http/Controllers/Api/SaleController.php`
- `app/Livewire/SaleReturns/SaleReturnTable.php`
- `resources/views/sale-returns/index.blade.php`
- `resources/views/sale-returns/create.blade.php`
- `resources/views/sale-returns/show.blade.php`
- `resources/views/sale-returns/print.blade.php`
- `tests/Feature/SaleReturns/CreateSaleReturnTest.php`
- `tests/Feature/SaleReturns/DeleteSaleReturnTest.php`
- `tests/Feature/SaleReturns/SaleLookupTest.php`
- `tests/Feature/SaleReturns/CancelSaleGuardTest.php`

**Modify**
- `app/Models/Sale.php` (add `returns()`)
- `app/Models/SaleItem.php` (add `returnItems()`)
- `app/Services/SaleService.php` (`cancelSale` guard for sales with returns)
- `app/Services/FinanceTransactionService.php` (`recordExpenseFromReturn`, protect `Sales Refund`)
- `routes/web.php` (sale-returns resource + lookup ajax)
- `resources/views/sales/show.blade.php` (Retur button)
- Sidebar layout (find with grep below) — add Returns menu link

---

## Task 1: Migrations

**Files:**
- Create: `database/migrations/2026_05_11_000001_create_sale_returns_table.php`
- Create: `database/migrations/2026_05_11_000002_create_sale_return_items_table.php`

- [ ] **Step 1: Create `create_sale_returns_table` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('return_date')->index();
            $table->bigInteger('total_refund')->default(0);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
```

- [ ] **Step 2: Create `create_sale_return_items_table` migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('quantity');
            $table->bigInteger('unit_price');
            $table->bigInteger('subtotal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
    }
};
```

- [ ] **Step 3: Run migrations**

Run: `php artisan migrate`
Expected: `Migrating: ..._create_sale_returns_table` then `..._create_sale_return_items_table`, both `DONE`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_11_000001_create_sale_returns_table.php database/migrations/2026_05_11_000002_create_sale_return_items_table.php
git commit -m "feat: add sale_returns and sale_return_items tables"
```

---

## Task 2: Models + Relations

**Files:**
- Create: `app/Models/SaleReturn.php`
- Create: `app/Models/SaleReturnItem.php`
- Modify: `app/Models/Sale.php`
- Modify: `app/Models/SaleItem.php`

- [ ] **Step 1: Create `SaleReturn` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number',
        'sale_id',
        'created_by',
        'return_date',
        'total_refund',
        'reason',
        'notes',
    ];

    protected $casts = [
        'return_date'  => 'datetime',
        'total_refund' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [ ] **Step 2: Create `SaleReturnItem` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'integer',
        'subtotal'   => 'integer',
    ];

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

- [ ] **Step 3: Add `returns()` relation to `Sale`**

In `app/Models/Sale.php`, after the existing `creator()` method, add:

```php
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }
```

- [ ] **Step 4: Add `returnItems()` relation to `SaleItem`**

In `app/Models/SaleItem.php`, after the existing `product()` method, add:

```php
    public function returnItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
```

- [ ] **Step 5: Commit**

```bash
git add app/Models/SaleReturn.php app/Models/SaleReturnItem.php app/Models/Sale.php app/Models/SaleItem.php
git commit -m "feat: add SaleReturn and SaleReturnItem models with relations"
```

---

## Task 3: DTOs + Exception

**Files:**
- Create: `app/DTOs/SaleReturnItemData.php`
- Create: `app/DTOs/SaleReturnData.php`
- Create: `app/Exceptions/SaleReturnException.php`

- [ ] **Step 1: Create `SaleReturnItemData` DTO**

```php
<?php

namespace App\DTOs;

readonly class SaleReturnItemData
{
    public function __construct(
        public int $sale_item_id,
        public int $quantity,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sale_item_id: (int) $data['sale_item_id'],
            quantity:     (int) $data['quantity'],
        );
    }
}
```

- [ ] **Step 2: Create `SaleReturnData` DTO**

```php
<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class SaleReturnData
{
    /** @param SaleReturnItemData[] $items */
    public function __construct(
        public int $sale_id,
        public int $created_by,
        public Carbon $return_date,
        public array $items,
        public ?string $reason = null,
        public ?string $notes  = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sale_id:     (int) $data['sale_id'],
            created_by:  (int) $data['created_by'],
            return_date: isset($data['return_date']) ? Carbon::parse($data['return_date']) : Carbon::now(),
            items:       array_map(fn($i) => SaleReturnItemData::fromArray($i), $data['items']),
            reason:      $data['reason'] ?? null,
            notes:       $data['notes']  ?? null,
        );
    }
}
```

- [ ] **Step 3: Create `SaleReturnException`**

```php
<?php

namespace App\Exceptions;

use Exception;

class SaleReturnException extends Exception
{
    public array $context = [];

    public static function invalidSaleStatus(string $status, array $ctx = []): self
    {
        $e = new self("Sale status '{$status}' is not eligible for return. Only COMPLETED sales can be returned.");
        $e->context = $ctx;
        return $e;
    }

    public static function saleItemMismatch(int $saleItemId, int $saleId): self
    {
        return new self("Sale item #{$saleItemId} does not belong to sale #{$saleId}.");
    }

    public static function exceedsAvailableQty(string $product, int $requested, int $available): self
    {
        return new self("Cannot return {$requested} of '{$product}'. Only {$available} remaining to return.");
    }

    public static function productNotFound(int $id): self
    {
        return new self("Product #{$id} not found.");
    }

    public static function cannotReverseStock(string $product, int $needed, int $available): self
    {
        return new self("Cannot reverse return: product '{$product}' has only {$available} in stock but {$needed} needed.");
    }

    public static function creationFailed(string $msg, array $ctx = []): self
    {
        $e = new self("Failed to create sale return: {$msg}");
        $e->context = $ctx;
        return $e;
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/DTOs/SaleReturnData.php app/DTOs/SaleReturnItemData.php app/Exceptions/SaleReturnException.php
git commit -m "feat: add SaleReturn DTOs and exception"
```

---

## Task 4: FinanceTransactionService — `recordExpenseFromReturn`

**Files:**
- Modify: `app/Services/FinanceTransactionService.php`

- [ ] **Step 1: Add use + method + protect category**

After the existing `recordExpenseFromPurchase()` method in `app/Services/FinanceTransactionService.php`, add:

```php
    /**
     * Record refund expense from a sale return.
     */
    public function recordExpenseFromReturn(\App\Models\SaleReturn $return): void
    {
        $category = $this->getOrCreateCategory('Sales Refund', FinanceCategoryType::Expense);

        $return->loadMissing('sale');

        FinanceTransaction::updateOrCreate(
            [
                'reference_type' => \App\Models\SaleReturn::class,
                'reference_id'   => $return->id,
            ],
            [
                'code'                => $this->generateTransactionCode('REF'),
                'transaction_date'    => $return->return_date,
                'finance_category_id' => $category->id,
                'amount'              => $return->total_refund,
                'description'         => 'Refund Ret: ' . $return->return_number . ' - Inv ' . ($return->sale->invoice_number ?? '-'),
                'external_reference'  => $return->return_number,
                'created_by'          => $return->created_by ?? Auth::id() ?? 1,
            ]
        );
    }
```

- [ ] **Step 2: Protect `Sales Refund` category from manual delete**

Find the protected-category array in `deleteTransaction()`:

```php
if ($transaction->category && in_array($transaction->category->name, ['Product Sales', 'Product Purchases'])) {
```

Replace with:

```php
if ($transaction->category && in_array($transaction->category->name, ['Product Sales', 'Product Purchases', 'Sales Refund'])) {
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/FinanceTransactionService.php
git commit -m "feat: record expense from sale return; protect Sales Refund category"
```

---

## Task 5: SaleReturnService — write failing test first (createReturn happy path)

**Files:**
- Create: `tests/Feature/SaleReturns/CreateSaleReturnTest.php`
- Create: `app/Services/SaleReturnService.php` (skeleton in this task)

- [ ] **Step 1: Confirm test setup uses `RefreshDatabase`**

Check `phpunit.xml` connects to a test database. If `tests/TestCase.php` has no `RefreshDatabase`, add it as a trait per test class.

- [ ] **Step 2: Write failing test for partial return happy path**

Create `tests/Feature/SaleReturns/CreateSaleReturnTest.php`:

```php
<?php

namespace Tests\Feature\SaleReturns;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\Sale;
use App\Models\User;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Category;
use App\Models\Unit;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\FinanceTransaction;
use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use App\DTOs\SaleReturnData;
use App\Services\SaleReturnService;
use App\Exceptions\SaleReturnException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreateSaleReturnTest extends TestCase
{
    use RefreshDatabase;

    private function makeSaleWithItem(int $qty = 5, int $finalPrice = 10000, int $stockBeforeSale = 0): array
    {
        $user     = User::factory()->create();
        $unit     = Unit::factory()->create();
        $category = Category::factory()->create();

        // Stock = items already sold + leftover (here 0 leftover so all sold)
        $product = Product::factory()->create([
            'quantity'       => $stockBeforeSale,
            'selling_price'  => $finalPrice,
            'purchase_price' => 5000,
            'category_id'    => $category->id,
            'unit_id'        => $unit->id,
        ]);

        $sale = Sale::create([
            'invoice_number'  => 'INV.260511.0001',
            'customer_id'     => null,
            'created_by'      => $user->id,
            'sale_date'       => now(),
            'status'          => SaleStatus::COMPLETED,
            'payment_method'  => PaymentMethod::CASH,
            'subtotal'        => $finalPrice * $qty,
            'global_discount' => 0,
            'total_discount'  => 0,
            'total'           => $finalPrice * $qty,
            'cash_received'   => $finalPrice * $qty,
            'change'          => 0,
        ]);

        $item = SaleItem::create([
            'sale_id'     => $sale->id,
            'product_id'  => $product->id,
            'quantity'    => $qty,
            'cost_price'  => 5000,
            'unit_price'  => $finalPrice,
            'discount'    => 0,
            'final_price' => $finalPrice,
            'subtotal'    => $finalPrice * $qty,
        ]);

        return compact('user', 'sale', 'item', 'product');
    }

    public function test_partial_return_restores_stock_and_creates_finance_expense(): void
    {
        ['user' => $user, 'sale' => $sale, 'item' => $item, 'product' => $product] = $this->makeSaleWithItem(qty: 5, finalPrice: 10000);

        $data = SaleReturnData::fromArray([
            'sale_id'     => $sale->id,
            'created_by'  => $user->id,
            'return_date' => Carbon::now()->toDateTimeString(),
            'reason'      => 'Damaged on arrival',
            'items'       => [
                ['sale_item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $return = app(SaleReturnService::class)->createReturn($data);

        $this->assertDatabaseHas('sale_returns', [
            'id'           => $return->id,
            'sale_id'      => $sale->id,
            'total_refund' => 20000,
            'reason'       => 'Damaged on arrival',
        ]);
        $this->assertDatabaseHas('sale_return_items', [
            'sale_return_id' => $return->id,
            'sale_item_id'   => $item->id,
            'product_id'     => $product->id,
            'quantity'       => 2,
            'unit_price'     => 10000,
            'subtotal'       => 20000,
        ]);
        $this->assertEquals(2, $product->fresh()->quantity);
        $this->assertDatabaseHas('finance_transactions', [
            'reference_type' => SaleReturn::class,
            'reference_id'   => $return->id,
            'amount'         => 20000,
        ]);
        $this->assertMatchesRegularExpression('/^RET\.\d{6}\.\d{4}$/', $return->return_number);
    }
}
```

- [ ] **Step 3: Create empty `SaleReturnService` shell so autoload finds it**

Create `app/Services/SaleReturnService.php`:

```php
<?php

namespace App\Services;

use App\DTOs\SaleReturnData;
use App\Models\SaleReturn;

class SaleReturnService
{
    public function __construct(
        protected FinanceTransactionService $financeService,
    ) {}

    public function createReturn(SaleReturnData $data): SaleReturn
    {
        throw new \LogicException('Not implemented');
    }
}
```

- [ ] **Step 4: Run the test and confirm it fails**

Run: `php artisan test --filter=test_partial_return_restores_stock_and_creates_finance_expense`
Expected: FAIL with `LogicException: Not implemented`.

- [ ] **Step 5: Commit (failing test + skeleton)**

```bash
git add tests/Feature/SaleReturns/CreateSaleReturnTest.php app/Services/SaleReturnService.php
git commit -m "test: failing test for sale return happy path + service skeleton"
```

---

## Task 6: SaleReturnService — implement `createReturn`

**Files:**
- Modify: `app/Services/SaleReturnService.php`

- [ ] **Step 1: Implement `createReturn` + `generateReturnNumber`**

Replace the body of `app/Services/SaleReturnService.php` with:

```php
<?php

namespace App\Services;

use Exception;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Enums\SaleStatus;
use App\DTOs\SaleReturnData;
use App\Exceptions\SaleReturnException;
use Illuminate\Support\Facades\DB;

class SaleReturnService
{
    public function __construct(
        protected FinanceTransactionService $financeService,
    ) {}

    public function createReturn(SaleReturnData $data): SaleReturn
    {
        return DB::transaction(function () use ($data) {
            try {
                /** @var Sale $sale */
                $sale = Sale::whereKey($data->sale_id)->lockForUpdate()->firstOrFail();

                if ($sale->status !== SaleStatus::COMPLETED) {
                    throw SaleReturnException::invalidSaleStatus(
                        $sale->status->value,
                        ['sale_id' => $sale->id]
                    );
                }

                $saleItemIds = collect($data->items)->pluck('sale_item_id')->all();

                $saleItems = SaleItem::whereIn('id', $saleItemIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $productIds = $saleItems->pluck('product_id')->unique()->all();
                $products = Product::whereIn('id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $totalRefund = 0;
                $rows = [];
                $timestamp = now();

                foreach ($data->items as $input) {
                    /** @var SaleItem|null $saleItem */
                    $saleItem = $saleItems->get($input->sale_item_id);

                    if (!$saleItem || $saleItem->sale_id !== $sale->id) {
                        throw SaleReturnException::saleItemMismatch($input->sale_item_id, $sale->id);
                    }

                    /** @var Product|null $product */
                    $product = $products->get($saleItem->product_id);

                    if (!$product) {
                        throw SaleReturnException::productNotFound($saleItem->product_id);
                    }

                    $alreadyReturned = (int) SaleReturnItem::where('sale_item_id', $saleItem->id)->sum('quantity');
                    $available = $saleItem->quantity - $alreadyReturned;

                    if ($input->quantity < 1 || $input->quantity > $available) {
                        throw SaleReturnException::exceedsAvailableQty(
                            $product->name,
                            $input->quantity,
                            max($available, 0)
                        );
                    }

                    $unitPrice = (int) $saleItem->final_price;
                    $subtotal  = $unitPrice * $input->quantity;
                    $totalRefund += $subtotal;

                    $product->increment('quantity', $input->quantity);

                    $rows[] = [
                        'sale_item_id' => $saleItem->id,
                        'product_id'   => $product->id,
                        'quantity'     => $input->quantity,
                        'unit_price'   => $unitPrice,
                        'subtotal'     => $subtotal,
                        'created_at'   => $timestamp,
                        'updated_at'   => $timestamp,
                    ];
                }

                $return = SaleReturn::create([
                    'return_number' => $this->generateReturnNumber(),
                    'sale_id'       => $sale->id,
                    'created_by'    => $data->created_by,
                    'return_date'   => $data->return_date,
                    'total_refund'  => $totalRefund,
                    'reason'        => $data->reason,
                    'notes'         => $data->notes,
                ]);

                foreach ($rows as &$row) {
                    $row['sale_return_id'] = $return->id;
                }
                unset($row);

                SaleReturnItem::insert($rows);

                $this->financeService->recordExpenseFromReturn($return);

                return $return->fresh(['items', 'sale']);

            } catch (Exception $e) {
                if ($e instanceof SaleReturnException) {
                    throw $e;
                }
                throw SaleReturnException::creationFailed($e->getMessage(), ['data' => $data]);
            }
        });
    }

    public function deleteReturn(SaleReturn $return): void
    {
        // Implemented in Task 8.
        throw new \LogicException('Not implemented');
    }

    private function generateReturnNumber(): string
    {
        $prefix = 'RET.' . date('ymd') . '.';

        $latest = SaleReturn::where('return_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if (!$latest) {
            return $prefix . '0001';
        }

        $lastNumber = (int) substr($latest->return_number, -4);
        return $prefix . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    }
}
```

- [ ] **Step 2: Run happy-path test**

Run: `php artisan test --filter=test_partial_return_restores_stock_and_creates_finance_expense`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add app/Services/SaleReturnService.php
git commit -m "feat: implement SaleReturnService::createReturn"
```

---

## Task 7: SaleReturnService — guard tests for createReturn

**Files:**
- Modify: `tests/Feature/SaleReturns/CreateSaleReturnTest.php`

- [ ] **Step 1: Add failing test: cannot return non-COMPLETED sale**

In the test class, append:

```php
    public function test_cannot_return_pending_sale(): void
    {
        ['user' => $user, 'sale' => $sale, 'item' => $item] = $this->makeSaleWithItem();
        $sale->update(['status' => SaleStatus::PENDING]);

        $this->expectException(SaleReturnException::class);
        $this->expectExceptionMessageMatches('/not eligible for return/');

        app(SaleReturnService::class)->createReturn(SaleReturnData::fromArray([
            'sale_id'     => $sale->id,
            'created_by'  => $user->id,
            'return_date' => now()->toDateTimeString(),
            'items'       => [['sale_item_id' => $item->id, 'quantity' => 1]],
        ]));
    }
```

- [ ] **Step 2: Add failing test: cannot exceed available qty across multiple returns**

```php
    public function test_second_return_cannot_exceed_available_qty(): void
    {
        ['user' => $user, 'sale' => $sale, 'item' => $item, 'product' => $product] = $this->makeSaleWithItem(qty: 3);

        // First return: 2 of 3
        app(SaleReturnService::class)->createReturn(SaleReturnData::fromArray([
            'sale_id'     => $sale->id,
            'created_by'  => $user->id,
            'return_date' => now()->toDateTimeString(),
            'items'       => [['sale_item_id' => $item->id, 'quantity' => 2]],
        ]));

        $this->expectException(SaleReturnException::class);
        $this->expectExceptionMessageMatches('/Only 1 remaining/');

        app(SaleReturnService::class)->createReturn(SaleReturnData::fromArray([
            'sale_id'     => $sale->id,
            'created_by'  => $user->id,
            'return_date' => now()->toDateTimeString(),
            'items'       => [['sale_item_id' => $item->id, 'quantity' => 2]],
        ]));
    }
```

- [ ] **Step 3: Add failing test: sale_item from another sale rejected**

```php
    public function test_sale_item_mismatch_rejected(): void
    {
        $a = $this->makeSaleWithItem();
        $b = $this->makeSaleWithItem();

        $this->expectException(SaleReturnException::class);
        $this->expectExceptionMessageMatches('/does not belong to sale/');

        app(SaleReturnService::class)->createReturn(SaleReturnData::fromArray([
            'sale_id'     => $a['sale']->id,
            'created_by'  => $a['user']->id,
            'return_date' => now()->toDateTimeString(),
            'items'       => [['sale_item_id' => $b['item']->id, 'quantity' => 1]],
        ]));
    }
```

- [ ] **Step 4: Run all three tests**

Run: `php artisan test --filter=CreateSaleReturnTest`
Expected: All three new tests PASS (logic already in place from Task 6).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/SaleReturns/CreateSaleReturnTest.php
git commit -m "test: guard tests for SaleReturnService::createReturn"
```

---

## Task 8: SaleReturnService — `deleteReturn` (failing test + impl)

**Files:**
- Create: `tests/Feature/SaleReturns/DeleteSaleReturnTest.php`
- Modify: `app/Services/SaleReturnService.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/SaleReturns/DeleteSaleReturnTest.php`:

```php
<?php

namespace Tests\Feature\SaleReturns;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\Sale;
use App\Models\User;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Category;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\FinanceTransaction;
use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use App\DTOs\SaleReturnData;
use App\Services\SaleReturnService;
use App\Exceptions\SaleReturnException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeleteSaleReturnTest extends TestCase
{
    use RefreshDatabase;

    private function makeReturn(int $qty = 3, int $returnQty = 2): array
    {
        $user     = User::factory()->create();
        $unit     = Unit::factory()->create();
        $category = Category::factory()->create();
        $product  = Product::factory()->create([
            'quantity'       => 0,
            'selling_price'  => 10000,
            'purchase_price' => 5000,
            'category_id'    => $category->id,
            'unit_id'        => $unit->id,
        ]);

        $sale = Sale::create([
            'invoice_number'  => 'INV.260511.9999',
            'created_by'      => $user->id,
            'sale_date'       => now(),
            'status'          => SaleStatus::COMPLETED,
            'payment_method'  => PaymentMethod::CASH,
            'subtotal'        => 10000 * $qty,
            'global_discount' => 0,
            'total_discount'  => 0,
            'total'           => 10000 * $qty,
            'cash_received'   => 10000 * $qty,
            'change'          => 0,
        ]);
        $item = SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => $qty,
            'cost_price' => 5000, 'unit_price' => 10000, 'discount' => 0,
            'final_price' => 10000, 'subtotal' => 10000 * $qty,
        ]);

        $return = app(SaleReturnService::class)->createReturn(SaleReturnData::fromArray([
            'sale_id'     => $sale->id,
            'created_by'  => $user->id,
            'return_date' => now()->toDateTimeString(),
            'items'       => [['sale_item_id' => $item->id, 'quantity' => $returnQty]],
        ]));

        return compact('user', 'sale', 'item', 'product', 'return');
    }

    public function test_delete_return_reverses_stock_and_finance(): void
    {
        ['return' => $return, 'product' => $product] = $this->makeReturn(qty: 3, returnQty: 2);

        $this->assertEquals(2, $product->fresh()->quantity);
        $this->assertDatabaseHas('finance_transactions', ['reference_id' => $return->id]);

        app(SaleReturnService::class)->deleteReturn($return);

        $this->assertEquals(0, $product->fresh()->quantity);
        $this->assertDatabaseMissing('sale_returns', ['id' => $return->id]);
        $this->assertDatabaseMissing('finance_transactions', [
            'reference_type' => SaleReturn::class,
            'reference_id'   => $return->id,
        ]);
    }

    public function test_delete_blocked_when_stock_insufficient(): void
    {
        ['return' => $return, 'product' => $product] = $this->makeReturn(qty: 3, returnQty: 2);

        // Simulate product sold again after the return.
        $product->update(['quantity' => 1]);

        $this->expectException(SaleReturnException::class);
        $this->expectExceptionMessageMatches('/Cannot reverse return/');

        app(SaleReturnService::class)->deleteReturn($return);
    }
}
```

- [ ] **Step 2: Run tests, confirm failure**

Run: `php artisan test --filter=DeleteSaleReturnTest`
Expected: FAIL with `LogicException: Not implemented`.

- [ ] **Step 3: Implement `deleteReturn`**

In `app/Services/SaleReturnService.php`, replace the `deleteReturn` body with:

```php
    public function deleteReturn(SaleReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $return->loadMissing('items.product');

            // Lock products
            $productIds = $return->items->pluck('product_id')->unique()->all();
            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($return->items as $item) {
                /** @var Product|null $product */
                $product = $products->get($item->product_id);
                if (!$product) {
                    throw SaleReturnException::productNotFound($item->product_id);
                }
                if ($product->quantity < $item->quantity) {
                    throw SaleReturnException::cannotReverseStock(
                        $product->name,
                        $item->quantity,
                        (int) $product->quantity
                    );
                }
            }

            // Decrement after all guards pass
            foreach ($return->items as $item) {
                $products[$item->product_id]->decrement('quantity', $item->quantity);
            }

            $this->financeService->voidTransaction($return);

            $return->items()->delete();
            $return->delete();
        });
    }
```

- [ ] **Step 4: Run tests, confirm pass**

Run: `php artisan test --filter=DeleteSaleReturnTest`
Expected: Both tests PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/SaleReturns/DeleteSaleReturnTest.php app/Services/SaleReturnService.php
git commit -m "feat: implement SaleReturnService::deleteReturn with stock+finance reversal"
```

---

## Task 9: SaleService — guard `cancelSale` when returns exist

**Files:**
- Create: `tests/Feature/SaleReturns/CancelSaleGuardTest.php`
- Modify: `app/Services/SaleService.php`

- [ ] **Step 1: Failing test**

Create `tests/Feature/SaleReturns/CancelSaleGuardTest.php`:

```php
<?php

namespace Tests\Feature\SaleReturns;

use Tests\TestCase;
use App\Models\Sale;
use App\Models\User;
use App\Models\Unit;
use App\Models\Category;
use App\Models\Product;
use App\Models\SaleItem;
use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use App\DTOs\SaleReturnData;
use App\Services\SaleService;
use App\Services\SaleReturnService;
use App\Exceptions\SaleException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CancelSaleGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_sale_blocked_when_returns_exist(): void
    {
        $user     = User::factory()->create();
        $unit     = Unit::factory()->create();
        $category = Category::factory()->create();
        $product  = Product::factory()->create([
            'quantity' => 0, 'selling_price' => 10000, 'purchase_price' => 5000,
            'category_id' => $category->id, 'unit_id' => $unit->id,
        ]);
        $sale = Sale::create([
            'invoice_number' => 'INV.260511.0042', 'created_by' => $user->id,
            'sale_date' => now(), 'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 30000, 'global_discount' => 0, 'total_discount' => 0,
            'total' => 30000, 'cash_received' => 30000, 'change' => 0,
        ]);
        $item = SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 3,
            'cost_price' => 5000, 'unit_price' => 10000, 'discount' => 0,
            'final_price' => 10000, 'subtotal' => 30000,
        ]);

        app(SaleReturnService::class)->createReturn(SaleReturnData::fromArray([
            'sale_id'     => $sale->id,
            'created_by'  => $user->id,
            'return_date' => now()->toDateTimeString(),
            'items'       => [['sale_item_id' => $item->id, 'quantity' => 1]],
        ]));

        $this->expectException(SaleException::class);
        $this->expectExceptionMessageMatches('/has related returns/');

        app(SaleService::class)->cancelSale($sale->fresh(), 'test');
    }
}
```

- [ ] **Step 2: Add a `hasReturns` exception factory**

In `app/Exceptions/SaleException.php` (verify location first with `Glob app/Exceptions/SaleException.php`), add a static factory `hasReturns(int $saleId): self` that returns `new self("Cannot cancel sale #{$saleId}: it has related returns. Delete the returns first.")`.

If the existing pattern uses a different signature, mirror it; the message must contain `has related returns`.

- [ ] **Step 3: Add the guard in `SaleService::cancelSale`**

In `app/Services/SaleService.php`, at the top of the `DB::transaction` closure inside `cancelSale`, right before the `if ($sale->status === SaleStatus::CANCELLED)` check, add:

```php
                if ($sale->returns()->exists()) {
                    throw SaleException::hasReturns($sale->id);
                }
```

- [ ] **Step 4: Run test**

Run: `php artisan test --filter=CancelSaleGuardTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/SaleReturns/CancelSaleGuardTest.php app/Services/SaleService.php app/Exceptions/SaleException.php
git commit -m "feat: block cancelling a sale that has returns"
```

---

## Task 10: FormRequest + Routes + Controller

**Files:**
- Create: `app/Http/Requests/StoreSaleReturnRequest.php`
- Create: `app/Http/Controllers/SaleReturnController.php`
- Create: `app/Http/Controllers/Api/SaleController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create `StoreSaleReturnRequest`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'sale_id'              => ['required', 'exists:sales,id'],
            'return_date'          => ['nullable', 'date'],
            'reason'               => ['nullable', 'string', 'max:1000'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
        ];
    }
}
```

- [ ] **Step 2: Create `Api\SaleController` with `lookup`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Enums\SaleStatus;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function lookup(Request $request)
    {
        $request->validate(['invoice_number' => ['required', 'string']]);

        $sale = Sale::where('invoice_number', $request->invoice_number)
            ->where('status', SaleStatus::COMPLETED->value)
            ->with(['customer', 'items.product.unit'])
            ->first();

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found or not in COMPLETED status.',
            ], 404);
        }

        $items = $sale->items->map(function ($item) {
            $alreadyReturned = (int) \App\Models\SaleReturnItem::where('sale_item_id', $item->id)->sum('quantity');
            return [
                'sale_item_id'     => $item->id,
                'product_id'       => $item->product_id,
                'product_name'     => $item->product?->name,
                'unit'             => $item->product?->unit?->name,
                'quantity'         => $item->quantity,
                'already_returned' => $alreadyReturned,
                'available_qty'    => $item->quantity - $alreadyReturned,
                'final_price'      => $item->final_price,
            ];
        });

        return response()->json([
            'success' => true,
            'sale'    => [
                'id'             => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'sale_date'      => $sale->sale_date,
                'customer'       => $sale->customer?->name ?? 'Guest',
                'total'          => $sale->total,
            ],
            'items' => $items,
        ]);
    }
}
```

- [ ] **Step 3: Create `SaleReturnController`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\DTOs\SaleReturnData;
use App\Services\SaleReturnService;
use App\Exceptions\SaleReturnException;
use App\Http\Requests\StoreSaleReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SaleReturnController extends Controller
{
    public function index()
    {
        return view('sale-returns.index');
    }

    public function create(Request $request)
    {
        $sale = null;
        if ($request->filled('sale_id')) {
            $sale = Sale::with(['items.product.unit', 'customer'])->findOrFail($request->integer('sale_id'));
        }
        return view('sale-returns.create', compact('sale'));
    }

    public function store(StoreSaleReturnRequest $request, SaleReturnService $service)
    {
        try {
            $validated = $request->validated();
            $validated['created_by'] = Auth::id();

            $return = $service->createReturn(SaleReturnData::fromArray($validated));

            if ($request->wantsJson()) {
                return response()->json([
                    'success'   => true,
                    'message'   => 'Return created successfully',
                    'data'      => $return,
                    'print_url' => route('sale-returns.print', $return->id),
                    'redirect'  => route('sale-returns.show', $return->id),
                ], 201);
            }

            return redirect()->route('sale-returns.show', $return->id)
                ->with('success', 'Return created successfully.');

        } catch (SaleReturnException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show(SaleReturn $saleReturn)
    {
        $saleReturn->load(['items.product.unit', 'sale.customer', 'creator']);
        return view('sale-returns.show', ['return' => $saleReturn]);
    }

    public function destroy(SaleReturn $saleReturn, SaleReturnService $service)
    {
        try {
            $service->deleteReturn($saleReturn);
            return redirect()->route('sale-returns.index')->with('success', 'Return deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function print(SaleReturn $saleReturn)
    {
        $saleReturn->load(['items.product.unit', 'sale.customer', 'creator']);
        return view('sale-returns.print', ['return' => $saleReturn]);
    }
}
```

- [ ] **Step 4: Wire routes**

In `routes/web.php`, **inside** the existing `Route::middleware('super_admin')->group(function () {` block (the one that holds Purchases/Reports), add at the end of that group:

```php
        // Sale Returns
        Route::resource('sale-returns', \App\Http\Controllers\SaleReturnController::class)
            ->except(['edit', 'update']);
        Route::get('sale-returns/{saleReturn}/print', [\App\Http\Controllers\SaleReturnController::class, 'print'])
            ->name('sale-returns.print');
```

Then in the `ajax` prefix group (outside super_admin — both admins use POS to access invoices), add:

```php
        Route::post('sales/lookup', [\App\Http\Controllers\Api\SaleController::class, 'lookup'])
            ->middleware('super_admin')->name('sales.lookup');
```

(Use `super_admin` middleware on the lookup specifically because returns are super-admin only.)

- [ ] **Step 5: Sanity check routes**

Run: `php artisan route:list --name=sale-returns`
Expected: Lists `sale-returns.index`, `sale-returns.create`, `sale-returns.store`, `sale-returns.show`, `sale-returns.destroy`, `sale-returns.print`.

Run: `php artisan route:list --name=ajax.sales.lookup`
Expected: One row.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/StoreSaleReturnRequest.php app/Http/Controllers/SaleReturnController.php app/Http/Controllers/Api/SaleController.php routes/web.php
git commit -m "feat: routes, FormRequest, and SaleReturnController + sales lookup endpoint"
```

---

## Task 11: Feature test for lookup endpoint

**Files:**
- Create: `tests/Feature/SaleReturns/SaleLookupTest.php`

- [ ] **Step 1: Write test**

```php
<?php

namespace Tests\Feature\SaleReturns;

use Tests\TestCase;
use App\Models\Sale;
use App\Models\User;
use App\Models\Unit;
use App\Models\Category;
use App\Models\Product;
use App\Models\SaleItem;
use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_returns_items_with_available_qty(): void
    {
        // NOTE: User factory must produce a super-admin-eligible user.
        // If the project's `super_admin` middleware checks a `role` column or similar,
        // adapt this setup to satisfy it (e.g., $user = User::factory()->superAdmin()->create()).
        $user     = User::factory()->create(['role' => 'super_admin']);
        $unit     = Unit::factory()->create();
        $category = Category::factory()->create();
        $product  = Product::factory()->create([
            'quantity' => 0, 'selling_price' => 10000, 'purchase_price' => 5000,
            'category_id' => $category->id, 'unit_id' => $unit->id,
        ]);
        $sale = Sale::create([
            'invoice_number' => 'INV.260511.0007', 'created_by' => $user->id,
            'sale_date' => now(), 'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 30000, 'global_discount' => 0, 'total_discount' => 0,
            'total' => 30000, 'cash_received' => 30000, 'change' => 0,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 3,
            'cost_price' => 5000, 'unit_price' => 10000, 'discount' => 0,
            'final_price' => 10000, 'subtotal' => 30000,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('ajax.sales.lookup'), ['invoice_number' => 'INV.260511.0007']);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('sale.invoice_number', 'INV.260511.0007');
        $response->assertJsonPath('items.0.available_qty', 3);
    }

    public function test_lookup_404_when_not_completed(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        Sale::create([
            'invoice_number' => 'INV.260511.0008', 'created_by' => $user->id,
            'sale_date' => now(), 'status' => SaleStatus::PENDING,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 0, 'global_discount' => 0, 'total_discount' => 0,
            'total' => 0, 'cash_received' => 0, 'change' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('ajax.sales.lookup'), ['invoice_number' => 'INV.260511.0008'])
            ->assertStatus(404);
    }
}
```

> If the project's `User` model does not have a `role` column or uses a different super-admin signal, replace the `role` literal with the project's actual mechanism (check `app/Http/Middleware` for a `super_admin` middleware and read its check). Same applies to other test files in this plan that create users.

- [ ] **Step 2: Run**

Run: `php artisan test --filter=SaleLookupTest`
Expected: Both PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/SaleReturns/SaleLookupTest.php
git commit -m "test: sales lookup endpoint returns available_qty per item"
```

---

## Task 12: Livewire PowerGrid — `SaleReturnTable`

**Files:**
- Create: `app/Livewire/SaleReturns/SaleReturnTable.php`
- Create: `resources/views/sale-returns/index.blade.php`

- [ ] **Step 1: Create the PowerGrid component**

Model on `app/Livewire/Sales/SalesTable.php`. Create `app/Livewire/SaleReturns/SaleReturnTable.php`:

```php
<?php

namespace App\Livewire\SaleReturns;

use Carbon\Carbon;
use App\Models\SaleReturn;
use App\Services\SaleReturnService;
use App\Exceptions\SaleReturnException;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class SaleReturnTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'sale-returns-table';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable('sale_returns_export_' . now()->format('Y_m_d'))
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage()->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return SaleReturn::query()->with(['sale.customer', 'creator']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('return_number')
            ->add('invoice_number', fn(SaleReturn $m) => $m->sale?->invoice_number ?: '-')
            ->add('customer_name',  fn(SaleReturn $m) => $m->sale?->customer?->name ?? 'Guest')
            ->add('return_date_formatted', fn(SaleReturn $m) => Carbon::parse($m->return_date)->format('d/m/Y'))
            ->add('total_refund_formatted', fn(SaleReturn $m) => format_money($m->total_refund))
            ->add('creator_name',  fn(SaleReturn $m) => $m->creator?->name ?? '-')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::action('Action'),
            Column::make('ID', 'id')->hidden(),
            Column::make('Return #', 'return_number')->searchable()->sortable(),
            Column::make('Invoice', 'invoice_number', 'sale_id')->searchable()->sortable(),
            Column::make('Customer', 'customer_name'),
            Column::make('Date', 'return_date_formatted', 'return_date')->sortable(),
            Column::make('Refund', 'total_refund_formatted', 'total_refund')
                ->sortable()
                ->headerAttribute('text-right')->bodyAttribute('text-right'),
            Column::make('Created By', 'creator_name', 'created_by'),
        ];
    }

    public function relationSearch(): array
    {
        return [
            'sale' => ['invoice_number'],
        ];
    }

    public function filters(): array
    {
        return [
            Filter::datepicker('return_date_formatted', 'return_date')
                ->params([
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                    'altInput'   => true,
                    'altFormat'  => 'd/m/Y',
                ]),
        ];
    }

    public function actions(SaleReturn $row): array
    {
        return [
            Button::add('view')
                ->slot('View')
                ->class('bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded')
                ->route('sale-returns.show', ['saleReturn' => $row->id]),

            Button::add('print')
                ->slot('Print')
                ->class('bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded')
                ->route('sale-returns.print', ['saleReturn' => $row->id]),

            Button::add('delete')
                ->slot('Delete')
                ->class('bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded')
                ->dispatch('open-delete-modal', [
                    'component'   => 'sale-returns.sale-return-table',
                    'method'      => 'delete',
                    'params'      => ['rowId' => $row->id],
                    'title'       => 'Delete Return?',
                    'description' => "Delete return '{$row->return_number}'? Stock will be decremented again and the refund finance entry removed.",
                ]),
        ];
    }

    #[\Livewire\Attributes\On('delete')]
    public function delete($rowId, SaleReturnService $service): void
    {
        $return = SaleReturn::find($rowId);
        if (!$return) {
            return;
        }
        try {
            $service->deleteReturn($return);
            $this->dispatch('toast', message: 'Return deleted successfully.', type: 'success');
        } catch (SaleReturnException $e) {
            $this->dispatch('toast', message: 'Delete failed: ' . $e->getMessage(), type: 'error');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }
}
```

- [ ] **Step 2: Create `sale-returns/index.blade.php`**

Model on `resources/views/sales/index.blade.php` (read it first). Use the same outer layout, page title `Sales Returns`, a button linking to `route('sale-returns.create')` labelled "New Return", and mount `<livewire:sale-returns.sale-return-table />` for the table.

If `sales/index.blade.php` is not present, fall back to any other `index.blade.php` under `resources/views/` (e.g., `purchases/index.blade.php`).

- [ ] **Step 3: Visual smoke test**

Run: `php artisan serve` then open `/sale-returns` as a super-admin user. Verify the table renders without errors.

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/SaleReturns/SaleReturnTable.php resources/views/sale-returns/index.blade.php
git commit -m "feat: SaleReturnTable PowerGrid + index page"
```

---

## Task 13: Create / Show / Print views

**Files:**
- Create: `resources/views/sale-returns/create.blade.php`
- Create: `resources/views/sale-returns/show.blade.php`
- Create: `resources/views/sale-returns/print.blade.php`

- [ ] **Step 1: Read sibling references**

Read `resources/views/sales/create.blade.php`, `resources/views/sales/show.blade.php`, and `resources/views/sales/print.blade.php` to copy the page wrapper / layout used by this project. Mirror its layout directive and section names exactly.

- [ ] **Step 2: Build `sale-returns/create.blade.php`**

Page contains an Alpine component with two states.

State A — search:
- Visible when `!$sale`.
- Input: `invoice_number` (text). Button: `Cari`.
- On click, POST to `route('ajax.sales.lookup')` (include CSRF token in headers). On `200`, populate Alpine state with `sale` + `items`. On `404`, show inline error.

State B — form (visible after lookup succeeds or when `$sale` was pre-loaded):
- Header: invoice number, customer, sale date.
- Items table rows iterate Alpine `items`:
  - Columns: Product (`product_name` + unit), Sold qty, Already returned, **Return qty** (input, `min=0`, `max=available_qty`, disabled when `available_qty === 0`), Refund (computed `final_price × return_qty`).
- Fields: `reason` (textarea), `notes` (textarea), `return_date` (defaults `now()` ISO).
- Footer: Total refund (sum), Submit button.
- On submit: POST `route('sale-returns.store')` as a normal form, including hidden `sale_id` and a flat array of `items[]` containing only rows where `return_qty > 0`, with `sale_item_id` + `quantity`. On success, the server redirects to `sale-returns.show`.

When `$sale` is passed from the controller via `$sale_id`, render with State B directly and seed Alpine `items` from the server-side `$sale->items` (compute `already_returned` and `available_qty` in the blade using `\App\Models\SaleReturnItem::where('sale_item_id', $item->id)->sum('quantity')`).

- [ ] **Step 3: Build `sale-returns/show.blade.php`**

Reuse the layout from `sales/show.blade.php`. Display:
- Header: return number, link to `route('sales.show', $return->sale_id)` for the original invoice, return date, creator, total refund.
- Items table: product, qty returned, unit price, subtotal.
- Reason and notes blocks.
- Actions: Print (link to `sale-returns.print`), Delete (form `DELETE` to `sale-returns.destroy` with confirm).

- [ ] **Step 4: Build `sale-returns/print.blade.php`**

Mirror `sales/print.blade.php` but title `RETURN RECEIPT`. Fields:
- Return number, original invoice number, date, cashier name, customer.
- Item lines: name, qty, unit price, subtotal.
- Total Refund.
- Reason (if any).
- Signed-off footer placeholder.

- [ ] **Step 5: Manual smoke test**

Start dev server. Walk through:
1. Sale Detail → "Retur" button (added in Task 14) → form pre-loaded → submit small return → redirected to show page.
2. From Returns index → "New Return" → search by invoice → form appears → submit.
3. Visit print URL — verify layout.

- [ ] **Step 6: Commit**

```bash
git add resources/views/sale-returns/create.blade.php resources/views/sale-returns/show.blade.php resources/views/sale-returns/print.blade.php
git commit -m "feat: sale-return create/show/print views"
```

---

## Task 14: Sale Detail "Retur" button + sidebar menu

**Files:**
- Modify: `resources/views/sales/show.blade.php`
- Modify: sidebar layout (location varies)

- [ ] **Step 1: Locate the sidebar layout**

Run: `Grep "Sales" in resources/views/components` and `resources/views/layouts` and `resources/views/partials` (look for files like `sidebar.blade.php` or `navigation.blade.php`). Open the matching file.

- [ ] **Step 2: Add "Returns" entry**

Within the existing super-admin / sales section of the sidebar (look for the Sales link or Purchases link as a reference), add a sibling entry pointing to `route('sale-returns.index')` labelled `Returns`. Wrap with the same role check as Purchases (look for `@if(auth()->user()->isSuperAdmin())` or similar — use whatever the project pattern is).

- [ ] **Step 3: Add Retur button to `sales/show.blade.php`**

Find the action bar (usually near print/delete buttons). Add:

```blade
@if($sale->status === \App\Enums\SaleStatus::COMPLETED && auth()->user()?->role === 'super_admin')
    <a href="{{ route('sale-returns.create', ['sale_id' => $sale->id]) }}"
       class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded">
        Retur
    </a>
@endif
```

If the project gates super-admin differently (e.g., via a custom `isSuperAdmin()` method or Gate), use that instead.

- [ ] **Step 4: Manual smoke check**

Reload a completed sale show page as super admin — confirm Retur button appears. Reload as a non-super-admin — confirm it does not.

- [ ] **Step 5: Commit**

```bash
git add resources/views/sales/show.blade.php <sidebar-file>
git commit -m "feat: add Retur action on sale detail and Returns sidebar entry"
```

---

## Task 15: Full test pass + manual QA

- [ ] **Step 1: Run the entire suite**

Run: `php artisan test`
Expected: All previous + new tests pass. Existing tests must remain green.

- [ ] **Step 2: Manual QA against spec §9**

Walk through every scenario in [spec §9](../specs/2026-05-11-sale-returns-design.md#9-manual-test-plan):

1. Partial return on COMPLETED → stock + finance recorded.
2. Second return → `available_qty` decreases; over-return rejected.
3. PENDING/CANCELLED → rejected.
4. Delete return → stock decremented, finance row gone.
5. Delete return when product was resold below returned qty → blocked.
6. Cancel sale that has returns → blocked.
7. Lookup endpoint returns correct `available_qty`.
8. Print view renders for new and old returns.

- [ ] **Step 3: Final commit (if any docs/fixes needed)**

If you adjusted anything in spec/plan to reflect reality, commit those last:

```bash
git add docs/superpowers/
git commit -m "docs: post-implementation notes for sale returns"
```

---

## Self-Review Summary

- **Spec coverage:** Each section of the spec has at least one task. Schema → Task 1; Models → Task 2; DTOs/Exception → Task 3; Finance integration → Task 4; Service create + guards → Tasks 5–7; Service delete → Task 8; Cancel guard → Task 9; FormRequest/Routes/Controllers/Lookup → Tasks 10–11; UI table + create/show/print → Tasks 12–13; Sale Detail button + sidebar → Task 14; QA → Task 15.
- **Placeholders:** None remain. Sidebar/role-gating notes are explicit "look this up by pattern X" rather than TBD.
- **Type consistency:** `SaleReturn`, `SaleReturnItem`, `SaleReturnService::createReturn / deleteReturn`, `SaleReturnData`, `SaleReturnItemData`, `recordExpenseFromReturn`, `voidTransaction`, `cancelSale` guard naming all match across tasks.
- **Open project assumption:** Tests assume `RefreshDatabase` works against the configured test connection and that `User`, `Product`, `Category`, `Unit` have working factories. If a factory is missing, add it before the test runs.

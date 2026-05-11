# Sale Returns (Retur Penjualan) — Design Spec

**Date:** 2026-05-11
**Status:** Approved (brainstorming phase)

## 1. Goal

Add a refund-based return feature tied to existing sales invoices. Super Admin selects a completed invoice, picks items and quantities to return, and the system:

- Records a `SaleReturn` referencing the original `Sale`.
- Restores product stock by the returned quantity.
- Auto-creates a `FinanceTransaction` expense (refund) for the refund amount.

Partial returns are allowed; the same invoice may be returned multiple times until each line item's returnable quantity is exhausted.

## 2. Scope

**In scope**
- Refund-only returns (no exchange).
- Partial returns (per item, custom qty).
- Multiple returns per invoice (bertahap).
- Returns only for `Sale.status = COMPLETED`.
- Auto-record expense in Finance (`Sales Refund` category).
- UI entry points: button on Sale Detail + standalone Returns page (index/create/show/print).
- Access: Super Admin only.

**Out of scope (not built)**
- Exchange / store credit.
- Time-window limits on returns.
- Proration of `Sale.global_discount` across returned items (refund is computed from `SaleItem.final_price`, ignoring global discount — see Trade-offs).
- Partial refunds against a previously cancelled invoice.

## 3. Data Model

### 3.1 `sale_returns`

| Column           | Type      | Notes                                          |
| ---------------- | --------- | ---------------------------------------------- |
| `id`             | bigint PK |                                                |
| `return_number`  | string    | Unique. Format `RET.YYMMDD.0001`.              |
| `sale_id`        | FK sales  | `restrictOnDelete`.                            |
| `created_by`     | FK users  | `restrictOnDelete`.                            |
| `return_date`    | dateTime  | Indexed.                                       |
| `total_refund`   | bigint    | Sum of item subtotals at save time.            |
| `reason`         | text null | Why the return happened.                       |
| `notes`          | text null | Free-form.                                     |
| timestamps       |           |                                                |

### 3.2 `sale_return_items`

| Column            | Type             | Notes                                       |
| ----------------- | ---------------- | ------------------------------------------- |
| `id`              | bigint PK        |                                             |
| `sale_return_id`  | FK sale_returns  | `cascadeOnDelete`.                          |
| `sale_item_id`    | FK sale_items    | `restrictOnDelete`.                         |
| `product_id`      | FK products      | `restrictOnDelete`.                         |
| `quantity`        | integer          | Quantity being returned in this record.     |
| `unit_price`      | bigint           | Snapshot of `sale_items.final_price`.       |
| `subtotal`        | bigint           | `quantity * unit_price`.                    |
| timestamps        |                  |                                             |

### 3.3 Eloquent

- `SaleReturn` — `belongsTo Sale`, `belongsTo User as creator`, `hasMany SaleReturnItem as items`.
- `SaleReturnItem` — `belongsTo SaleReturn`, `belongsTo SaleItem`, `belongsTo Product`.
- `Sale::returns(): HasMany SaleReturn`.
- `SaleItem::returnItems(): HasMany SaleReturnItem`.

## 4. Service Layer

### 4.1 `App\Services\SaleReturnService`

```php
public function createReturn(SaleReturnData $data): SaleReturn
public function deleteReturn(SaleReturn $return): void
private function generateReturnNumber(): string  // RET.YYMMDD.0001
```

**`createReturn` transaction steps:**

1. Load `Sale` with `lockForUpdate`; verify `status === COMPLETED`, else throw `SaleReturnException::invalidSaleStatus`.
2. Load involved `SaleItem` rows (by `sale_item_id`) with `lockForUpdate`. Verify each `sale_item.sale_id === sale.id`, else throw `saleItemMismatch`.
3. For each input item, compute `alreadyReturned = SaleReturnItem::where('sale_item_id', $id)->sum('quantity')` inside the lock; `availableQty = saleItem.quantity - alreadyReturned`. If `input.quantity > availableQty`, throw `exceedsAvailableQty`. If `input.quantity < 1`, throw validation-style error.
4. Lock `Product` rows via `lockForUpdate`; `product->increment('quantity', $input.quantity)`.
5. Build row: `unit_price = saleItem.final_price`, `subtotal = unit_price * quantity`. Accumulate `totalRefund`.
6. Create `SaleReturn` with generated `return_number`, then batch insert `SaleReturnItem` rows.
7. Call `FinanceTransactionService::recordExpenseFromReturn($return)`.
8. Return `$return`.

**`deleteReturn` transaction steps:**

1. Load `items.product` with `lockForUpdate` on products.
2. For each item: if `product.quantity < item.quantity` throw `cannotReverseStock`; else `decrement`.
3. `FinanceTransactionService::voidTransaction($return)`.
4. Delete items (cascade handles this) and the return row.

### 4.2 DTOs

```php
class SaleReturnData {
    public function __construct(
        public int $sale_id,
        public int $created_by,
        public \DateTimeInterface $return_date,
        public ?string $reason,
        public ?string $notes,
        /** @var SaleReturnItemData[] */
        public array $items,
    ) {}
    public static function fromArray(array $a): self;
}

class SaleReturnItemData {
    public function __construct(
        public int $sale_item_id,
        public int $quantity,
    ) {}
}
```

### 4.3 Exceptions — `App\Exceptions\SaleReturnException`

- `invalidSaleStatus(string $status)`
- `saleItemMismatch(int $saleItemId, int $saleId)`
- `exceedsAvailableQty(string $product, int $requested, int $available)`
- `productNotFound(int $id)`
- `cannotReverseStock(string $product, int $needed, int $available)`
- `creationFailed(string $msg, array $context = [])`

### 4.4 Cross-service guards

- `SaleService::cancelSale` — before cancelling, check `$sale->returns()->exists()`; if true, throw a `SaleException` with a clear message. Sales with returns cannot be cancelled.

## 5. HTTP Layer

### 5.1 Routes (`routes/web.php`, inside `super_admin` group)

```php
Route::resource('sale-returns', SaleReturnController::class)->except(['edit', 'update']);
Route::get('sale-returns/{saleReturn}/print', [SaleReturnController::class, 'print'])
    ->name('sale-returns.print');

Route::post('ajax/sales/lookup', [\App\Http\Controllers\Api\SaleController::class, 'lookup'])
    ->name('ajax.sales.lookup');
```

### 5.2 `SaleReturnController`

- `index()` — `view('sale-returns.index')`.
- `create(Request $request)` — accepts optional `?sale_id=`; passes pre-loaded `Sale` (with items + available_qty) to view if present.
- `store(StoreSaleReturnRequest, SaleReturnService)` — JSON-aware, mirrors `SalesController::store`. Returns redirect or JSON `{success, data, print_url, redirect}`.
- `show(SaleReturn $saleReturn)` — eager load `items.product.unit`, `sale.customer`, `creator`.
- `destroy(SaleReturn $saleReturn, SaleReturnService)` — calls `deleteReturn`.
- `print(SaleReturn $saleReturn)` — eager load and render `sale-returns.print`.

### 5.3 `Api\SaleController::lookup`

`POST {invoice_number}` → respond with sale + items (each with `available_qty`, `final_price`, `product`) when `status = COMPLETED`; 404 otherwise.

### 5.4 `StoreSaleReturnRequest`

```php
'sale_id'              => ['required','exists:sales,id'],
'return_date'          => ['nullable','date'],
'reason'               => ['nullable','string','max:1000'],
'notes'                => ['nullable','string','max:1000'],
'items'                => ['required','array','min:1'],
'items.*.sale_item_id' => ['required','exists:sale_items,id'],
'items.*.quantity'     => ['required','integer','min:1'],
```

Business-rule validation (status, available_qty, sale_item ↔ sale linkage) handled in the service.

## 6. Finance Integration

Add to `FinanceTransactionService`:

```php
public function recordExpenseFromReturn(SaleReturn $return): void
```

- Category: `Sales Refund` (Expense), created on demand via `getOrCreateCategory`.
- `reference_type = SaleReturn::class`, `reference_id = $return->id`.
- `code = REF.YYMMDD.XXXX`.
- `description = "Refund Ret: {return_number} - Inv {sale.invoice_number}"`.
- `external_reference = return_number`.

Also:
- Add `'Sales Refund'` to the protected-categories list in `deleteTransaction()` so the finance row cannot be deleted manually.
- `voidTransaction($return)` is generic and reused on `deleteReturn`.

## 7. UI

### 7.1 Sale Detail (`resources/views/sales/show.blade.php`)

- Add **Retur** button visible when `status === COMPLETED` and user is super admin.
- Links to `route('sale-returns.create', ['sale_id' => $sale->id])`.

### 7.2 Sidebar

- Add **Returns** menu under Sales, super_admin section.

### 7.3 New views

- `sale-returns/index.blade.php` — hosts Livewire `SaleReturns\SaleReturnTable`:
  - Columns: `return_number`, `invoice_number` (link to `sales.show`), customer, `return_date`, `total_refund`, creator, actions.
  - Filters: date range, search by `return_number`/`invoice_number`.
  - Actions: show, delete (modal confirm).

- `sale-returns/create.blade.php` — two modes:
  - **Pre-loaded:** `?sale_id=` present → render form directly.
  - **Search:** input `invoice_number` + Cari → AJAX `ajax.sales.lookup` → render items.
  - Item table: `Product | Sold Qty | Already Returned | Return Qty (input) | Refund (auto)`. Rows with `available_qty = 0` disabled.
  - Fields: `reason`, `notes`. Footer: auto-sum total refund (JS). Submit posts to `sale-returns.store`.

- `sale-returns/show.blade.php` — header (return number, invoice ref, date, creator), items table, total refund, reason, notes. Actions: print, delete.

- `sale-returns/print.blade.php` — thermal-printer style, mirrors `sales/print`.

### 7.4 Livewire

- `App\Livewire\SaleReturns\SaleReturnTable` — modeled on `SalesTable`.
- Form stays plain Blade + Alpine/JS to match the existing POS pattern (no Livewire form).

## 8. Edge Cases & Invariants

1. **Multiple returns per invoice** — `available_qty = saleItem.quantity - SUM(returned)`; computed by lookup endpoint and re-checked under lock in service.
2. **Concurrency** — Sale, SaleItem, Product rows locked via `lockForUpdate` inside the create transaction.
3. **Global discount** — refund is `final_price * qty`, not prorated. Total refund may exceed `sale.total` when `global_discount > 0`. Documented trade-off; revisit if needed.
4. **Delete return** — reverses stock; if product stock is insufficient (sold again after return), throws `cannotReverseStock` and blocks the delete.
5. **Cancel sale that has returns** — blocked by guard in `SaleService::cancelSale`.
6. **Restrict on delete** — both `sales.id` and `sale_items.id` foreign keys use `restrictOnDelete`; deleting a sale/sale_item with related returns is impossible.
7. **Print template** — shows return header, items returned, total refund, reason; signed-off block for cashier/customer.

## 9. Manual Test Plan

- Partial return on a COMPLETED sale → stock incremented, `Sales Refund` finance row created with correct amount.
- Second return on the same invoice → `available_qty` decreases; over-return rejected.
- Attempt return on a PENDING or CANCELLED sale → rejected with `invalidSaleStatus`.
- Delete a return → stock decremented, finance row gone.
- Delete a return when product was resold below the returned qty → blocked with `cannotReverseStock`.
- Cancel a sale that has at least one return → blocked.
- Lookup endpoint returns correct `available_qty` per item.
- Print view renders for both new and old returns.

## 10. Files Touched / Created

**New**
- `database/migrations/2026_05_11_000001_create_sale_returns_table.php`
- `database/migrations/2026_05_11_000002_create_sale_return_items_table.php`
- `app/Models/SaleReturn.php`
- `app/Models/SaleReturnItem.php`
- `app/Services/SaleReturnService.php`
- `app/DTOs/SaleReturnData.php`
- `app/DTOs/SaleReturnItemData.php`
- `app/Exceptions/SaleReturnException.php`
- `app/Http/Controllers/SaleReturnController.php`
- `app/Http/Controllers/Api/SaleController.php` (lookup)
- `app/Http/Requests/StoreSaleReturnRequest.php`
- `app/Livewire/SaleReturns/SaleReturnTable.php`
- `resources/views/sale-returns/{index,create,show,print}.blade.php`
- `resources/views/livewire/sale-returns/sale-return-table.blade.php`

**Modified**
- `app/Models/Sale.php` (add `returns()` relation)
- `app/Models/SaleItem.php` (add `returnItems()` relation)
- `app/Services/SaleService.php` (`cancelSale` guard)
- `app/Services/FinanceTransactionService.php` (`recordExpenseFromReturn`, protect `Sales Refund` category)
- `routes/web.php` (routes inside `super_admin` group + ajax lookup)
- `resources/views/sales/show.blade.php` (Retur button)
- Sidebar layout (add Returns menu link)

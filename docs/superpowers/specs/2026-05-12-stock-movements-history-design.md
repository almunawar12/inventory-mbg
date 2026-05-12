# Stock Movements History — Design Spec

**Date:** 2026-05-12
**Owner:** tanamteam
**Status:** Draft (pending implementation plan)

## 1. Goal

Provide an admin-facing view of inventory stock movements (incoming and outgoing) so super admins can audit how product quantities change over time. Two views:

1. **Global stock movement log** — chronological list of every IN/OUT movement across all products, with filters.
2. **Per-product stock card** — drill-down ledger for a single product, with running balance.

## 2. Scope

### In scope
- Read-only reporting views derived from existing tables (`purchases`, `purchase_items`, `sales`, `sale_items`, `sale_returns`, `sale_return_items`).
- Filters: date range (preset + custom), movement type (IN/OUT/All), product (SKU/name search), source (purchase/sale/return/all).
- Excel + PDF export for both views, matching the pattern used by `ProductReport`.
- Super admin access only (`super_admin` middleware).
- Sidebar navigation entry under Reports.

### Out of scope
- New `stock_movements` ledger table. Data is queried live from source tables.
- Manual stock adjustments (no dedicated source for them yet).
- Draft/cancelled purchases or sales. Only "settled" movements count.
- Non-super-admin access.

## 3. Data Model & Sources

No schema changes. Three logical movement sources are unioned at query time:

| Source           | Table(s)                                    | Filter                                | Type | Date column           |
|------------------|---------------------------------------------|---------------------------------------|------|------------------------|
| Purchase received| `purchase_items` JOIN `purchases`           | `purchases.received_at IS NOT NULL`   | IN   | `purchases.received_at`|
| Sale completed   | `sale_items` JOIN `sales`                   | `sales.status = 'completed'`          | OUT  | `sales.completed_at`   |
| Sale return      | `sale_return_items` JOIN `sale_returns`     | (all return rows; tighten if status column exists) | IN | `sale_returns.return_date` (or equivalent) |

**Unified row shape** (output of the service):

```
occurred_at      datetime
type             enum('IN','OUT')
source           enum('purchase','sale','return')
reference_id     bigint        // FK to purchase/sale/sale_return id
reference_no     string        // invoice / return number
reference_url    string        // route to detail page
product_id       bigint
sku              string
product_name     string
qty              integer       // always positive; sign implied by `type`
unit_price       integer|null
line_total       integer|null  // qty * unit_price (or stored line total)
```

Implementation note: each leg SELECTs into this shape with constant literals for `type` and `source`; the service unions them via `unionAll`, wraps as a subquery, then applies outer filters / ordering / pagination.

## 4. Architecture

### 4.1 Service

`app/Services/StockMovementService.php`

Methods:
- `query(array $filters): \Illuminate\Database\Query\Builder` — builds the unified subquery and applies filters that survive the union wrap (date range, type, source, product search).
- `paginate(array $filters, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator` — for the global log UI.
- `collect(array $filters): \Illuminate\Support\Collection` — for export (no pagination).
- `productLedger(int $productId, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): array` — returns `['opening' => int, 'rows' => Collection, 'closing' => int]`.
- `totals(array $filters): array` — returns `['in' => int, 'out' => int, 'net' => int]` for footer display.

**Filter array shape:**

```
[
  'start'          => CarbonInterface,    // inclusive (UTC)
  'end'            => CarbonInterface,    // inclusive (UTC)
  'type'           => 'all'|'in'|'out',
  'source'         => 'all'|'purchase'|'sale'|'return',
  'product_search' => ?string,            // matches SKU or name, ILIKE
  'product_id'     => ?int,               // for per-product card
]
```

**Filter push-down:** date range, source restriction, and `product_id` are pushed into each leg before union (faster, smaller intermediate set). `type` and `product_search` may be applied either in-leg or in the outer wrapper; default to outer for simplicity.

**Opening-balance algorithm (ledger):**

Given current `products.quantity` (P) and net movement strictly after `end` (N_after):

```
opening_at_start = P - net_in_window - N_after
running_balance[i] = opening_at_start + cumulative_net_up_to_row_i
```

Where `net_in_window` = sum(IN) − sum(OUT) within `[start, end]`.

This anchors the ledger to the canonical `products.quantity` rather than reconstructing from zero (which would diverge from reality if there were untracked adjustments).

### 4.2 Livewire components

**`App\Livewire\Reports\StockMovementReport`** (global log)

State:
- `dateFilter: string` (from `DatePeriod` enum — reuse existing)
- `customStartDate: ?string`, `customEndDate: ?string`
- `type: string = 'all'`
- `source: string = 'all'`
- `productSearch: string = ''` (debounce 300ms)

Methods:
- `mount()` — defaults to current month, matching `ProductReport`.
- `updateCustomRange(start, end)` — same pattern as `ProductReport`.
- `exportExcel()`, `exportPdf()`.
- `render()` — passes paginator + totals to view.

View: `resources/views/livewire/reports/stock-movement-report.blade.php`
- Filter bar (date preset, custom range picker, type select, source select, product search input).
- Table columns: Tanggal, Jenis (badge IN/OUT), Sumber, No. Ref (link), SKU, Produk, Qty, Harga, Total.
- Footer summary: Total IN, Total OUT, Net.
- Per-row action: link "Kartu Stok" → `reports.stock-movements.product` with `product` param.

**`App\Livewire\Reports\ProductStockCard`** (per-product ledger)

State:
- `product: Product` (route-model binding)
- `dateFilter`, `customStartDate`, `customEndDate` (same as global)
- `type: string = 'all'` (optional)

Methods:
- `mount(Product $product)`
- `updateCustomRange(...)`
- `exportExcel()`, `exportPdf()`
- `render()` — passes `opening`, `rows` (with running balance computed in PHP after fetch), `closing`.

View: `resources/views/livewire/reports/product-stock-card.blade.php`
- Header: product name, SKU, current stock.
- Filter bar (date only by default).
- Opening balance row at top.
- Table columns: Tanggal, Jenis, Sumber, No. Ref, Qty, Saldo Berjalan.
- Closing balance row at bottom.

### 4.3 Routes

In `routes/web.php`, inside the existing super-admin `Route::prefix('reports')->name('reports.')->group(...)`:

```php
Route::get('stock-movements', \App\Livewire\Reports\StockMovementReport::class)
    ->name('stock-movements');
Route::get('stock-movements/products/{product}', \App\Livewire\Reports\ProductStockCard::class)
    ->name('stock-movements.product');
```

### 4.4 Exports

- `app/Exports/StockMovementExport.php` — implements `FromCollection`, `WithHeadings`, `WithMapping`, `ShouldAutoSize`. Mirrors `ProductReportExport`.
- `app/Exports/ProductStockCardExport.php` — same pattern, includes opening + running balance columns.
- PDF views: `resources/views/exports/pdf/stock-movement.blade.php` and `product-stock-card.blade.php`. Landscape A4, same header/footer style as `product-report.blade.php`.

### 4.5 Navigation

Add entry to sidebar Reports group: "Riwayat Stok" → `reports.stock-movements`. File location depends on existing sidebar partial (to be located during implementation; likely `resources/views/layouts/*` or a Livewire navigation component).

## 5. Edge Cases

- **Purchase not yet received** (`received_at IS NULL`) — excluded.
- **Sale not completed** (`status != 'completed'` or `completed_at IS NULL`) — excluded.
- **Sale return without a status column** — include all; if a status column exists, restrict to settled returns.
- **Deleted product** — if `products` row is hard-deleted, the JOIN drops the row. Use a LEFT JOIN and fall back to snapshot fields on `*_items` (SKU/name) if present; otherwise show "(produk dihapus)". Confirm during implementation which columns `purchase_items` / `sale_items` / `sale_return_items` snapshot.
- **Same-second timestamps** — secondary sort by `source`, then `reference_id`, then item PK to keep ordering stable.
- **Timezone** — store/query in UTC; render in `Asia/Jakarta` (same as `ProductReport`).
- **Large date ranges** — paginate global log at 25/page. Export uses `collect()` which loads all rows; document as acceptable for current data volume, revisit if it grows.

## 6. Testing

Feature tests (`tests/Feature/Reports/`):
- `StockMovementReportTest`
  - Guards: non-super-admin gets 403.
  - Renders with seeded data: 1 purchase received + 1 sale completed + 1 sale return → 3 rows, correct totals.
  - Filter `type=in` returns 2 rows; `type=out` returns 1.
  - Filter `source=purchase` returns 1.
  - Product search by SKU narrows results.
  - Date range excludes outside-window rows.
- `ProductStockCardTest`
  - Opening balance + running balance reconcile to `products.quantity` at the end of the window.
  - Excludes other products' movements.

Unit test for `StockMovementService::query()` shape (column names, type/source constants).

## 7. Implementation Order (preview, finalised in plan)

1. `StockMovementService` + unit test for query shape and filters.
2. Routes + `StockMovementReport` Livewire component + blade view.
3. `ProductStockCard` component + view, including opening-balance math.
4. Excel + PDF exports.
5. Sidebar nav entry.
6. Feature tests + manual smoke test.

## 8. Open Questions

- Does `sale_returns` have a status column that should gate inclusion? Verify in implementation; default to including all rows if no status exists.
- Which fields does `sale_return_items` snapshot (qty, unit_price)? Confirm before wiring the export columns.
- Confirm sidebar location and styling pattern for the new nav entry.

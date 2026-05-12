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

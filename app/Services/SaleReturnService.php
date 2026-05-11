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

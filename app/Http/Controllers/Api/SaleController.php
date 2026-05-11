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

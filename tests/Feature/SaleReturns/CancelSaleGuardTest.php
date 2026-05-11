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
            'invoice_number' => 'INV.260511.' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'created_by' => $user->id,
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

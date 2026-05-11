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

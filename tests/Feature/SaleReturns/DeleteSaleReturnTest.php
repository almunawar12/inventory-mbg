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
            'invoice_number'  => 'INV.260511.' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
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

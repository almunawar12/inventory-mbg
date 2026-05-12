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

    public function test_product_ledger_running_balance_anchors_to_current_stock(): void
    {
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

    private function seedThreeMovements(): void
    {
        $user     = $this->makeUser();
        $product  = $this->makeProduct(stock: 8);
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
}

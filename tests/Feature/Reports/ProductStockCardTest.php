<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Unit;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SaleItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductStockCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_ledger_with_running_balance(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $product = Product::factory()->create([
            'quantity'       => 7,
            'purchase_price' => 5000,
            'selling_price'  => 10000,
            'category_id'    => Category::factory()->create()->id,
            'unit_id'        => Unit::factory()->create()->id,
        ]);

        $supplier = Supplier::factory()->create();
        $purchase = Purchase::create([
            'invoice_number' => 'PO-X', 'supplier_id' => $supplier->id,
            'purchase_date'  => '2026-05-01', 'total' => 50000,
            'status' => PurchaseStatus::RECEIVED, 'created_by' => $user->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $purchase->id, 'product_id' => $product->id,
            'quantity' => 10, 'unit_price' => 5000, 'subtotal' => 50000,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'SO-X', 'created_by' => $user->id,
            'sale_date' => '2026-05-05', 'status' => SaleStatus::COMPLETED,
            'subtotal' => 30000, 'total_discount' => 0, 'total' => 30000,
            'cash_received' => 30000, 'change' => 0, 'payment_method' => PaymentMethod::CASH,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id,
            'quantity' => 3, 'cost_price' => 5000, 'unit_price' => 10000,
            'discount' => 0, 'final_price' => 10000, 'subtotal' => 30000,
        ]);

        $this->actingAs($user)
            ->get(route('reports.stock-movements.product', $product))
            ->assertOk()
            ->assertSee('PO-X')
            ->assertSee('SO-X')
            ->assertSeeText('Saldo Berjalan');
    }
}

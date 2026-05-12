<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StockMovementReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_is_forbidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('reports.stock-movements'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->get(route('reports.stock-movements'))
            ->assertOk();
    }

    public function test_global_log_renders_rows_with_filters(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $product = \App\Models\Product::factory()->create([
            'quantity'       => 8,
            'purchase_price' => 5000,
            'selling_price'  => 10000,
            'category_id'    => \App\Models\Category::factory()->create()->id,
            'unit_id'        => \App\Models\Unit::factory()->create()->id,
        ]);

        $supplier = \App\Models\Supplier::factory()->create();
        $purchase = \App\Models\Purchase::create([
            'invoice_number' => 'PO-NAV', 'supplier_id' => $supplier->id,
            'purchase_date' => now()->format('Y-m-d'), 'total' => 50000,
            'status' => \App\Enums\PurchaseStatus::RECEIVED, 'created_by' => $user->id,
        ]);
        \App\Models\PurchaseItem::create([
            'purchase_id' => $purchase->id, 'product_id' => $product->id,
            'quantity' => 10, 'unit_price' => 5000, 'subtotal' => 50000,
        ]);

        $sale = \App\Models\Sale::create([
            'invoice_number' => 'SO-NAV', 'created_by' => $user->id,
            'sale_date' => now(), 'status' => \App\Enums\SaleStatus::COMPLETED,
            'subtotal' => 20000, 'total_discount' => 0, 'total' => 20000,
            'cash_received' => 20000, 'change' => 0,
            'payment_method' => \App\Enums\PaymentMethod::CASH,
        ]);
        \App\Models\SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id,
            'quantity' => 2, 'cost_price' => 5000, 'unit_price' => 10000,
            'discount' => 0, 'final_price' => 10000, 'subtotal' => 20000,
        ]);

        $this->actingAs($user)
            ->get(route('reports.stock-movements'))
            ->assertOk()
            ->assertSee('PO-NAV')
            ->assertSee('SO-NAV')
            ->assertSeeText('Total Masuk');
    }
}

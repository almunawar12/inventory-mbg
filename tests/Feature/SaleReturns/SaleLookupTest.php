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
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_returns_items_with_available_qty(): void
    {
        $user     = User::factory()->create(['role' => 'super_admin']);
        $unit     = Unit::factory()->create();
        $category = Category::factory()->create();
        $product  = Product::factory()->create([
            'quantity' => 0, 'selling_price' => 10000, 'purchase_price' => 5000,
            'category_id' => $category->id, 'unit_id' => $unit->id,
        ]);
        $sale = Sale::create([
            'invoice_number' => 'INV.260511.0007', 'created_by' => $user->id,
            'sale_date' => now(), 'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 30000, 'global_discount' => 0, 'total_discount' => 0,
            'total' => 30000, 'cash_received' => 30000, 'change' => 0,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 3,
            'cost_price' => 5000, 'unit_price' => 10000, 'discount' => 0,
            'final_price' => 10000, 'subtotal' => 30000,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('ajax.sales.lookup'), ['invoice_number' => 'INV.260511.0007']);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('sale.invoice_number', 'INV.260511.0007');
        $response->assertJsonPath('items.0.available_qty', 3);
    }

    public function test_lookup_404_when_not_completed(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        Sale::create([
            'invoice_number' => 'INV.260511.0008', 'created_by' => $user->id,
            'sale_date' => now(), 'status' => SaleStatus::PENDING,
            'payment_method' => PaymentMethod::CASH,
            'subtotal' => 0, 'global_discount' => 0, 'total_discount' => 0,
            'total' => 0, 'cash_received' => 0, 'change' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('ajax.sales.lookup'), ['invoice_number' => 'INV.260511.0008'])
            ->assertStatus(404);
    }
}

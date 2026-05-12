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
}

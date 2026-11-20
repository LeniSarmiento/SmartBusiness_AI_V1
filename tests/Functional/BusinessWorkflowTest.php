<?php

namespace Tests\Functional;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    public function test_administrator_can_run_the_complete_sales_lifecycle(): void
    {
        $registration = $this->postJson('/api/v1/auth/register', [
            'name' => 'Functional Admin',
            'email' => 'functional@example.com',
            'password' => 'FunctionalPassword123',
            'password_confirmation' => 'FunctionalPassword123',
            'organization_name' => 'Functional Company',
        ])->assertCreated();

        $organizationId = $registration->json('data.memberships.0.organization.id');
        $base = "/api/v1/organizations/{$organizationId}";

        $categoryId = $this->postJson("{$base}/categories", [
            'name' => 'Technology',
            'description' => 'Functional category',
        ])->assertCreated()->json('data.id');

        $supplierId = $this->postJson("{$base}/suppliers", [
            'name' => 'Functional Supplier',
            'email' => 'supplier@example.com',
        ])->assertCreated()->json('data.id');

        $customerId = $this->postJson("{$base}/customers", [
            'name' => 'Functional Customer',
            'email' => 'customer@example.com',
        ])->assertCreated()->json('data.id');

        $productId = $this->postJson("{$base}/products", [
            'name' => 'Functional Keyboard',
            'sku' => 'FUNC-001',
            'purchase_price' => '50.00',
            'sale_price' => '100.00',
            'tax_rate_bps' => 1900,
            'minimum_stock' => 2,
            'category_id' => $categoryId,
            'supplier_id' => $supplierId,
        ])->assertCreated()->assertJsonPath('data.current_stock', 0)->json('data.id');

        $this->postJson("{$base}/inventory-movements", [
            'product_id' => $productId,
            'type' => 'entry',
            'quantity' => 10,
            'reason' => 'Initial functional stock',
        ])->assertCreated()->assertJsonPath('data.balance_after', 10);

        $sale = $this->postJson("{$base}/sales", [
            'request_key' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'notes' => 'Functional browser-equivalent sale',
            'items' => [['product_id' => $productId, 'quantity' => 3]],
        ])->assertCreated()
            ->assertJsonPath('data.customer_name', 'Functional Customer')
            ->assertJsonPath('data.subtotal', '300.00')
            ->assertJsonPath('data.tax', '57.00')
            ->assertJsonPath('data.total', '357.00')
            ->json('data');

        $this->assertSame(7, Product::findOrFail($productId)->current_stock);
        $this->getJson("{$base}/customers/{$customerId}/sales")
            ->assertOk()
            ->assertJsonPath('data.0.id', $sale['id']);
        $this->getJson("{$base}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.sales_count_month', 1)
            ->assertJsonPath('data.products_sold_month', 3);

        $this->postJson("{$base}/sales/{$sale['id']}/cancel", [
            'reason' => 'Functional cancellation test',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(10, Product::findOrFail($productId)->current_stock);
        $this->assertSame('cancelled', Sale::findOrFail($sale['id'])->status->value);
        $this->assertSame(1, InventoryMovement::where('type', 'cancellation')->where('sale_id', $sale['id'])->count());
        $this->getJson("{$base}/dashboard")->assertOk()->assertJsonPath('data.sales_count_month', 0);
    }
}

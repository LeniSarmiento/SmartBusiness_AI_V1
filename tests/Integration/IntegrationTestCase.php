<?php

namespace Tests\Integration;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    use DatabaseMigrations;

    protected Organization $organization;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->organization = Organization::create(['name' => 'Integration company']);
        $this->admin = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'role_id' => Role::where('name', 'admin')->firstOrFail()->id,
        ]);
    }

    protected function product(int $stock = 0): Product
    {
        $product = Product::create([
            'organization_id' => $this->organization->id,
            'name' => 'Integration product',
            'sku' => fake()->unique()->bothify('INT-#####'),
            'purchase_price' => '4.00',
            'sale_price' => '10.05',
            'tax_rate_bps' => 1900,
            'minimum_stock' => 2,
        ]);

        if ($stock > 0) {
            app(InventoryService::class)->adjust($this->organization, $this->admin, [
                'product_id' => $product->id,
                'type' => 'entry',
                'quantity' => $stock,
                'reason' => 'Integration opening stock',
            ]);
        }

        return $product->refresh();
    }
}

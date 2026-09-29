<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class BusinessTestCase extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->organization = Organization::create(['name' => 'Test company']);
        $this->admin = $this->member('admin');
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    protected function member(string $role, ?Organization $organization = null): User
    {
        $user = User::factory()->create();
        OrganizationMembership::create(['organization_id' => ($organization ?? $this->organization)->id, 'user_id' => $user->id, 'role_id' => Role::where('name', $role)->firstOrFail()->id]);

        return $user;
    }

    protected function product(int $stock = 10, ?Organization $organization = null): Product
    {
        $org = $organization ?? $this->organization;
        $product = Product::create(['organization_id' => $org->id, 'name' => 'Teclado', 'sku' => fake()->unique()->bothify('SKU-########'), 'purchase_price' => '5.00', 'sale_price' => '10.05', 'tax_rate_bps' => 1900, 'minimum_stock' => 2]);
        if ($stock) {
            $user = $organization ? $this->member('admin', $organization) : $this->admin;
            app(InventoryService::class)->adjust($org, $user, ['product_id' => $product->id, 'type' => 'entry', 'quantity' => $stock, 'reason' => 'Opening stock']);
        }

        return $product->refresh();
    }

    protected function endpoint(string $path): string
    {
        return '/api/v1/organizations/'.$this->organization->id.'/'.$path;
    }
}

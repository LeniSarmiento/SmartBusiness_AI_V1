<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Role;
use Illuminate\Support\Str;

class AuthorizationTest extends BusinessTestCase
{
    public function test_other_organizations_are_inaccessible(): void
    {
        $other = Organization::create(['name' => 'Other']);
        $product = $this->product(5, $other);
        $this->actingAs($this->admin)->getJson('/api/v1/organizations/'.$other->id.'/products')->assertNotFound();
        $this->getJson($this->endpoint('products/'.$product->id))->assertNotFound();
        $this->patchJson($this->endpoint('products/'.$product->id), ['name' => 'Attacked'])->assertNotFound();
        $this->deleteJson($this->endpoint('products/'.$product->id))->assertNotFound();
        $this->getJson($this->endpoint('products'))->assertJsonCount(0, 'data');
    }

    public function test_seller_cannot_change_stock_prices_or_roles_and_cannot_see_costs(): void
    {
        $product = $this->product();
        $this->actingAs($this->member('seller'))->getJson($this->endpoint('products'))->assertOk()->assertJsonMissingPath('data.0.purchase_price');
        $this->patchJson($this->endpoint('products/'.$product->id), ['sale_price' => '1.00'])->assertForbidden();
        $this->postJson($this->endpoint('inventory-movements'), ['product_id' => $product->id, 'type' => 'entry', 'quantity' => 1, 'reason' => 'Bad adjustment'])->assertForbidden();
        $this->getJson($this->endpoint('members'))->assertForbidden();
        $this->getJson($this->endpoint('dashboard'))->assertForbidden();
    }

    public function test_cross_tenant_category_is_rejected(): void
    {
        $other = Organization::create(['name' => 'Other']);
        $category = Category::create(['organization_id' => $other->id, 'name' => 'Other category']);
        $this->actingAs($this->admin)->postJson($this->endpoint('products'), ['name' => 'Invalid', 'sku' => 'BAD', 'sale_price' => '10.00', 'purchase_price' => '5.00', 'category_id' => $category->id])->assertUnprocessable();
    }

    public function test_stock_and_tenant_cannot_be_mass_assigned(): void
    {
        $product = $this->product();
        $this->actingAs($this->admin)->patchJson($this->endpoint('products/'.$product->id), ['current_stock' => 999])->assertUnprocessable();
        $this->patchJson($this->endpoint('products/'.$product->id), ['organization_id' => 999])->assertUnprocessable();
    }

    public function test_last_administrator_cannot_be_demoted(): void
    {
        $id = $this->admin->memberships()->firstOrFail()->id;
        $this->actingAs($this->admin)->patchJson($this->endpoint('members/'.$id), ['role' => 'seller'])->assertConflict();
    }

    public function test_admin_can_create_member_and_assign_role(): void
    {
        $response = $this->actingAs($this->admin)->postJson($this->endpoint('members'), ['name' => 'Seller', 'email' => 'seller@example.com', 'password' => 'SellerPassword123', 'role' => 'seller'])->assertCreated();
        $this->patchJson($this->endpoint('members/'.$response->json('data.id')), ['role' => 'manager'])->assertOk();
    }

    public function test_cross_tenant_attacks_are_blocked_across_all_modules(): void
    {
        $other = Organization::create(['name' => 'Empresa objetivo']);
        $otherAdmin = $this->member('admin', $other);
        $otherProduct = $this->product(5, $other);
        $otherMembership = $otherAdmin->memberships()->where('organization_id', $other->id)->firstOrFail();
        $otherInvitation = OrganizationInvitation::create([
            'organization_id' => $other->id,
            'invited_by' => $otherAdmin->id,
            'email' => 'target@example.com',
            'role_id' => Role::where('name', 'seller')->firstOrFail()->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDay(),
        ]);

        foreach (['dashboard', 'customers', 'suppliers', 'categories', 'products', 'inventory-movements', 'sales', 'members', 'invitations', 'ai/tools', 'security-audit-logs'] as $path) {
            $this->actingAs($this->admin)
                ->getJson("/api/v1/organizations/{$other->id}/{$path}")
                ->assertNotFound();
        }

        $this->postJson($this->endpoint('inventory-movements'), [
            'product_id' => $otherProduct->id,
            'type' => 'entry',
            'quantity' => 1,
            'reason' => 'Intento entre empresas',
        ])->assertUnprocessable();
        $this->postJson($this->endpoint('sales'), [
            'request_key' => (string) Str::uuid(),
            'items' => [['product_id' => $otherProduct->id, 'quantity' => 1]],
        ])->assertUnprocessable();
        $this->patchJson($this->endpoint("members/{$otherMembership->id}"), ['role' => 'seller'])->assertNotFound();
        $this->deleteJson($this->endpoint("invitations/{$otherInvitation->id}"))->assertNotFound();
        $this->postJson($this->endpoint('ai/tools/execute'), [
            'tool' => 'adjust_inventory',
            'arguments' => [
                'product_id' => $otherProduct->id,
                'type' => 'entry',
                'quantity' => 1,
                'reason' => 'Intento IA entre empresas',
            ],
        ])->assertUnprocessable();

        $this->assertSame(5, $otherProduct->refresh()->current_stock);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Models\Sale;
use Illuminate\Support\Str;

class SalesInventoryTest extends BusinessTestCase
{
    private function saleData(int $productId, int $quantity = 2): array
    {
        return ['request_key' => (string) Str::uuid(), 'items' => [['product_id' => $productId, 'quantity' => $quantity]]];
    }

    public function test_sale_calculates_totals_and_records_stock_atomically(): void
    {
        $product = $this->product();
        $response = $this->actingAs($this->admin)->postJson($this->endpoint('sales'), $this->saleData($product->id))->assertCreated();
        $response->assertJsonPath('data.subtotal', '20.10')->assertJsonPath('data.tax', '3.82')->assertJsonPath('data.total', '23.92');
        $this->assertSame(8, $product->refresh()->current_stock);
        $this->assertDatabaseHas('inventory_movements', ['sale_id' => $response->json('data.id'), 'quantity_delta' => -2, 'balance_after' => 8]);
    }

    public function test_sale_is_idempotent_and_conflicting_reuse_is_rejected(): void
    {
        $product = $this->product();
        $data = $this->saleData($product->id);
        $a = $this->actingAs($this->admin)->postJson($this->endpoint('sales'), $data)->assertCreated();
        $b = $this->postJson($this->endpoint('sales'), $data)->assertCreated();
        $this->assertSame($a->json('data.id'), $b->json('data.id'));
        $this->assertSame(8, $product->refresh()->current_stock);
        $data['items'][0]['quantity'] = 3;
        $this->postJson($this->endpoint('sales'), $data)->assertConflict();
    }

    public function test_insufficient_stock_rolls_back_all_lines(): void
    {
        $first = $this->product(10);
        $second = $this->product(1);
        $data = $this->saleData($first->id);
        $data['items'][] = ['product_id' => $second->id, 'quantity' => 2];
        $this->actingAs($this->admin)->postJson($this->endpoint('sales'), $data)->assertUnprocessable();
        $this->assertSame(10, $first->refresh()->current_stock);
        $this->assertSame(0, Sale::count());
        $this->assertSame(2, InventoryMovement::count());
    }

    public function test_duplicate_products_and_client_totals_are_rejected(): void
    {
        $product = $this->product();
        $data = $this->saleData($product->id);
        $data['items'][] = $data['items'][0];
        $this->actingAs($this->admin)->postJson($this->endpoint('sales'), $data)->assertUnprocessable();
        $data = $this->saleData($product->id);
        $data['total'] = '0.01';
        $this->postJson($this->endpoint('sales'), $data)->assertUnprocessable();
    }

    public function test_cross_tenant_product_and_customer_are_rejected(): void
    {
        $other = Organization::create(['name' => 'Other']);
        $foreignProduct = $this->product(4, $other);
        $local = $this->product();
        $customer = Customer::create(['organization_id' => $other->id, 'name' => 'Other customer']);
        $this->actingAs($this->admin)->postJson($this->endpoint('sales'), $this->saleData($foreignProduct->id))->assertUnprocessable();
        $data = $this->saleData($local->id);
        $data['customer_id'] = $customer->id;
        $this->postJson($this->endpoint('sales'), $data)->assertUnprocessable();
    }

    public function test_cancel_reverses_stock_exactly_once_and_preserves_history(): void
    {
        $product = $this->product();
        $sale = $this->actingAs($this->admin)->postJson($this->endpoint('sales'), $this->saleData($product->id))->assertCreated()->json('data');
        for ($i = 0; $i < 2; $i++) {
            $this->postJson($this->endpoint('sales/'.$sale['id'].'/cancel'), ['reason' => 'Customer requested cancellation'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        }
        $this->assertSame(10, $product->refresh()->current_stock);
        $this->assertSame(1, InventoryMovement::where('type', 'cancellation')->count());
        $this->assertDatabaseCount('sale_items', 1);
        $this->getJson($this->endpoint('dashboard'))->assertOk()->assertJsonPath('data.sales_count_month', 0);
    }

    public function test_seller_can_sell_but_cannot_cancel(): void
    {
        $product = $this->product();
        $sale = $this->actingAs($this->member('seller'))->postJson($this->endpoint('sales'), $this->saleData($product->id))->assertCreated()->json('data');
        $this->postJson($this->endpoint('sales/'.$sale['id'].'/cancel'), ['reason' => 'Not authorized'])->assertForbidden();
    }

    public function test_cancellation_restores_archived_product_so_returned_stock_is_visible(): void
    {
        $product = $this->product(1);
        $sale = $this->actingAs($this->admin)->postJson($this->endpoint('sales'), $this->saleData($product->id, 1))->assertCreated()->json('data');
        $this->deleteJson($this->endpoint('products/'.$product->id))->assertNoContent();
        $this->assertSoftDeleted($product);
        $this->postJson($this->endpoint('sales/'.$sale['id'].'/cancel'), ['reason' => 'Return the last unit'])->assertOk();
        $this->assertSame(1, $product->refresh()->current_stock);
        $this->assertFalse($product->trashed());
        $this->getJson($this->endpoint('products/'.$product->id))->assertOk();
    }

    public function test_idempotency_normalizes_numeric_identifiers(): void
    {
        $product = $this->product();
        $customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Customer']);
        $data = $this->saleData($product->id);
        $data['customer_id'] = $customer->id;
        $first = $this->actingAs($this->admin)->postJson($this->endpoint('sales'), $data)->assertCreated();
        $data['customer_id'] = (string) $customer->id;
        $data['items'][0]['product_id'] = (string) $product->id;
        $second = $this->postJson($this->endpoint('sales'), $data)->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(8, $product->refresh()->current_stock);
    }

    public function test_sales_preserve_product_price_and_name_snapshots(): void
    {
        $product = $this->product();
        $sale = $this->actingAs($this->admin)->postJson($this->endpoint('sales'), $this->saleData($product->id))->assertCreated()->json('data');
        $product->update(['name' => 'New name', 'sale_price' => '50.00']);
        $this->getJson($this->endpoint('sales/'.$sale['id']))->assertJsonPath('data.items.0.product_name', 'Teclado')->assertJsonPath('data.items.0.unit_price', '10.05');
    }

    public function test_manual_movements_reject_negative_stock_and_keep_history(): void
    {
        $product = $this->product(2);
        $this->actingAs($this->admin)->postJson($this->endpoint('inventory-movements'), ['product_id' => $product->id, 'type' => 'exit', 'quantity' => 3, 'reason' => 'Too much stock'])->assertUnprocessable();
        $this->postJson($this->endpoint('inventory-movements'), ['product_id' => $product->id, 'type' => 'adjustment', 'direction' => 'decrease', 'quantity' => 1, 'reason' => 'Damaged product'])->assertCreated();
        $this->assertSame(1, $product->refresh()->current_stock);
        $this->assertSame(1, (int) $product->movements()->sum('quantity_delta'));
    }

    public function test_product_with_stock_cannot_be_archived(): void
    {
        $product = $this->product(1);
        $this->actingAs($this->admin)->deleteJson($this->endpoint('products/'.$product->id))->assertConflict();
        $empty = $this->product(0);
        $this->deleteJson($this->endpoint('products/'.$empty->id))->assertNoContent();
        $this->assertSoftDeleted($empty);
    }
}

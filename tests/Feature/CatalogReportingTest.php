<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use App\Services\SaleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class CatalogReportingTest extends BusinessTestCase
{
    public function test_customer_crud_search_and_pagination(): void
    {
        $created = $this->actingAs($this->admin)->postJson($this->endpoint('customers'), ['name' => 'Acme', 'email' => 'acme@example.com'])->assertCreated()->json('data');
        $this->getJson($this->endpoint('customers?search=ACM'))->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson($this->endpoint('customers/'.$created['id']), ['phone' => '123'])->assertOk()->assertJsonPath('data.phone', '123');
        $this->getJson($this->endpoint('customers?per_page=1000'))->assertUnprocessable();
        $this->deleteJson($this->endpoint('customers/'.$created['id']))->assertNoContent();
        $this->getJson($this->endpoint('customers/'.$created['id']))->assertNotFound();
    }

    public function test_sku_unique_per_organization_including_archived_products(): void
    {
        $product = $this->product(0);
        $data = ['name' => 'New', 'sku' => $product->sku, 'sale_price' => '1.00', 'purchase_price' => '0.00'];
        $this->actingAs($this->admin)->postJson($this->endpoint('products'), $data)->assertUnprocessable();
        $product->delete();
        $this->postJson($this->endpoint('products'), $data)->assertUnprocessable();
    }

    public function test_database_rejects_negative_stock(): void
    {
        $product = $this->product();
        $this->expectException(QueryException::class);
        $product->forceFill(['current_stock' => -1])->save();
    }

    public function test_database_rejects_cross_tenant_foreign_key(): void
    {
        $other = Organization::create(['name' => 'Other']);
        $category = Category::create(['name' => 'Other', 'organization_id' => $other->id]);
        $product = $this->product();
        $this->expectException(QueryException::class);
        $product->update(['category_id' => $category->id]);
    }

    public function test_dashboard_uses_organization_timezone_and_excludes_other_companies(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-27 02:00:00', 'UTC'));
        $product = $this->product();
        $sale = app(SaleService::class)->create($this->organization, $this->admin, ['request_key' => (string) Str::uuid(), 'items' => [['product_id' => $product->id, 'quantity' => 1]]]);
        $sale->update(['sold_at' => '2026-09-27 01:00:00+00']);
        $response = $this->actingAs($this->admin)->getJson($this->endpoint('dashboard'))->assertOk();
        $this->assertSame('11.96', $response->json('data.sales_today'));
        $this->assertSame('2026-09-26', collect($response->json('data.trend'))->last()['day']);
        $other = Organization::create(['name' => 'Other']);
        $this->member('admin', $other);
        $this->assertSame(1, $response->json('data.sales_count_month'));
        $this->travelBack();
    }
}

<?php

namespace Tests\Feature;

use App\Events\LowStockDetected;
use App\Listeners\SendLowStockNotification;
use App\Models\AdjustmentReason;
use App\Models\Organization;
use App\Models\Product;
use App\Notifications\LowStockNotification;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class InventoryImprovementTest extends BusinessTestCase
{
    public function test_can_manage_adjustment_reasons(): void
    {
        $this->actingAs($this->admin);

        // Create reason
        $createResponse = $this->postJson($this->endpoint('adjustment-reasons'), [
            'name' => 'Merma por vencimiento',
            'type' => 'exit',
            'active' => true,
        ])->assertCreated();

        $reasonId = $createResponse->json('data.id');
        $this->assertEquals('Merma por vencimiento', $createResponse->json('data.name'));

        // List reasons
        $listResponse = $this->getJson($this->endpoint('adjustment-reasons'))
            ->assertOk();
        $this->assertCount(1, $listResponse->json('data'));

        // Update reason
        $this->putJson($this->endpoint("adjustment-reasons/{$reasonId}"), [
            'name' => 'Merma por daño',
            'active' => false,
        ])->assertOk();

        $this->assertDatabaseHas('adjustment_reasons', [
            'id' => $reasonId,
            'name' => 'Merma por daño',
            'active' => false,
        ]);

        // Delete reason
        $this->deleteJson($this->endpoint("adjustment-reasons/{$reasonId}"))
            ->assertNoContent();

        $this->assertDatabaseMissing('adjustment_reasons', ['id' => $reasonId]);
    }

    public function test_adjustment_reason_enforces_unique_name_per_organization(): void
    {
        $this->actingAs($this->admin);

        AdjustmentReason::create([
            'organization_id' => $this->organization->id,
            'name' => 'Inventario Inicial',
            'type' => 'entry',
            'active' => true,
        ]);

        $this->postJson($this->endpoint('adjustment-reasons'), [
            'name' => 'Inventario Inicial',
            'type' => 'entry',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_adjustment_reason_tenant_isolation(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org']);
        $otherReason = AdjustmentReason::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Ajuste Especial',
            'type' => 'adjustment',
            'active' => true,
        ]);

        $this->actingAs($this->admin);

        // Cannot update or delete another org's reason
        $this->putJson($this->endpoint("adjustment-reasons/{$otherReason->id}"), [
            'name' => 'Hacked',
        ])->assertNotFound();

        $this->deleteJson($this->endpoint("adjustment-reasons/{$otherReason->id}"))
            ->assertNotFound();
    }

    public function test_seller_cannot_manage_adjustment_reasons(): void
    {
        $seller = $this->member('seller');
        $this->actingAs($seller);

        $this->postJson($this->endpoint('adjustment-reasons'), [
            'name' => 'Test Reason',
            'type' => 'entry',
        ])->assertForbidden();
    }

    public function test_movement_stores_and_returns_reference_number(): void
    {
        $this->actingAs($this->admin);
        $product = $this->product(10);

        $response = $this->postJson($this->endpoint('inventory-movements'), [
            'product_id' => $product->id,
            'type' => 'entry',
            'quantity' => 5,
            'reason' => 'Compra según factura',
            'reference_number' => 'FAC-98765',
        ])->assertCreated();

        $this->assertEquals('FAC-98765', $response->json('data.reference_number'));

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'reference_number' => 'FAC-98765',
        ]);
    }

    public function test_movement_filtering(): void
    {
        $this->actingAs($this->admin);
        $user1 = $this->admin;
        $user2 = $this->member('manager');

        $prod1 = $this->product(10);
        $prod2 = $this->product(20);

        // Register movement with user2
        $this->actingAs($user2);
        $this->postJson($this->endpoint('inventory-movements'), [
            'product_id' => $prod2->id,
            'type' => 'exit',
            'quantity' => 2,
            'reason' => 'Muestra comercial',
            'reference_number' => 'REM-001',
        ])->assertCreated();

        $this->actingAs($this->admin);

        // Filter by product
        $res = $this->getJson($this->endpoint("inventory-movements?product_id={$prod2->id}"))->assertOk();
        foreach ($res->json('data') as $m) {
            $this->assertEquals($prod2->id, $m['product_id']);
        }

        // Filter by user
        $resUser = $this->getJson($this->endpoint("inventory-movements?user_id={$user2->id}"))->assertOk();
        $this->assertNotEmpty($resUser->json('data'));
        foreach ($resUser->json('data') as $m) {
            $this->assertEquals($user2->id, $m['user']['id']);
        }

        // Filter by type
        $resType = $this->getJson($this->endpoint('inventory-movements?type=exit'))->assertOk();
        $this->assertNotEmpty($resType->json('data'));
        foreach ($resType->json('data') as $m) {
            $this->assertEquals('exit', $m['type']);
        }

        // Filter by search
        $resSearch = $this->getJson($this->endpoint('inventory-movements?search=REM-001'))->assertOk();
        $this->assertCount(1, $resSearch->json('data'));
        $this->assertEquals('REM-001', $resSearch->json('data.0.reference_number'));
    }

    public function test_inventory_export_csv(): void
    {
        $this->actingAs($this->admin);
        $product = $this->product(15);

        $this->postJson($this->endpoint('inventory-movements'), [
            'product_id' => $product->id,
            'type' => 'entry',
            'quantity' => 10,
            'reason' => 'Ingreso de prueba para CSV',
            'reference_number' => 'CSV-REF-123',
        ])->assertCreated();

        $response = $this->get($this->endpoint('inventory-movements/export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('CSV-REF-123', $content);
        $this->assertStringContainsString('Ingreso de prueba para CSV', $content);
        $this->assertStringContainsString('Teclado', $content);
    }

    public function test_inactive_product_blocked_from_manual_movements(): void
    {
        $this->actingAs($this->admin);
        $product = $this->product(10);
        $product->update(['active' => false]);

        $this->postJson($this->endpoint('inventory-movements'), [
            'product_id' => $product->id,
            'type' => 'entry',
            'quantity' => 5,
            'reason' => 'Intento de ingreso a producto inactivo',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_low_stock_triggers_alert_and_event(): void
    {
        Event::fake([LowStockDetected::class]);
        $this->actingAs($this->admin);

        // Product with minimum_stock = 5, initial stock = 0
        $product = Product::create([
            'organization_id' => $this->organization->id,
            'name' => 'Mouse USB',
            'sku' => 'SKU-MOUSE-01',
            'purchase_price' => '3.00',
            'sale_price' => '8.00',
            'minimum_stock' => 5,
            'active' => true,
        ]);

        // Add 10 units
        app(InventoryService::class)->adjust($this->organization, $this->admin, [
            'product_id' => $product->id,
            'type' => 'entry',
            'quantity' => 10,
            'reason' => 'Stock inicial',
        ]);

        Event::assertNotDispatched(LowStockDetected::class);

        // Reduce stock down to 4 (which is <= minimum_stock 5)
        $this->postJson($this->endpoint('inventory-movements'), [
            'product_id' => $product->id,
            'type' => 'exit',
            'quantity' => 6,
            'reason' => 'Salida grande',
        ])->assertCreated();

        Event::assertDispatched(LowStockDetected::class, function ($event) use ($product) {
            return $event->product_id === $product->id
                && $event->current_stock === 4
                && $event->minimum_stock === 5;
        });

        // Stock alert record was created
        $this->assertDatabaseHas('stock_alerts', [
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'threshold_quantity' => 5,
            'resolved_at' => null,
        ]);

        // Stock alerts API lists it
        $alertsResponse = $this->getJson($this->endpoint('stock-alerts'))->assertOk();
        $this->assertCount(1, $alertsResponse->json('data'));
        $this->assertEquals($product->id, $alertsResponse->json('data.0.product_id'));
        $this->assertEquals(4, $alertsResponse->json('data.0.current_stock'));

        // If we add stock back up to 10 (> minimum_stock 5), alert is marked resolved
        $this->postJson($this->endpoint('inventory-movements'), [
            'product_id' => $product->id,
            'type' => 'entry',
            'quantity' => 6,
            'reason' => 'Reposición de stock',
        ])->assertCreated();

        $this->assertDatabaseMissing('stock_alerts', [
            'product_id' => $product->id,
            'resolved_at' => null,
        ]);
    }

    public function test_low_stock_notification_sent_to_managers(): void
    {
        Notification::fake();

        $manager = $this->member('manager');
        $seller = $this->member('seller');

        $event = new LowStockDetected(
            organization_id: $this->organization->id,
            product_id: 1,
            product_name: 'Monitor LED',
            product_sku: 'MON-01',
            current_stock: 1,
            minimum_stock: 5,
        );

        $listener = new SendLowStockNotification;
        $listener->handle($event);

        // Admin and Manager should receive the notification, Seller should not
        Notification::assertSentTo($this->admin, LowStockNotification::class);
        Notification::assertSentTo($manager, LowStockNotification::class);
        Notification::assertNotSentTo($seller, LowStockNotification::class);
    }

    public function test_inventory_valuation_report(): void
    {
        $this->actingAs($this->admin);

        // Create products and set stock via forceFill
        $prodA = Product::create([
            'organization_id' => $this->organization->id,
            'name' => 'Producto A',
            'sku' => 'VAL-A',
            'purchase_price' => '10.00',
            'sale_price' => '15.00',
            'minimum_stock' => 2,
            'active' => true,
        ]);
        $prodA->forceFill(['current_stock' => 10])->save();

        $prodB = Product::create([
            'organization_id' => $this->organization->id,
            'name' => 'Producto B',
            'sku' => 'VAL-B',
            'purchase_price' => '20.00',
            'sale_price' => '30.00',
            'minimum_stock' => 1,
            'active' => true,
        ]);
        $prodB->forceFill(['current_stock' => 5])->save();

        $response = $this->getJson($this->endpoint('reports/inventory-valuation'))
            ->assertOk();

        $summary = $response->json('data.summary');
        $this->assertEquals(2, $summary['total_products']);
        $this->assertEquals(15, $summary['total_units']);
        $this->assertEquals('200.00', $summary['total_cost_value']);
        $this->assertEquals('300.00', $summary['total_sale_value']);
        $this->assertEquals('100.00', $summary['potential_profit']);
    }

    public function test_inventory_valuation_requires_reports_read_permission(): void
    {
        $seller = $this->member('seller');
        $this->actingAs($seller);

        $this->getJson($this->endpoint('reports/inventory-valuation'))
            ->assertForbidden();
    }
}

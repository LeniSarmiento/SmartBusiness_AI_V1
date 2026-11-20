<?php

namespace Tests\Integration;

use App\Events\SaleCompleted;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleServiceTest extends IntegrationTestCase
{
    public function test_service_persists_sale_lines_stock_movement_and_domain_event(): void
    {
        Event::fake([SaleCompleted::class]);
        $product = $this->product(5);

        $sale = app(SaleService::class)->create($this->organization, $this->admin, [
            'request_key' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $this->assertSame('20.10', $sale->subtotal);
        $this->assertSame('3.82', $sale->tax);
        $this->assertSame('23.92', $sale->total);
        $this->assertSame(3, $product->refresh()->current_stock);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2]);
        $this->assertDatabaseHas('inventory_movements', ['sale_id' => $sale->id, 'quantity_delta' => -2, 'balance_after' => 3]);
        Event::assertDispatched(fn (SaleCompleted $event) => $event->saleId === $sale->id && $event->organizationId === $this->organization->id);
    }

    public function test_multi_line_failure_rolls_back_the_complete_service_transaction(): void
    {
        $available = $this->product(5);
        $insufficient = $this->product(1);
        $movementCount = InventoryMovement::count();

        try {
            app(SaleService::class)->create($this->organization, $this->admin, [
                'request_key' => (string) Str::uuid(),
                'items' => [
                    ['product_id' => $available->id, 'quantity' => 2],
                    ['product_id' => $insufficient->id, 'quantity' => 2],
                ],
            ]);
            $this->fail('The service accepted a sale with insufficient stock.');
        } catch (ValidationException) {
            $this->assertSame(5, $available->refresh()->current_stock);
            $this->assertSame(1, $insufficient->refresh()->current_stock);
            $this->assertSame(0, Sale::count());
            $this->assertSame($movementCount, InventoryMovement::count());
        }
    }
}

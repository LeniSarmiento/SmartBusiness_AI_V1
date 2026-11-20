<?php

namespace Tests\Integration;

use App\Enums\MovementType;
use App\Services\InventoryService;
use Illuminate\Validation\ValidationException;

class InventoryServiceTest extends IntegrationTestCase
{
    public function test_entry_and_exit_keep_product_balance_and_ledger_in_sync(): void
    {
        $product = $this->product();
        $service = app(InventoryService::class);

        $entry = $service->adjust($this->organization, $this->admin, [
            'product_id' => $product->id,
            'type' => 'entry',
            'quantity' => 8,
            'reason' => 'Received from supplier',
        ]);
        $exit = $service->adjust($this->organization, $this->admin, [
            'product_id' => $product->id,
            'type' => 'exit',
            'quantity' => 3,
            'reason' => 'Damaged units removed',
        ]);

        $this->assertSame(8, $entry->balance_after);
        $this->assertSame(5, $exit->balance_after);
        $this->assertSame(MovementType::Exit, $exit->type);
        $this->assertSame(5, $product->refresh()->current_stock);
        $this->assertSame(5, (int) $product->movements()->sum('quantity_delta'));
        $this->assertCount(2, $product->movements);
    }

    public function test_record_refuses_inventory_changes_without_a_transaction(): void
    {
        $product = $this->product();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Inventory changes require a transaction.');

        app(InventoryService::class)->record(
            $product,
            $this->admin,
            1,
            MovementType::Entry,
            'Invalid direct change',
        );
    }

    public function test_failed_exit_does_not_change_balance_or_create_a_movement(): void
    {
        $product = $this->product(2);
        $movementsBefore = $product->movements()->count();

        try {
            app(InventoryService::class)->adjust($this->organization, $this->admin, [
                'product_id' => $product->id,
                'type' => 'exit',
                'quantity' => 3,
                'reason' => 'More than available stock',
            ]);
            $this->fail('The service accepted an exit larger than available stock.');
        } catch (ValidationException) {
            $this->assertSame(2, $product->refresh()->current_stock);
            $this->assertSame($movementsBefore, $product->movements()->count());
        }
    }
}

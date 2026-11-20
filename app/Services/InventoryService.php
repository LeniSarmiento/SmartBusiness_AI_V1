<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Events\LowStockDetected;
use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private SecurityAuditService $audit) {}

    public function adjust(Organization $organization, User $user, array $data): InventoryMovement
    {
        return DB::transaction(function () use ($organization, $user, $data) {
            $product = Product::forOrganization($organization)->lockForUpdate()->findOrFail($data['product_id']);

            $manualTypes = [MovementType::Entry, MovementType::Exit, MovementType::Adjustment];
            if (in_array(MovementType::from($data['type']), $manualTypes, true) && ! $product->active) {
                throw ValidationException::withMessages(['product_id' => 'No se pueden registrar movimientos en productos inactivos.']);
            }

            $decrease = $data['type'] === 'exit' || ($data['type'] === 'adjustment' && $data['direction'] === 'decrease');
            $before = ['current_stock' => $product->current_stock];
            $movement = $this->record(
                $product,
                $user,
                (int) $data['quantity'] * ($decrease ? -1 : 1),
                MovementType::from($data['type']),
                $data['reason'],
                null,
                $data['reference_number'] ?? null,
            );

            $this->audit->record(
                $organization,
                $user,
                'inventory.adjusted',
                $product,
                $before,
                ['current_stock' => $movement->balance_after],
                [
                    'movement_id' => $movement->id,
                    'type' => $movement->type->value,
                    'quantity_delta' => $movement->quantity_delta,
                    'reason' => $movement->reason,
                    'reference_number' => $movement->reference_number,
                ]
            );

            $this->checkLowStock($product);

            return $movement;
        }, 3);
    }

    /** Caller must hold a FOR UPDATE product lock inside a transaction. */
    public function record(Product $product, User $user, int $delta, MovementType $type, string $reason, ?int $saleId = null, ?string $referenceNumber = null): InventoryMovement
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Inventory changes require a transaction.');
        }
        $balance = $product->current_stock + $delta;
        if ($delta === 0 || $balance < 0 || $balance > 2000000000) {
            throw ValidationException::withMessages(['stock' => "Stock insuficiente o cantidad inválida para {$product->name}. Disponible: {$product->current_stock}."]);
        }
        $product->forceFill(['current_stock' => $balance])->save();

        return InventoryMovement::create([
            'organization_id' => $product->organization_id, 'product_id' => $product->id,
            'user_id' => $user->id, 'sale_id' => $saleId, 'type' => $type,
            'quantity_delta' => $delta, 'balance_after' => $balance, 'reason' => $reason,
            'reference_number' => $referenceNumber, 'created_at' => now(),
        ]);
    }

    private function checkLowStock(Product $product): void
    {
        $product->refresh();
        if ($product->minimum_stock > 0 && $product->current_stock <= $product->minimum_stock) {
            $alert = StockAlert::updateOrCreate(
                ['organization_id' => $product->organization_id, 'product_id' => $product->id, 'resolved_at' => null],
                ['threshold_quantity' => $product->minimum_stock],
            );

            if (! $alert->notified_at) {
                LowStockDetected::dispatch(
                    $product->organization_id,
                    $product->id,
                    $product->name,
                    $product->sku,
                    $product->current_stock,
                    $product->minimum_stock,
                );
                $alert->update(['notified_at' => now()]);
            }
        } else {
            StockAlert::where('organization_id', $product->organization_id)
                ->where('product_id', $product->id)
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);
        }
    }
}

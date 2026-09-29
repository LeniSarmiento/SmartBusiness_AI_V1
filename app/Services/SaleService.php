<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\SaleStatus;
use App\Events\SaleCompleted;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(private InventoryService $inventory) {}

    public function create(Organization $organization, User $seller, array $data): Sale
    {
        return DB::transaction(function () use ($organization, $seller, $data) {
            // A per-organization lock gives sequential invoice numbers and a single lock order.
            $organization = Organization::lockForUpdate()->findOrFail($organization->id);
            $normalized = collect($data['items'])->map(fn ($item) => ['product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity']])->sortBy('product_id')->values()->all();
            $customerId = empty($data['customer_id']) ? null : (int) $data['customer_id'];
            $hash = hash('sha256', json_encode([$seller->id, $customerId, $data['notes'] ?? null, $normalized], JSON_THROW_ON_ERROR));
            $existing = Sale::forOrganization($organization)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'La clave de solicitud ya se utilizó para una venta diferente.');

                return $existing->load('items', 'seller');
            }
            $products = Product::forOrganization($organization)->whereIn('id', array_column($normalized, 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $customer = empty($data['customer_id']) ? null : Customer::forOrganization($organization)->where('active', true)->findOrFail($data['customer_id']);
            $lines = [];
            $subtotal = $tax = 0;
            foreach ($normalized as $item) {
                $product = $products->get($item['product_id']);
                if (! $product || ! $product->active || $product->current_stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => 'Un producto ya no está disponible o no tiene stock suficiente.']);
                }
                $lineSubtotal = Money::cents($product->sale_price) * $item['quantity'];
                $lineTax = Money::tax($lineSubtotal, $product->tax_rate_bps);
                $subtotal += $lineSubtotal;
                $tax += $lineTax;
                $lines[] = [
                    'organization_id' => $organization->id, 'product_id' => $product->id,
                    'product_name' => $product->name, 'product_sku' => $product->sku,
                    'quantity' => $item['quantity'], 'unit_price' => $product->sale_price,
                    'tax_rate_bps' => $product->tax_rate_bps, 'subtotal' => Money::decimal($lineSubtotal),
                    'tax' => Money::decimal($lineTax), 'total' => Money::decimal($lineSubtotal + $lineTax),
                ];
            }
            if ($subtotal + $tax > 9000000000000000) {
                throw ValidationException::withMessages(['items' => 'El importe de la venta supera el límite permitido.']);
            }
            $sale = Sale::create([
                'organization_id' => $organization->id, 'seller_id' => $seller->id,
                'customer_id' => $customer?->id, 'customer_name' => $customer?->name,
                'number' => $organization->next_sale_number, 'request_key' => $data['request_key'],
                'payload_hash' => $hash, 'status' => SaleStatus::Completed,
                'currency' => $organization->currency, 'subtotal' => Money::decimal($subtotal),
                'tax' => Money::decimal($tax), 'total' => Money::decimal($subtotal + $tax),
                'notes' => $data['notes'] ?? null, 'sold_at' => now(),
            ]);
            $organization->increment('next_sale_number');
            $sale->items()->createMany($lines);
            foreach ($normalized as $item) {
                $this->inventory->record($products[$item['product_id']], $seller, -$item['quantity'], MovementType::Sale, "Venta #{$sale->number}", $sale->id);
            }
            SaleCompleted::dispatch($sale->id, $organization->id);

            return $sale->load('items', 'seller');
        }, 3);
    }

    public function cancel(Organization $organization, User $user, int $saleId, string $reason): Sale
    {
        return DB::transaction(function () use ($organization, $user, $saleId, $reason) {
            Organization::lockForUpdate()->findOrFail($organization->id);
            $sale = Sale::forOrganization($organization)->lockForUpdate()->findOrFail($saleId);
            if ($sale->status === SaleStatus::Cancelled) {
                return $sale->load('items', 'seller');
            }
            $products = Product::withTrashed()->forOrganization($organization)->whereIn('id', $sale->items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($sale->items as $item) {
                $product = $products[$item->product_id];
                // Returned stock must remain visible even if the zero-stock product was archived.
                if ($product->trashed()) {
                    $product->restore();
                }
                $this->inventory->record($product, $user, $item->quantity, MovementType::Cancellation, "Anulación #{$sale->number}: {$reason}", $sale->id);
            }
            $sale->update(['status' => SaleStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancellation_reason' => $reason]);

            return $sale->load('items', 'seller');
        }, 3);
    }
}

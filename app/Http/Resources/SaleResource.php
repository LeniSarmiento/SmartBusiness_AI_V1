<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->only('id', 'number', 'customer_id', 'customer_name', 'currency', 'subtotal', 'tax', 'total', 'notes', 'cancellation_reason'),
            'status' => $this->status->value,
            'sold_at' => $this->sold_at->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'seller' => $this->whenLoaded('seller', fn () => $this->seller->only('id', 'name')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => $item->only('id', 'product_id', 'product_name', 'product_sku', 'quantity', 'unit_price', 'tax_rate_bps', 'subtotal', 'tax', 'total'))),
        ];
    }
}

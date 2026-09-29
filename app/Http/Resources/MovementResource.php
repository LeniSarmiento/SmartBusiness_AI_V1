<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->only('id', 'product_id', 'sale_id', 'quantity_delta', 'balance_after', 'reason'),
            'type' => $this->type->value,
            'created_at' => $this->created_at->toIso8601String(),
            'product' => $this->whenLoaded('product', fn () => $this->product->only('id', 'name', 'sku')),
            'user' => $this->whenLoaded('user', fn () => $this->user->only('id', 'name')),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeCosts = $request->attributes->get('membership')?->role->permissions->contains('name', 'products.write') ?? false;

        return [
            ...$this->resource->only('id', 'name', 'sku', 'code', 'description', 'category_id', 'supplier_id', 'sale_price', 'tax_rate_bps', 'current_stock', 'minimum_stock', 'active'),
            'purchase_price' => $this->when($canSeeCosts, $this->purchase_price),
            'category' => $this->whenLoaded('category', fn () => $this->category?->only('id', 'name')),
            'supplier' => $this->when($canSeeCosts && $this->relationLoaded('supplier'), fn () => $this->supplier?->only('id', 'name')),
            'low_stock' => $this->current_stock <= $this->minimum_stock,
        ];
    }
}

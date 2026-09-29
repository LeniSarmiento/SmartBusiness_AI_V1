<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToOrganization, SoftDeletes;

    // Stock is intentionally absent: only InventoryService may change it.
    protected $fillable = ['organization_id', 'name', 'sku', 'code', 'description', 'category_id', 'supplier_id', 'purchase_price', 'sale_price', 'tax_rate_bps', 'minimum_stock', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'purchase_price' => 'decimal:2', 'sale_price' => 'decimal:2', 'current_stock' => 'integer', 'minimum_stock' => 'integer', 'tax_rate_bps' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}

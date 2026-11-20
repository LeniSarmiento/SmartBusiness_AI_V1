<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAlert extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'product_id', 'threshold_quantity', 'notified_at', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'threshold_quantity' => 'integer',
            'notified_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AdjustmentReason extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'name', 'type', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}

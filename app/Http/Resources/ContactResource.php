<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->only('id', 'name', 'identification', 'email', 'phone', 'address', 'active', 'created_at');
    }
}

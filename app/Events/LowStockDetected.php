<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class LowStockDetected
{
    use Dispatchable;

    public function __construct(
        public int $organization_id,
        public int $product_id,
        public string $product_name,
        public string $product_sku,
        public int $current_stock,
        public int $minimum_stock,
    ) {}
}

<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/** Observable, retryable side effect. Never modifies the stock or the sale. */
class LogSaleCompleted implements ShouldQueue
{
    public int $tries = 3;

    public function handle(SaleCompleted $event): void
    {
        Log::info('sale.completed', ['organization_id' => $event->organizationId, 'sale_id' => $event->saleId]);
    }
}

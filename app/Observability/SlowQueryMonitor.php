<?php

namespace App\Observability;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

class SlowQueryMonitor
{
    public function handle(QueryExecuted $query): void
    {
        if (! config('observability.database.slow_query_logging')) {
            return;
        }

        $threshold = max(0, (float) config('observability.database.slow_query_ms', 250));
        if (($query->time ?? 0) < $threshold) {
            return;
        }

        $normalizedStatement = preg_replace('/\s+/', ' ', trim($query->sql)) ?? $query->sql;
        $operation = strtoupper((string) strtok(ltrim($normalizedStatement), " \t\n\r"));

        Log::warning('database.slow_query', [
            'connection' => $query->connectionName,
            'duration_ms' => round((float) $query->time, 2),
            'operation' => $operation ?: 'UNKNOWN',
            'read_write_type' => $query->readWriteType,
            'statement_hash' => hash('sha256', $normalizedStatement),
        ]);
    }
}

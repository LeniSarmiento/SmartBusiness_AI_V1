<?php

namespace Tests\Feature;

use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DockerRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_health_command_checks_database_and_queue_schema(): void
    {
        $this->artisan('app:health-check')->assertSuccessful()->expectsOutput('healthy');
        $this->artisan('app:health-check --queue')->assertSuccessful()->expectsOutput('healthy');
    }

    public function test_queue_heartbeat_job_records_worker_confirmation(): void
    {
        $cacheKey = 'test:queue-heartbeat';

        (new RecordQueueHeartbeat($cacheKey))->handle();

        $this->assertTrue(Cache::get($cacheKey));
    }

    public function test_queue_smoke_command_completes_with_the_sync_test_driver(): void
    {
        $this->artisan('app:queue-smoke --timeout=2')
            ->assertSuccessful()
            ->expectsOutput('Worker procesó el Job correctamente.');
    }
}

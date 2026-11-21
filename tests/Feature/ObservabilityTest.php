<?php

namespace Tests\Feature;

use App\Observability\QueueMonitor;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;

class ObservabilityTest extends BusinessTestCase
{
    public function test_request_id_and_safe_structured_context_are_recorded(): void
    {
        Log::spy();

        $requestId = 'portfolio-request-123';
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Request-ID', $requestId)
            ->getJson($this->endpoint('products'))
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId);

        Log::shouldHaveReceived('info')->with(
            'http.request.completed',
            Mockery::on(function (array $context) use ($requestId, $response): bool {
                return $context['request_id'] === $requestId
                    && $context['method'] === 'GET'
                    && $context['status_code'] === $response->getStatusCode()
                    && $context['user_id'] === $this->admin->id
                    && $context['organization_id'] === $this->organization->id
                    && isset($context['duration_ms'])
                    && ! array_key_exists('headers', $context)
                    && ! array_key_exists('payload', $context);
            }),
        )->once();
    }

    public function test_invalid_request_id_is_replaced_and_password_is_not_logged(): void
    {
        Log::spy();

        $response = $this->withHeader('X-Request-ID', "invalid\nidentifier")
            ->postJson('/api/v1/auth/login', [
                'email' => 'unknown@example.com',
                'password' => 'never-log-this-password',
            ])
            ->assertUnprocessable();

        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f-]{36}\z/',
            (string) $response->headers->get('X-Request-ID'),
        );

        Log::shouldHaveReceived('info')->with(
            'http.request.completed',
            Mockery::on(fn (array $context): bool => ! str_contains(
                json_encode($context, JSON_THROW_ON_ERROR),
                'never-log-this-password',
            )),
        )->once();
    }

    public function test_slow_query_log_contains_hash_but_not_sql_or_bindings(): void
    {
        config([
            'observability.database.slow_query_logging' => true,
            'observability.database.slow_query_ms' => 0,
        ]);
        Log::spy();

        DB::select('select 1 as observability_probe');

        Log::shouldHaveReceived('warning')->with(
            'database.slow_query',
            Mockery::on(fn (array $context): bool => $context['operation'] === 'SELECT'
                && strlen($context['statement_hash']) === 64
                && ! array_key_exists('sql', $context)
                && ! array_key_exists('bindings', $context)),
        )->atLeast()->once();
    }

    public function test_queue_monitor_records_duration_and_safe_failure_metadata(): void
    {
        config(['observability.queue.log_processed_jobs' => true]);
        Log::spy();
        $job = Mockery::mock(JobContract::class);
        $job->shouldReceive('getJobId')->andReturn('job-observability');
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\ExampleJob');
        $job->shouldReceive('attempts')->andReturn(2);
        $monitor = app(QueueMonitor::class);

        $monitor->processing(new JobProcessing('database', $job));
        $monitor->processed(new JobProcessed('database', $job));

        Log::shouldHaveReceived('info')->with(
            'queue.job.processed',
            Mockery::on(fn (array $context): bool => $context['job_id'] === 'job-observability'
                && $context['attempts'] === 2
                && isset($context['duration_ms'])),
        )->once();

        $monitor->processing(new JobProcessing('database', $job));
        $monitor->failed(new JobFailed(
            'database',
            $job,
            new RuntimeException('secret customer payload'),
        ));

        Log::shouldHaveReceived('error')->with(
            'queue.job.failed',
            Mockery::on(fn (array $context): bool => $context['exception_class'] === RuntimeException::class
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'secret customer payload')
                && ! array_key_exists('payload', $context)),
        )->once();
    }

    public function test_diagnostics_json_is_safe_and_can_fail_on_failed_jobs(): void
    {
        config(['queue.default' => 'database']);

        $this->assertSame(0, Artisan::call('app:diagnostics', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('healthy', $report['status']);
        $this->assertSame('healthy', $report['database']['status']);
        $this->assertSame('healthy', $report['cache']['status']);
        $this->assertSame(0, $report['queue']['failed_jobs']);
        $this->assertArrayNotHasKey('password', $report);
        $this->assertStringNotContainsString('DB_PASSWORD', Artisan::output());

        DB::table('failed_jobs')->insert([
            'uuid' => 'failed-observability-test',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'test',
            'failed_at' => now(),
        ]);

        $this->assertSame(1, Artisan::call('app:diagnostics', [
            '--json' => true,
            '--fail-on-failed-jobs' => true,
        ]));
        $failedReport = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('unhealthy', $failedReport['status']);
        $this->assertSame(1, $failedReport['queue']['failed_jobs']);
    }
}

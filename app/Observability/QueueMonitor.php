<?php

namespace App\Observability;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;

class QueueMonitor
{
    /** @var array<string, int> */
    private array $startedJobs = [];

    public function processing(JobProcessing $event): void
    {
        $this->startedJobs[$this->key($event->job)] = hrtime(true);
    }

    public function processed(JobProcessed $event): void
    {
        $key = $this->key($event->job);
        $startedAt = $this->startedJobs[$key] ?? null;
        unset($this->startedJobs[$key]);

        if (! config('observability.queue.log_processed_jobs')) {
            return;
        }

        Log::info('queue.job.processed', $this->metadata(
            $event->connectionName,
            $event->job,
            $startedAt,
        ));
    }

    public function exceptionOccurred(JobExceptionOccurred $event): void
    {
        Log::warning('queue.job.exception', array_merge(
            $this->metadata(
                $event->connectionName,
                $event->job,
                $this->startedJobs[$this->key($event->job)] ?? null,
            ),
            ['exception_class' => $event->exception::class],
        ));
    }

    public function failed(JobFailed $event): void
    {
        $key = $this->key($event->job);
        $startedAt = $this->startedJobs[$key] ?? null;
        unset($this->startedJobs[$key]);

        Log::error('queue.job.failed', array_merge(
            $this->metadata($event->connectionName, $event->job, $startedAt),
            ['exception_class' => $event->exception::class],
        ));
    }

    private function metadata(string $connection, Job $job, ?int $startedAt): array
    {
        return array_filter([
            'connection' => $connection,
            'queue' => $job->getQueue(),
            'job_id' => $job->getJobId(),
            'job_name' => $job->resolveName(),
            'attempts' => $job->attempts(),
            'duration_ms' => $startedAt === null
                ? null
                : round((hrtime(true) - $startedAt) / 1_000_000, 2),
        ], static fn ($value) => $value !== null);
    }

    private function key(Job $job): string
    {
        return (string) ($job->getJobId() ?: spl_object_id($job));
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        $request->attributes->set('request_id', $requestId);
        Log::shareContext(['request_id' => $requestId]);

        $startedAt = hrtime(true);
        $response = null;
        $exception = null;

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } catch (Throwable $caught) {
            $exception = $caught;

            throw $caught;
        } finally {
            if ($this->shouldLog($request)) {
                $context = $this->context($request, $response, $exception, $startedAt, $requestId);

                if ($context['status_code'] >= 500) {
                    Log::error('http.request.completed', $context);
                } else {
                    Log::info('http.request.completed', $context);
                }
            }

            Log::withoutContext(['request_id', 'user_id', 'organization_id']);
            Log::flushSharedContext();
        }
    }

    private function requestId(Request $request): string
    {
        $candidate = (string) $request->header('X-Request-ID', '');

        return preg_match('/\A[a-zA-Z0-9._-]{8,100}\z/', $candidate)
            ? $candidate
            : (string) Str::uuid();
    }

    private function shouldLog(Request $request): bool
    {
        if (! config('observability.http.enabled')) {
            return false;
        }

        foreach (config('observability.http.excluded_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        return true;
    }

    private function context(
        Request $request,
        ?Response $response,
        ?Throwable $exception,
        int $startedAt,
        string $requestId,
    ): array {
        $organization = $request->route('organization');

        return array_filter([
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $this->statusCode($response, $exception),
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'organization_id' => is_object($organization) && method_exists($organization, 'getKey')
                ? $organization->getKey()
                : (is_numeric($organization) ? (int) $organization : null),
            'exception_class' => $exception ? $exception::class : null,
        ], static fn ($value) => $value !== null);
    }

    private function statusCode(?Response $response, ?Throwable $exception): int
    {
        if ($response !== null) {
            return $response->getStatusCode();
        }

        return match (true) {
            $exception instanceof ValidationException => $exception->status,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }
}

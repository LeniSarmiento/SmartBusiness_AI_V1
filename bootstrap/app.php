<?php

use App\Http\Middleware\EnforceRequestSize;
use App\Http\Middleware\EnsureOrganizationMember;
use App\Http\Middleware\RequestContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(RequestContext::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->api(append: [EnforceRequestSize::class]);
        $middleware->alias(['organization' => EnsureOrganizationMember::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            $requestId = $request->attributes->get('request_id');

            if ($requestId) {
                $response->headers->set('X-Request-ID', (string) $requestId);
            }

            return $response;
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! app()->isProduction() || (! $request->is('api/*') && ! $request->expectsJson())) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            if ($status < 500) {
                return null;
            }

            return response()->json([
                'message' => 'Ocurrió un error interno. Inténtalo nuevamente.',
                'request_id' => $request->attributes->get('request_id'),
            ], $status)->header('X-Request-ID', (string) $request->attributes->get('request_id'));
        });
    })->create();

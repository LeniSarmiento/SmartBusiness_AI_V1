<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceRequestSize
{
    public function handle(Request $request, Closure $next): Response
    {
        $maximum = max(1024, (int) config('security.max_request_bytes'));
        $contentLength = $request->server('CONTENT_LENGTH');

        if ((is_numeric($contentLength) && (int) $contentLength > $maximum)
            || strlen($request->getContent()) > $maximum) {
            return response()->json([
                'message' => 'La solicitud supera el tamaño máximo permitido.',
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }
}

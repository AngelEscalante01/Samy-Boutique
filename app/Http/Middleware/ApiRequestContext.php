<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApiRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) ($request->header('X-Request-Id') ?: Str::uuid());

        $request->attributes->set('request_id', $requestId);

        $baseContext = [
            'request_id' => $requestId,
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
        ];

        Log::withContext($baseContext);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        $status = $response->getStatusCode();
        if ($status >= 500) {
            Log::channel('api')->error('API response error', [
                ...$baseContext,
                'status' => $status,
                'route' => optional($request->route())->getName(),
            ]);
        } elseif ($status >= 400) {
            Log::channel('api')->warning('API response warning', [
                ...$baseContext,
                'status' => $status,
                'route' => optional($request->route())->getName(),
            ]);
        }

        return $response;
    }
}

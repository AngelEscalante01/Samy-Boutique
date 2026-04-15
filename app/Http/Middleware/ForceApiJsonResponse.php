<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceApiJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        $response = $next($request);

        if ($request->is('api/*') && $response instanceof JsonResponse) {
            $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        }

        return $response;
    }
}

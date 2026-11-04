<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Symfony\Component\HttpFoundation\Response;

/** Reuses controller data and business rules; Android renders native widgets. */
class NativeApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('Accept', 'application/json');
        $original = app('session.store');
        $flash = new Store('native-request', new ArraySessionHandler(1));
        $flash->start();
        $request->setLaravelSession($flash);
        app('redirect')->setSession($flash);
        try {
            $response = $next($request);
            if ($response instanceof RedirectResponse) {
                $error = $response->getSession()?->get('error');

                return response()->json([
                    'success' => ! $error,
                    'message' => $error ?: ($response->getSession()?->get('success') ?: 'Cambios guardados.'),
                    'data' => ['destination' => parse_url($response->getTargetUrl(), PHP_URL_PATH)],
                ], $error ? 422 : 200);
            }
            if ($response instanceof JsonResponse) {
                $body = $response->getData(true);
                if ($response->headers->get('X-Inertia') === 'true') {
                    return response()->json(['success' => true, 'data' => $body['props'] ?? []]);
                }
                if ($response->isSuccessful() && ! array_key_exists('success', $body)) {
                    return response()->json(['success' => true, 'data' => $body['data'] ?? $body], $response->getStatusCode());
                }
            }

            return $response;
        } finally {
            app('redirect')->setSession($original);
        }
    }
}

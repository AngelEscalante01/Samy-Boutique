<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveApiUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->active) {
            $token = $request->user()?->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json(['success' => false, 'message' => 'La cuenta ya no está activa. Inicia sesión con una cuenta autorizada.'], 401);
        }

        return $next($request);
    }
}

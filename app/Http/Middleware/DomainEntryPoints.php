<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DomainEntryPoints
{
    public function handle(Request $request, Closure $next): Response
    {
        $public = rtrim((string) config('storefront.public_url'), '/');
        $system = rtrim((string) config('storefront.system_url'), '/');
        $publicHost = parse_url($public, PHP_URL_HOST);
        $systemHost = parse_url($system, PHP_URL_HOST);
        if (! $publicHost || ! $systemHost || $publicHost === $systemHost) {
            return $next($request);
        }

        $host = $request->getHost();
        if ($host === 'www.'.$publicHost) {
            abort_unless($request->isMethodSafe(), 404);
            return redirect()->away($public.$request->getRequestUri(), 301);
        }
        $path = '/'.$request->path();
        if ($path === '//') {
            $path = '/';
        }
        $catalog = $path === '/' || $path === '/catalogo' || str_starts_with($path, '/catalogo/p/');
        $suffix = $request->getQueryString() ? '?'.$request->getQueryString() : '';
        if ($host === $publicHost) {
            if ($path === '/catalogo') {
                return redirect()->away($public.'/'.$suffix, 301);
            }
            $publicResource = in_array($path, ['/offline', '/sw.js', '/firebase-messaging-sw.js', '/manifest.webmanifest', '/robots.txt', '/sitemap.xml'], true)
                || str_starts_with($path, '/storage/');
            if (! $catalog && ! $publicResource) {
                abort_if(! $request->isMethodSafe() || $request->is('api/*'), 404);
                return redirect()->away($system.$request->getRequestUri(), 308);
            }
        }
        if ($host === $systemHost && $catalog) {
            if ($path === '/') {
                return redirect('/login');
            }
            return redirect()->away($public.($path === '/catalogo' ? '/' : $path).$suffix, 301);
        }
        $response = $next($request);
        if ($host === $systemHost) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        return $response;
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TRUSTED_PROXIES');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        parent::tearDown();
    }

    private function configureProxy(string $value): void
    {
        putenv('TRUSTED_PROXIES='.$value);
        $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = $value;
        $this->refreshApplication();
        Route::get('/_test/proxy', fn (Request $request) => response()->json([
            'secure' => $request->isSecure(),
            'client' => $request->ip(),
        ]));
    }

    public function test_direct_installation_ignores_forwarded_headers(): void
    {
        $this->configureProxy('');
        $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.10'])
            ->get('/_test/proxy')->assertOk()->assertJson(['secure' => false, 'client' => '127.0.0.1']);
    }

    public function test_selected_reverse_proxy_preserves_https_and_client_address(): void
    {
        $this->configureProxy('127.0.0.1');
        $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.10'])
            ->get('/_test/proxy')->assertOk()->assertJson(['secure' => true, 'client' => '203.0.113.10']);
    }
}

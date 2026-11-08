<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProxyAssetSchemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_local_login_uses_https_for_dynamic_assets_without_proxy_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('x-load-src="https://localhost/js/filament/forms/components/text-input/password-reveal.js', false);
        $response->assertSee('data-update-uri="https://localhost/livewire-', false);
    }

    public function test_local_login_keeps_http_for_dynamic_assets(): void
    {
        $this->app->instance('env', 'local');
        URL::forceScheme(null);
        (new AppServiceProvider($this->app))->boot();

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('x-load-src="http://localhost/js/filament/forms/components/text-input/password-reveal.js', false);
        $response->assertSee('data-update-uri="http://localhost/livewire-', false);
    }
}

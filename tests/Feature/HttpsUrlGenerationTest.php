<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Behind Cloudflare + Traefik the request arrives as plain http. Generated links (plate images, storage
 * assets) must still be https when APP_URL is https.
 */
class HttpsUrlGenerationTest extends TestCase
{
    private function bootWithAppUrl(string $appUrl): void
    {
        putenv('APP_URL=' . $appUrl);
        $_ENV['APP_URL'] = $_SERVER['APP_URL'] = $appUrl;
        $this->refreshApplication();

        Route::get('/__qa/url', fn () => response()->json([
            'url'   => url('storage/plates/x.png'),
            'asset' => asset('storage/temp_plates/x.html'),
        ]));
    }

    protected function tearDown(): void
    {
        putenv('APP_URL');
        unset($_ENV['APP_URL'], $_SERVER['APP_URL']);
        parent::tearDown();
    }

    public function test_http_request_generates_https_links_when_app_url_is_https(): void
    {
        $this->bootWithAppUrl('https://be.dabapp.co');

        $this->get('http://be.dabapp.co/__qa/url')
            ->assertOk()
            ->assertJson([
                'url'   => 'https://be.dabapp.co/storage/plates/x.png',
                'asset' => 'https://be.dabapp.co/storage/temp_plates/x.html',
            ]);
    }

    public function test_http_app_url_keeps_http_links(): void
    {
        $this->bootWithAppUrl('http://localhost:8000');

        $this->get('http://localhost:8000/__qa/url')
            ->assertOk()
            ->assertJson(['url' => 'http://localhost:8000/storage/plates/x.png']);
    }
}

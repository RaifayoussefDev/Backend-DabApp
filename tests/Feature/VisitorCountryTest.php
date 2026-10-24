<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Behind Cloudflare the visitor country arrives as CF-IPCountry (ISO code). The app reads X-Forwarded-Country
 * (full name, as Cloudways injected it); without it registration defaulted to Morocco (+212) and the website
 * showed no country.
 */
class VisitorCountryTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_COUNTRY'], $_SERVER['HTTP_X_FORWARDED_CONTINENT']);
        parent::tearDown();
    }

    public function test_cloudflare_country_code_becomes_the_country_name(): void
    {
        $this->getJson('/api/get-country', ['CF-IPCountry' => 'SA', 'CF-IPContinent' => 'AS'])
            ->assertOk()
            ->assertJson(['country' => 'Saudi Arabia', 'continent' => 'AS']);
    }

    public function test_countries_endpoint_detects_the_visitor_country(): void
    {
        $this->getJson('/api/countries', ['CF-IPCountry' => 'SA'])
            ->assertOk()
            ->assertJsonPath('detected_country', 'Saudi Arabia');
    }

    public function test_client_supplied_country_is_overwritten_behind_cloudflare(): void
    {
        $this->getJson('/api/get-country', ['CF-IPCountry' => 'AE', 'X-Forwarded-Country' => 'Morocco', 'X-Forwarded-Continent' => 'AF'])
            ->assertOk()
            ->assertJson(['country' => 'United Arab Emirates', 'continent' => 'Unknown']);
    }

    public function test_unknown_cloudflare_country_gives_no_country(): void
    {
        $this->getJson('/api/get-country', ['CF-IPCountry' => 'XX', 'X-Forwarded-Country' => 'Morocco'])
            ->assertOk()
            ->assertJson(['country' => 'Unknown']);
    }

    public function test_without_cloudflare_the_request_is_untouched(): void
    {
        $this->getJson('/api/get-country')->assertOk()->assertJson(['country' => 'Unknown']);
    }
}

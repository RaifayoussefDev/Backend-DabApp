<?php

namespace Tests\Feature;

use App\Helpers\CountryHelper;
use Tests\TestCase;

/**
 * countries.json used to exist only on the server (storage/app), so every new environment needed a manual
 * copy or phone formatting and /api/countries broke. The code now ships a copy in resources/data.
 */
class CountriesDataTest extends TestCase
{
    public function test_bundled_countries_file_is_complete(): void
    {
        $countries = json_decode(file_get_contents(resource_path('data/countries.json')), true);

        $this->assertCount(242, $countries);
        $this->assertSame(['name', 'dial_code', 'code', 'flag'], array_keys($countries[0]));
    }

    public function test_lookups_work_from_the_resolved_file(): void
    {
        $this->assertNotNull(CountryHelper::countriesPath());
        $this->assertSame('+966', CountryHelper::getDialCode('SA'));
        $this->assertSame('MA', CountryHelper::getCountryInfoByName('Morocco')['code']);
    }

    public function test_countries_endpoint_returns_the_list(): void
    {
        $this->getJson('/api/countries')->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Helpers\CountryHelper;
use Tests\TestCase;

/**
 * Behind Dokploy the visitor country is unknown and registration defaults to Morocco. A number typed in
 * international form (+966…) was then stored as +212966…, so WhatsApp OTP went to a number that does not exist.
 */
class PhoneFormattingTest extends TestCase
{
    public function test_international_number_keeps_its_own_country_code(): void
    {
        $this->assertSame('+966568001066', CountryHelper::formatPhoneByCountryName('+966568001066', 'Morocco'));
        $this->assertSame('+966568001066', CountryHelper::formatPhoneByCountryName('+966 56 800 1066', 'Morocco'));
        $this->assertSame('+966568001066', CountryHelper::formatPhoneByCountryName('00966568001066', 'Morocco'));
        $this->assertSame('+966568001066', CountryHelper::formatPhone('+966568001066', 'MA'));
    }

    public function test_registration_helper_stores_the_international_number(): void
    {
        $data = CountryHelper::processCountryAndPhoneByName('+966568001066', 'Morocco');

        $this->assertSame('+966568001066', $data['formatted_phone']);
    }

    public function test_existing_behaviour_is_unchanged(): void
    {
        // local number → detected country's code
        $this->assertSame('+966688808238', CountryHelper::formatPhone('0688808238', 'SA'));
        $this->assertSame('+212688808238', CountryHelper::formatPhoneByCountryName('0688808238', 'Morocco'));
        // already carries the detected country's code (with or without +)
        $this->assertSame('+966688808238', CountryHelper::formatPhone('966688808238', 'SA'));
        $this->assertSame('+212688808080', CountryHelper::formatPhoneByCountryName('+212688808080', 'Morocco'));
        // trunk 0 after the detected country's code is removed
        $this->assertSame('+212688808238', CountryHelper::formatPhoneByCountryName('+2120688808238', 'Morocco'));
        // unknown country → unchanged
        $this->assertSame('+966568001066', CountryHelper::formatPhoneByCountryName('+966568001066', 'Unknown'));
    }
}

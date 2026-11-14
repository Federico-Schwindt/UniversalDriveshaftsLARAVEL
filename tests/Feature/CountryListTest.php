<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Owner, 2026-11-13: the checkout's country list looked messy, so it is A to Z (the United States included) and has
 * no "Other"; and the state became a list too (config/states.php, public/js/states.js).
 */
class CountryListTest extends TestCase
{
    public function test_countries_are_alphabetical_real_and_never_embargoed(): void
    {
        $c = config('countries');
        $names = array_values($c);
        $sorted = $names;
        usort($sorted, fn ($a, $b) => strcasecmp(\Illuminate\Support\Str::ascii($a), \Illuminate\Support\Str::ascii($b)));   // Åland with the A's
        $this->assertSame($sorted, $names);
        $this->assertCount(233, $c);
        $this->assertArrayHasKey('US', $c);
        // no "Other": the carriers cannot quote it, so a customer picking it could not check out
        foreach (array_keys($c) as $code) $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $code);
        // never the U.S. embargoes or where the carriers stopped (Belarus, Russia); the U.S. territories are states
        foreach (['CU', 'IR', 'KP', 'SY', 'BY', 'RU', 'GU', 'VI'] as $code) $this->assertArrayNotHasKey($code, $c);
    }

    public function test_state_lists_are_alphabetical_and_keyed_by_the_code_the_carriers_use(): void
    {
        $s = config('states');
        foreach (['US', 'CA'] as $cc) {
            $names = array_values($s[$cc]);
            $sorted = $names;
            sort($sorted, SORT_STRING);
            $this->assertSame($sorted, $names, "$cc names A to Z");
            foreach (array_keys($s[$cc]) as $code) $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $code);
        }
        $this->assertSame('Florida', $s['US']['FL']);
        $this->assertCount(59, $s['US']);   // 50 states, DC, 5 territories, 3 military
        $this->assertCount(13, $s['CA']);
    }

    public function test_the_checkout_and_the_address_book_get_the_state_dropdown(): void
    {
        $this->get('/checkout');   // an empty cart sends you back to the cart, so check the markup the page is built from
        $view = file_get_contents(resource_path('views/checkout.blade.php'));
        $this->assertStringContainsString('data-state-list="billing_country"', $view);
        $this->assertStringContainsString('data-state-list="shipping_country"', $view);
        $this->assertStringContainsString("@include('partials.state-lists')", $view);
        $html = view('partials.state-lists')->render();
        $this->assertStringContainsString('"FL":"Florida"', $html);
        $this->assertStringContainsString('/js/states.js?v=', $html);
    }
}

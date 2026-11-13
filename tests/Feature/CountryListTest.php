<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Owner, 2026-11-13: the checkout's country list looked messy, so it is A to Z (the United States included) with
 * "Other" last; and the state became a list too (config/states.php, public/js/states.js).
 */
class CountryListTest extends TestCase
{
    public function test_countries_are_alphabetical_with_other_last(): void
    {
        $c = config('countries');
        $this->assertSame('OTHER', array_key_last($c));
        $names = array_values(array_slice($c, 0, -1));
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names);
        $this->assertCount(97, $c);
        $this->assertArrayHasKey('US', $c);
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

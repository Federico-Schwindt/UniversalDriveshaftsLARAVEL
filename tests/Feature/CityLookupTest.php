<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Owner, 2026-11-13 (city: "Sugerencias + ZIP"): city suggestions per state and the ZIP lookup read static files built
 * from the GeoNames postal code data (public/js/cities.js, public/data/cities, public/data/zip).
 */
class CityLookupTest extends TestCase
{
    private function data(string $path): mixed
    {
        return json_decode((string) file_get_contents(public_path($path)), true);
    }

    public function test_a_zip_gives_its_city_and_state(): void
    {
        $this->assertSame(['Tampa', 'FL'], $this->data('data/zip/336.json')['33602']);
        $this->assertSame(['New Port Richey', 'FL'], $this->data('data/zip/346.json')['34655']);
        $this->assertSame(['Beverly Hills', 'CA'], $this->data('data/zip/902.json')['90210']);
        $this->assertSame('PR', $this->data('data/zip/006.json')['00601'][1]);
    }

    public function test_city_lists_are_alphabetical_per_state_and_every_state_has_one(): void
    {
        foreach (array_keys(config('states.US')) as $st) {
            if (in_array($st, ['AA', 'AE', 'AP'], true)) continue;   // military addresses use APO / FPO, not cities
            $this->assertFileExists(public_path("data/cities/$st.json"), "$st has a city list");
        }
        $fl = $this->data('data/cities/FL.json');
        $sorted = $fl;
        usort($sorted, fn ($a, $b) => strcasecmp($a, $b));
        $this->assertSame($sorted, $fl);
        $this->assertContains('Tampa', $fl);
        $this->assertContains(['Tampa', 'FL'], $this->data('data/cities/all/T.json'));
    }

    public function test_the_address_forms_get_the_city_lookup_and_geonames_is_credited(): void
    {
        $view = file_get_contents(resource_path('views/checkout.blade.php'));
        $this->assertStringContainsString('data-city-state="billing_state" data-city-country="billing_country" data-city-zip="co-bzip"', $view);
        $this->assertStringContainsString('data-city-state="shipping_state" data-city-country="shipping_country" data-city-zip="co-szip"', $view);
        $this->assertStringContainsString('/js/cities.js?v=', view('partials.state-lists')->render());
        $this->get('/copyright')->assertOk()->assertSee('GeoNames')->assertSee('CC BY 4.0');
    }
}

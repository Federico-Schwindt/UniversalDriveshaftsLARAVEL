<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** AI-search readiness: llms.txt, IndexNow (never from a non-production host), entity data. */
class GeoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_llms_txt_summarises_the_store_with_address_catalog_and_policies(): void
    {
        \Cache::forget('seo:llms');
        $t = $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();
        $this->assertStringStartsWith('# Universal Driveshafts', $t);
        foreach (['11611 Prospect Drive STE 4, Odessa, FL 33556', '(813) 524-6491', '/driveshafts', '/shop/driveshaft-components/u-joints', '14 days with an RMA'] as $needle) $this->assertStringContainsString($needle, $t);
    }

    public function test_indexnow_never_submits_outside_production_and_serves_its_key(): void
    {
        \Http::fake();
        $this->artisan('seo:indexnow', ['--all' => true])->expectsOutputToContain('not production: nothing sent')->assertSuccessful();
        \Http::assertNothingSent();
        $key = \App\Console\Commands\SeoIndexNow::key();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);
        $this->assertSame($key, file_get_contents(public_path($key . '.txt')));
    }

    public function test_organization_and_shop_nodes_describe_the_business(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $nodes = collect($m[1])->flatMap(fn ($b) => json_decode($b, true)['@graph'] ?? [json_decode($b, true)]);
        $org = $nodes->firstWhere('@type', 'Organization');
        $this->assertContains('Driveshafts', $org['knowsAbout']);
        $shop = $nodes->first(fn ($n) => str_ends_with($n['@id'] ?? '', '#shop'));
        $this->assertSame('Odessa', $shop['address']['addressLocality']);
    }
}

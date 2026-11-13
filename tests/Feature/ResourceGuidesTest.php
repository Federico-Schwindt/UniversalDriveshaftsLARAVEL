<?php

namespace Tests\Feature;

use App\Support\ResourceGuides;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Owner, 2026-11-13: "en la página vieja (actual) tenemos una sección de guías, replicalas pero con nuestro diseño, no la
 * añadas a ningún menu es solo para tenerlas guardadas, añadelas solo al footer, menos driveshaft designer".
 * The guides answer at their old /resources addresses, so their search traffic needs no redirect.
 */
class ResourceGuidesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_every_old_guide_answers_at_its_old_address_with_its_pictures(): void
    {
        $this->assertGreaterThanOrEqual(20, count(ResourceGuides::all()));
        foreach (ResourceGuides::all() as $g) {
            $html = $this->get(ResourceGuides::url($g['path']))->assertOk()->getContent();
            $this->assertSame(1, preg_match_all('#<h1[^>]*>#', $html), $g['path']);
            $this->assertStringContainsString('<link rel="canonical" href="' . rtrim(config('app.url'), '/') . ResourceGuides::url($g['path']) . '">', $html);
            foreach ($g['blocks'] as $b) foreach ($b['items'] ?? [] as $im) if (isset($im['src'])) $this->assertFileExists(public_path(ltrim($im['src'], '/')));
        }
        $this->get('/resources')->assertOk()->assertSee('Measuring guides');
    }

    public function test_old_links_land_on_the_guide_in_one_hop(): void
    {
        // the old store's links end in a slash (sent as a browser does: the test client would trim it): one 301 to the guide
        $res = TestResponse::fromBaseResponse($this->app->make(Kernel::class)->handle(Request::create('/resources/u-joint/universal-joint-olutside-snap-ring-style/', 'GET')));
        $this->assertSame(301, $res->getStatusCode());
        $this->assertStringEndsWith('/resources/u-joint/universal-joint-olutside-snap-ring-style', (string) $res->headers->get('Location'));
        // the old buttons that filed a guide under the wrong folder
        $this->get('/resources/two-piece-driveshaft/universal-joint-olutside-snap-ring-style')->assertRedirect('/resources/u-joint/universal-joint-olutside-snap-ring-style');
        $this->get('/resources/driveshaft-designer-choose-type')->assertRedirect('/driveshaft-designer');
        $this->get('/resources/troubleshooting-guide')->assertRedirect('/resources');
        $this->get('/common-terms')->assertRedirect('/resources/common-terms');
    }

    public function test_the_guides_are_linked_from_the_footer_only_and_listed_in_the_sitemap(): void
    {
        $html = $this->get('/contact')->getContent();
        [$page, $footer] = explode('<footer class="ufoot">', $html, 2) + [1 => ''];
        $this->assertStringContainsString('href="/resources/u-joint"', $footer);
        $this->assertStringNotContainsString('href="/resources', $page);
        Cache::forget('uds_sitemap_pages');
        $xml = $this->get('/sitemap-pages.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/resources/flange-yoke/male-flange-extended-pilot-hole</loc>', $xml);
        Cache::forget('uds_sitemap_pages');
    }

    public function test_the_bearing_chart_is_drawn_from_the_catalog(): void
    {
        $html = $this->get('/resources/center-support-bearing-size-chart')->assertOk()->getContent();
        $this->assertGreaterThan(50, substr_count($html, 'href="/parts/'));
    }
}

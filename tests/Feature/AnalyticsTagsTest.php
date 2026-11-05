<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Google tags carried over from the old site's Site Kit: GA4 + Ads with consent mode, never for staff, purchase once on the thank-you page. */
class AnalyticsTagsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tags_render_with_consent_mode_when_enabled(): void
    {
        config(['uds.analytics_enabled' => true]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('gtag/js?id=G-MG6P2JC6GL', $html);
        $this->assertStringContainsString("gtag('config', 'AW-18204111895')", $html);
        $this->assertStringContainsString("gtag('consent', 'default'", $html);
    }

    public function test_no_tags_when_disabled_or_for_admins(): void
    {
        config(['uds.analytics_enabled' => false]);
        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com/gtag', false);
        config(['uds.analytics_enabled' => true]);
        $admin = User::query()->where('is_admin', 1)->firstOrFail();
        $this->actingAs($admin)->get('/')->assertOk()->assertDontSee('googletagmanager.com/gtag', false);
    }

    public function test_thank_you_page_sends_the_purchase(): void
    {
        config(['uds.analytics_enabled' => true]);
        $o = \DB::table('orders')->where('payment_method', '!=', 'stripe')->orderByDesc('id')->first()
            ?? \DB::table('orders')->where('payment_status', 'paid')->orderByDesc('id')->first();
        if (!$o) $this->markTestSkipped('no order to show');
        $this->get('/order-received/' . $o->order_number . '?email=' . urlencode($o->email))->assertOk()
            ->assertSee("gtag('event', 'purchase'", false)->assertSee('"' . $o->order_number . '"', false);
    }
}

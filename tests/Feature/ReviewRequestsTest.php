<?php

namespace Tests\Feature;

use App\Support\ReviewRequests;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Google review requests: who gets one, once, and how to stop them. */
class ReviewRequestsTest extends TestCase
{
    use DatabaseTransactions;

    private function order(array $over = []): object
    {
        $id = DB::table('orders')->insertGetId($over + ['order_number' => 'UDS-REV-' . uniqid(), 'email' => 'rev-' . uniqid() . '@example.com', 'billing_first_name' => 'Alex',
            'status' => 'completed', 'payment_status' => 'paid', 'source' => 'web', 'total' => 80, 'subtotal' => 80, 'shipped_at' => now()->subDays(9), 'delivered_at' => now()->subDays(6),
            'created_at' => now()->subDays(12), 'updated_at' => now()]);
        DB::table('order_items')->insert(['order_id' => $id, 'type' => 'product', 'name' => '1310 Series U-Joint', 'sku' => '5-153X', 'price' => 40, 'qty' => 2, 'line_total' => 80, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('orders')->find($id);
    }

    public function test_only_eligible_orders_are_asked_once_and_opt_out_sticks(): void
    {
        Mail::fake();
        Settings::set('reviews.enabled', true); Settings::set('reviews.delay_days', 5);
        $ok = $this->order();
        $imported = $this->order(['source' => 'woocommerce', 'wc_status' => 'completed']);
        $refunded = $this->order(['payment_status' => 'refunded']);
        $tooSoon = $this->order(['delivered_at' => now()->subDays(2)]);
        $returning = $this->order();
        DB::table('rma_requests')->insert(['ref' => 'RMA-T-' . uniqid(), 'type' => 'return', 'order_number' => $returning->order_number, 'name' => 'Alex', 'email' => $returning->email, 'product' => 'x', 'reason' => 'x', 'status' => 'new', 'created_at' => now(), 'updated_at' => now()]);
        $due = ReviewRequests::due(500)->pluck('id')->all();
        $this->assertContains($ok->id, $due);
        foreach ([$imported, $refunded, $tooSoon, $returning] as $o) $this->assertNotContains($o->id, $due);

        $this->artisan('reviews:request')->assertSuccessful();
        Mail::assertSent(\App\Mail\Branded::class, fn ($m) => $m->hasTo($ok->email));
        $this->assertTrue(DB::table('order_events')->where('order_id', $ok->id)->where('type', 'notify:email:review')->exists());
        // never twice, and the same customer is not asked again for a second order soon after
        $again = $this->order(['email' => $ok->email]);
        $this->assertNotContains($ok->id, ReviewRequests::due(500)->pluck('id')->all());
        $this->assertNotContains($again->id, ReviewRequests::due(500)->pluck('id')->all());

        // opting out from the e-mail link
        $other = $this->order();
        $token = substr(ReviewRequests::unsubscribeUrl($other->email), strlen(url('/reviews/unsubscribe/')) + 1);
        $this->get('/reviews/unsubscribe/' . $token)->assertOk()->assertSee('No more review requests');
        $this->assertNotContains($other->id, ReviewRequests::due(500)->pluck('id')->all());
        $this->get('/reviews/unsubscribe/' . substr($token, 0, 31) . 'x' . substr($token, 32))->assertNotFound();
    }

    public function test_off_by_default_and_the_message_carries_the_google_link(): void
    {
        Mail::fake();
        Settings::set('reviews.enabled', false);
        $this->order();
        $this->artisan('reviews:request')->expectsOutputToContain('off')->assertSuccessful();
        Mail::assertNothingSent();
        [$subject, $c] = ReviewRequests::message($this->order());
        $this->assertSame('How did we do, Alex?', $subject);
        $this->assertSame(ReviewRequests::DEFAULT_URL, $c['button'][1]);
        $admin = \App\Models\User::query()->where('is_admin', 1)->firstOrFail();
        $this->actingAs($admin)->get('/admin/settings/email-preview/review-request')->assertOk()->assertSee('Review us on Google');
        $this->actingAs($admin)->get('/admin/settings?tab=reviews')->assertOk()->assertSee('Ask customers for a Google review');
    }

    public function test_rating_badge_uses_the_figures_from_settings_and_can_be_hidden(): void
    {
        Settings::set('reviews.badge_enabled', true); Settings::set('reviews.google_rating', 4.8); Settings::set('reviews.google_count', 215);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('215+ Google reviews', $html);
        $this->assertStringContainsString('--g-fill: 96%', $html);
        $this->assertStringNotContainsString('aggregateRating', $html);   // a badge, never self-serving review markup
        Settings::set('reviews.badge_enabled', false);
        $this->get('/')->assertOk()->assertDontSee('Google reviews');
        Settings::set('reviews.badge_enabled', true);
        \Cache::forget('seo:llms');
        $this->get('/llms.txt')->assertOk()->assertSee('Rated 4.8 out of 5 on Google from 215+ reviews');
    }
}

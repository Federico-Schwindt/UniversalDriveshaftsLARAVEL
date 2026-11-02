<?php

namespace Tests\Feature;

use App\Console\Commands\WeeklyReport;
use App\Http\Controllers\AlertsController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\RecoveryController;
use App\Models\User;
use App\Services\B2bAccounts;
use App\Services\PtiDropship;
use App\Services\SmsService;
use App\Support\OrderEvents;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Background jobs, e-mail / SMS and integrations: dedupe, opt-outs, availability, schedule timezone, reports, retries. */
class JobsHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private function order(array $extra = []): object
    {
        $id = DB::table('orders')->insertGetId($extra + ['order_number' => 'T-' . strtoupper(Str::random(8)), 'status' => 'processing', 'payment_status' => 'paid', 'payment_method' => 'stripe',
            'email' => 'jobs-' . uniqid() . '@example.com', 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US',
            'subtotal' => 100, 'shipping_total' => 0, 'discount_total' => 0, 'total' => 100, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('orders')->find($id);
    }

    /** Messages captured by the array mail transport (MAIL_MAILER=array: nothing leaves the box). */
    private function sentMail(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()->all();
    }

    private function clearMail(): void
    {
        app('mailer')->getSymfonyTransport()->flush();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('array', config('mail.default'), 'run with MAIL_MAILER=array');
        Http::preventStrayRequests();
        $this->clearMail();
    }

    public function test_order_event_once_is_written_once_even_when_racing(): void
    {
        $o = $this->order();
        $this->assertTrue(OrderEvents::record($o, 'notify:email:shipped', 'Shipping e-mail sent', null, true));
        $this->assertFalse(OrderEvents::record($o, 'notify:email:shipped', 'Shipping e-mail sent', null, true));
        $this->assertSame(1, DB::table('order_events')->where('order_id', $o->id)->where('type', 'notify:email:shipped')->count());

        // the race itself: both callers passed has(); the unique (order_id, dedupe_key) lets only one insert through
        $row = ['order_id' => $o->id, 'type' => 'notify:email:delivered', 'dedupe_key' => 'notify:email:delivered', 'label' => 'x', 'created_at' => now()];
        $this->assertSame(1, DB::table('order_events')->insertOrIgnore($row));
        $this->assertSame(0, DB::table('order_events')->insertOrIgnore($row));

        // non-once rows still stack, and forget() re-opens a once key
        OrderEvents::record($o, 'note', 'a'); OrderEvents::record($o, 'note', 'b');
        $this->assertSame(2, DB::table('order_events')->where('order_id', $o->id)->where('type', 'note')->count());
        OrderEvents::forget($o, 'notify:email:shipped');
        $this->assertTrue(OrderEvents::record($o, 'notify:email:shipped', 'again', null, true));
    }

    public function test_cart_unsubscribe_survives_new_carts_and_recovery_skips_it(): void
    {
        config(['uds.cart_reminder_sms' => false]);
        $email = 'unsub-' . uniqid() . '@example.com';
        $items = json_encode([['type' => 'custom_build', 'name' => 'Custom driveshaft', 'sku' => 'CB-1', 'qty' => 1, 'price' => 300]]);
        $cart = fn (string $mail) => DB::table('abandoned_carts')->insertGetId(['session_id' => Str::random(30), 'token' => $t = Str::random(40), 'email' => $mail, 'items' => $items,
            'subtotal' => 300, 'reminders_sent' => 0, 'last_seen_at' => now()->subHours(2), 'created_at' => now()->subHours(2), 'updated_at' => now()]);

        $first = $cart($email);
        $token = DB::table('abandoned_carts')->where('id', $first)->value('token');
        $this->post('/cart/recovery/unsubscribe/' . $token)->assertOk();   // one-click POST, no CSRF token
        $this->assertTrue(RecoveryController::suppressed($email));

        // capture() on a new session marks the new cart unsubscribed too
        $this->postJson('/cart/recovery/capture', ['email' => strtoupper($email)])->assertOk();
        $this->assertNotNull(DB::table('abandoned_carts')->where('email', $email)->where('session_id', session()->getId())->value('unsubscribed_at'));

        // a cart row that slipped past capture is still never mailed; a normal address is
        $later = $cart($email);
        DB::table('abandoned_carts')->where('id', $later)->update(['last_seen_at' => now()->subHours(2)->addMinute()]);
        $other = 'keep-' . uniqid() . '@example.com';
        $cart($other);
        Artisan::call('recovery:send');
        $to = collect($this->sentMail())->flatMap(fn ($m) => array_map(fn ($a) => $a->getAddress(), $m->getOriginalMessage()->getTo()));
        $this->assertNotContains($email, $to->all());
        $this->assertContains($other, $to->all());

        // the reminder carries one-click unsubscribe headers
        $msg = collect($this->sentMail())->first(fn ($m) => $m->getOriginalMessage()->getTo()[0]->getAddress() === $other)->getOriginalMessage();
        $this->assertStringContainsString('/cart/recovery/unsubscribe/', $msg->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertSame('List-Unsubscribe=One-Click', $msg->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());
    }

    public function test_newsletter_unsubscribe_token_is_signed(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response([], 204)]);
        $email = 'news-' . uniqid() . '@example.com';
        $url = NewsletterController::unsubscribeUrl($email);
        $path = parse_url($url, PHP_URL_PATH);
        $this->get($path)->assertOk()->assertSee('You are unsubscribed');
        $this->assertTrue(RecoveryController::suppressed($email, 'newsletter'));
        // tampered: someone else's address with this signature
        $forged = substr(basename($path), 0, 32) . rtrim(strtr(base64_encode('victim@example.com'), '+/', '-_'), '=');
        $this->get('/newsletter/unsubscribe/' . $forged)->assertNotFound();
    }

    public function test_back_in_stock_uses_live_row_including_pti_stock(): void
    {
        $wpSnapshot = ['id' => 999999991, 'stock' => 0, 'stock_status' => 'outofstock'];
        $this->assertTrue(AlertsController::productAvailable($wpSnapshot, ['in_stock' => true]));    // PTI has it (catalog_live in_stock || pti_in_stock)
        $this->assertFalse(AlertsController::productAvailable(['stock' => 5] + $wpSnapshot, ['in_stock' => false]));
        // no live row: fall back to the product's stock_status / stock
        $this->assertTrue(AlertsController::productAvailable(['id' => 999999992, 'stock' => null, 'stock_status' => 'instock']));
        $this->assertFalse(AlertsController::productAvailable(['id' => 999999993, 'stock' => 0, 'stock_status' => 'instock']));
    }

    public function test_alerts_check_expires_old_and_processes_beyond_300(): void
    {
        $old = DB::table('stock_alerts')->insertGetId(['email' => 'old-' . uniqid() . '@example.com', 'kind' => 'product', 'ref' => 'x-none', 'label' => 'Old', 'created_at' => now()->subDays(200), 'updated_at' => now()]);
        Artisan::call('alerts:check', ['--dry' => true]);
        $this->assertNull(DB::table('stock_alerts')->where('id', $old)->value('expired_at'));   // dry run writes nothing
        Artisan::call('alerts:check');
        $this->assertNotNull(DB::table('stock_alerts')->where('id', $old)->value('expired_at'));
    }

    public function test_schedule_runs_on_eastern_time(): void
    {
        Artisan::call('schedule:list');   // boots the console routes
        $events = collect(app(Schedule::class)->events());
        $boxes = $events->first(fn ($e) => str_contains((string) $e->command, 'pti:sync') && str_contains((string) $e->command, '--boxes'));
        $this->assertNotNull($boxes);
        $tz = $boxes->timezone instanceof \DateTimeZone ? $boxes->timezone->getName() : $boxes->timezone;
        $this->assertSame('America/New_York', $tz);
        $this->assertSame('0 3 * * *', $boxes->expression);
        $recovery = $events->first(fn ($e) => str_contains((string) $e->command, 'recovery:send'));
        $this->assertSame(30, $recovery->expiresAt);
    }

    public function test_sms_normalisation_and_masked_logs(): void
    {
        $this->assertSame('+18135246491', SmsService::e164('(813) 524-6491'));
        $this->assertSame('+18135246491', SmsService::e164('1 813 524 6491'));
        $this->assertSame('+447700900123', SmsService::e164('+44 7700 900123'));
        $this->assertNull(SmsService::e164('524-6491'));
        $this->assertNull(SmsService::e164('52 813 524 6491'));
        $this->assertSame('…6491', SmsService::mask('(813) 524-6491'));

        config(['services.twilio.enabled' => false]);
        Log::spy();
        (new SmsService())->send('(813) 555-0142', 'Your secret order link https://example.com/x');
        Log::shouldHaveReceived('info')->withArgs(fn ($m) => str_contains($m, '…0142') && !str_contains($m, '555') && !str_contains($m, 'secret'))->once();
    }

    public function test_weekly_report_reads_our_orders(): void
    {
        $sku = 'WR-' . strtoupper(Str::random(6));
        foreach ([['paid', 'processing', 3], ['partially_refunded', 'completed', 2], ['paid', 'cancelled', 50], ['unpaid', 'pending', 40], ['refunded', 'refunded', 30]] as [$pay, $st, $qty]) {
            $o = $this->order(['payment_status' => $pay, 'status' => $st]);
            DB::table('order_items')->insert(['order_id' => $o->id, 'type' => 'product', 'wp_product_id' => null, 'name' => "Test part $sku", 'sku' => $sku, 'price' => 10, 'qty' => $qty, 'line_total' => 10 * $qty, 'created_at' => now(), 'updated_at' => now()]);
        }
        $row = WeeklyReport::topSellers(30, 500)->firstWhere('sku', $sku);
        $this->assertNotNull($row);
        $this->assertSame(5, $row->units);
        $this->assertSame(2, $row->orders);
    }

    public function test_terms_reminder_is_retried_after_a_mail_failure(): void
    {
        $u = User::query()->create(['name' => 'Alex Morgan', 'email' => 'b2b-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $u->id)->update(['wholesale_status' => 'approved']);
        $o = $this->order(['user_id' => $u->id, 'email' => $u->email, 'payment_method' => 'net_terms', 'payment_status' => 'unpaid', 'terms_due_at' => now('America/New_York')->addDays(3)->toDateString()]);

        // a transport that always fails (nothing is contacted)
        Mail::extend('boom', fn () => new class extends \Symfony\Component\Mailer\Transport\AbstractTransport {
            protected function doSend(\Symfony\Component\Mailer\SentMessage $message): void { throw new \RuntimeException('smtp down'); }
            public function __toString(): string { return 'boom://'; }
        });
        config(['mail.mailers.boom' => ['transport' => 'boom'], 'mail.default' => 'boom']);
        Mail::purge('boom');
        B2bAccounts::remind();
        $this->assertFalse(OrderEvents::has($o, 'terms:reminder'));   // not burned by the failed send

        config(['mail.default' => 'array']);
        Mail::fake();
        $this->travel(1)->days();   // tomorrow: 2 days to go, still inside the reminder window
        B2bAccounts::remind();
        $this->assertTrue(OrderEvents::has($o, 'terms:reminder'));
        Mail::assertSent(\App\Mail\Branded::class, fn ($m) => $m->hasTo($u->email));
    }

    public function test_tracking_problem_alerts_the_shop_once(): void
    {
        $o = $this->order(['tracking_number' => '1Z999AA10123456784', 'tracking_carrier' => 'UPS', 'shipped_at' => now()]);
        $st = ['raw' => 'return_to_sender', 'status' => 'Being returned to sender', 'est' => null];
        $this->assertTrue(OrderEvents::notifyShopOfProblem($o, $st));
        $this->assertFalse(OrderEvents::notifyShopOfProblem($o, $st));
        $this->assertFalse(OrderEvents::notifyShopOfProblem($o, ['raw' => 'in_transit', 'status' => 'In transit']));
        $mails = $this->sentMail();
        $this->assertCount(1, $mails);
        $this->assertSame(config('uds.email'), $mails[0]->getOriginalMessage()->getTo()[0]->getAddress());
    }

    public function test_delivered_mail_shows_eastern_time_and_dropship_wording(): void
    {
        $o = $this->order(['delivered_at' => '2026-07-01 18:05:00', 'tracking_number' => '9400111899223344556677', 'tracking_carrier' => 'USPS']);
        app(\App\Services\OrderMailer::class)->sendDelivered($o);
        $this->assertStringContainsString('2:05 PM ET', $this->sentMail()[0]->getOriginalMessage()->getHtmlBody());

        $this->clearMail();
        $o2 = $this->order(['tracking_service' => 'Shipped by PTI (dropship)']);
        app(\App\Services\OrderMailer::class)->sendShipped($o2, 'USPS', '9400111899223344556677');
        $html = $this->sentMail()[0]->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('is on its way with USPS', $html);
        $this->assertStringNotContainsString('left our shop', $html);

        $this->assertSame('USPS', PtiDropship::carrierFor('9400111899223344556677'));
        $this->assertSame('DHL', PtiDropship::carrierFor('1234567890'));
        $this->assertSame('UPS', PtiDropship::carrierFor('1Z999AA10123456784'));
        $this->assertSame('FedEx', PtiDropship::carrierFor('123456789012'));
        $this->assertSame('USPS', PtiDropship::carrierFor('123456789012', 'USPS Priority'));
    }
}

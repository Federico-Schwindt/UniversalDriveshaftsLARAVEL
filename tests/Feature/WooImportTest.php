<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OrderMailer;
use App\Support\MailGuard;
use App\Support\OrderEvents;
use App\Support\WpPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Old-store import (woo:import): WordPress passwords keep working, imported orders never contact anyone, monthly sales page. */
class WooImportTest extends TestCase
{
    use DatabaseTransactions;

    private function wpUser(string $hash): int
    {
        return DB::table('users')->insertGetId(['name' => 'Alex Morgan', 'email' => 'alex.morgan.wp@example.com', 'password' => null, 'wp_password' => $hash,
            'wp_user_id' => 999001, 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function importedOrder(array $over = []): object
    {
        $id = DB::table('orders')->insertGetId($over + ['order_number' => '990001', 'wc_order_id' => 990001, 'wc_status' => 'processing', 'source' => 'woocommerce', 'status' => 'processing',
            'payment_status' => 'paid', 'payment_method' => 'stripe', 'email' => 'alex.morgan.wp@example.com', 'phone' => '8135550100', 'sms_opt_in' => 1,
            'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'subtotal' => 100, 'total' => 100, 'tracking_number' => '1Z999AA10123456784',
            'tracking_carrier' => 'UPS', 'shipped_at' => now()->subDays(2), 'created_at' => now()->subDays(3), 'updated_at' => now()]);
        return DB::table('orders')->find($id);
    }

    public function test_wordpress_hashes_are_recognised(): void
    {
        $this->assertTrue(WpPassword::check('test12345', '$P$9IQRaTwmfeRo7ud9Fh4E2PdI0S3r.L0'));   // phpass reference vector
        $this->assertFalse(WpPassword::check('test1234', '$P$9IQRaTwmfeRo7ud9Fh4E2PdI0S3r.L0'));
        $wp = '$wp' . password_hash(base64_encode(hash_hmac('sha384', 'Gear oil 90w', 'wp-sha384', true)), PASSWORD_BCRYPT);
        $this->assertTrue(WpPassword::check('Gear oil 90w', $wp));
        $this->assertFalse(WpPassword::check('Gear oil 80w', $wp));
    }

    public function test_imported_customer_signs_in_with_the_old_password_once_converted(): void
    {
        $id = $this->wpUser('$wp' . password_hash(base64_encode(hash_hmac('sha384', 'Driveline-2024', 'wp-sha384', true)), PASSWORD_BCRYPT));
        $this->post('/login', ['email' => 'alex.morgan.wp@example.com', 'password' => 'wrong one'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'alex.morgan.wp@example.com', 'password' => 'Driveline-2024'])->assertRedirect('/account');
        $this->assertAuthenticatedAs(User::find($id));
        $u = DB::table('users')->find($id);
        $this->assertNull($u->wp_password);
        $this->assertTrue(\Hash::check('Driveline-2024', $u->password));
    }

    public function test_imported_orders_never_notify(): void
    {
        Mail::fake();
        $o = $this->importedOrder();
        $this->assertTrue(OrderEvents::imported($o));
        $this->assertFalse(OrderEvents::record($o, 'notify:email:delivered', 'Delivery e-mail sent', $o->email, true));
        OrderEvents::markDelivered($o, 'test', true);
        app(OrderMailer::class)->sendShipped($o, 'UPS', $o->tracking_number);
        app(OrderMailer::class)->send($o, []);
        $this->assertSame([], \App\Support\PayLink::send($o, true));
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertFalse(DB::table('order_events')->where('order_id', $o->id)->where('type', 'like', 'notify:%')->exists());
        $this->assertNotNull(DB::table('orders')->where('id', $o->id)->value('delivered_at'));   // the record still moves
    }

    public function test_tracking_poll_skips_imported_orders(): void
    {
        $o = $this->importedOrder();
        $this->artisan('orders:track', ['--dry' => true])->doesntExpectOutputToContain($o->order_number)->assertSuccessful();
    }

    public function test_mail_guard_only_lets_shop_addresses_through_before_launch(): void
    {
        config(['uds.mail_guard' => true, 'mail.default' => 'array']);
        app('mail.manager')->forgetMailers();
        $admin = DB::table('users')->where('is_admin', 1)->value('email');
        \App\Support\Mailer::simple('a.customer@example.com', 'Should not leave', ['intro' => 'x']);
        \App\Support\Mailer::simple([$admin, 'b.customer@example.com'], 'Admin copy only', ['intro' => 'x']);
        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $to = array_map(fn ($a) => $a->getAddress(), $sent[0]->getOriginalMessage()->getTo());
        $this->assertSame([$admin], $to);
        $this->assertFalse(MailGuard::allowsPhone('+1 (360) 555-0142'));

        // a verification code the visitor asked for goes out to them (owner, 2026-11-11: no OTP when creating an account)
        $this->assertNull(\App\Support\EmailOtp::send('register', 'new.visitor@example.com'));
        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(2, $sent);
        $otp = $sent[1]->getOriginalMessage();
        $this->assertSame(['new.visitor@example.com'], array_map(fn ($a) => $a->getAddress(), $otp->getTo()));
        $this->assertStringStartsWith('Your verification code', $otp->getSubject());
        $this->assertFalse($otp->getHeaders()->has(MailGuard::REQUESTED_HEADER), 'the marker never leaves the server');
        // anything else to a customer is still held back
        \App\Support\Mailer::simple('c.customer@example.com', 'Still blocked', ['intro' => 'x']);
        $this->assertCount(2, app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_monthly_sales_page_counts_imported_history_in_eastern_months(): void
    {
        // 2019-08-01 02:00 UTC is still July 31 in Florida (a year with no other sales)
        $this->importedOrder(['order_number' => '990002', 'wc_order_id' => 990002, 'status' => 'completed', 'total' => 250, 'subtotal' => 250, 'created_at' => '2019-08-01 02:00:00']);
        \Cache::forget('adm_analytics_months');
        $admin = User::query()->where('is_admin', 1)->firstOrFail();
        $html = $this->actingAs($admin)->get('/admin/analytics?year=2019')->assertOk()->assertSee('Month by month')->getContent();
        $this->assertMatchesRegularExpression('/Jul 2019<\/b>.*?\$250\.00/s', $html);
        $this->actingAs($admin)->get('/admin/analytics')->assertOk()->assertSee('Last 12 months');
    }

    public function test_net_sales_leave_out_shipping_and_tax_and_take_refunds_off_in_their_month(): void
    {
        // March 2019: $120 order = $100 of parts + $15 shipping + $5 tax; refunded $40 on April 10
        $o = $this->importedOrder(['order_number' => '990003', 'wc_order_id' => 990003, 'wc_status' => 'completed', 'status' => 'completed', 'subtotal' => 100,
            'shipping_total' => 15, 'tax_total' => 5, 'total' => 120, 'refund_total' => 40, 'created_at' => '2019-03-15 15:00:00']);
        DB::table('order_refunds')->insert(['order_id' => $o->id, 'amount' => 40, 'created_at' => '2019-04-10 15:00:00']);
        // a cancelled one never counts
        $this->importedOrder(['order_number' => '990004', 'wc_order_id' => 990004, 'wc_status' => 'cancelled', 'status' => 'cancelled', 'total' => 500, 'created_at' => '2019-03-16 15:00:00']);
        $mar = \App\Support\SalesStats::totals(\Carbon\Carbon::parse('2019-03-01'), \Carbon\Carbon::parse('2019-03-31'));
        $apr = \App\Support\SalesStats::totals(\Carbon\Carbon::parse('2019-04-01'), \Carbon\Carbon::parse('2019-04-30'));
        $this->assertEqualsWithDelta(100.0, $mar['net'], 0.001);
        $this->assertEqualsWithDelta(15.0, $mar['shipping'], 0.001);
        $this->assertSame(1, $mar['orders']);
        $this->assertEqualsWithDelta(-40.0, $apr['net'], 0.001);
        $this->assertEqualsWithDelta(40.0, $apr['refunds'], 0.001);
    }

    public function test_passwords_refresh_from_a_live_users_export_but_never_over_a_password_set_here(): void
    {
        $wpNew = '$wp$2y$10$' . str_repeat('A', 53);
        $sql = "-- phpMyAdmin SQL Dump\nCREATE TABLE `wpx7_users` (`ID` bigint);\n"
             . "INSERT INTO `wpx7_users` (`ID`, `user_login`, `user_pass`, `user_nicename`, `user_email`, `display_name`) VALUES\n"
             . "(999001, 'alex', '" . $wpNew . "', 'alex', 'alex.morgan.wp@example.com', 'Alex O\\'Morgan, Jr.'),\n"
             . "(999002, 'sam', '\$P\$Bxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'sam', 'sam.wp@example.com', 'Sam');\n";
        $rows = \App\Console\Commands\WooImport::usersFromSql($sql);
        $this->assertCount(2, $rows);
        $this->assertSame("Alex O'Morgan, Jr.", $rows[0]['display_name']);
        $this->assertSame($wpNew, $rows[0]['user_pass']);

        $stale = $this->wpUser('$P$Bstalestalestalestalestalesta');   // wp_user_id 999001, never signed in here
        $kept = DB::table('users')->insertGetId(['name' => 'Sam', 'email' => 'sam.wp@example.com', 'password' => bcrypt('set-here'), 'wp_user_id' => 999002, 'created_at' => now(), 'updated_at' => now()]);
        $f = tempnam(sys_get_temp_dir(), 'wpu') . '.sql'; file_put_contents($f, $sql);
        $hashFile = storage_path('app/import/wp-user-hashes.json'); $had = is_file($hashFile) ? file_get_contents($hashFile) : null;
        try {
            $this->artisan('woo:import', ['--hashes' => $f])->assertSuccessful();
            $this->assertSame($wpNew, DB::table('users')->where('id', $stale)->value('wp_password'));
            $this->assertNull(DB::table('users')->where('id', $kept)->value('wp_password'));
        } finally {
            @unlink($f);
            if ($had !== null) file_put_contents($hashFile, $had); else @unlink($hashFile);
        }
    }

    public function test_the_import_runs_without_the_staging_snapshot(): void
    {
        // Hostinger has no wp_src database: the customers step must fall back to the live export instead of stopping
        config(['database.connections.wp_src.host' => '127.0.0.1', 'database.connections.wp_src.port' => '1', 'database.connections.wp_src.username' => 'none']);
        \Illuminate\Support\Facades\DB::purge('wp_src');
        $cmd = app(\App\Console\Commands\WooImport::class);
        (fn () => $this->dir = storage_path('app/import'))->call($cmd);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, (fn () => $this->hashes())->call($cmd));
        \Illuminate\Support\Facades\DB::purge('wp_src');
    }

    public function test_imported_completed_order_reads_completed_to_the_customer(): void
    {
        $o = $this->importedOrder(['status' => 'completed']);
        $this->assertSame(['delivered', 'Completed', 4], \App\Http\Controllers\AccountController::orderState($o));
    }
}

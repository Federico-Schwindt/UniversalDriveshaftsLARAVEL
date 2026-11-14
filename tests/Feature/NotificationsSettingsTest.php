<?php

namespace Tests\Feature;

use App\Services\OrderMailer;
use App\Services\SmsService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Owner, 2026-11-14: new-order e-mails also go to more addresses (Settings → Notifications), and the site sends no
 * texts and shows no SMS option ("only notifications via mail") unless texts are switched back on.
 */
class NotificationsSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_every_new_order_address_gets_its_own_copy(): void
    {
        Mail::fake();
        Settings::set('notify.new_order_cc', 'modi@example.com, Owner@Example.com; info@example.org, not-an-email');
        $this->assertSame([strtolower(config('uds.email')), 'modi@example.com', 'owner@example.com', 'info@example.org'], OrderMailer::shopRecipients());

        $id = DB::table('orders')->insertGetId(['order_number' => 'UDS-NTF-' . strtoupper(substr(uniqid(), -6)), 'status' => 'processing', 'source' => 'web', 'payment_status' => 'paid', 'payment_method' => 'stripe',
            'email' => 'buyer-' . uniqid() . '@example.com', 'billing_first_name' => 'Alex', 'billing_last_name' => 'Morgan', 'billing_country' => 'US', 'ship_to_different' => 0,
            'shipping_method' => 'upsdap-ground', 'shipping_label' => 'UPS Ground', 'subtotal' => 50, 'shipping_total' => 10, 'discount_total' => 0, 'total' => 60, 'created_at' => now(), 'updated_at' => now()]);
        app(OrderMailer::class)->send(DB::table('orders')->find($id), []);
        foreach (OrderMailer::shopRecipients() as $to) {
            Mail::assertSent(\App\Mail\Branded::class, fn ($m) => $m->hasTo($to) && count($m->to) === 1 && str_starts_with($m->subjectLine, 'New order'));
        }
        Settings::set('notify.new_order_cc', '');
        $this->assertSame([strtolower(config('uds.email'))], OrderMailer::shopRecipients());
    }

    public function test_texts_are_off_and_no_sms_option_is_shown(): void
    {
        Settings::set('notify.sms', false);
        $this->assertFalse(SmsService::on());
        config(['services.twilio.enabled' => true, 'services.twilio.sid' => 'AC1', 'services.twilio.token' => 't', 'services.twilio.from' => '+18135550100']);
        $this->assertFalse(app(SmsService::class)->enabled(), 'Twilio set up is not enough: the site switch is off');
        $this->get('/contact')->assertOk()->assertDontSee('name="sms_consent"', false);
        $this->assertStringContainsString("@if(\\App\\Services\\SmsService::on())<label class=\"co-agree\"", file_get_contents(resource_path('views/checkout.blade.php')));

        Settings::set('notify.sms', true);
        $this->get('/contact')->assertOk()->assertSee('name="sms_consent"', false);
    }
}

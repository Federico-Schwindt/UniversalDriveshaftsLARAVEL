<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Returns / exchanges as a workflow (2026-11-10: a request on order 8773 did not show on the order, the shop heard
 * nothing, and the admin had no page per request): linked to the order, on its timeline, both sides e-mailed, an admin
 * page with only the allowed next steps, customer e-mails per step, notes and return tracking, and the customer sees
 * where it stands.
 */
class RmaWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function order(): object
    {
        $id = DB::table('orders')->insertGetId(['order_number' => 'UDS-RMA-' . uniqid(), 'email' => 'rma-' . uniqid() . '@example.com', 'billing_first_name' => 'Sam',
            'billing_last_name' => 'Lee', 'status' => 'completed', 'payment_status' => 'paid', 'source' => 'web', 'total' => 80, 'subtotal' => 80,
            'created_at' => now()->subDays(10), 'updated_at' => now()]);
        DB::table('order_items')->insert(['order_id' => $id, 'type' => 'product', 'name' => '1310 Series U-Joint', 'sku' => '5-153X', 'price' => 40, 'qty' => 2, 'line_total' => 80, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('orders')->find($id);
    }

    private function file(object $o, string $type = 'exchange'): object
    {
        $this->post('/rma', ['type' => $type, 'order_number' => $o->order_number, 'name' => 'Sam Lee', 'email' => $o->email, 'product' => '5-153X', 'reason' => 'Wrong part received', 'details' => 'Got a 1330 instead'])
            ->assertOk()->assertJsonPath('success', true);
        return DB::table('rma_requests')->where('order_number', $o->order_number)->latest('id')->first();
    }

    public function test_a_request_links_its_order_reaches_both_sides_and_shows_to_the_customer(): void
    {
        Mail::fake();
        $o = $this->order();
        $rma = $this->file($o);
        $this->assertSame((int) $o->id, (int) $rma->order_id);
        $this->assertSame('new', $rma->status);
        $this->assertTrue(DB::table('order_events')->where('order_id', $o->id)->where('type', 'rma')->where('label', 'like', '%' . $rma->ref . '%')->exists());
        $this->assertTrue(DB::table('rma_events')->where('rma_id', $rma->id)->where('type', 'created')->exists());
        $this->assertSame(2, DB::table('rma_events')->where('rma_id', $rma->id)->where('type', 'email')->count());   // shop + customer
        Mail::assertSent(\App\Mail\Branded::class, fn ($m) => $m->hasTo(config('uds.email')));
        Mail::assertSent(\App\Mail\Branded::class, fn ($m) => $m->hasTo($o->email));

        // the customer sees it on the order's status page
        $this->get(\App\Support\OrderEvents::statusUrl($o, false))->assertOk()->assertSee($rma->ref)->assertSee('Under review');
    }

    public function test_admin_pages_steps_and_customer_mails(): void
    {
        Mail::fake();
        $admin = User::query()->where('is_admin', 1)->firstOrFail();
        $o = $this->order();
        $rma = $this->file($o);

        $this->actingAs($admin)->get('/admin/rma')->assertOk()->assertSee($rma->ref);
        $this->actingAs($admin)->get('/admin/rma?q=' . urlencode($o->order_number))->assertOk()->assertSee($rma->ref);
        $this->actingAs($admin)->get('/admin/rma/' . $rma->id)->assertOk()->assertSee('Next step')->assertSee('1310 Series U-Joint')->assertSee('Approve');
        $this->actingAs($admin)->get('/admin/orders/' . $o->id)->assertOk()->assertSee($rma->ref);

        // only allowed moves: a new request cannot jump to resolved
        $this->actingAs($admin)->post('/admin/rma/' . $rma->id, ['status' => 'resolved', 'resolution' => 'refund'])->assertSessionHasErrors('status');

        Mail::fake();
        $this->actingAs($admin)->post('/admin/rma/' . $rma->id, ['status' => 'approved', 'message' => 'Include the packing slip.', 'notify' => 1])->assertSessionHasNoErrors();
        $this->assertSame('approved', DB::table('rma_requests')->where('id', $rma->id)->value('status'));
        Mail::assertSent(\App\Mail\Branded::class, fn ($m) => $m->hasTo($o->email));

        $this->actingAs($admin)->post('/admin/rma/' . $rma->id . '/tracking', ['return_carrier' => 'UPS', 'return_tracking' => '1Z999'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/rma/' . $rma->id . '/note', ['body' => 'Box arrived dented'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/rma/' . $rma->id, ['status' => 'received'])->assertSessionHasNoErrors();
        // resolving needs a resolution
        $this->actingAs($admin)->post('/admin/rma/' . $rma->id, ['status' => 'resolved'])->assertSessionHasErrors('resolution');
        $this->actingAs($admin)->post('/admin/rma/' . $rma->id, ['status' => 'resolved', 'resolution' => 'replacement', 'notify' => 1])->assertSessionHasNoErrors();

        $r = DB::table('rma_requests')->find($rma->id);
        $this->assertSame(['resolved', 'replacement', '1Z999'], [$r->status, $r->resolution, $r->return_tracking]);
        $this->assertNotNull($r->decided_at); $this->assertNotNull($r->received_at); $this->assertNotNull($r->closed_at);
        $this->assertSame(['created', 'email', 'email', 'status', 'email', 'tracking', 'note', 'status', 'status', 'email'],
            DB::table('rma_events')->where('rma_id', $rma->id)->orderBy('id')->pluck('type')->all());
        $this->actingAs($admin)->get('/admin/rma/' . $rma->id)->assertOk()->assertSee('Replacement sent')->assertSee('Box arrived dented')->assertSee('Reopen');
    }

    /** Owner, 2026-11-11: returns and exchanges only within 14 days of delivery, and nobody can file one after that. */
    public function test_returns_close_fourteen_days_after_delivery(): void
    {
        Mail::fake();
        $o = $this->order();
        $form = fn (array $over = []) => $this->postJson('/rma', $over + ['type' => 'return', 'order_number' => $o->order_number, 'name' => 'Sam Lee', 'email' => $o->email, 'product' => '5-153X', 'reason' => 'No longer needed']);

        // delivered 13 days ago: still open, and the order page says until when
        DB::table('orders')->where('id', $o->id)->update(['shipped_at' => now()->subDays(16), 'delivered_at' => now()->subDays(13)]);
        $o = DB::table('orders')->find($o->id);
        $this->get(\App\Support\OrderEvents::statusUrl($o, false))->assertOk()->assertSee('/return-request-form?order=', false)->assertDontSee('Returns closed on');
        $form()->assertOk()->assertJsonPath('success', true);

        // delivered 15 days ago: refused everywhere
        DB::table('orders')->where('id', $o->id)->update(['delivered_at' => now()->subDays(15)]);
        $o = DB::table('orders')->find($o->id);
        $before = DB::table('rma_requests')->count();
        $form()->assertStatus(422)->assertJsonPath('success', false)->assertJsonFragment(['message' => \App\Support\Rma::refusal('return', $o)]);
        $form(['type' => 'exchange'])->assertStatus(422);
        $this->assertSame($before, DB::table('rma_requests')->count());
        $this->get(\App\Support\OrderEvents::statusUrl($o, false))->assertOk()->assertDontSee('/return-request-form?order=', false)->assertSee('Returns closed on');
        $this->get('/return-request-form?order=' . urlencode($o->order_number) . '&e=' . urlencode($o->email))->assertOk()->assertSee('has closed')->assertDontSee('id="rma-form"', false);

        // no delivery scan: counted from the ship date plus transit days
        DB::table('orders')->where('id', $o->id)->update(['delivered_at' => null, 'shipped_at' => now()->subDays(17)]);
        $form()->assertOk();
        DB::table('orders')->where('id', $o->id)->update(['shipped_at' => now()->subDays(20)]);
        $form()->assertStatus(422);

        // the window can't be skipped by leaving the order out or typing one that doesn't exist
        $form(['order_number' => ''])->assertStatus(422);
        $form(['order_number' => 'UDS-NOPE-123'])->assertStatus(422);

        // cancellations follow their own rule
        $this->assertNull(\App\Support\Rma::refusal('cancel', DB::table('orders')->find($o->id)));
    }

    public function test_mail_failures_are_recorded_not_swallowed(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $o = $this->order();
        $rma = $this->file($o);
        $this->assertSame(2, DB::table('rma_events')->where('rma_id', $rma->id)->where('type', 'email_failed')->count());
        $this->assertStringContainsString('SMTP down', (string) DB::table('rma_events')->where('rma_id', $rma->id)->where('type', 'email_failed')->value('detail'));
    }
}

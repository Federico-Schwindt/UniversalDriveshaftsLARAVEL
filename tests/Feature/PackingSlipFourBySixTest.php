<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Owner, 2026-11-14: "4" × 6" paper for the packing slip please". */
class PackingSlipFourBySixTest extends TestCase
{
    use DatabaseTransactions;

    public function test_packing_slips_print_on_4x6(): void
    {
        $id = DB::table('users')->insertGetId(['name' => 'Packer', 'email' => 'slip-' . uniqid() . '@example.test', 'password' => bcrypt('x'), 'is_admin' => 1, 'admin_role' => 'staff', 'wholesale_status' => 'none', 'created_at' => now(), 'updated_at' => now()]);
        $o = DB::table('orders')->whereExists(fn ($q) => $q->from('order_items')->whereColumn('order_items.order_id', 'orders.id'))->orderByDesc('id')->first();
        if (!$o) $this->markTestSkipped('no order with items');
        $this->actingAs(User::find($id));
        $html = $this->get("/admin/orders/{$o->id}/packing-slip")->assertOk()->assertSee('size:4in 6in', false)->assertSee($o->order_number)->getContent();
        if ($out = getenv('SLIP_HTML_OUT')) file_put_contents($out, $html);
    }
}

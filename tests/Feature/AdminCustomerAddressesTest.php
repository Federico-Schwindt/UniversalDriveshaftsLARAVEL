<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Owner, 2026-11-14: "admin cant edit customers addreses or profile info". Staff edit the profile, billing address and saved addresses. */
class AdminCustomerAddressesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_staff_edit_a_customers_profile_and_addresses(): void
    {
        Mail::fake();
        $aid = DB::table('users')->insertGetId(['name' => 'Staff Edit', 'email' => 'se-' . uniqid() . '@example.test', 'password' => bcrypt('x'), 'is_admin' => 1, 'admin_role' => 'staff', 'wholesale_status' => 'none', 'created_at' => now(), 'updated_at' => now()]);
        $cid = DB::table('users')->insertGetId(['name' => 'Cara Diaz', 'email' => 'cara-' . uniqid() . '@example.test', 'password' => bcrypt('x'), 'is_admin' => 0, 'wholesale_status' => 'none', 'created_at' => now(), 'updated_at' => now()]);
        $c = DB::table('users')->find($cid);
        $this->actingAs(User::find($aid));
        $page = $this->get("/admin/customers/{$cid}")->assertOk();
        $page->assertSee('name="billing_address_1"', false)->assertSee('Add address');

        $this->post("/admin/customers/{$cid}", ['name' => 'Cara Díaz', 'email' => $c->email, 'phone' => '813-555-0101', 'company' => 'Diaz Fleet', 'tags' => '',
            'billing_address_1' => '12 Oak St', 'billing_address_2' => '', 'billing_city' => 'Tampa', 'billing_state' => 'FL', 'billing_postcode' => '33602', 'billing_country' => 'us'])
            ->assertRedirect()->assertSessionHas('admin_ok');
        $u = DB::table('users')->find($cid);
        $this->assertSame(['Cara Díaz', '813-555-0101', '12 Oak St', 'Tampa', 'FL', '33602', 'US'], [$u->name, $u->phone, $u->billing_address_1, $u->billing_city, $u->billing_state, $u->billing_postcode, $u->billing_country]);

        $addr = ['name' => 'Cara Diaz', 'street' => '500 Dock Rd', 'city' => 'Odessa', 'state' => 'FL', 'postcode' => '33556', 'country' => 'US', 'kind' => 'shipping', 'residential' => 1];
        $this->post("/admin/customers/{$cid}/addresses", $addr)->assertRedirect()->assertSessionHas('admin_ok', 'Address added.');
        $a = DB::table('dropship_addresses')->where('user_id', $cid)->first();
        $this->assertSame('500 Dock Rd', $a->street);
        $this->assertTrue((bool) $a->is_default, 'the first own address is the default');
        $this->post("/admin/customers/{$cid}/addresses", ['address_id' => $a->id, 'street' => '502 Dock Rd'] + $addr)->assertSessionHas('admin_ok', 'Address updated.');
        $this->assertSame('502 Dock Rd', DB::table('dropship_addresses')->where('id', $a->id)->value('street'));

        // another customer's address can't be reached through this one
        $other = DB::table('dropship_addresses')->insertGetId(['user_id' => $aid, 'kind' => 'shipping', 'name' => 'X', 'street' => '1 Elm', 'city' => 'Tampa', 'postcode' => '33602', 'country' => 'US', 'created_at' => now(), 'updated_at' => now()]);
        $this->post("/admin/customers/{$cid}/addresses", ['address_id' => $other] + $addr)->assertNotFound();
        $this->post("/admin/customers/{$cid}/addresses/{$other}/delete")->assertNotFound();

        $this->post("/admin/customers/{$cid}/addresses/{$a->id}/delete")->assertSessionHas('admin_ok', 'Address removed.');
        $this->assertSame(0, DB::table('dropship_addresses')->where('user_id', $cid)->count());
    }
}

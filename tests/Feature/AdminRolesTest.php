<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The owner manages who has admin access and which role they have (Settings → Admins). */
class AdminRolesTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(?string $role): User
    {
        $u = User::query()->create(['name' => 'Alex Morgan', 'email' => 'adm-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $u->id)->update(['is_admin' => 1, 'admin_role' => $role, 'email_verified_at' => now()]);
        return $u->fresh();
    }

    public function test_owner_changes_roles(): void
    {
        $owner = $this->admin(null);   // legacy is_admin without a role = owner
        $staff = $this->admin('staff');
        $this->actingAs($owner)->get('/admin/settings?tab=users')->assertOk()->assertSee($staff->email)->assertSee('/admin/settings/users/' . $staff->id, false);
        $this->actingAs($owner)->post('/admin/settings/users/' . $staff->id, ['role' => 'manager'])->assertRedirect();
        $this->assertSame('manager', DB::table('users')->where('id', $staff->id)->value('admin_role'));
    }

    public function test_staff_buy_real_labels_but_not_freight_or_refunds(): void
    {
        $staff = $this->admin('staff');
        $this->assertTrue(\App\Support\AdminAccess::can('labels', $staff));
        foreach (['purchases', 'refunds', 'exports', 'catalog_write', 'settings'] as $s) $this->assertFalse(\App\Support\AdminAccess::can($s, $staff), $s);
        $this->assertTrue(\App\Support\AdminAccess::can('labels', $this->admin('manager')));
    }

    public function test_cart_refuses_a_free_product_line(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\CartService::class)->add(['type' => 'product', 'wp_product_id' => 1, 'name' => 'x', 'price' => 0, 'qty' => 1]);
    }

    public function test_hiding_a_shaft_in_admin_marks_it_hidden_by_staff(): void
    {
        $p = DB::table('ds_parts')->where('is_active', 1)->first();
        if (!$p) $this->markTestSkipped('no active shaft');
        $owner = $this->admin(null);
        $this->actingAs($owner)->post('/admin/driveshafts/' . rawurlencode($p->part_number), ['description' => $p->description, 'is_active' => 0])->assertRedirect();
        $this->assertSame('admin', DB::table('ds_parts')->where('part_number', $p->part_number)->value('hidden_reason'));
    }
}

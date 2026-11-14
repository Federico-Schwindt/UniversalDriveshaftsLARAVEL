<?php

namespace Tests\Feature;

use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Owner, 2026-11-14: "remove the google signin, like hide it because is not working". Back with Settings → Storefront. */
class GoogleSignInHiddenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_google_sign_in_is_hidden_until_switched_on(): void
    {
        config(['uds.google_client_id' => 'test-client.apps.googleusercontent.com']);
        Settings::set('auth.google', false);
        foreach (['/login', '/register'] as $p) $this->get($p)->assertOk()->assertDontSee('accounts.google.com/gsi', false)->assertDontSee('g_id_onload', false);
        $this->post('/auth/google', ['credential' => 'x'])->assertNotFound();

        Settings::set('auth.google', true);
        $this->get('/login')->assertOk()->assertSee('accounts.google.com/gsi', false);
    }
}

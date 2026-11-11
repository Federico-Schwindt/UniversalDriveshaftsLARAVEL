<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Fixes from the 2026-11-11 pre-launch security review (owner: "busca brechas de seguridad y vulnerabilidades"). */
class SecurityAuditNov11Test extends TestCase
{
    use DatabaseTransactions;

    /** The admin chat page links to where the customer is: only a path on this site may get there (it was stored XSS). */
    public function test_heartbeat_keeps_only_site_paths(): void
    {
        $u = User::query()->create(['name' => 'Sam Lee', 'email' => 'sec-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        $cases = ['/cart?step=2' => '/cart?step=2', '/x" onmouseover="alert(1)' => '/', 'javascript:alert(1)' => '/', '//evil.example/x' => '/', '/\\evil.example' => '/'];
        foreach ($cases as $sent => $kept) {
            $this->actingAs($u)->postJson('/assistant/ping', ['url' => $sent, 'title' => 'Cart'])->assertOk();
            $this->assertSame($kept, Cache::get("presence:u{$u->id}")['url'] ?? null, $sent);
        }
    }

    /** esc() helpers that go through textContent/innerHTML leave quotes as they are, so they break out of attributes. */
    public function test_no_quote_blind_escape_helper_is_left(): void
    {
        $files = array_merge(glob(resource_path('views/**/*.blade.php')) ?: [], glob(resource_path('views/*.blade.php')) ?: [], glob(public_path('js/*.js')) ?: []);
        foreach ($files as $f) {
            $this->assertDoesNotMatchRegularExpression('/textContent\s*=\s*s\s*==\s*null\s*\?\s*\'\'\s*:\s*(String\()?s\)?\s*;\s*return\s+d\.innerHTML/', file_get_contents($f), $f);
        }
    }

    /** JSON in a <script> with unescaped slashes must hex-encode < and & so catalog text can't close the element. */
    public function test_json_in_pages_cannot_close_its_script(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $f) {
            if (!str_ends_with($f, '.blade.php')) continue;
            $src = file_get_contents($f);
            $this->assertGreaterThanOrEqual(substr_count($src, 'JSON_UNESCAPED_SLASHES'), substr_count($src, 'JSON_HEX_TAG'), (string) $f);
        }
        $html = $this->get('/parts/5-1310XU')->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $json) { $this->assertStringNotContainsString('<', $json); $this->assertNotNull(json_decode($json)); }
    }

    /** Endpoints that write, send mail or call Stripe are rate limited. */
    public function test_abuse_prone_routes_are_throttled(): void
    {
        $want = [['POST', 'forgot-password'], ['POST', 'reset-password'], ['POST', 'dsb/quote'], ['POST', 'dsb/save-design'], ['POST', 'dsb/submit'],
            ['POST', 'catalog/driveshafts/add'], ['POST', 'quick-order/resolve'], ['GET', 'order-received/{number}']];
        foreach ($want as [$method, $uri]) {
            $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $uri && in_array($method, $r->methods(), true));
            $this->assertNotNull($route, $uri);
            $this->assertNotEmpty(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')), "$method $uri");
        }
        // login: per e-mail+IP, per e-mail per hour, and per IP alone
        $limits = \Illuminate\Support\Facades\RateLimiter::limiter('login')(request()->merge(['email' => 'a@b.c']));
        $this->assertCount(3, $limits);
        $this->assertStringStartsWith('ip|', $limits[2]->key);
    }

    /** The reset link comes from APP_URL, not from the Host header of the request that asked for it. */
    public function test_password_reset_link_uses_the_app_url(): void
    {
        config(['app.url' => 'https://shop.example']);
        $u = User::query()->create(['name' => 'Sam Lee', 'email' => 'sec-' . uniqid() . '@example.com', 'password' => bcrypt('x' . uniqid())]);
        $html = (new \Illuminate\Auth\Notifications\ResetPassword('tok123'))->toMail($u)->render();
        $this->assertStringContainsString('https://shop.example/reset-password/tok123', $html);
    }
}

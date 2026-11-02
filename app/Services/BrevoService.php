<?php

namespace App\Services;

use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Brevo (ex-Sendinblue): marketing lists + marketing sends from noreply@. Transactional mail stays on SMTP. */
class BrevoService
{
    public function enabled(): bool
    {
        return (bool) config('services.brevo.enabled') && config('services.brevo.key') && (bool) Settings::get('brevo.enabled', true);
    }

    private function api(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['api-key' => config('services.brevo.key'), 'accept' => 'application/json'])->baseUrl('https://api.brevo.com/v3')->timeout(15);
    }

    public function sender(): array
    {
        return ['email' => Settings::get('brevo.from_address') ?: config('services.brevo.from'), 'name' => Settings::get('brevo.from_name') ?: config('services.brevo.from_name')];
    }

    /** Account + sender status for the settings page (cached 10 min). */
    public function status(): array
    {
        if (!config('services.brevo.key')) return ['ok' => false, 'error' => 'No API key'];
        return Cache::remember('brevo_status', 600, function () {
            try {
                $a = $this->api()->get('/account'); if (!$a->successful()) return ['ok' => false, 'error' => 'HTTP ' . $a->status()];
                $s = $this->api()->get('/senders')->json('senders', []);
                $l = $this->api()->get('/contacts/lists', ['limit' => 50])->json('lists', []);
                return ['ok' => true, 'email' => $a->json('email'), 'company' => $a->json('companyName'), 'credits' => $a->json('plan.0.credits'),
                    'senders' => array_map(fn ($x) => ['email' => $x['email'], 'active' => (bool) $x['active']], $s), 'lists' => array_map(fn ($x) => ['id' => $x['id'], 'name' => $x['name'], 'count' => $x['uniqueSubscribers'] ?? $x['totalSubscribers'] ?? null], $l)];
            } catch (\Throwable $e) { return ['ok' => false, 'error' => $e->getMessage()]; }
        });
    }

    /** Get-or-create a list by name; the id is remembered in settings. */
    public function listId(string $key, string $name): ?int
    {
        $id = (int) Settings::get("brevo.list_$key", 0);
        if ($id) return $id;
        try {
            foreach ($this->api()->get('/contacts/lists', ['limit' => 50])->json('lists', []) as $l) if (strcasecmp($l['name'], $name) === 0) { Settings::set("brevo.list_$key", (int) $l['id']); return (int) $l['id']; }
            $folder = $this->api()->get('/contacts/folders', ['limit' => 1])->json('folders.0.id') ?: $this->api()->post('/contacts/folders', ['name' => 'Website'])->json('id');
            $r = $this->api()->post('/contacts/lists', ['name' => $name, 'folderId' => $folder]);
            if ($r->successful()) { Settings::set("brevo.list_$key", (int) $r->json('id')); Cache::forget('brevo_status'); return (int) $r->json('id'); }
        } catch (\Throwable $e) { report($e); }
        return null;
    }

    /** Create/update a contact and put it on lists. $attrs: FIRSTNAME, LASTNAME, SMS, COMPANY, LAST_ORDER, TOTAL_SPENT… */
    public function upsertContact(string $email, array $attrs = [], array $listKeys = ['customers']): bool
    {
        if (!$this->enabled() || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
        $lists = array_values(array_filter(array_map(fn ($k) => $this->listId($k, $k === 'newsletter' ? 'Newsletter' : 'Website customers'), $listKeys)));
        try {
            $r = $this->api()->post('/contacts', ['email' => $email, 'attributes' => array_filter($attrs, fn ($v) => $v !== null && $v !== ''), 'listIds' => $lists, 'updateEnabled' => true]);
            return $r->successful() || $r->status() === 204;
        } catch (\Throwable $e) { report($e); return false; }
    }

    /** Marketing-style send through Brevo from noreply@ (branded HTML). $headers: e.g. List-Unsubscribe (Brevo's {{ unsubscribe }} tag does not work in /smtp/email). */
    public function send(string $to, string $subject, string $html, ?string $toName = null, ?string $replyTo = null, array $headers = []): array
    {
        if (!$this->enabled()) return ['ok' => false, 'error' => 'Brevo is disabled'];
        try {
            $r = $this->api()->post('/smtp/email', array_filter([
                'sender' => $this->sender(), 'to' => [array_filter(['email' => $to, 'name' => $toName])], 'subject' => $subject, 'htmlContent' => $html,
                'textContent' => \App\Mail\Branded::textFromHtml($html), 'replyTo' => $replyTo ? ['email' => $replyTo] : null, 'tags' => ['website'], 'headers' => $headers ?: null,
            ]));
            return $r->successful() ? ['ok' => true, 'id' => $r->json('messageId')] : ['ok' => false, 'error' => $r->json('message') ?: ('HTTP ' . $r->status())];
        } catch (\Throwable $e) { return ['ok' => false, 'error' => $e->getMessage()]; }
    }

    /** Newsletter opt-out: Brevo blocklists the address for marketing sends (our marketing_suppressions row is the source of truth). */
    public function unsubscribe(string $email): bool
    {
        if (!$this->enabled()) return false;
        try { $r = $this->api()->put('/contacts/' . rawurlencode($email), ['emailBlacklisted' => true]); return $r->successful() || $r->status() === 204; }
        catch (\Throwable $e) { report($e); return false; }
    }

    /** Ask Brevo to register a sender (it e-mails a verification code to that address). */
    public function createSender(string $email, string $name): array
    {
        try { $r = $this->api()->post('/senders', ['name' => $name, 'email' => $email]); Cache::forget('brevo_status'); return $r->successful() ? ['ok' => true] : ['ok' => false, 'error' => $r->json('message') ?: ('HTTP ' . $r->status())]; }
        catch (\Throwable $e) { return ['ok' => false, 'error' => $e->getMessage()]; }
    }

    /** Sync a customer from an order (called after checkout / admin orders). */
    public function syncOrder(object $order): void
    {
        if (!Settings::get('brevo.sync_customers', true)) return;
        try {
            $stats = \Illuminate\Support\Facades\DB::table('orders')->where('email', $order->email)->where('status', '!=', 'cancelled')->selectRaw('COUNT(*) n, SUM(total) t')->first();
            $this->upsertContact($order->email, ['FIRSTNAME' => $order->billing_first_name, 'LASTNAME' => $order->billing_last_name, 'COMPANY' => $order->billing_company, 'SMS' => $order->sms_opt_in ? preg_replace('/[^0-9+]/', '', (string) $order->phone) : null,
                'STATE' => $order->billing_state, 'ORDERS' => (int) ($stats->n ?? 1), 'TOTAL_SPENT' => round((float) ($stats->t ?? $order->total), 2), 'LAST_ORDER' => now()->toDateString()], ['customers']);
        } catch (\Throwable $e) {}
    }
}

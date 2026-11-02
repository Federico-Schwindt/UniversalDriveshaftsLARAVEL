<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Powertrain Industries dealer portal (c2.powertrainindustries.com) through the JSON API its own
 * front end calls. Sign-in: the portal hands a guest API_KEY cookie, POST /auth/login with Basic auth
 * returns the session key, and every later call sends it as x-api-key.
 */
class PtiClient
{
    private const API = 'https://api.powertrainindustries.com/api/';
    private const PORTAL = 'https://c2.powertrainindustries.com/';
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

    private ?string $key = null;

    public function configured(): bool
    {
        return (bool) config('services.pti.user') && (bool) config('services.pti.pass');
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders(['User-Agent' => self::UA, 'Origin' => rtrim(self::PORTAL, '/'), 'Referer' => self::PORTAL, 'Accept' => 'application/json'])
            ->timeout(60)->retry(2, 1500, throw: false);
    }

    private function login(bool $fresh = false): string
    {
        if ($this->key && !$fresh) return $this->key;
        if (!$fresh && ($k = Cache::get('pti_api_key'))) return $this->key = $k;
        $guest = Http::withHeaders(['User-Agent' => self::UA])->timeout(30)->get(self::PORTAL . 'auth')->cookies()->getCookieByName('API_KEY')?->getValue();
        if (!$guest) throw new \RuntimeException('PTI portal did not hand out a guest API key');
        $r = $this->http()->withHeaders(['x-api-key' => $guest])->withBasicAuth(config('services.pti.user'), config('services.pti.pass'))->post(self::API . 'auth/login');
        if (!$r->json('success') || !is_string($r->json('data'))) throw new \RuntimeException('PTI login failed: ' . mb_substr((string) $r->json('message'), 0, 120));
        Cache::put('pti_api_key', $r->json('data'), now()->addHours(6));
        return $this->key = $r->json('data');
    }

    /** One API call; signs in again once when the session has expired. */
    public function call(string $method, string $path, array $data = []): array
    {
        for ($try = 0; $try < 2; $try++) {
            $req = $this->http()->withHeaders(['x-api-key' => $this->login($try > 0)]);
            $r = $method === 'GET' ? $req->get(self::API . $path, $data) : $req->asForm()->send($method, self::API . $path, ['form_params' => $data]);
            $j = $r->json() ?? [];
            if (!empty($j['success'])) { $data = $j['data'] ?? []; return is_array($data) ? $data : ['message' => $data]; }
            if (!preg_match('/session|login/i', (string) ($j['message'] ?? ''))) throw new \RuntimeException("PTI $path: " . mb_substr((string) ($j['message'] ?? 'HTTP ' . $r->status()), 0, 160));
            Cache::forget('pti_api_key');
        }
        throw new \RuntimeException("PTI $path: could not sign in");
    }

    /** Every part listed under a component-parts type, with its specs, pictures and our price. */
    public function catalog(string $type, string $catalog = 'component_parts'): array
    {
        $q = ['brand' => 'all', 'series' => 'all', 'vehicle_class' => 'all', 'year' => 'all', 'make' => 'all', 'model' => 'all'];
        $d = $this->call('GET', "catalogs/$catalog/type/" . $type, $q);
        return ['cols' => $d['cols'] ?? [], 'results' => $d['results'] ?? []];
    }

    /** On-hand by PTI store id plus what they can build. */
    public function stock(string $part): array
    {
        $d = $this->call('GET', 'my/instks/instks_by_store_id/', ['part_number' => $part]);
        $by = array_map('intval', (array) ($d['instks_by_store_id'] ?? []));
        $build = array_sum(array_map('intval', (array) ($d['can_builds_by_store_id'] ?? [])));
        return ['by_store' => $by, 'total' => array_sum($by), 'can_build' => $build];
    }

    /**
     * Current PTI prices for complete driveshafts, by part number (catalogs/driveshafts/search), several at once.
     * Returns ['prices' => part => ['list' => strike price, 'net' => our price], 'absent' => parts PTI answered for
     * but no longer lists, or lists without a price (so they can't be ordered)]. A part whose request failed is in
     * neither, so a PTI outage never looks like a removal.
     */
    public function driveshaftPrices(array $parts, int $concurrency = 6): array
    {
        $key = $this->login(); $out = []; $absent = []; $failed = [];
        $money = fn ($v) => ($n = (float) str_replace(['$', ','], '', (string) $v)) > 0 ? $n : null;
        // PTI's firewall answers 403 when pushed too hard: anything that failed gets two slower, one-at-a-time retries
        foreach ([[array_values($parts), $concurrency, 0], [null, 1, 1200000], [null, 1, 4000000]] as [$list, $width, $pause]) {
        if ($list === null) { $list = $failed; $failed = []; }
        foreach (array_chunk($list, $width) as $chunk) {
            if ($pause) usleep($pause);
            $resp = Http::pool(fn ($pool) => array_map(fn ($pn) => $pool->as($pn)->withHeaders(['User-Agent' => self::UA, 'Origin' => rtrim(self::PORTAL, '/'), 'Referer' => self::PORTAL, 'Accept' => 'application/json', 'x-api-key' => $key])
                ->timeout(40)->get(self::API . 'catalogs/driveshafts/search', ['search' => $pn, 'merge_applications' => 'true']), $chunk));
            foreach ($chunk as $pn) {
                $r = $resp[$pn] ?? null;
                if (!$r instanceof \Illuminate\Http\Client\Response) { $failed[] = $pn; continue; }
                if (!$r->json('success') && preg_match('/session|login/i', (string) $r->json('message'))) { Cache::forget('pti_api_key'); $key = $this->login(true); continue; }
                if (!$r->successful() || !$r->json('success')) { $failed[] = $pn; continue; }
                $found = false;
                foreach ((array) $r->json('data.results') as $row) {
                    if (strcasecmp(trim((string) ($row['part_number'] ?? '')), $pn) !== 0) continue;   // search is fuzzy: exact part only
                    $net = $money($row['price'] ?? null); $strike = $money($row['strike_price'] ?? null) ?? $net;
                    if ($strike) { $out[$pn] = ['list' => $strike, 'net' => $net ?? $strike]; $found = true; }   // listed with no price = can't be ordered
                    break;
                }
                if (!$found) $absent[] = $pn;
            }
        }
        }
        return ['prices' => $out, 'absent' => $absent];
    }

    /**
     * Run many catalogs/driveshafts/search queries (the search matches any part number CONTAINING the text) and
     * return every result row by part number. Same slow retry for firewall 403s as driveshaftPrices().
     * @return array{rows: array<string,array>, failed: string[]}
     */
    public function searchDriveshafts(array $queries, int $concurrency = 4): array
    {
        $key = $this->login(); $rows = []; $failed = [];
        foreach ([[array_values($queries), $concurrency, 0], [null, 1, 1200000], [null, 1, 4000000]] as [$list, $width, $pause]) {
            if ($list === null) { $list = $failed; $failed = []; }
            foreach (array_chunk($list, $width) as $chunk) {
                if ($pause) usleep($pause);
                $resp = Http::pool(fn ($pool) => array_map(fn ($q) => $pool->as($q)->withHeaders(['User-Agent' => self::UA, 'Origin' => rtrim(self::PORTAL, '/'), 'Referer' => self::PORTAL, 'Accept' => 'application/json', 'x-api-key' => $key])
                    ->timeout(60)->get(self::API . 'catalogs/driveshafts/search', ['search' => $q, 'merge_applications' => 'true']), $chunk));
                foreach ($chunk as $q) {
                    $r = $resp[$q] ?? null;
                    if (!$r instanceof \Illuminate\Http\Client\Response || !$r->successful() || !$r->json('success')) {
                        if ($r instanceof \Illuminate\Http\Client\Response && preg_match('/session|login/i', (string) $r->json('message'))) { Cache::forget('pti_api_key'); $key = $this->login(true); }
                        $failed[] = $q; continue;
                    }
                    foreach ((array) $r->json('data.results') as $row) {
                        $pn = trim((string) ($row['part_number'] ?? ''));
                        if ($pn !== '' && !isset($rows[$pn])) $rows[$pn] = $row;
                    }
                }
            }
        }
        return ['rows' => $rows, 'failed' => $failed];
    }

    public function cartItems(): array
    {
        return $this->call('GET', 'my/cart/cart', ['is_checkout' => 'false'])['items'] ?? [];
    }

    /**
     * Weight and box for one part: put it in the (empty) PTI cart, read the line weight and the box
     * PTI's own shipping quote packs it in, then take it out again. Never touches anything else in the cart.
     */
    public function shipBox(string $part): ?array
    {
        $this->call('PUT', 'my/cart/item/', ['part_number' => $part, 'quantity' => 1]);
        try {
            $line = collect($this->cartItems())->firstWhere('part_number', $part);
            $rates = $this->call('GET', 'my/cart/shipping_rates');
            $box = null;
            foreach ((array) $rates as $rate) { if (!empty($rate['boxes'][0])) { $box = $rate['boxes'][0]; break; } }
            if (!$line && !$box) return null;
            return [
                'description' => $line['description'] ?? null,
                'item_weight' => isset($line['weight']) ? (float) $line['weight'] : null,
                'ship_weight' => isset($box['weight']) ? (float) $box['weight'] : null,
                'box' => $box ? [(float) $box['length'], (float) $box['width'], (float) $box['height']] : null,
            ];
        } finally {
            $this->call('DELETE', 'my/cart/item/', ['part_number' => $part, 'is_checkout' => 'false']);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\B2bAccounts;
use App\Services\CartService;
use App\Support\LiveOrigin;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Brings the WooCommerce store's customers and orders into this app (owner, 2026-11-04: all customers and orders,
 * same passwords, orders with their tracking numbers).
 *
 *  - Source: the live store's wc/v3 API (read-only keys, through LiveOrigin), cached under storage/app/import so a
 *    re-run works offline unless --refresh. The API never exposes passwords: hashes come from an export of the live
 *    store's users table (`--hashes=file`, phpMyAdmin SQL or CSV from Hostinger, kept as storage/app/import/wp-user-hashes.json),
 *    else from the wp_src snapshot (the stale Plesk copy, Aug 2026).
 *  - Re-runnable: users match on wp_user_id (then e-mail), orders on wc_order_id; an order is rewritten only when the
 *    store modified it since the last run. Run it again at launch to bring the last days across. Accounts that already
 *    signed in here keep their password.
 *  - Silent: imported orders carry source=woocommerce, which OrderEvents / OrderMailer / PayLink / orders:track treat as
 *    history (no e-mail, text, CRM sync, stock movement or tracking poll), and nothing in this command sends anything.
 *  - WordPress staff (administrator, shop_manager, production) are not imported; existing admins are only linked.
 */
class WooImport extends Command
{
    protected $signature = 'woo:import {--customers : customers only} {--orders : orders only} {--refresh : download again from the live store}
        {--force : rewrite every order, not only the ones changed since the last run} {--dry-run : report only}
        {--hashes= : an export of the live store\'s wp_users table (SQL or CSV): refresh the WordPress passwords}';
    protected $description = 'Import the old WooCommerce store: customers (with their passwords) and orders (with tracking)';

    private const STAFF_ROLES = ['administrator', 'shop_manager', 'production', 'editor', 'author'];
    private const STAFF_GROUPS = ['41062'];                     // B2BKing "Production STAFF"
    private const GROUPS = ['39917' => 'Business B2B'];
    private const CARRIERS = ['ups' => 'UPS', 'usps' => 'USPS', 'fedex' => 'FedEx', 'dhl' => 'DHL', 'dhl-us' => 'DHL', 'ontrac' => 'OnTrac', 'lasership' => 'LaserShip'];

    private string $dir;
    private bool $dry;

    public function handle(): int
    {
        $this->dir = storage_path('app/import');
        if (!is_dir($this->dir)) mkdir($this->dir, 0775, true);
        $this->dry = (bool) $this->option('dry-run');
        if ($file = $this->option('hashes')) {
            $this->loadHashes((string) $file);
            if (!$this->option('customers') && !$this->option('orders')) return self::SUCCESS;   // hashes only
        }
        $both = !$this->option('customers') && !$this->option('orders');
        if ($both || $this->option('customers')) $this->customers();
        if ($both || $this->option('orders')) $this->orders();
        return self::SUCCESS;
    }

    /* ---------------- customers ---------------- */

    private function customers(): void
    {
        $rows = $this->fetch('customers', 'customers', ['role' => 'all', 'orderby' => 'id', 'order' => 'asc']);
        $hashes = $this->hashes();
        $st = ['new' => 0, 'updated' => 0, 'admins_linked' => 0, 'staff_skipped' => 0, 'invalid_email' => 0, 'without_password' => 0, 'b2b_approved' => 0, 'b2b_pending' => 0];
        $staff = [];
        foreach ($rows as $c) {
            $email = strtolower(trim((string) ($c['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $st['invalid_email']++; continue; }
            $meta = $this->meta($c['meta_data'] ?? []);
            $group = (string) ($meta['b2bking_customergroup'] ?? 'no');
            $existing = DB::table('users')->where('wp_user_id', $c['id'])->first() ?: DB::table('users')->where('email', $email)->first();
            if ($existing && $existing->is_admin) {
                if (!$existing->wp_user_id && !$this->dry) DB::table('users')->where('id', $existing->id)->update(['wp_user_id' => $c['id']]);
                $st['admins_linked']++;
                continue;
            }
            if (in_array($c['role'] ?? '', self::STAFF_ROLES, true) || in_array($group, self::STAFF_GROUPS, true)) { $st['staff_skipped']++; $staff[] = "{$email} ({$c['role']})"; continue; }

            $b = $c['billing'] ?? [];
            $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')) ?: (string) ($c['username'] ?? strstr($email, '@', true));
            [$ws, $wg] = $this->wholesale($meta, $group);
            if ($ws) $st['b2b_' . $ws]++;
            $row = [
                'name' => mb_substr($name, 0, 255), 'email' => $email, 'wp_user_id' => $c['id'],
                'phone' => $this->str($b['phone'] ?? null, 255), 'company' => $this->str($b['company'] ?? null, 120),
                'billing_address_1' => $this->str($b['address_1'] ?? null, 190), 'billing_address_2' => $this->str($b['address_2'] ?? null, 120),
                'billing_city' => $this->str($b['city'] ?? null, 120), 'billing_state' => $this->str($b['state'] ?? null, 60),
                'billing_postcode' => $this->str($b['postcode'] ?? null, 20), 'billing_country' => $this->country($b['country'] ?? null),
                'updated_at' => now(),
            ];
            $hash = $hashes[$c['id']] ?? null;
            if (!$hash) $st['without_password']++;
            if ($this->dry) { $st[$existing ? 'updated' : 'new']++; continue; }

            if (!$existing) {
                $created = $this->utc($c['date_created_gmt'] ?? null) ?? now();
                // they confirmed their e-mail on the old store long ago: no verification step on the first sign-in
                $id = DB::table('users')->insertGetId($row + ['password' => null, 'wp_password' => $hash, 'is_admin' => 0, 'email_verified_at' => $created, 'net_terms' => 0,
                    'wholesale_status' => $ws ?: 'none', 'wholesale_group' => $wg, 'created_at' => $created]);
                $st['new']++;
            } else {
                $id = $existing->id;
                // never undo what happened here: a password set on this site, a B2B decision taken in the admin
                if (!$existing->password) $row['wp_password'] = $hash;
                if ($ws && ($existing->wholesale_status ?? 'none') === 'none') $row += ['wholesale_status' => $ws, 'wholesale_group' => $wg];
                DB::table('users')->where('id', $id)->update($row);
                $st['updated']++;
            }
            $this->address($id, $c['shipping'] ?? [], $name);
        }
        $this->table(array_keys($st), [array_values($st)]);
        if ($staff) $this->line('Not imported (WordPress staff): ' . implode(', ', $staff));
    }

    /** WordPress password hashes by user id: the live store's export when there is one, else the stale snapshot. */
    private function hashes(): \Illuminate\Support\Collection
    {
        // the snapshot lives only on the staging box; on Hostinger (where the launch import runs) the export is the source
        try { $snap = DB::connection('wp_src')->table('wp_users')->pluck('user_pass', 'ID'); } catch (\Throwable) { $snap = collect(); }
        $file = "{$this->dir}/wp-user-hashes.json";
        if (!is_file($file)) return $snap;
        $live = collect(json_decode(file_get_contents($file), true) ?: [])->map(fn ($u) => $u['pass']);
        return $snap->replace($live);
    }

    /**
     * Read an export of the live store's users table and refresh the WordPress hash of every account that has not
     * signed in here yet (an account with its own password on this site is never touched).
     */
    private function loadHashes(string $file): void
    {
        $path = is_file($file) ? $file : base_path($file);
        if (!is_file($path)) { $this->error("No such file: $file"); return; }
        $rows = str_ends_with(strtolower($path), '.csv') ? self::usersFromCsv($path) : self::usersFromSql((string) file_get_contents($path));
        $rows = array_filter($rows, fn ($r) => !empty($r['ID']) && !empty($r['user_pass']) && preg_match('/^(\$wp\$2y\$|\$P\$|\$H\$|\$2y\$|[a-f0-9]{32}$)/', (string) $r['user_pass']));
        if (!$rows) { $this->error('No users with a password hash found in that file (expected the wp_users table: ID, user_email, user_pass).'); return; }
        $map = [];
        foreach ($rows as $r) $map[(int) $r['ID']] = ['email' => strtolower(trim((string) ($r['user_email'] ?? ''))), 'pass' => (string) $r['user_pass']];
        $st = ['in_file' => count($map), 'refreshed' => 0, 'already_current' => 0, 'signed_in_here_kept' => 0, 'not_imported_yet' => 0];
        foreach ($map as $wpId => $u) {
            $acc = DB::table('users')->where('wp_user_id', $wpId)->first(['id', 'password', 'wp_password'])
                ?? ($u['email'] !== '' ? DB::table('users')->where('email', $u['email'])->where('is_admin', 0)->first(['id', 'password', 'wp_password']) : null);
            if (!$acc) { $st['not_imported_yet']++; continue; }
            if ($acc->password) { $st['signed_in_here_kept']++; continue; }
            if ($acc->wp_password === $u['pass']) { $st['already_current']++; continue; }
            if (!$this->dry) DB::table('users')->where('id', $acc->id)->update(['wp_password' => $u['pass'], 'updated_at' => now()]);
            $st['refreshed']++;
        }
        if (!$this->dry) { file_put_contents("{$this->dir}/wp-user-hashes.json", json_encode($map)); @chmod("{$this->dir}/wp-user-hashes.json", 0600); }
        $this->info('Passwords from ' . basename($path) . ':');
        $this->table(array_keys($st), [array_values($st)]);
    }

    /** Rows of the users table from a phpMyAdmin / mysqldump SQL export (any table prefix). */
    public static function usersFromSql(string $sql): array
    {
        $out = [];
        if (!preg_match_all('/INSERT INTO `?\w*users`?\s*\(([^)]*)\)\s*VALUES\s*/i', $sql, $m, PREG_OFFSET_CAPTURE)) return [];
        foreach ($m[0] as $i => $hit) {
            $cols = array_map(fn ($c) => trim($c, " `"), explode(',', $m[1][$i][0]));
            $pos = $hit[1] + strlen($hit[0]); $len = strlen($sql);
            // walk the VALUES list: (…),(…); with quoted strings and backslash escapes
            while ($pos < $len) {
                while ($pos < $len && $sql[$pos] !== '(' && $sql[$pos] !== ';') $pos++;
                if ($pos >= $len || $sql[$pos] === ';') break;
                $pos++; $vals = []; $cur = ''; $q = false;
                for (; $pos < $len; $pos++) {
                    $ch = $sql[$pos];
                    if ($q) {
                        if ($ch === '\\' && $pos + 1 < $len) { $n = $sql[++$pos]; $cur .= ['n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0"][$n] ?? $n; continue; }
                        if ($ch === "'" && ($sql[$pos + 1] ?? '') === "'") { $cur .= "'"; $pos++; continue; }
                        if ($ch === "'") { $q = false; continue; }
                        $cur .= $ch; continue;
                    }
                    if ($ch === "'") { $q = true; continue; }
                    if ($ch === ',') { $vals[] = $cur; $cur = ''; continue; }
                    if ($ch === ')') { $vals[] = $cur; $pos++; break; }
                    if (!ctype_space($ch)) $cur .= $ch;
                }
                if (count($vals) === count($cols)) $out[] = array_combine($cols, array_map(fn ($v) => $v === 'NULL' ? null : $v, $vals));
            }
        }
        return $out;
    }

    /** Rows of the users table from a CSV export with a header row. */
    public static function usersFromCsv(string $path): array
    {
        $out = []; $h = fopen($path, 'r'); $head = null;
        while (($r = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
            if ($head === null) { $head = array_map(fn ($c) => trim((string) $c, " \xEF\xBB\xBF\""), $r); continue; }
            if (count($r) === count($head)) $out[] = array_combine($head, $r);
        }
        fclose($h);
        return $out;
    }

    /** B2BKing → [wholesale_status, wholesale_group]. */
    private function wholesale(array $meta, string $group): array
    {
        if (($meta['b2bking_b2buser'] ?? '') === 'yes' && !in_array($group, ['', 'no'], true)) {
            return [($meta['b2bking_account_approved'] ?? '') === 'no' ? 'pending' : 'approved', self::GROUPS[$group] ?? "B2B group {$group}"];
        }
        if (($meta['b2bking_b2b_application_pending'] ?? '') === 'yes' || ($meta['b2bking_account_approved'] ?? '') === 'no') return ['pending', 'Business B2B'];
        return [null, null];
    }

    /** The store's shipping address becomes the account's default "my places" address (once). */
    private function address(int $userId, array $s, string $name): void
    {
        if (trim((string) ($s['address_1'] ?? '')) === '' || DB::table('dropship_addresses')->where('user_id', $userId)->where('kind', 'shipping')->exists()) return;
        DB::table('dropship_addresses')->insert([
            'user_id' => $userId, 'name' => mb_substr(trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')) ?: $name, 0, 160), 'label' => 'Shipping', 'kind' => 'shipping',
            'company' => $this->str($s['company'] ?? null, 160), 'street' => $this->str($s['address_1'] ?? null, 255), 'unit' => $this->str($s['address_2'] ?? null, 255),
            'city' => $this->str($s['city'] ?? null, 255), 'state' => $this->str($s['state'] ?? null, 255), 'postcode' => $this->str($s['postcode'] ?? null, 255),
            'country' => $this->country($s['country'] ?? null) ?? 'US', 'phone' => $this->str($s['phone'] ?? null, 255), 'residential' => 0, 'is_default' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /* ---------------- orders ---------------- */

    private function orders(): void
    {
        $orders = $this->fetch('orders', 'orders', ['status' => 'any', 'orderby' => 'id', 'order' => 'asc']);
        $notes = $this->notes($orders);
        $refunds = $this->refunds($orders);
        $users = DB::table('users')->whereNotNull('wp_user_id')->pluck('id', 'wp_user_id');
        $byEmail = DB::table('users')->pluck('id', 'email')->mapWithKeys(fn ($id, $e) => [strtolower($e) => $id]);
        $mfile = "{$this->dir}/orders-manifest.json";
        $manifest = is_file($mfile) ? (json_decode(file_get_contents($mfile), true) ?: []) : [];
        $st = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'kept_local_work' => 0, 'skipped_drafts' => 0, 'with_tracking' => 0, 'tracking_from_notes' => 0, 'linked_to_account' => 0];
        $bar = $this->output->createProgressBar(count($orders));
        foreach ($orders as $o) {
            $bar->advance();
            if (in_array($o['status'], ['checkout-draft', 'trash', 'auto-draft'], true)) { $st['skipped_drafts']++; continue; }
            $existing = DB::table('orders')->where('wc_order_id', $o['id'])->first(['id']);
            $mod = (string) ($o['date_modified_gmt'] ?? '');
            if ($existing && !$this->option('force') && ($manifest[$o['id']] ?? null) === $mod) { $st['unchanged']++; continue; }
            // staff already worked on it here (dropship, label, freight, a note): this site owns it now
            if ($existing && $this->workedHere($existing->id)) { $st['kept_local_work']++; continue; }

            $meta = $this->meta($o['meta_data'] ?? []);
            $tracks = $this->tracking($meta, $notes[$o['id']]['n'] ?? [], $fromNotes);
            if ($tracks) $st['with_tracking']++;
            if ($fromNotes) $st['tracking_from_notes']++;
            $email = strtolower(trim((string) ($o['billing']['email'] ?? '')));
            $userId = $users[$o['customer_id']] ?? ($email !== '' ? ($byEmail[$email] ?? null) : null);   // guest orders join the account with that e-mail
            if ($userId) $st['linked_to_account']++;
            if ($this->dry) { $st[$existing ? 'updated' : 'new']++; continue; }

            DB::transaction(function () use ($o, $meta, $tracks, $userId, $email, $existing, $notes, $refunds, &$st) {
                $row = $this->orderRow($o, $meta, $tracks, $userId, $email);
                if ($existing) {
                    $id = $existing->id;
                    DB::table('orders')->where('id', $id)->update($row);
                    // only what this command wrote is replaced; line ids stay put (forms and dropships refer to them)
                    DB::table('order_events')->where('order_id', $id)->where('dedupe_key', 'like', 'woo:%')->delete();
                    DB::table('order_notes')->where('order_id', $id)->whereNull('user_id')->delete();
                    $st['updated']++;
                } else {
                    $id = DB::table('orders')->insertGetId($row + ['wc_order_id' => $o['id'], 'order_number' => $this->number($o)]);
                    $st['new']++;
                }
                $this->items($id, $o);
                $this->saveRefunds($id, $refunds[$o['id']]['r'] ?? []);
                $this->events($id, $o, $tracks, $refunds[$o['id']]['r'] ?? []);
                $this->orderNotes($id, $notes[$o['id']]['n'] ?? []);
            });
            $manifest[$o['id']] = $mod;
        }
        $bar->finish(); $this->newLine();
        if (!$this->dry) file_put_contents($mfile, json_encode($manifest));
        $this->table(array_keys($st), [array_values($st)]);

        // B2B tiers from the imported purchases, quietly (the first tier is never announced)
        if (!$this->dry) foreach (DB::table('users')->whereNotNull('wp_user_id')->where('wholesale_status', 'approved')->pluck('id') as $uid) {
            B2bAccounts::evaluate((int) $uid, false, 'Imported from the old store');
        }
    }

    private function orderRow(array $o, array $meta, array $tracks, ?int $userId, string $email): array
    {
        $b = $o['billing'] ?? []; $s = $o['shipping'] ?? [];
        $refund = round(array_sum(array_map(fn ($r) => abs((float) $r['total']), $o['refunds'] ?? [])), 2);
        $total = round((float) $o['total'], 2);
        $paid = !empty($o['date_paid_gmt']) || in_array($o['status'], ['processing', 'completed', 'refunded'], true);
        $fees = array_sum(array_map(fn ($f) => (float) $f['total'], $o['fee_lines'] ?? []));
        $subtotal = array_sum(array_map(fn ($l) => (float) $l['subtotal'], $o['line_items'] ?? [])) + $fees;
        $ship = $o['shipping_lines'] ?? [];
        // some coupon orders (2024–25) carry the coupon amount in total_tax as well; the total charged never included it
        $tax = round((float) $o['total_tax'], 2);
        $rest = $subtotal - (float) $o['discount_total'] + (float) $o['shipping_total'];
        if (abs($rest + $tax - $total) > 0.02) $tax = max(0.0, round($total - $rest, 2));
        $pickup = ($meta['_uds_pickup'] ?? '') !== '' && ($meta['_uds_pickup'] ?? '') !== 'no' || collect($ship)->contains(fn ($l) => str_starts_with((string) $l['method_id'], 'local_pickup'));
        $shipAddr = [$s['address_1'] ?? '', $s['city'] ?? '', $s['postcode'] ?? ''];
        $created = $this->utc($o['date_created_gmt'] ?? null) ?? now();
        $completed = $this->utc($o['date_completed_gmt'] ?? null);
        $shippedAt = $tracks ? (collect($tracks)->pluck('at')->filter()->min() ?? $completed) : (in_array($o['status'], ['completed', 'refunded'], true) && !$pickup ? $completed : null);

        return [
            'status' => $this->status($o), 'source' => 'woocommerce', 'wc_status' => mb_substr((string) $o['status'], 0, 30),
            'payment_status' => $refund > 0 && $refund >= $total - 0.005 ? 'refunded' : ($refund > 0 ? 'partially_refunded' : ($paid ? 'paid' : 'unpaid')),
            'payment_method' => $this->method((string) ($o['payment_method'] ?? ''), (string) ($o['payment_method_title'] ?? '')),
            'stripe_payment_intent' => $this->str($meta['_stripe_intent_id'] ?? null, 255),
            'email' => $email, 'phone' => $this->str($b['phone'] ?? null, 255), 'sms_opt_in' => 0, 'user_id' => $userId,
            'billing_first_name' => $this->str($b['first_name'] ?? null, 255), 'billing_last_name' => $this->str($b['last_name'] ?? null, 255),
            'billing_company' => $this->str($b['company'] ?? null, 255), 'billing_address_1' => $this->str($b['address_1'] ?? null, 255),
            'billing_address_2' => $this->str($b['address_2'] ?? null, 255), 'billing_city' => $this->str($b['city'] ?? null, 255),
            'billing_state' => $this->str($b['state'] ?? null, 255), 'billing_postcode' => $this->str($b['postcode'] ?? null, 255),
            'billing_country' => $this->country($b['country'] ?? null) ?? 'US',
            'ship_to_different' => trim(implode('', $shipAddr)) !== '' && $shipAddr !== [$b['address_1'] ?? '', $b['city'] ?? '', $b['postcode'] ?? ''] ? 1 : 0,
            'shipping_first_name' => $this->str($s['first_name'] ?? null, 255), 'shipping_last_name' => $this->str($s['last_name'] ?? null, 255),
            'shipping_company' => $this->str($s['company'] ?? null, 190), 'shipping_address_1' => $this->str($s['address_1'] ?? null, 255),
            'shipping_address_2' => $this->str($s['address_2'] ?? null, 255), 'shipping_city' => $this->str($s['city'] ?? null, 255),
            'shipping_state' => $this->str($s['state'] ?? null, 255), 'shipping_postcode' => $this->str($s['postcode'] ?? null, 255),
            'shipping_country' => $this->country($s['country'] ?? null),
            'shipping_method' => $pickup ? CartService::PICKUP_ID : $this->str('woo:' . ($ship[0]['method_id'] ?? 'none'), 255),
            'shipping_label' => $this->str(implode(' + ', array_column($ship, 'method_title')) ?: null, 255),
            'subtotal' => round($subtotal, 2), 'shipping_total' => round((float) $o['shipping_total'], 2), 'discount_total' => round((float) $o['discount_total'], 2),
            'tax_total' => $tax, 'total' => $total, 'refund_total' => $refund,
            'coupon_code' => $this->str(implode(', ', array_column($o['coupon_lines'] ?? [], 'code')) ?: null, 255),
            'po_number' => $this->str($meta['_uds_po_number'] ?? null, 80), 'customer_note' => trim((string) ($o['customer_note'] ?? '')) ?: null,
            'ip' => $this->str($o['customer_ip_address'] ?? null, 45),
            'tracking_number' => $tracks[0]['n'] ?? null, 'tracking_carrier' => $tracks[0]['c'] ?? null, 'tracking_service' => $tracks ? $this->str($ship[0]['method_title'] ?? null, 255) : null,
            'shipped_at' => $shippedAt, 'delivered_at' => null, 'paid_at' => $this->utc($o['date_paid_gmt'] ?? null) ?? ($paid ? $created : null),
            'terms_due_at' => null, 'created_by' => null, 'b2b_tier' => null,
            'created_at' => $created, 'updated_at' => $this->utc($o['date_modified_gmt'] ?? null) ?? $created,
        ];
    }

    private function status(array $o): string
    {
        $done = !empty($o['date_completed_gmt']);
        return match ($o['status']) {
            'completed' => 'completed',
            'processing' => 'processing',
            'pending', 'on-hold' => 'pending',
            'cancelled' => 'cancelled',
            'failed' => 'failed',
            'refunded' => $done ? 'completed' : 'cancelled',   // the money side lives in payment_status / refund_total
            default => $done ? 'completed' : (!empty($o['date_paid_gmt']) ? 'processing' : 'pending'),   // custom statuses (uds-refund-req…)
        };
    }

    private function method(string $id, string $title): string
    {
        $t = strtolower($title);
        if ($id === '') return 'manual';
        if (str_contains($id, 'stripe') || str_contains($id, 'affirm') || str_contains($id, 'klarna')) return 'stripe';
        if (str_contains($id, 'authnet') || str_contains($id, 'authorize')) return 'authnet';   // 2024 card payments, before Stripe
        if (str_contains($t, 'pickup')) return 'pay_at_pickup';
        return match ($id) { 'cod' => str_contains($t, 'cash') ? 'cash' : 'pay_at_pickup', 'bacs' => 'wire', 'cheque' => 'check', default => 'manual' };
    }

    /** Keep the store's number (customers know it from their e-mails); prefix only on a clash with an order placed here. */
    private function number(array $o): string
    {
        $n = (string) ($o['number'] ?: $o['id']);
        return DB::table('orders')->where('order_number', $n)->exists() ? 'WC-' . $n : $n;
    }

    private function items(int $id, array $o): void
    {
        $rows = [];
        foreach ($o['line_items'] ?? [] as $l) {
            $qty = max(1, (int) $l['quantity']);
            $opts = collect($l['meta_data'] ?? [])->filter(fn ($m) => !str_starts_with((string) $m['key'], '_') && is_scalar($m['display_value'] ?? null) && trim(strip_tags((string) $m['display_value'])) !== '')
                ->map(fn ($m) => trim(strip_tags((string) $m['display_key'])) . ': ' . trim(strip_tags(html_entity_decode((string) $m['display_value']))))->implode(' · ');
            $rows[] = ['order_id' => $id, 'type' => 'product', 'wp_product_id' => ((int) $l['product_id']) ?: null, 'name' => mb_substr(html_entity_decode((string) $l['name']), 0, 255),
                'sku' => $this->str($l['sku'] ?? null, 255), 'price' => round((float) $l['subtotal'] / $qty, 2), 'qty' => $qty, 'line_total' => round((float) $l['subtotal'], 2),
                'meta' => json_encode(array_filter(['summary' => mb_substr($opts, 0, 500), 'image' => $l['image']['src'] ?? null, 'wc_item_id' => $l['id'], 'variation_id' => ((int) $l['variation_id']) ?: null])),
                'created_at' => now(), 'updated_at' => now()];
        }
        foreach ($o['fee_lines'] ?? [] as $f) {
            $rows[] = ['order_id' => $id, 'type' => 'fee', 'wp_product_id' => null, 'name' => mb_substr((string) $f['name'], 0, 255), 'sku' => null,
                'price' => round((float) $f['total'], 2), 'qty' => 1, 'line_total' => round((float) $f['total'], 2), 'meta' => '{}', 'created_at' => now(), 'updated_at' => now()];
        }
        // keep each line's id across re-runs: match on the WooCommerce line id (fees on their name)
        $old = DB::table('order_items')->where('order_id', $id)->get()
            ->keyBy(fn ($i) => (string) ((json_decode((string) $i->meta, true) ?: [])['wc_item_id'] ?? ('fee:' . $i->name)));
        foreach ($rows as $row) {
            $key = (string) ((json_decode($row['meta'], true) ?: [])['wc_item_id'] ?? ('fee:' . $row['name']));
            if (isset($old[$key])) { DB::table('order_items')->where('id', $old[$key]->id)->update(array_diff_key($row, ['created_at' => 1])); unset($old[$key]); }
            else DB::table('order_items')->insert($row);
        }
        if ($old->count()) DB::table('order_items')->whereIn('id', $old->pluck('id'))->delete();
    }

    /** The store's refunds of this order with their dates and the shipping / tax they gave back (net sales leave those out). */
    private function saveRefunds(int $id, array $refunds): void
    {
        DB::table('order_refunds')->where('order_id', $id)->whereNotNull('wc_refund_id')->delete();
        foreach ($refunds as $r) {
            DB::table('order_refunds')->insert([
                'order_id' => $id, 'wc_refund_id' => (int) $r['id'], 'amount' => round(abs((float) $r['amount']), 2),
                'shipping' => round(abs(array_sum(array_map(fn ($l) => (float) ($l['total'] ?? 0), $r['shipping_lines'] ?? []))), 2),
                'tax' => round(abs(array_sum(array_map(fn ($l) => (float) ($l['total_tax'] ?? 0), array_merge($r['line_items'] ?? [], $r['shipping_lines'] ?? [])))), 2),
                'reason' => mb_substr(trim((string) ($r['reason'] ?? '')), 0, 500) ?: null,
                'created_at' => $this->utc($r['date_created_gmt'] ?? null) ?? now(),
            ]);
        }
    }

    /** A dropship, label, freight booking or staff note on the order: it is being handled on this site. */
    private function workedHere(int $id): bool
    {
        return DB::table('pti_dropships')->where('order_id', $id)->exists() || DB::table('shipping_labels')->where('order_id', $id)->exists()
            || DB::table('freight_bookings')->where('order_id', $id)->exists() || DB::table('order_notes')->where('order_id', $id)->whereNotNull('user_id')->exists();
    }

    /** The timeline as it happened on the old store (no notify:* rows: nothing was sent from here). */
    private function events(int $id, array $o, array $tracks, array $refunds = []): void
    {
        $created = $this->utc($o['date_created_gmt'] ?? null) ?? now();
        $modified = $this->utc($o['date_modified_gmt'] ?? null) ?? $created;
        $ev = [['placed', 'Order placed', (($o['payment_method_title'] ?? '') !== '' ? $o['payment_method_title'] . ' · ' : '') . 'old website', $created]];
        if ($paid = $this->utc($o['date_paid_gmt'] ?? null)) $ev[] = ['paid', 'Paid', $o['payment_method_title'] ?: null, $paid];
        foreach ($tracks as $i => $t) $ev[] = [$i ? 'tracking' : 'shipped', $i ? 'Tracking number added' : 'Shipped', "{$t['c']} · {$t['n']}", $t['at'] ?? $this->utc($o['date_completed_gmt'] ?? null) ?? $modified];
        if ($o['status'] === 'cancelled' || $o['status'] === 'failed') $ev[] = ['cancelled', $o['status'] === 'failed' ? 'Payment failed' : 'Cancelled', null, $modified];
        // each refund on the day it was made (the order list only says how much, the refunds endpoint says when)
        if ($refunds) foreach ($refunds as $r) $ev[] = ['refund', 'Refunded ' . money(abs((float) $r['amount'])), trim((string) ($r['reason'] ?? '')) ?: null, $this->utc($r['date_created_gmt'] ?? null) ?? $modified];
        else foreach ($o['refunds'] ?? [] as $r) $ev[] = ['refund', 'Refunded ' . money(abs((float) $r['total'])), trim((string) ($r['reason'] ?? '')) ?: null, $modified];
        $ev[] = ['import', 'Imported from the old store', 'WooCommerce order #' . ($o['number'] ?: $o['id']) . ' · status "' . $o['status'] . '"', now()];
        // dedupe_key "woo:N" marks the rows this command owns (a re-run replaces only those)
        DB::table('order_events')->insert(array_map(fn ($e, $i) => ['order_id' => $id, 'type' => $e[0], 'label' => $e[1], 'detail' => $e[2] !== null ? mb_substr((string) $e[2], 0, 2000) : null,
            'dedupe_key' => 'woo:' . $i, 'created_at' => $e[3]], $ev, array_keys($ev)));
    }

    private function orderNotes(int $id, array $notes): void
    {
        $rows = [];
        foreach ($notes as $n) {
            $body = trim(preg_replace("/\n{3,}/", "\n\n", strip_tags(html_entity_decode(str_replace(['<br>', '<br/>', '<br />'], "\n", (string) $n['note'])))));
            if ($body === '') continue;
            $author = trim((string) ($n['author'] ?? ''));
            $rows[] = ['order_id' => $id, 'user_id' => null, 'kind' => !empty($n['customer_note']) ? 'customer' : 'internal',
                'body' => ($author !== '' && !in_array(strtolower($author), ['system', 'woocommerce'], true) ? "{$author}: " : '') . $body,
                'created_at' => $this->utc($n['date_created_gmt'] ?? null) ?? now()];
        }
        if ($rows) DB::table('order_notes')->insert($rows);
    }

    /**
     * Tracking numbers: the Shipment Tracking meta (ShipStation fills it), else numbers ShipStation / staff wrote in
     * the order notes. [['n' => number, 'c' => carrier, 'at' => Carbon|null], …]
     */
    private function tracking(array $meta, array $notes, ?bool &$fromNotes = null): array
    {
        $fromNotes = false;
        $out = [];
        foreach ((array) ($meta['_wc_shipment_tracking_items'] ?? []) as $t) {
            $n = strtoupper(preg_replace('/\s+/', '', (string) ($t['tracking_number'] ?? '')));
            if ($n === '' || isset($out[$n])) continue;
            $out[$n] = ['n' => $n, 'c' => $this->carrier((string) ($t['tracking_provider'] ?? '') ?: (string) ($t['custom_tracking_provider'] ?? ''), $n),
                'at' => !empty($t['date_shipped']) && ctype_digit((string) $t['date_shipped']) ? Carbon::createFromTimestampUTC((int) $t['date_shipped']) : null];
        }
        if (!$out) {
            foreach (array_reverse($notes) as $note) {
                $text = strip_tags(html_entity_decode((string) $note['note']));
                if (!preg_match_all('/(?:tracking(?:\s*(?:number|no\.?|#))?|track(?:ing)?\s*#)\s*[:#]?\s*([A-Z0-9]{10,34})\b/i', $text, $m)) continue;
                foreach ($m[1] as $n) {
                    $n = strtoupper($n);
                    if (isset($out[$n]) || !preg_match('/\d{6,}/', $n)) continue;
                    $carrier = preg_match('/\b(UPS|USPS|FedEx|DHL)\b/i', $text, $cm) ? $cm[1] : '';
                    $out[$n] = ['n' => $n, 'c' => $this->carrier($carrier, $n), 'at' => $this->utc($note['date_created_gmt'] ?? null)];
                    $fromNotes = true;
                }
            }
        }
        return array_values($out);
    }

    private function carrier(string $provider, string $n): string
    {
        $p = strtolower(trim($provider));
        if (isset(self::CARRIERS[$p])) return self::CARRIERS[$p];
        foreach (self::CARRIERS as $k => $v) if ($p !== '' && str_contains($p, $k)) return $v;
        if (str_starts_with($n, '1Z')) return 'UPS';
        if (preg_match('/^(9[1-5]\d{18,24}|[A-Z]{2}\d{9}US)$/', $n)) return 'USPS';
        if (preg_match('/^\d{12}$|^\d{15}$/', $n)) return 'FedEx';
        return $provider !== '' ? ucwords($provider) : 'UPS';
    }

    /* ---------------- fetching ---------------- */

    /** Every page of a wc/v3 list, cached as JSON. */
    private function fetch(string $name, string $path, array $query): array
    {
        $file = "{$this->dir}/wc-{$name}.json";
        if (!$this->option('refresh') && is_file($file)) return json_decode(file_get_contents($file), true) ?: [];
        $this->info("Downloading {$name} from the live store…");
        $all = [];
        for ($page = 1; ; $page++) {
            $r = LiveOrigin::wc()->timeout(90)->retry(3, 3000, throw: false)->get($path, $query + ['per_page' => 100, 'page' => $page]);
            if (!$r->successful()) throw new \RuntimeException("{$path} page {$page}: HTTP " . $r->status());
            $rows = $r->json() ?: [];
            $all = array_merge($all, $rows);
            if (count($rows) < 100) break;
        }
        file_put_contents($file, json_encode($all));
        $this->line(count($all) . " {$name}");
        return $all;
    }

    /** Order notes, one request per order, kept until the order changes on the store. */
    private function notes(array $orders): array
    {
        $file = "{$this->dir}/wc-notes.json";
        $cache = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
        $todo = array_values(array_filter($orders, fn ($o) => ($cache[$o['id']]['m'] ?? null) !== ($o['date_modified_gmt'] ?? '')));
        if (!$todo) return $cache;
        $this->info('Downloading notes of ' . count($todo) . ' order(s)…');
        $bar = $this->output->createProgressBar(count($todo));
        // the store answers each request in ~3 s: six at a time (gentle on it), saved after every batch
        $base = rtrim((string) config('services.woocommerce.url', 'https://universaldriveshafts.com'), '/') . '/wp-json/wc/v3';
        foreach (array_chunk($todo, 6) as $chunk) {
            $resps = \Illuminate\Support\Facades\Http::pool(fn ($pool) => array_map(fn ($o) => $pool->as((string) $o['id'])->withOptions(LiveOrigin::httpOptions())
                ->withBasicAuth((string) config('services.woocommerce.key'), (string) config('services.woocommerce.secret'))->timeout(60)
                ->get("{$base}/orders/{$o['id']}/notes"), $chunk));
            foreach ($chunk as $o) {
                $r = $resps[(string) $o['id']] ?? null;
                if ($r instanceof \Illuminate\Http\Client\Response && $r->successful()) $cache[$o['id']] = ['m' => $o['date_modified_gmt'] ?? '', 'n' => $r->json() ?: []];
                $bar->advance();
            }
            file_put_contents($file, json_encode($cache));
        }
        $bar->finish(); $this->newLine();
        file_put_contents($file, json_encode($cache));
        return $cache;
    }

    /** Refunds of the orders that have any, one request per order, kept until the order changes on the store. */
    private function refunds(array $orders): array
    {
        $file = "{$this->dir}/wc-refunds-by-order.json";
        $cache = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
        $todo = array_values(array_filter($orders, fn ($o) => !empty($o['refunds']) && ($cache[$o['id']]['m'] ?? null) !== ($o['date_modified_gmt'] ?? '')));
        if (!$todo) return $cache;
        $this->info('Downloading refunds of ' . count($todo) . ' order(s)…');
        $base = rtrim((string) config('services.woocommerce.url', 'https://universaldriveshafts.com'), '/') . '/wp-json/wc/v3';
        foreach (array_chunk($todo, 6) as $chunk) {
            $resps = \Illuminate\Support\Facades\Http::pool(fn ($pool) => array_map(fn ($o) => $pool->as((string) $o['id'])->withOptions(LiveOrigin::httpOptions())
                ->withBasicAuth((string) config('services.woocommerce.key'), (string) config('services.woocommerce.secret'))->timeout(60)
                ->get("{$base}/orders/{$o['id']}/refunds"), $chunk));
            foreach ($chunk as $o) {
                $r = $resps[(string) $o['id']] ?? null;
                if ($r instanceof \Illuminate\Http\Client\Response && $r->successful()) $cache[$o['id']] = ['m' => $o['date_modified_gmt'] ?? '', 'r' => $r->json() ?: []];
            }
            file_put_contents($file, json_encode($cache));
        }
        return $cache;
    }

    /* ---------------- helpers ---------------- */

    private function meta(array $meta): array
    {
        $out = [];
        foreach ($meta as $m) if (isset($m['key'])) $out[$m['key']] = $m['value'];
        return $out;
    }

    private function utc(?string $gmt): ?Carbon
    {
        return $gmt ? Carbon::parse($gmt, 'UTC') : null;
    }

    private function str(mixed $v, int $max): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private function country(?string $c): ?string
    {
        $c = strtoupper(trim((string) $c));
        return strlen($c) === 2 ? $c : null;
    }
}

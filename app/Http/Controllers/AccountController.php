<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    /** Shared account data (orders, designs, appts, requests). */
    private function data($u): array
    {
        $email = strtolower($u->email);
        // history keyed on an e-mail address is only shown once the owner has proven they control that mailbox
        $ver = !empty($u->email_verified_at);
        return [
            'user' => $u,
            'orders' => DB::table('orders')
                ->where(fn ($q) => $q->where('user_id', $u->id)->when($ver, fn ($w) => $w->orWhereRaw('LOWER(email) = ?', [$email])))
                ->orderByDesc('id')->limit(100)->get(),
            'appts' => DB::table('appointments')
                ->when($ver, fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]), fn ($q) => $q->whereRaw('1 = 0'))
                ->orderByDesc('starts_at')->limit(100)->get(),
            'designs' => DB::table('saved_designs')->where('user_id', $u->id)->orderByDesc('id')->limit(100)->get(),
            'requests' => DB::table('leads')->when($ver, fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]), fn ($q) => $q->whereRaw('1 = 0'))
                ->where('source', 'like', '%custom-driveshaft%')->orderByDesc('id')->limit(100)->get(),
            'defaultAddress' => DB::table('dropship_addresses')->where('user_id', $u->id)->orderByDesc('is_default')->orderByDesc('id')->first(),
            'addressCount' => DB::table('dropship_addresses')->where('user_id', $u->id)->count(),
        ];
    }

    /** One status per order for the customer: [pill key, label, steps reached of Placed > Paid > Shipped > Delivered]. */
    public static function orderState(object $o): array
    {
        $st = strtolower((string) $o->status);
        if (in_array($st, ['cancelled', 'canceled', 'failed'])) return ['cancelled', 'Cancelled', 1];
        if ($st === 'refunded' || ((float) ($o->refund_total ?? 0) > 0 && (float) $o->refund_total >= (float) $o->total)) return ['refunded', 'Refunded', 2];
        if (!empty($o->delivered_at)) return ['delivered', 'Delivered', 4];
        // history from the old store: "completed" there means fulfilled, with no delivery scan to show
        if ($st === 'completed' && ($o->source ?? '') === 'woocommerce') return ['delivered', 'Completed', 4];
        if (!empty($o->shipped_at)) return ['shipped', 'Shipped', 3];
        if ($o->payment_status === 'paid') return ['processing', 'Processing', 2];
        return ['pending', 'Awaiting payment', 1];
    }

    public function index()
    {
        return view('account.dashboard', $this->data(Auth::user()));
    }

    /** Orders, 25 a page, searchable (in SQL, so every order counts) by order number, PO or any part on the order (SKU or name). */
    public function ordersPage(\Illuminate\Http\Request $r)
    {
        $u = Auth::user();
        $q = trim(mb_substr((string) $r->query('q', ''), 0, 80));
        $ver = !empty($u->email_verified_at);
        $query = DB::table('orders')->where(fn ($w) => $w->where('user_id', $u->id)->when($ver, fn ($x) => $x->orWhereRaw('LOWER(email) = ?', [strtolower($u->email)])));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            // U2594-173 also finds U2594173 (and the other way round), like the catalog search
            $k = \App\Services\CatalogService::normKey($q);
            $norm = fn (string $col) => "REPLACE(REPLACE(REPLACE(REPLACE($col, '-', ''), ' ', ''), '.', ''), '/', '') LIKE ?";
            $nlike = '%' . addcslashes($k, '%_\\') . '%';
            $query->where(fn ($w) => $w->where('order_number', 'like', $like)->orWhere('po_number', 'like', $like)
                ->when($k !== '', fn ($x) => $x->orWhereRaw($norm('po_number'), [$nlike]))
                ->orWhereExists(fn ($e) => $e->select(DB::raw(1))->from('order_items')->whereColumn('order_items.order_id', 'orders.id')
                    ->where(fn ($i) => $i->where('order_items.sku', 'like', $like)->orWhere('order_items.name', 'like', $like)
                        ->when($k !== '', fn ($x) => $x->orWhereRaw($norm('order_items.sku'), [$nlike])))));
        }
        $orders = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $lines = DB::table('order_items')->whereIn('order_id', $orders->pluck('id'))->orderBy('id')->get(['order_id', 'sku', 'name', 'qty'])->groupBy('order_id');
        // return / exchange / cancellation requests per order, so the list shows where each one stands
        $rmas = DB::table('rma_requests')->whereIn('order_id', $orders->pluck('id'))->orderByDesc('id')->get()->groupBy('order_id');
        return view('account.orders', ['user' => $u, 'orders' => $orders, 'lines' => $lines, 'q' => $q, 'rmas' => $rmas]);
    }

    public function designsPage()
    {
        return view('account.designs', $this->data(Auth::user()));
    }


    public function requestsPage()
    {
        return view('account.requests', $this->data(Auth::user()));
    }

    public function settingsPage()
    {
        $u = Auth::user();
        $cards = [];
        try { $cards = \App\Support\StripeCards::list($u); } catch (\Throwable $e) { report($e); }
        return view('account.settings', $this->data($u) + ['cards' => $cards, 'avatarUrl' => \App\Support\Avatar::url($u),
            'reauthOk' => !$u->password && time() - (int) session('reauth_at', 0) < self::REAUTH_FOR]);   // code already confirmed: don't ask again
    }

    /**
     * POST /account/tax-certificate — a Florida resale (DR-13) or exemption certificate. Staff check it and approve it
     * on the customer page; from then on Florida orders on this account carry no sales tax (App\Support\SalesTax).
     */
    public function taxCertificate(Request $r)
    {
        $u = Auth::user();
        $d = $r->validate([
            'tax_cert_kind' => 'required|in:resale,exempt',
            'tax_cert_number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9 .\-]{6,40}$/'],
            'tax_cert_expires' => 'nullable|date|after:today',
            'tax_cert_file' => [$u->tax_cert_file ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ], [], ['tax_cert_kind' => __('certificate type'), 'tax_cert_number' => __('certificate number'), 'tax_cert_expires' => __('expiration date'), 'tax_cert_file' => __('copy of the certificate')]);
        $upd = ['tax_cert_kind' => $d['tax_cert_kind'], 'tax_cert_number' => strtoupper(trim($d['tax_cert_number'])),
            'tax_cert_expires' => $d['tax_cert_expires'] ?? null, 'tax_exempt_status' => 'pending', 'tax_cert_reviewed_at' => null, 'updated_at' => now()];
        if ($f = $r->file('tax_cert_file')) {
            $upd['tax_cert_file'] = $f->storeAs('tax-certs', $u->id . '-' . now()->format('YmdHis') . '.' . strtolower($f->extension()), 'local');
        }
        DB::table('users')->where('id', $u->id)->update($upd);
        \Illuminate\Support\Facades\Cache::forget('adm_nav_counts');
        return back()->with('acct_ok', __('Thanks — your certificate is under review. Once the shop approves it, your Florida orders are tax-free.'));
    }

    /** POST /account/avatar — a DiceBear style + seed picked in the settings picker. */
    public function avatarSave(Request $r)
    {
        $style = (string) $r->input('avatar_style');
        $seed = (string) $r->input('avatar_seed');
        if (!\App\Support\Avatar::validStyle($style) || !\App\Support\Avatar::validSeed($seed)) {
            return back()->with('acct_err', __('Please pick one of the pictures.'));
        }
        DB::table('users')->where('id', Auth::id())->update(['avatar_style' => $style, 'avatar_seed' => $seed, 'updated_at' => now()]);
        return back()->with('acct_ok', __('Profile picture updated.'));
    }

    /** POST /account/cards/{pm}/remove — forget a saved card (detached from the Stripe Customer). */
    public function cardRemove(string $pm)
    {
        $ok = \App\Support\StripeCards::remove(Auth::user(), $pm);
        return back()->with($ok ? 'acct_ok' : 'acct_err', $ok ? __('Card removed.') : __('That card could not be removed. Please try again.'));
    }

    public function dropshipPage()
    {
        // dropship addresses are part of the address book now
        return redirect('/account/addresses', 301);
    }

    /* ---------------- address book ---------------- */

    public function addressesPage()
    {
        $u = Auth::user();
        $addresses = DB::table('dropship_addresses')->where('user_id', $u->id)->orderByDesc('is_default')->orderByDesc('id')->get();
        $kind = request('kind') === 'dropship' ? 'dropship' : 'shipping';
        return view('account.addresses', ['user' => $u, 'addresses' => $addresses, 'edit' => request('edit'), 'newKind' => $kind]);
    }

    private function addressRules(): array
    {
        return [
            'label' => 'nullable|string|max:60', 'name' => 'nullable|string|max:160', 'company' => 'nullable|string|max:160',
            'street' => 'required|string|max:190', 'unit' => 'nullable|string|max:60', 'city' => 'required|string|max:120',
            'state' => 'nullable|string|max:60', 'postcode' => 'required|string|max:20', 'country' => 'required|string|size:2', 'phone' => 'nullable|string|max:40',
        ];
    }

    public function addressSave(Request $r)
    {
        $u = Auth::user();
        $d = $r->validate($this->addressRules() + ['id' => 'nullable|integer', 'kind' => 'nullable|in:shipping,dropship']);
        $kind = ($d['kind'] ?? 'shipping') === 'dropship' ? 'dropship' : 'shipping';
        if (empty($d['name']) && empty($d['company'])) return back()->withInput()->withErrors(['name' => __('Add a contact name or a company.')]);
        if (\App\Http\Controllers\ShopController::isPoBox($d['street'])) return back()->withInput()->withErrors(['street' => __('UPS and FedEx cannot deliver to a PO box. Please use a street address.')]);
        $row = [
            'label' => $d['label'] ?? null, 'name' => $d['name'] ?? null, 'company' => $d['company'] ?? null, 'street' => $d['street'], 'unit' => $d['unit'] ?? null,
            'city' => $d['city'], 'state' => $d['state'] ?? null, 'postcode' => $d['postcode'], 'country' => strtoupper($d['country']),
            'phone' => $d['phone'] ?? null, 'residential' => $r->boolean('residential'), 'kind' => $kind, 'updated_at' => now(),
        ];
        // the default is the customer's own shipping address (checkout prefills it); a dropship address never is
        if ($kind === 'dropship') $row['is_default'] = false;
        $mine = DB::table('dropship_addresses')->where('user_id', $u->id);
        $first = $kind === 'shipping' && !(clone $mine)->where('kind', 'shipping')->exists();
        if (!empty($d['id'])) {
            abort_unless((clone $mine)->where('id', $d['id'])->exists(), 404);
            DB::table('dropship_addresses')->where('id', $d['id'])->update($row);
            $id = (int) $d['id'];
        } else {
            $id = DB::table('dropship_addresses')->insertGetId($row + ['user_id' => $u->id, 'is_default' => $first, 'created_at' => now()]);
        }
        if ($kind === 'shipping' && $r->boolean('is_default')) $this->makeDefault($u->id, $id);
        elseif ($kind === 'dropship') $this->ensureDefault($u->id);
        return redirect('/account/addresses')->with('acct_ok', __(!empty($d['id']) ? 'Address updated.' : 'Address saved.'));
    }

    private function makeDefault(int $uid, int $id): void
    {
        DB::table('dropship_addresses')->where('user_id', $uid)->update(['is_default' => false]);
        DB::table('dropship_addresses')->where('user_id', $uid)->where('id', $id)->update(['is_default' => true]);
    }

    // after a delete or a move to dropship: the newest shipping address takes over, so checkout always has one to prefill
    private function ensureDefault(int $uid): void
    {
        $mine = DB::table('dropship_addresses')->where('user_id', $uid)->where('kind', 'shipping');
        if (!(clone $mine)->where('is_default', true)->exists() && ($next = (clone $mine)->orderByDesc('id')->value('id'))) $this->makeDefault($uid, $next);
    }

    public function addressDefault(int $id)
    {
        abort_unless(DB::table('dropship_addresses')->where('id', $id)->where('user_id', Auth::id())->where('kind', 'shipping')->exists(), 404);
        $this->makeDefault(Auth::id(), $id);
        return back()->with('acct_ok', __('Default shipping address updated.'));
    }

    public function addressDelete(int $id)
    {
        $row = DB::table('dropship_addresses')->where('id', $id)->where('user_id', Auth::id())->first();
        abort_unless($row, 404);
        DB::table('dropship_addresses')->where('id', $id)->delete();
        if ($row->is_default) $this->ensureDefault(Auth::id());
        return back()->with('acct_ok', __('Address removed.'));
    }

    public function billingSave(Request $r)
    {
        $d = $r->validate([
            'billing_address_1' => 'required|string|max:190', 'billing_address_2' => 'nullable|string|max:120', 'billing_city' => 'required|string|max:120',
            'billing_state' => 'nullable|string|max:60', 'billing_postcode' => 'required|string|max:20', 'billing_country' => 'required|string|size:2',
        ]);
        $d['billing_country'] = strtoupper($d['billing_country']);
        DB::table('users')->where('id', Auth::id())->update($d + ['updated_at' => now()]);
        return redirect('/account/addresses')->with('acct_ok', __('Billing address saved.'));
    }

    /* ---------------- e-mail change (code to the new address) ---------------- */

    /* Errors go to a named bag per form ('email', 'password'), so each shows under its own form. */

    /**
     * Accounts without a password (Google sign-in) have nothing to re-type, so a hijacked session could move the
     * account. They confirm a code sent to the current mailbox instead; once confirmed it holds for 10 minutes.
     */
    private const REAUTH_FOR = 600;

    public function reauthSend(Request $r)
    {
        $u = Auth::user();
        if ($u->password) return back();
        $bag = $r->input('for') === 'password' ? 'password' : 'email';
        if ($err = \App\Support\EmailOtp::send('reauth', $u->email, $u->locale ?: null)) return back()->withErrors(['reauth_code' => $err], $bag);
        return back()->with('reauth_sent', $bag)->with('acct_ok', __('We sent a 6-digit code to :email.', ['email' => \App\Support\EmailOtp::mask($u->email)]));
    }

    /** Null when this passwordless session has proven the mailbox (now or in the last 10 minutes); else why not. */
    private function reauthFails(Request $r, object $u): ?string
    {
        if ($u->password) return null;
        if (time() - (int) $r->session()->get('reauth_at', 0) < self::REAUTH_FOR) return null;
        $code = (string) $r->input('reauth_code', '');
        if (trim($code) === '') return __('Enter the code we sent to :email (use "Send code" first).', ['email' => \App\Support\EmailOtp::mask($u->email)]);
        if ($err = \App\Support\EmailOtp::check('reauth', $u->email, $code)) return $err;
        $r->session()->put('reauth_at', time());
        return null;
    }

    public function emailStart(Request $r)
    {
        $u = Auth::user();
        $d = $r->validateWithBag('email', ['new_email' => 'required|email:rfc|max:190', 'current_password' => $u->password ? 'required|string' : 'nullable', 'reauth_code' => 'nullable|string|max:12']);
        $new = strtolower(trim($d['new_email']));
        if ($u->password && !Hash::check((string) $d['current_password'], $u->password)) return back()->withInput()->withErrors(['current_password' => __('Current password is incorrect.')], 'email');
        if ($new === strtolower($u->email)) return back()->withInput()->withErrors(['new_email' => __('That is already your e-mail address.')], 'email');
        if (DB::table('users')->whereRaw('LOWER(email) = ?', [$new])->where('id', '!=', $u->id)->exists()) return back()->withInput()->withErrors(['new_email' => __('Another account already uses that e-mail address.')], 'email');
        if ($err = $this->reauthFails($r, $u)) return back()->withInput()->withErrors(['reauth_code' => $err], 'email');
        $r->session()->put('pending_email', ['email' => $new, 'at' => time()]);
        if ($err = \App\Support\EmailOtp::send('email', $new, $u->locale ?: null)) return back()->withInput()->withErrors(['new_email' => $err], 'email');
        return redirect('/account/email/verify');
    }

    public function emailVerifyPage(Request $r)
    {
        $p = $r->session()->get('pending_email');
        if (!$p || time() - $p['at'] > 3600) return redirect('/account/settings');
        return view('auth.verify-code', [
            'purpose' => 'email', 'email' => $p['email'], 'masked' => \App\Support\EmailOtp::mask($p['email']),
            'action' => '/account/email/verify', 'resend' => '/account/email/resend', 'cooldown' => \App\Support\EmailOtp::cooldown('email', $p['email']),
            'back' => ['/account/settings', __('Cancel and keep my current e-mail')],
        ]);
    }

    public function emailVerify(Request $r)
    {
        $u = Auth::user();
        $p = $r->session()->get('pending_email');
        if (!$p) return redirect('/account/settings');
        $r->validate(['code' => 'required|string|max:12']);
        if ($err = \App\Support\EmailOtp::check('email', $p['email'], (string) $r->input('code'))) return back()->withErrors(['code' => $err]);
        if (DB::table('users')->whereRaw('LOWER(email) = ?', [$p['email']])->where('id', '!=', $u->id)->exists()) {
            $r->session()->forget('pending_email');
            return redirect('/account/settings')->withErrors(['new_email' => __('Another account already uses that e-mail address.')], 'email');
        }
        $old = $u->email;
        // a new remember token: "keep me signed in" cookies on other browsers stop working; this one is re-issued below
        $u->forceFill(['email' => $p['email'], 'email_verified_at' => now(), 'remember_token' => \Illuminate\Support\Str::random(60)])->save();
        if ($r->cookies->has(Auth::guard()->getRecallerName())) Auth::login($u, true);
        $r->session()->forget('pending_email');
        // tell the old mailbox, so a hijacked session cannot move the account quietly
        try {
            \App\Support\Mailer::simple($old, 'Your account e-mail was changed', ['kicker' => 'Account security', 'title' => 'Your account e-mail was changed.',
                'intro' => 'The e-mail on your Universal Driveshafts account was changed to ' . \App\Support\EmailOtp::mask($p['email']) . '. Order confirmations and tracking now go there.',
                'after' => ['If you did not make this change, call us right away at (813) 524-6491 or reply to this e-mail.'], 'signoff' => false], ['locale' => $u->locale ?: null]);
        } catch (\Throwable $e) { report($e); }
        return redirect('/account/settings')->with('acct_ok', __('E-mail updated to :email.', ['email' => $p['email']]));
    }

    public function emailResend(Request $r)
    {
        $p = $r->session()->get('pending_email');
        if (!$p) return redirect('/account/settings');
        if ($err = \App\Support\EmailOtp::send('email', $p['email'], Auth::user()->locale ?: null)) return back()->withErrors(['code' => $err]);
        return back()->with('code_sent', __('A new code is on its way to :email.', ['email' => \App\Support\EmailOtp::mask($p['email'])]));
    }

    public function dropshipAdd(Request $r)
    {
        $u = Auth::user();
        $d = $r->validate([
            'company' => 'required|string|max:160', 'street' => 'required|string|max:190', 'unit' => 'nullable|string|max:60',
            'city' => 'required|string|max:120', 'state' => 'nullable|string|max:60', 'postcode' => 'required|string|max:20',
            'country' => 'required|string|size:2', 'phone' => 'nullable|string|max:40',
        ]);
        DB::table('dropship_addresses')->insert([
            'user_id' => $u->id, 'company' => $d['company'], 'street' => $d['street'], 'unit' => $d['unit'] ?? null,
            'city' => $d['city'], 'state' => $d['state'] ?? null, 'postcode' => $d['postcode'], 'country' => strtoupper($d['country']),
            'phone' => $d['phone'] ?? null, 'residential' => $r->boolean('residential'), 'kind' => 'dropship', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('acct_ok', __('Dropship address added.'));
    }

    public function dropshipDelete(int $id)
    {
        DB::table('dropship_addresses')->where('id', $id)->where('user_id', Auth::id())->delete();
        return back()->with('acct_ok', __('Address removed.'));
    }

    public function order(string $number)
    {
        $u = Auth::user();
        $order = DB::table('orders')->where('order_number', $number)
            ->where(fn ($q) => $q->where('user_id', $u->id)->when(!empty($u->email_verified_at), fn ($w) => $w->orWhereRaw('LOWER(email) = ?', [strtolower($u->email)])))
            ->first();
        abort_unless($order, 404);
        // one page per order for the customer: the live status page, with invoice and returns on it
        return redirect(\App\Support\OrderEvents::statusUrl($order, false));
    }

    public function update(Request $r)
    {
        $u = Auth::user();
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'company' => 'nullable|string|max:160',
            'phone' => 'nullable|string|max:40',
            'locale' => 'nullable|in:' . implode(',', \App\Http\Middleware\SetLocale::SUPPORTED),
        ]);
        DB::table('users')->where('id', $u->id)->update(['name' => $data['name'], 'company' => $data['company'] ?? null, 'phone' => $data['phone'] ?? null, 'locale' => $data['locale'] ?? $u->locale, 'updated_at' => now()]);
        return back()->with('acct_ok', __('Details saved.'));
    }

    public function password(Request $r)
    {
        $u = Auth::user();
        $data = $r->validateWithBag('password', ['password' => 'required|string|min:8|confirmed', 'current_password' => $u->password ? 'required|string' : 'nullable', 'reauth_code' => 'nullable|string|max:12']);
        if ($u->password && !Hash::check((string) $data['current_password'], $u->password)) {
            return back()->withErrors(['current_password' => __('Current password is incorrect.')], 'password');
        }
        if ($err = $this->reauthFails($r, $u)) return back()->withErrors(['reauth_code' => $err], 'password');
        $u->forceFill(['password' => Hash::make($data['password']), 'remember_token' => \Illuminate\Support\Str::random(60), 'wp_password' => null])->save();
        // every other session and remember-me cookie is signed out (AuthenticateSession); this one gets the new hash and cookie
        Auth::logoutOtherDevices($data['password']);
        return back()->with('acct_ok', __('Password updated. Any other devices signed in to your account were signed out.'));
    }

    public function loadDesign(string $ref)
    {
        $u = \Auth::user();
        $d = DB::table('saved_designs')->where('ref', $ref)->where('user_id', $u->id)->first();
        abort_unless($d, 404);
        $cfg = json_decode($d->config, true);
        if (($cfg['kind'] ?? '') === 'designer') { // a Driveshaft Designer drawing: reopen it with its measurements
            $h = http_build_query($cfg['dims'] ?? [], '', '&');
            return redirect('/driveshaft-designer/' . (int) $cfg['style'] . '/' . $cfg['sections'] . ($h ? '#' . $h : ''));
        }
        $pieces = ($cfg['pieces'] ?? 'one') === 'two' ? 'two' : 'one';
        // deep-link into the builder with the saved selections as query params
        $params = http_build_query(array_filter([
            'dsb_duty' => $cfg['duty_key'] ?? '', 'dsb_series' => $cfg['series_key'] ?? '',
            'dsb_front' => $cfg['front_app'] ?? '', 'dsb_rear' => $cfg['rear_app'] ?? '',
            'dsb_a1' => $cfg['a1'] ?? '', 'dsb_c1' => $cfg['c1'] ?? '', 'dsb_tier' => ($cfg['price_tier'] ?? '') === 'new' ? 'new' : '',
        ]));
        return redirect('/' . $pieces . '-piece-designer' . ($params ? '?' . $params : ''));
    }

    public function deleteDesign(string $ref)
    {
        DB::table('saved_designs')->where('ref', $ref)->where('user_id', \Auth::id())->delete();
        return back()->with('acct_ok', __('Design removed.'));
    }


    /** /account/business — tier, this month's purchases, Net terms and open invoices (approved business accounts). */
    public function business()
    {
        $u = Auth::user();
        if (!\App\Services\B2bAccounts::approved($u)) return view('account.business', ['user' => $u, 'st' => null]);
        [$ok, $open, $overdue, $limit, $why] = \App\Services\B2bAccounts::terms($u);
        return view('account.business', [
            'user' => $u, 'st' => \App\Services\B2bAccounts::status($u),
            'terms' => ['ok' => $ok, 'open' => $open, 'overdue' => $overdue, 'limit' => $limit, 'why' => $why, 'days' => \App\Services\B2bAccounts::termsDays()],
            'invoices' => \App\Services\B2bAccounts::openInvoices($u->id),
            'log' => DB::table('b2b_tier_log')->where('user_id', $u->id)->orderByDesc('id')->limit(6)->get(),
        ]);
    }

    public function wholesale()
    {
        return view('wholesale', ['authUser' => \Auth::user()]);
    }

    public function applyWholesale(Request $r)
    {
        $u = \Auth::user();
        $d = $r->validate(['business_name' => 'required|string|max:160', 'business_type' => 'nullable|string|max:80', 'tax_id' => 'nullable|string|max:60', 'volume' => 'nullable|string|max:60', 'notes' => 'nullable|string|max:500']);
        if ($err = self::submitWholesale($u, $d, $r->ip())) return redirect('/wholesale')->with('acct_err', __($err));
        return redirect('/wholesale')->with('acct_ok', __('Wholesale application submitted — we will review and email you.'));
    }

    public const WHOLESALE_APPROVED = 'This account is already an approved business account.';
    public const WHOLESALE_PENDING = 'Your application is already under review. We will e-mail you.';

    /**
     * File a wholesale / business-account application (the /wholesale form and UDS Assistant).
     * Null when filed; otherwise the (English) reason it was not: an approved account would lose its
     * approval and a pending one would only stack duplicate leads and e-mails.
     */
    public static function submitWholesale(object $u, array $d, ?string $ip): ?string
    {
        $cur = DB::table('users')->where('id', $u->id)->value('wholesale_status');
        if ($cur === 'approved') return self::WHOLESALE_APPROVED;
        if ($cur === 'pending') return self::WHOLESALE_PENDING;
        // conditional update: two quick submits can't both get through
        $n = DB::table('users')->where('id', $u->id)->where(fn ($q) => $q->whereNull('wholesale_status')->orWhereNotIn('wholesale_status', ['approved', 'pending']))
            ->update(['wholesale_status' => 'pending', 'updated_at' => now()]);
        if (!$n) return self::WHOLESALE_PENDING;
        // log as a lead for the admin panel
        DB::table('leads')->insert([
            'source' => 'wholesale-application', 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone,
            'message' => 'Business: ' . $d['business_name'] . ' (' . ($d['business_type'] ?? '') . '), Tax#: ' . ($d['tax_id'] ?? '-') . ', Vol: ' . ($d['volume'] ?? '-'),
            'data' => json_encode($d + ['user_id' => $u->id]), 'status' => 'new', 'ip' => $ip, 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            \App\Support\Mailer::simple(config('uds.email'), 'New wholesale application — ' . $d['business_name'], ['kicker' => 'Wholesale', 'title' => 'New wholesale application', 'kv' => ['User' => $u->name . ' <' . $u->email . '>', 'Business' => $d['business_name'], 'Type' => $d['business_type'] ?? null, 'Tax #' => $d['tax_id'] ?? null, 'Phone' => $d['phone'] ?? ($u->phone ?? null), 'Volume' => $d['volume'] ?? null, 'Notes' => $d['notes'] ?? null], 'button' => ['Review in admin', config('app.url') . '/admin/wholesale'], 'signoff' => false], ['replyTo' => [$u->email, $u->name]]);
        } catch (\Throwable $e) {}
        return null;
    }

}

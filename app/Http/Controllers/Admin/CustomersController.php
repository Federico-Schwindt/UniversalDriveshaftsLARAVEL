<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomersController extends Controller
{
    public function index(Request $r)
    {
        $tab = $r->query('tab', 'all');
        if ($tab === 'guests') {
            $q = DB::table('orders')->whereNotExists(fn ($e) => $e->selectRaw('1')->from('users')->whereColumn('users.email', 'orders.email'))
                ->selectRaw('email, MAX(CONCAT(billing_first_name," ",billing_last_name)) name, MAX(phone) phone, MAX(billing_company) company, COUNT(*) orders_n, SUM(total) spent, MAX(created_at) last_order, MIN(created_at) first_order')->groupBy('email');
            if ($s = trim((string) $r->query('q', ''))) $q->having('email', 'like', "%$s%")->orHaving('name', 'like', "%$s%");
            $rows = $q->orderByDesc('last_order')->paginate(40)->withQueryString();
            return view('admin.customers.index', compact('rows', 'tab') + ['f' => $r->query(), 'counts' => $this->counts()]);
        }
        $q = DB::table('users as u')->where('u.is_admin', 0)
            ->leftJoinSub(DB::table('orders')->where('status', '!=', 'cancelled')->selectRaw('email, COUNT(*) n, SUM(total) t, MAX(created_at) last')->groupBy('email'), 'o', 'o.email', '=', 'u.email')
            ->select('u.*', 'o.n as orders_n', 'o.t as spent', 'o.last as last_order');
        match ($tab) { 'wholesale' => $q->where('u.wholesale_status', 'approved'), 'pending' => $q->where('u.wholesale_status', 'pending'), 'repeat' => $q->where('o.n', '>=', 2), 'new' => $q->where('u.created_at', '>=', now()->subDays(30)), default => null };
        if ($s = trim((string) $r->query('q', ''))) { $like = "%$s%"; $q->where(fn ($w) => $w->where('u.name', 'like', $like)->orWhere('u.email', 'like', $like)->orWhere('u.phone', 'like', $like)->orWhere('u.company', 'like', $like)->orWhere('u.tags', 'like', $like)); }
        if ($tag = $r->query('tag')) $q->where('u.tags', 'like', "%$tag%");
        $sort = $r->query('sort', 'newest');
        match ($sort) { 'name' => $q->orderBy('u.name'), 'spent' => $q->orderByDesc('o.t'), 'orders' => $q->orderByDesc('o.n'), 'last' => $q->orderByDesc('o.last'), default => $q->orderByDesc('u.id') };
        if ($r->query('export') === 'csv') {
            abort_unless(\App\Support\AdminAccess::can('exports'), 403, 'Your admin role cannot export customer data.');
            $out = fopen('php://temp', 'w+'); \App\Support\Csv::put($out, ['Name', 'Email', 'Phone', 'Company', 'Wholesale', 'Discount', 'Orders', 'Spent', 'Last order', 'Tags', 'Joined']);
            foreach ((clone $q)->limit(10000)->get() as $u) \App\Support\Csv::put($out, [$u->name, $u->email, $u->phone, $u->company, $u->wholesale_status, $u->wholesale_discount, $u->orders_n, $u->spent, $u->last_order, $u->tags, $u->created_at]);
            rewind($out); return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="customers-' . date('Ymd') . '.csv"']);
        }
        $rows = $q->paginate(40)->withQueryString();
        return view('admin.customers.index', compact('rows', 'tab', 'sort') + ['f' => $r->query(), 'counts' => $this->counts()]);
    }

    private function counts(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('adm_cust_tabs', 60, fn () => [
            'all' => DB::table('users')->where('is_admin', 0)->count(), 'wholesale' => DB::table('users')->where('wholesale_status', 'approved')->count(), 'pending' => DB::table('users')->where('wholesale_status', 'pending')->count(),
            'new' => DB::table('users')->where('is_admin', 0)->where('created_at', '>=', now()->subDays(30))->count(),
            'guests' => DB::table('orders')->whereNotExists(fn ($e) => $e->selectRaw('1')->from('users')->whereColumn('users.email', 'orders.email'))->distinct()->count('email'),
        ]);
    }

    public function show(Request $r, string $key)
    {
        $user = ctype_digit($key) ? DB::table('users')->find((int) $key) : DB::table('users')->where('email', $key)->first();
        abort_if($user && $user->is_admin && \App\Support\AdminAccess::role() !== 'owner', 403);
        $email = $user->email ?? (str_contains($key, '@') ? $key : null);
        abort_unless($user || $email, 404);
        $orders = DB::table('orders')->where('email', $email)->orderByDesc('id')->get();
        $stats = ['n' => $orders->where('status', '!=', 'cancelled')->count(), 'spent' => (float) $orders->where('status', '!=', 'cancelled')->sum('total'), 'first' => $orders->min('created_at'), 'last' => $orders->max('created_at'), 'refunds' => (float) $orders->sum('refund_total')];
        $stats['aov'] = $stats['n'] ? $stats['spent'] / $stats['n'] : 0;
        $items = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('o.email', $email)->where('o.status', '!=', 'cancelled')->selectRaw('i.sku, i.name, i.wp_product_id, SUM(i.qty) q, SUM(i.line_total) t, MAX(o.created_at) last')->groupBy('i.sku', 'i.name', 'i.wp_product_id')->orderByDesc('t')->limit(10)->get();
        $notes = DB::table('customer_notes as n')->leftJoin('users as a', 'a.id', '=', 'n.author_id')->where('n.email', $email)->orderByDesc('n.id')->get(['n.*', 'a.name as author']);
        $designs = $user ? DB::table('saved_designs')->where('user_id', $user->id)->orderByDesc('id')->limit(10)->get() : collect();
        $carts = DB::table('abandoned_carts')->where('email', $email)->orderByDesc('id')->limit(5)->get();
        $alerts = DB::table('stock_alerts')->where('email', $email)->orderByDesc('id')->limit(10)->get();
        $leads = DB::table('leads')->where('email', $email)->orderByDesc('id')->limit(10)->get();
        $addresses = $orders->map(fn ($o) => ['name' => trim($o->billing_first_name . ' ' . $o->billing_last_name), 'company' => $o->billing_company, 'a1' => $o->billing_address_1, 'a2' => $o->billing_address_2, 'city' => $o->billing_city, 'state' => $o->billing_state, 'zip' => $o->billing_postcode, 'country' => $o->billing_country, 'phone' => $o->phone])
            ->unique(fn ($a) => strtolower($a['a1'] . $a['zip']))->filter(fn ($a) => $a['a1'])->values()->take(4);
        $dropship = $user ? DB::table('dropship_addresses')->where('user_id', $user->id)->get() : collect();
        $wpHistory = $this->legacyOrders($email);
        $activity = $user ? AdminLog::for('customer', $user->id, 10) : collect();
        return view('admin.customers.show', compact('user', 'email', 'orders', 'stats', 'items', 'notes', 'designs', 'carts', 'alerts', 'leads', 'addresses', 'dropship', 'wpHistory', 'activity'));
    }

    /** Orders from the old WooCommerce store for this e-mail (read from the snapshot tables). */
    private function legacyOrders(?string $email)
    {
        if (!$email) return collect();
        try {
            $cid = DB::connection('wp')->table('wp_wc_customer_lookup')->where('email', $email)->value('customer_id');
            if (!$cid) return collect();
            return DB::connection('wp')->table('wp_wc_order_stats')->where('customer_id', $cid)->orderByDesc('date_created')->limit(20)->get(['order_id', 'date_created', 'status', 'total_sales', 'num_items_sold']);
        } catch (\Throwable $e) { return collect(); }
    }

    public function create()
    {
        return view('admin.customers.form', ['u' => null]);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'phone' => 'nullable|string|max:40', 'company' => 'nullable|string|max:120', 'tags' => 'nullable|string|max:255',
            'wholesale_status' => 'nullable|in:none,pending,approved', 'wholesale_group' => 'nullable|string|max:60', 'wholesale_discount' => 'nullable|numeric|min:0|max:90', 'send_welcome' => 'nullable']);
        // pricing/credit is a wholesale-section decision: staff can create the account, not its discount
        if (!\App\Support\AdminAccess::can('wholesale')) { $d['wholesale_status'] = 'none'; $d['wholesale_group'] = null; $d['wholesale_discount'] = 0; }
        $pw = \Illuminate\Support\Str::random(14);
        $id = DB::table('users')->insertGetId(['name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'] ?? null, 'company' => $d['company'] ?? null, 'tags' => $d['tags'] ?? null, 'password' => bcrypt($pw), 'is_admin' => 0,
            'wholesale_status' => $d['wholesale_status'] ?? 'none', 'wholesale_group' => $d['wholesale_group'] ?? null, 'wholesale_discount' => $d['wholesale_discount'] ?? 0, 'created_at' => now(), 'updated_at' => now()]);
        if ($r->boolean('send_welcome')) { try { \App\Support\Mailer::simple($d['email'], 'Your Universal Driveshafts account', ['kicker' => 'Welcome', 'title' => "Your account is ready, {$d['name']}.", 'intro' => 'We created an account so you can track orders, reorder in one click and save driveshaft designs.', 'kv' => ['Sign in' => config('app.url') . '/login', 'E-mail' => $d['email'], 'Temporary password' => $pw], 'button' => ['Sign in', config('app.url') . '/login'], 'after' => ['Please change the password after signing in (My account → Settings).']]); } catch (\Throwable $e) {} }
        AdminLog::record('customer.create', 'Created customer ' . $d['email'], 'customer', $id);
        \Illuminate\Support\Facades\Cache::forget('adm_cust_tabs');
        return redirect('/admin/customers/' . $id)->with('admin_ok', 'Customer created' . ($r->boolean('send_welcome') ? ' and welcome e-mail sent.' : '.'));
    }

    public function update(Request $r, int $id)
    {
        $u = DB::table('users')->find($id); abort_unless($u, 404);
        abort_if(($u->is_admin ?? 0) && \App\Support\AdminAccess::role() !== 'owner', 403);
        $d = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email,' . $id, 'phone' => 'nullable|string|max:40', 'company' => 'nullable|string|max:120', 'tags' => 'nullable|string|max:255',
            'wholesale_status' => 'nullable|in:none,pending,approved', 'wholesale_group' => 'nullable|string|max:60', 'wholesale_discount' => 'nullable|numeric|min:0|max:90',
            'net_terms' => 'nullable', 'credit_limit' => 'nullable|numeric|min:0|max:1000000']);
        if ($u->is_admin) $d['email'] = $u->email;   // admin identities change only in Settings → Users
        $upd = ['name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'] ?? null, 'company' => $d['company'] ?? null, 'tags' => $d['tags'] ?? null, 'updated_at' => now()];
        if ($r->has('wholesale_status')) { $w = ['wholesale_status' => $d['wholesale_status'], 'wholesale_group' => $d['wholesale_group'] ?? null, 'wholesale_discount' => $d['wholesale_discount'] ?? 0,
            'net_terms' => $r->boolean('net_terms'), 'credit_limit' => ($d['credit_limit'] ?? '') === '' ? null : $d['credit_limit']];
            // discount / net terms / credit are money decisions: only roles with the wholesale section may change them
            $norm = fn ($k, $v) => in_array($k, ['wholesale_discount', 'credit_limit'], true) ? ($v === null || $v === '' ? null : (float) $v) : ($k === 'net_terms' ? (bool) $v : (string) $v);
            $changed = array_filter($w, fn ($v, $k) => $norm($k, $v) !== $norm($k, $u->$k ?? null), ARRAY_FILTER_USE_BOTH);
            if ($changed) { abort_unless(\App\Support\AdminAccess::can('wholesale'), 403, 'Only managers can change wholesale pricing, net terms or credit.'); $upd += $w; } }
        $emailChanged = strcasecmp($d['email'], $u->email) !== 0;
        abort_if($emailChanged && !\App\Support\AdminAccess::can('wholesale'), 403, 'Only managers can change a customer\'s e-mail.');
        DB::transaction(function () use ($id, $upd, $u, $d, $emailChanged) {
            DB::table('users')->where('id', $id)->update($upd);
            // orders, notes and carts are keyed by e-mail — move them with the account so history is not orphaned
            if ($emailChanged) {
                DB::table('orders')->where(fn ($w) => $w->where('user_id', $id)->orWhere('email', $u->email))->update(['email' => $d['email']]);
                foreach (['customer_notes', 'abandoned_carts', 'stock_alerts'] as $t) DB::table($t)->where('email', $u->email)->update(['email' => $d['email']]);
            }
        });
        if ($emailChanged) {
            AdminLog::record('customer.email', "E-mail changed {$u->email} → {$d['email']}", 'customer', $id, ['old' => $u->email, 'new' => $d['email']]);
            // same notice as a customer's own change (AccountController), so a move nobody asked for does not go unnoticed
            try {
                \App\Support\Mailer::simple($u->email, 'Your account e-mail was changed', ['kicker' => 'Account security', 'title' => 'Your account e-mail was changed.',
                    'intro' => 'The e-mail on your Universal Driveshafts account was changed to ' . \App\Support\EmailOtp::mask($d['email']) . '. Order confirmations and tracking now go there.',
                    'after' => ['If you did not ask for this change, call us right away at (813) 524-6491 or reply to this e-mail.'], 'signoff' => false], ['locale' => $u->locale ?: null]);
            } catch (\Throwable $e) { report($e); }
        }
        if (($upd['wholesale_status'] ?? null) === 'approved' && $u->wholesale_status !== 'approved') {
            // start at the tier this month (or last month) already earns; Bronze for a new account
            $tier = \App\Services\B2bAccounts::evaluate($id, false, 'Account approved') ?? 'bronze';
            $tiers = \App\Services\B2bAccounts::tiers();
            $fresh = DB::table('users')->find($id);
            $kv = ['Your tier' => \App\Services\B2bAccounts::label($tier) . ' off every order', 'Silver' => '$' . number_format($tiers['silver']['min']) . ' in a month: ' . $tiers['silver']['pct'] . '% off', 'Gold' => '$' . number_format($tiers['gold']['min']) . ' in a month: ' . $tiers['gold']['pct'] . '% off'];
            if ($fresh->net_terms) $kv['Pay by invoice'] = 'Net ' . \App\Services\B2bAccounts::termsDays() . ', up to $' . number_format(\App\Services\B2bAccounts::creditLimit($fresh)) . ' open';
            if (($d['wholesale_discount'] ?? 0) > 0) $kv['Extra catalog discount'] = rtrim(rtrim(number_format($d['wholesale_discount'], 2), '0'), '.') . '%';
            try { \App\Support\Mailer::simple($d['email'], 'Your business account is approved', ['kicker' => 'Business account', 'title' => "You're approved, {$d['name']}.", 'intro' => 'Your Universal Driveshafts business account is active. Sign in and your discount is applied in the cart; at checkout you can pay by card or by invoice with your PO number.', 'kv' => $kv + ['Group' => $d['wholesale_group'] ?? null], 'button' => ['See my business account', config('app.url') . '/account/business'], 'after' => ['Need a quote on volume or a build? Reply to this e-mail — it reaches the shop directly.']], ['replyTo' => [config('uds.email')]]); } catch (\Throwable $e) {}
            AdminLog::record('customer.wholesale', 'Approved wholesale for ' . $d['email'] . ' (' . ($d['wholesale_discount'] ?? 0) . '%)', 'customer', $id);
        } else AdminLog::record('customer.update', 'Updated customer ' . $d['email'], 'customer', $id);
        \Illuminate\Support\Facades\Cache::forget('adm_cust_tabs'); \Illuminate\Support\Facades\Cache::forget('adm_nav_counts');
        return back()->with('admin_ok', 'Customer saved.');
    }

    public function note(Request $r, string $email)
    {
        $d = $r->validate(['body' => 'required|string|max:4000']);
        DB::table('customer_notes')->insert(['email' => $email, 'author_id' => auth()->id(), 'body' => $d['body'], 'created_at' => now()]);
        return back()->with('admin_ok', 'Note added.');
    }

    public function deleteNote(int $id)
    {
        $n = DB::table('customer_notes')->find($id);
        DB::table('customer_notes')->where('id', $id)->delete();
        if ($n) AdminLog::record('customer.note.delete', 'Deleted note on ' . $n->email, 'customer_note', $id, ['body' => mb_substr($n->body, 0, 500)]);
        return back()->with('admin_ok', 'Note removed.');
    }

    /**
     * POST /admin/customers/{id}/tax — the Florida resale / exemption certificate: approved, the account's Florida
     * orders carry no sales tax (App\Support\SalesTax). A money decision, so managers only (wholesale section).
     */
    public function tax(Request $r, int $id)
    {
        $u = DB::table('users')->find($id); abort_unless($u, 404);
        abort_unless(\App\Support\AdminAccess::can('wholesale'), 403, 'Only managers can approve tax exemptions.');
        $d = $r->validate([
            'tax_exempt_status' => 'nullable|in:none,pending,approved,rejected', 'tax_cert_kind' => 'nullable|in:resale,exempt',
            'tax_cert_number' => 'nullable|string|max:40', 'tax_cert_expires' => 'nullable|date', 'tax_cert_note' => 'nullable|string|max:255',
            'tax_cert_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:8192',
        ]);
        $status = ($d['tax_exempt_status'] ?? 'none') === 'none' ? null : $d['tax_exempt_status'];
        if ($status === 'approved' && trim((string) ($d['tax_cert_number'] ?? '')) === '') return back()->withErrors(['tax_cert_number' => 'An approved exemption needs the certificate number.']);
        $upd = ['tax_exempt_status' => $status, 'tax_cert_kind' => $d['tax_cert_kind'] ?? null, 'tax_cert_number' => strtoupper(trim((string) ($d['tax_cert_number'] ?? ''))) ?: null,
            'tax_cert_expires' => $d['tax_cert_expires'] ?? null, 'tax_cert_note' => $d['tax_cert_note'] ?? null, 'updated_at' => now()];
        if ($status !== $u->tax_exempt_status) $upd['tax_cert_reviewed_at'] = in_array($status, ['approved', 'rejected'], true) ? now() : null;
        if ($f = $r->file('tax_cert_file')) $upd['tax_cert_file'] = $f->storeAs('tax-certs', $id . '-' . now()->format('YmdHis') . '.' . strtolower($f->extension()), 'local');
        DB::table('users')->where('id', $id)->update($upd);
        AdminLog::record('customer.tax', 'Tax exemption ' . ($status ?? 'removed') . ' for ' . $u->email . ($upd['tax_cert_number'] ? ' (' . $upd['tax_cert_number'] . ')' : ''), 'customer', $id, array_diff_key($upd, ['updated_at' => 1]));
        \Illuminate\Support\Facades\Cache::forget('adm_nav_counts');
        return back()->with('admin_ok', 'Tax exemption saved.');
    }

    /** GET /admin/customers/{id}/tax-certificate — the copy the customer sent (private disk). */
    public function taxFile(int $id)
    {
        $u = DB::table('users')->find($id);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($u && $u->tax_cert_file && $disk->exists($u->tax_cert_file), 404);
        return response()->file($disk->path($u->tax_cert_file), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * GET /admin/tax-exemptions — Florida sales tax: what each month collected, by county, for the DR-15 return (CSV with
     * ?csv=YYYY-MM), and the certificates waiting for a decision.
     */
    public function taxList(Request $r)
    {
        // a sale counts once it is paid, or billed on terms; cancelled and failed orders never. Months are Eastern.
        $sales = fn () => DB::table('orders')->whereNotNull('tax_county')->whereNotIn('status', ['cancelled', 'failed', 'trash', 'draft'])
            ->where(fn ($w) => $w->where('payment_status', 'paid')->orWhere('payment_method', 'net_terms'));
        $et = fn ($o) => \Carbon\Carbon::parse($o->created_at, 'UTC')->tz('America/New_York');
        if (preg_match('/^\d{4}-\d{2}$/', (string) $r->query('csv'))) {
            $m = $r->query('csv');
            $from = \Carbon\Carbon::createFromFormat('Y-m-d', $m . '-01', 'America/New_York')->startOfDay();
            $rows = $sales()->whereBetween('created_at', [$from->copy()->utc(), $from->copy()->addMonth()->utc()])->orderBy('created_at')
                ->get(['order_number', 'created_at', 'tax_county', 'tax_rate', 'taxable_total', 'tax_total', 'tax_exempt_cert', 'shipping_method', 'total', 'refund_total']);
            $out = fopen('php://temp', 'r+');
            fputcsv($out, ['Order', 'Date (ET)', 'County', 'Rate %', 'Taxable', 'Tax', 'Exempt certificate', 'Pickup', 'Order total', 'Refunded']);
            foreach ($rows as $o) fputcsv($out, [$o->order_number, $et($o)->format('Y-m-d H:i'), $o->tax_county, $o->tax_rate, $o->taxable_total, $o->tax_total, $o->tax_exempt_cert, $o->shipping_method === \App\Services\CartService::PICKUP_ID ? 'yes' : '', $o->total, $o->refund_total]);
            rewind($out);
            return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=florida-sales-tax-$m.csv"]);
        }
        $orders = $sales()->where('created_at', '>=', now('America/New_York')->subMonths(12)->startOfMonth()->utc())
            ->get(['created_at', 'tax_county', 'tax_rate', 'taxable_total', 'tax_total', 'tax_exempt_cert'])->groupBy(fn ($o) => $et($o)->format('Y-m'))->sortKeysDesc();
        $months = $orders->map(fn ($g, $m) => (object) ['m' => $m, 'n' => $g->count(), 'tax' => $g->sum('tax_total'),
            'taxable' => $g->whereNull('tax_exempt_cert')->sum('taxable_total'), 'exempt' => $g->whereNotNull('tax_exempt_cert')->sum('taxable_total')])->values();
        $counties = collect($orders->first() ?? [])->whereNull('tax_exempt_cert')->groupBy('tax_county')
            ->map(fn ($g, $c) => (object) ['c' => $c, 'rate' => $g->max('tax_rate'), 'taxable' => $g->sum('taxable_total'), 'tax' => $g->sum('tax_total')])->sortByDesc('tax')->values();
        $rows = DB::table('users')->whereNotNull('tax_exempt_status')
            ->orderByRaw("FIELD(tax_exempt_status, 'pending', 'approved', 'rejected')")->orderByDesc('updated_at')
            ->get(['id', 'name', 'email', 'company', 'tax_exempt_status', 'tax_cert_kind', 'tax_cert_number', 'tax_cert_expires', 'tax_cert_reviewed_at', 'updated_at']);
        return view('admin.customers.tax', compact('rows', 'months', 'counties'));
    }

    public function resetLink(int $id)
    {
        $u = DB::table('users')->find($id); abort_unless($u, 404);
        abort_if(($u->is_admin ?? 0) && \App\Support\AdminAccess::role() !== 'owner', 403);
        $token = \Illuminate\Support\Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(['email' => $u->email], ['token' => bcrypt($token), 'created_at' => now()]);
        $url = config('app.url') . '/reset-password/' . $token . '?email=' . urlencode($u->email);
        try { \App\Support\Mailer::simple($u->email, 'Set your password — Universal Driveshafts', ['kicker' => 'Account', 'title' => "Set a new password, {$u->name}.", 'intro' => 'Use the button below to choose a new password for your Universal Driveshafts account. The link is valid for 60 minutes.', 'button' => ['Set my password', $url], 'after' => ['If you did not ask for this, you can ignore this e-mail — nothing changes.'], 'signoff' => false]); } catch (\Throwable $e) { report($e); return back()->with('admin_err', 'Could not send: ' . $e->getMessage()); }
        AdminLog::record('customer.reset', 'Sent password reset to ' . $u->email, 'customer', $id);
        return back()->with('admin_ok', 'Password reset link sent to ' . $u->email);
    }

    public function wholesale(Request $r)
    {
        $tab = $r->query('tab', 'pending');
        $q = DB::table('users')->where('wholesale_status', '!=', 'none');
        if ($tab !== 'all') $q->where('wholesale_status', $tab);
        $rows = $q->orderByDesc('id')->paginate(30)->withQueryString();
        $stats = DB::table('orders')->whereIn('email', collect($rows->items())->pluck('email'))->where('status', '!=', 'cancelled')->selectRaw('email, COUNT(*) n, SUM(total) t')->groupBy('email')->get()->keyBy('email');
        $groups = DB::table('users')->whereNotNull('wholesale_group')->where('wholesale_group', '!=', '')->distinct()->orderBy('wholesale_group')->pluck('wholesale_group');
        $counts = ['pending' => DB::table('users')->where('wholesale_status', 'pending')->count(), 'approved' => DB::table('users')->where('wholesale_status', 'approved')->count()];
        return view('admin.customers.wholesale', compact('rows', 'tab', 'stats', 'groups', 'counts'));
    }
}

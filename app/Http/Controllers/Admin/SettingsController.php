<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAccess;
use App\Support\AdminLog;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /** [min, max] for numeric settings; anything not listed is 0 … 10,000,000. */
    private const BOUNDS = [
        'shipping.free_min' => [0, 100000], 'shipping.handling_days' => [0, 30], 'shipping.signature_over' => [0, 100000],
        'b2b.bronze_pct' => [0, 50], 'b2b.silver_pct' => [0, 50], 'b2b.gold_pct' => [0, 50],
        'b2b.silver_min' => [0, 1000000], 'b2b.gold_min' => [0, 1000000],
        'b2b.terms_days' => [1, 120], 'b2b.credit_limit' => [0, 1000000], 'b2b.remind_days' => [0, 60],
        'reviews.delay_days' => [1, 30], 'reviews.google_rating' => [1, 5], 'reviews.google_count' => [0, 1000000],
    ];

    /** Editable keys → [label, type, default from config, hint]. */
    public static function fields(): array
    {
        return [
            'store' => [
                'store.name' => ['Store name', 'text', 'Universal Driveshafts', ''],
                'store.email' => ['Orders & contact e-mail', 'email', config('uds.email'), 'Receives new-order, contact and quote e-mails.'],
                'store.phone' => ['Phone (display)', 'text', config('uds.phone', config('uds.pickup.phone')), ''],
                'store.phone_href' => ['Phone (dial link)', 'text', config('uds.phone_href'), 'Digits only, e.g. +18135246491'],
                'store.address' => ['Shop address', 'textarea', config('uds.address', config('uds.pickup.address')), ''],
                'store.hours' => ['Business hours', 'text', config('uds.hours'), ''],
                'alerts.email' => ['Inventory / alert e-mail', 'email', config('uds.inventory_alert_email'), 'Low-stock reports and back-in-stock requests.'],
            ],
            'shipping' => [
                'shipping.free_min' => ['Free shipping threshold (USD)', 'number', config('uds.free_shipping_min', 1000), 'U.S. only. Tubing and complete driveshafts never count toward it and are rated separately.'],
                'pickup.enabled' => ['Offer local pickup', 'bool', true, 'Shown only to U.S. addresses.'],
                'pickup.hours' => ['Pickup hours', 'text', config('uds.pickup.hours'), ''],
                'shipping.handling_days' => ['Handling time (business days)', 'number', 1, 'Shown on product pages and the order status page.'],
                'shipping.cutoff' => ['Same-day cutoff (Eastern time)', 'text', '14:00', '24-hour time, e.g. 14:00. In-stock orders placed before it on a weekday ship that day; the countdown on product pages, cart and checkout counts down to it.'],
                'freight.carriers' => ['LTL carriers (pallets)', 'select', 'fedex', 'Which freight carriers checkout offers and the "Book freight" modal books. Prices come live from Warp.', \App\Services\WarpFreightService::CARRIER_MODES],
                'shipping.signature_over' => ['Require signature over (USD)', 'number', 500, 'Labels for orders above this amount default to signature confirmation.'],
            ],
            'b2b' => [
                'b2b.bronze_pct' => ['Bronze discount (%)', 'number', 5, 'Every approved business account starts here.'],
                'b2b.silver_min' => ['Silver: monthly purchases from (USD)', 'number', 5500, 'Merchandise bought in one calendar month (Eastern), after discounts, without shipping or tax.'],
                'b2b.silver_pct' => ['Silver discount (%)', 'number', 8, ''],
                'b2b.gold_min' => ['Gold: monthly purchases from (USD)', 'number', 7000, ''],
                'b2b.gold_pct' => ['Gold discount (%)', 'number', 10, 'A tier applies the moment the month crosses its amount and holds through the next month; on the 1st, accounts whose month fell short drop a tier. The customer gets an e-mail either way.'],
                'b2b.terms_days' => ['Net terms (days)', 'number', 20, 'Approved accounts can pay by invoice, due this many days after the order.'],
                'b2b.credit_limit' => ['Default credit limit (USD)', 'number', 10000, 'Most a business account can owe on open invoices. Change it per customer on the customer page.'],
                'b2b.remind_days' => ['Invoice reminder (days before due)', 'number', 3, 'Overdue notices go the day after the due date and every 7 days after that. Any overdue invoice pauses terms for that customer.'],
            ],
            'storefront' => [
                'promo.text' => ['Top bar message', 'text', 'U.S. free shipping on orders over $1,000 (excluding tubes and driveshafts)', ''],
                'promo.link' => ['Top bar link', 'text', '/shipping-policy', ''],
                'promo.enabled' => ['Show top bar', 'bool', true, ''],
                'storefront.announcement' => ['Site announcement (banner)', 'textarea', '', 'Leave empty to hide. Plain text, shown above the header on every page.'],
                'storefront.holiday_notice' => ['Checkout notice', 'textarea', '', 'Shown on the cart and checkout, e.g. holiday closures.'],
            ],
            'email' => [
                'mail.from_name' => ['Sender name (transactional)', 'text', config('mail.from.name'), 'Order confirmations, shipping, payment links, RMA — sent through the Hostinger SMTP account.'],
                'mail.from_address' => ['Sender address (transactional)', 'email', config('mail.from.address'), 'Must be the mailbox the SMTP account can send as.'],
                'brevo.enabled' => ['Brevo marketing e-mail', 'bool', true, 'Newsletter welcome, customer list sync and campaigns go through Brevo.'],
                'brevo.from_address' => ['Marketing sender address', 'email', config('services.brevo.from'), 'Has to be a verified sender in Brevo.'],
                'brevo.from_name' => ['Marketing sender name', 'text', config('services.brevo.from_name'), ''],
                'brevo.sync_customers' => ['Add every customer to the Brevo "Website customers" list', 'bool', true, 'Name, company, state, order count and spend are kept up to date after each order.'],
            ],
            'assistant' => [
                'assistant.enabled' => ['Turn on UDS Assistant (beta)', 'bool', false, 'The AI chat for signed-in customers, bottom-right of every storefront page.'],
                'assistant.provider' => ['AI that answers', 'select', 'claude', 'Claude needs Anthropic API credits (about 1–3 US cents a message). Gemini runs on the Google AI Studio key. OpenRouter and Groq use open models with free tiers. Switching starts a fresh chat for everyone.', ['claude' => 'Claude (Anthropic)', 'gemini' => 'Gemini (Google AI Studio)', 'openrouter' => 'OpenRouter (free models)', 'groq' => 'Groq (fast open models)']],
                'assistant.voice_replies' => ['Voice-note answers', 'select', 'auto', 'Needs the ElevenLabs key. Auto: short, simple answers arrive as a voice note (played right away when the customer spoke); anything with lists, links or several numbers stays text with a Listen button. Every answer uses ElevenLabs characters.', ['auto' => 'Auto: short answers as voice notes', 'spoken' => 'Only when the customer sent a voice message', 'off' => 'Never (text with a Listen button)']],
                'assistant.groq_models' => ['Groq models', 'text', 'openai/gpt-oss-120b, qwen/qwen3.8-27b, openai/gpt-oss-20b', 'Only used with Groq. Tried in this order; the next one answers when one is busy or rate-limited.'],
                'assistant.openrouter_models' => ['OpenRouter models', 'text', 'openrouter/free, google/gemma-4-31b-it:free, nvidia/nemotron-3-super-120b-a12b:free', 'Only used with OpenRouter. Tried in this order: when one is busy or rate-limited OpenRouter moves to the next. "openrouter/free" picks a free model that supports tools. A free account allows 50 requests a day; adding $10 of credit raises it to 1,000.'],
                'assistant.gemini_model' => ['Gemini model', 'select', 'gemini-3.5-flash', 'Only used when Gemini answers. If the chosen model is busy, the chat falls back to Gemini 3.5 Flash, then Flash-Lite.', ['gemini-3.5-flash' => 'Gemini 3.5 Flash (recommended)', 'gemini-3.8-flash' => 'Gemini 3.8 Flash (newest, often busy)', 'gemini-flash-lite-latest' => 'Gemini Flash-Lite (fastest)', 'gemini-pro-latest' => 'Gemini Pro (needs a paid Google plan)']],
            ],
            'reviews' => [
                'reviews.enabled' => ['Ask customers for a Google review', 'bool', false, 'One e-mail per delivered order, a few days after it arrives; at most one per customer every 6 months. Never for orders imported from the old site, cancelled or refunded orders, open returns or staff. Everyone eligible gets the same link (Google does not allow asking only happy customers).'],
                'reviews.google_url' => ['Google review link', 'text', \App\Support\ReviewRequests::DEFAULT_URL, 'Google Business Profile → "Ask for reviews" → copy the link. The default is the listing the old website pointed at: check it opens YOUR business (address 11611 Prospect Drive, Odessa).'],
                'reviews.delay_days' => ['Days after delivery', 'number', 5, 'Orders without a delivery scan are asked 7 days later than that, counted from shipping.'],
                'reviews.badge_enabled' => ['Show the Google rating badge', 'bool', true, 'Home page, part and driveshaft pages, checkout. Keep the two figures below current by hand.'],
                'reviews.google_rating' => ['Google rating (stars)', 'number', 4.7, 'As shown on your Google Business Profile, e.g. 4.7.'],
                'reviews.google_count' => ['Number of Google reviews', 'number', 200, 'Shown as "200+ Google reviews". Round down, never up.'],
                'reviews.google_profile_url' => ['Link to your Google reviews', 'text', \App\Support\ReviewRequests::PROFILE_URL, 'Where the badge opens. Default: the reviews of the listing the old website pointed at.'],
            ],
            'notifications' => [
                'notify.new_order' => ['E-mail the shop on every new order', 'bool', true, ''],
                'notify.sms_shop' => ['Text the shop on new orders', 'bool', false, 'Needs Twilio enabled.'],
                'notify.sms_shop_number' => ['Shop mobile for texts', 'text', '', ''],
                'notify.low_stock' => ['Weekly low-stock report', 'bool', true, ''],
                'notify.abandoned' => ['Abandoned-cart reminders', 'bool', true, 'Sent at 1 h and 24 h after a cart is left with an e-mail.'],
            ],
        ];
    }

    public function index(Request $r)
    {
        $tab = $r->query('tab', 'store');
        $fields = self::fields();
        $values = [];
        foreach ($fields as $grp) foreach ($grp as $k => $f) $values[$k] = Settings::get($k, $f[2]);
        $integrations = [
            ['Stripe payments', (bool) config('services.stripe.enabled') && config('services.stripe.secret'), 'STRIPE_ENABLED / STRIPE_SECRET', 'Card payments at checkout and refunds from the order page.'],
            ['EasyPost shipping', (bool) config('services.easypost.enabled') && config('services.easypost.key'), 'EASYPOST_ENABLED / EASYPOST_KEY', 'Live UPS, FedEx and USPS rates, label buying and carrier tracking.'],
            ['Twilio SMS', (bool) config('services.twilio.enabled'), 'TWILIO_ENABLED / SID / TOKEN / FROM', 'Order texts (placed / shipped / delivered) and abandoned-cart texts.'],
            ['Google reCAPTCHA', (bool) config('uds.recaptcha_enabled'), 'RECAPTCHA_*', 'Protects the contact, quote and RMA forms.'],
            ['Google sign-in', (bool) config('uds.google_client_id'), 'GOOGLE_CLIENT_ID', 'One-tap account creation at checkout.'],
            ['Analytics', (bool) config('uds.analytics_enabled'), 'GA4 / Google Ads / Meta pixel', 'Tracking tags on the storefront.'],
            ['Mail', config('mail.default', 'log'), 'MAIL_*', 'Transactional e-mail transport: ' . config('mail.default')],
        ];
        $users = DB::table('users')->where('is_admin', 1)->orderBy('name')->get();
        $mail = ['mailer' => config('mail.default'), 'host' => config('mail.mailers.smtp.host'), 'port' => config('mail.mailers.smtp.port'), 'user' => config('mail.mailers.smtp.username'), 'from' => config('mail.from.address')];
        $brevo = $tab === 'email' ? app(\App\Services\BrevoService::class)->status() : null;
        $templates = self::TEMPLATES;
        $schedule = [];
        try { $schedule = [\App\Support\LiveOrigin::retired() ? ['catalog:sync-live', 'off', 'Catalog is local since ' . \App\Support\Settings::get('catalog.detached_at') . ': products, prices and stock are edited only here'] : ['catalog:sync-live', 'hourly', 'Images, prices and stock from the live store (skips admin-edited products)'], ['recovery:send', 'every 15 min', 'Abandoned-cart reminders'], ['alerts:check', 'hourly', 'Back-in-stock notifications'], ['orders:track', 'every 30 min', 'Carrier status from EasyPost'], ['report:weekly', 'Mondays', 'Weekly sales e-mail']]; } catch (\Throwable $e) {}
        return view('admin.settings.index', compact('tab', 'fields', 'values', 'integrations', 'users', 'schedule', 'mail', 'brevo', 'templates'));
    }

    public const TEMPLATES = ['review-request' => 'Google review request', 'order-confirmation' => 'Order confirmation (customer)', 'order-shop' => 'New order (shop copy)', 'order-shipped' => 'Shipped', 'order-delivered' => 'Delivered', 'pay-link' => 'Payment link', 'pickup-ready' => 'Ready for pickup', 'account-welcome' => 'Account welcome', 'wholesale-approved' => 'Wholesale approved', 'password-reset' => 'Password reset', 'rma' => 'RMA received', 'cart-reminder' => 'Abandoned cart reminder', 'back-in-stock' => 'Back in stock', 'newsletter-welcome' => 'Newsletter welcome (Brevo)'];

    /** Sample render of a template: [subject, view, data] built from the newest order / a sample. */
    private function sample(string $tpl): array
    {
        $order = (object) ['id' => 0, 'order_number' => 'UDS-261020-DEMO', 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'payment_link', 'email' => 'customer@example.com', 'phone' => '(813) 555-0100', 'sms_opt_in' => 1, 'billing_first_name' => 'Alex', 'billing_last_name' => 'Rivera', 'billing_company' => 'Rivera Fleet Services', 'billing_address_1' => '4021 Industrial Blvd', 'billing_address_2' => '', 'billing_city' => 'Tampa', 'billing_state' => 'FL', 'billing_postcode' => '33605', 'billing_country' => 'US', 'ship_to_different' => 0, 'shipping_method' => 'ups_ground', 'shipping_label' => 'UPS® Ground', 'subtotal' => 412.40, 'shipping_total' => 24.63, 'discount_total' => 0, 'tax_total' => 0, 'refund_total' => 0, 'coupon_code' => null, 'total' => 437.03, 'customer_note' => 'Please call before delivery.', 'ship_instructions' => null, 'signature_required' => 0, 'po_number' => 'PO-8841', 'source' => 'web', 'pay_token' => 'sampletokensampletokensample', 'tracking_number' => '1Z999AA10123456784', 'tracking_carrier' => 'UPS', 'tracking_service' => 'Ground', 'delivered_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        $items = $order->id ? \App\Services\OrderMailer::items(DB::table('order_items')->where('order_id', $order->id)->get()) : [(object) ['name' => '1310 Series U-Joint, Greaseable', 'sku' => '5-153X', 'qty' => 4, 'price' => 38.10, 'line_total' => 152.40, 'meta' => '{}', 'wp_product_id' => 0, 'image' => ''], (object) ['name' => 'Slip Yoke 1310 Series, 27 Spline', 'sku' => '2-3-6041X', 'qty' => 1, 'price' => 260.00, 'line_total' => 260.00, 'meta' => '{}', 'wp_product_id' => 0, 'image' => '']];
        $name = $order->billing_first_name; $status = \App\Support\OrderEvents::statusUrl($order); $pay = $order->id ? \App\Support\PayLink::url($order) : config('app.url') . '/pay/' . $order->order_number . '/sample';
        $base = rtrim(config('app.url'), '/');
        return match ($tpl) {
            'review-request' => (function () use ($order, $items) { [$s, $c] = \App\Support\ReviewRequests::message($order, array_map(fn ($i) => (object) $i, json_decode(json_encode($items), true)));
                return [$s, 'emails.generic', ['hero' => ['kicker' => $c['kicker'], 'title' => $c['title'], 'text' => $c['intro']], 'preheader' => $c['preheader']] + array_intersect_key($c, array_flip(['paragraphs', 'button', 'after', 'signoff']))]; })(),
            'order-confirmation' => ["Order {$order->order_number} confirmed — Universal Driveshafts", 'emails.order.confirmation', ['order' => $order, 'items' => $items, 'statusUrl' => $status, 'payUrl' => $pay, 'hero' => ['kicker' => 'Order ' . $order->order_number, 'title' => "Thanks, {$name} — we have your order.", 'text' => 'Stock parts leave our Florida shop the same business day when ordered before 2 pm EST; custom shafts are built to order.']]],
            'order-shop' => ["New order {$order->order_number} — $" . number_format($order->total, 2), 'emails.order.shop', ['order' => $order, 'items' => $items, 'hero' => ['kicker' => 'New order', 'title' => $order->order_number . ' · $' . number_format($order->total, 2), 'text' => trim($order->billing_first_name . ' ' . $order->billing_last_name) . ' · ' . $order->shipping_label]]],
            'order-shipped' => ["Your order {$order->order_number} has shipped — UPS 1Z999AA10123456784", 'emails.order.shipped', ['order' => $order, 'carrier' => 'UPS', 'tracking' => '1Z999AA10123456784', 'service' => 'Ground', 'trackUrl' => 'https://www.ups.com/track?tracknum=1Z999AA10123456784', 'statusUrl' => $status, 'items' => $items, 'hero' => ['kicker' => 'On its way', 'title' => "Good news, {$name} — your order shipped.", 'text' => "Order {$order->order_number} left our shop in Florida with UPS."]]],
            'order-delivered' => ["Delivered: order {$order->order_number}", 'emails.generic', ['hero' => ['kicker' => 'Delivered', 'title' => "Your order arrived, {$name}.", 'text' => "Order {$order->order_number} was delivered today. (UPS · 1Z999AA10123456784)"], 'paragraphs' => ['Please check the parts against your order as soon as you can. If anything is damaged in transit or does not match what you ordered, reply to this e-mail or call (813) 524-6491 within 48 hours and we will sort it out.'], 'button' => ['Order details', $status], 'after' => ['Thanks for choosing Universal Driveshafts — we would love to hear how the install went.']]],
            'pay-link' => ["Payment link for order {$order->order_number}", 'emails.order.pay-link', ['order' => $order, 'items' => $items, 'balance' => (float) $order->total, 'payUrl' => $pay, 'note' => null, 'hero' => ['kicker' => 'Payment request', 'title' => "Hi {$name}, your order is ready to be paid.", 'text' => 'Pay in under a minute with any major card — your parts start moving as soon as it clears.']]],
            'pickup-ready' => ["Order {$order->order_number} is ready for pickup", 'emails.generic', ['hero' => ['kicker' => 'Ready for pickup', 'title' => "Come and get it, {$name}!", 'text' => "Order {$order->order_number} is packed and waiting at the counter."], 'kv' => ['Where' => config('uds.pickup.address'), 'Hours' => config('uds.pickup.hours'), 'Bring' => 'Your order number'], 'button' => ['Order status', $status], 'after' => ['Need another time? Call (813) 524-6491 first so we can have it ready.']]],
            'account-welcome' => ['Your Universal Driveshafts account', 'emails.generic', ['hero' => ['kicker' => 'Welcome', 'title' => "Your account is ready, {$name}.", 'text' => 'We created an account so you can track orders, reorder in one click and save driveshaft designs.'], 'kv' => ['Sign in' => $base . '/login', 'E-mail' => $order->email, 'Temporary password' => 'k7Pq-sample'], 'button' => ['Sign in', $base . '/login'], 'after' => ['Please change the password after signing in (My account → Settings).']]],
            'wholesale-approved' => ['Your wholesale account is approved', 'emails.generic', ['hero' => ['kicker' => 'Wholesale', 'title' => "You're approved, {$name}.", 'text' => 'Your Universal Driveshafts wholesale account is active. Sign in and your pricing is applied automatically at checkout.'], 'kv' => ['Your discount' => '15% off catalog pricing', 'Group' => 'Fleet'], 'button' => ['Start shopping', $base . '/shop'], 'after' => ['Need a quote on volume or a build? Reply to this e-mail — it reaches the shop directly.']]],
            'password-reset' => ['Set your password — Universal Driveshafts', 'emails.generic', ['hero' => ['kicker' => 'Account', 'title' => "Set a new password, {$name}.", 'text' => 'Use the button below to choose a new password for your Universal Driveshafts account. The link is valid for 60 minutes.'], 'button' => ['Set my password', $base . '/reset-password/sample'], 'after' => ['If you did not ask for this, you can ignore this e-mail — nothing changes.'], 'signoff' => false]],
            'rma' => ['Return request received — RMA-2610-7F3K', 'emails.generic', ['hero' => ['kicker' => 'Return', 'title' => "We got your request, {$name}.", 'text' => 'Reference RMA-2610-7F3K. Our team reviews every request personally and will get back to you shortly — usually within one business day.'], 'kv' => ['Reference' => 'RMA-2610-7F3K', 'Order' => $order->order_number, 'Product' => '5-153X', 'Reason' => 'Ordered wrong series'], 'after' => ['Please do not ship anything back until you receive the RMA instructions.']]],
            'cart-reminder' => ['Your cart is waiting', 'emails.recovery.cart', ['name' => $name, 'n' => 1, 'count' => 3, 'items' => [['name' => '1310 Series U-Joint, Greaseable', 'sku' => '5-153X', 'qty' => 2, 'line' => 76.20, 'image' => '', 'avail' => true], ['name' => 'Slip Yoke 1310 Series', 'sku' => '2-3-6041X', 'qty' => 1, 'line' => 260.00, 'image' => '', 'avail' => true]], 'subtotal' => 336.20, 'restoreUrl' => $base . '/cart', 'unsubUrl' => $base . '/cart/recovery/unsubscribe/sample', 'title' => 'Your cart is waiting', 'preheader' => 'We saved your parts']],
            'back-in-stock' => ['Back in stock: 5-153X', 'emails.recovery.back-in-stock', ['name' => $name, 'part' => ['name' => '1310 Series U-Joint, Greaseable', 'sku' => '5-153X', 'price' => 38.10, 'url' => $base . '/product/5-153x', 'image' => null, 'stock' => 12, 'vehicle' => null], 'title' => 'Back in stock', 'preheader' => '5-153X is available again']],
            default => ['Welcome to Universal Driveshafts', 'emails.marketing.welcome', ['hero' => ['kicker' => 'Welcome aboard', 'title' => "Thanks for joining, {$name}.", 'text' => 'Driveline parts, custom shafts and straight answers from a shop that builds them every day.'], 'unsubscribe' => '#', 'preheader' => 'What to expect from us']],
        };
    }

    public function emailPreview(string $tpl)
    {
        abort_unless(isset(self::TEMPLATES[$tpl]), 404);
        [$subject, $view, $data] = $this->sample($tpl);
        try { return response(view($view, $data + ['title' => $subject])->render()); }
        catch (\Throwable $e) { return response('<pre style="padding:20px;color:#c02a2a">' . e($e->getMessage()) . "\n" . e($e->getFile() . ':' . $e->getLine()) . '</pre>', 500); }
    }

    public function mailTest(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'template' => 'required|string', 'via' => 'nullable|in:smtp,brevo']);
        if (!DB::table('users')->where('is_admin', 1)->whereRaw('LOWER(email) = ?', [strtolower($d['email'])])->exists()) return back()->with('admin_err', 'Test e-mails can only be sent to a staff address (an admin user).');
        abort_unless(isset(self::TEMPLATES[$d['template']]), 404);
        [$subject, $view, $data] = $this->sample($d['template']);
        try {
            if (($d['via'] ?? 'smtp') === 'brevo') {
                $res = app(\App\Services\BrevoService::class)->send($d['email'], '[Test] ' . $subject, view($view, $data + ['title' => $subject])->render());
                if (!$res['ok']) return back()->with('admin_err', 'Brevo refused the test: ' . $res['error']);
                $how = 'through Brevo from ' . app(\App\Services\BrevoService::class)->sender()['email'];
            } else { \App\Support\Mailer::send($d['email'], '[Test] ' . $subject, $view, $data); $how = 'through SMTP (' . config('mail.mailers.smtp.host') . ') from ' . config('mail.from.address'); }
        } catch (\Throwable $e) { return back()->with('admin_err', 'Send failed: ' . $e->getMessage()); }
        AdminLog::record('settings.mailtest', 'Test e-mail "' . self::TEMPLATES[$d['template']] . '" to ' . $d['email'], 'settings', 'email');
        return back()->with('admin_ok', 'Test "' . self::TEMPLATES[$d['template']] . '" sent to ' . $d['email'] . ' ' . $how . '.');
    }

    public function brevoSender(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'name' => 'nullable|string|max:80']);
        $res = app(\App\Services\BrevoService::class)->createSender($d['email'], $d['name'] ?: 'Universal Driveshafts');
        return back()->with($res['ok'] ? 'admin_ok' : 'admin_err', $res['ok'] ? 'Brevo is sending a verification e-mail to ' . $d['email'] . ' — click the link in it, then the address can send.' : 'Brevo: ' . $res['error']);
    }

    public function update(Request $r)
    {
        $tab = $r->input('tab', 'store');
        $fields = self::fields()[$tab] ?? [];
        // a typo here reprices the store (0% tiers, $0 free-shipping line, 0-day terms): every value is checked first
        $rules = []; $names = [];
        foreach ($fields as $k => $f) {
            $in = str_replace('.', '__', $k); $names[$in] = $f[0];
            $rules[$in] = match ($f[1]) {
                'number' => ['nullable', 'numeric', 'min:' . (self::BOUNDS[$k][0] ?? 0), 'max:' . (self::BOUNDS[$k][1] ?? 10000000)],
                'email' => ['nullable', 'email', 'max:190'],
                'select' => ['nullable', 'in:' . implode(',', array_keys($f[4] ?? []))],
                'bool' => ['nullable'],
                default => ['nullable', 'string', 'max:' . ($f[1] === 'textarea' ? 5000 : 500)],
            };
        }
        if (isset($fields['shipping.cutoff'])) $rules['shipping__cutoff'][] = 'regex:/^([01]?\d|2[0-3]):[0-5]\d$/';
        $r->validate($rules, [], $names);
        $pairs = [];
        foreach ($fields as $k => $f) {
            $v = $r->input(str_replace('.', '__', $k));
            $pairs[$k] = match ($f[1]) { 'bool' => $r->boolean(str_replace('.', '__', $k)), 'number' => $v === null || $v === '' ? null : (float) $v, default => $v === null ? null : trim((string) $v) };
        }
        Settings::setMany($pairs);
        \Illuminate\Support\Facades\Cache::forget('brevo_status');
        \Illuminate\Support\Facades\Cache::forget('seo:llms');   // it quotes the Google rating and store details
        AdminLog::record('settings.update', 'Updated ' . $tab . ' settings', 'settings', $tab, $pairs);
        return back()->with('admin_ok', ucfirst($tab) . ' settings saved.');
    }

    /* ---------- admin users ---------- */

    public function addUser(Request $r)
    {
        abort_unless(AdminAccess::role() === 'owner', 403);
        $d = $r->validate(['email' => 'required|email', 'role' => 'required|in:owner,manager,staff', 'name' => 'nullable|string|max:120']);
        $u = DB::table('users')->where('email', $d['email'])->first();
        if ($u) DB::table('users')->where('id', $u->id)->update(['is_admin' => 1, 'admin_role' => $d['role'], 'updated_at' => now()]);
        else {
            $pw = \Illuminate\Support\Str::random(14);
            $id = DB::table('users')->insertGetId(['name' => $d['name'] ?: strstr($d['email'], '@', true), 'email' => $d['email'], 'password' => bcrypt($pw), 'is_admin' => 1, 'admin_role' => $d['role'], 'wholesale_status' => 'none', 'created_at' => now(), 'updated_at' => now()]);
            try { \App\Support\Mailer::simple($d['email'], 'Universal Driveshafts admin access', ['kicker' => 'Admin', 'title' => 'You have admin access.', 'intro' => "You've been added to the Universal Driveshafts admin as {$d['role']}.", 'kv' => ['Sign in' => config('app.url') . '/login', 'E-mail' => $d['email'], 'Temporary password' => $pw], 'button' => ['Open the admin', config('app.url') . '/admin'], 'after' => ['Change the password after signing in.']]); } catch (\Throwable $e) {}
            $u = DB::table('users')->find($id);
        }
        AdminLog::record('users.add', 'Granted ' . $d['role'] . ' access to ' . $d['email'], 'user', $u->id);
        return back()->with('admin_ok', $d['email'] . ' now has ' . $d['role'] . ' access.');
    }

    public function updateUser(Request $r, int $id)
    {
        abort_unless(AdminAccess::role() === 'owner', 403);
        $u = DB::table('users')->find($id); abort_unless($u, 404);
        $d = $r->validate(['role' => 'required|in:owner,manager,staff,remove']);
        if ($d['role'] === 'remove') {
            if ($id === auth()->id()) return back()->with('admin_err', 'You cannot remove your own access.');
            if (DB::table('users')->where('is_admin', 1)->where(fn ($w) => $w->whereNull('admin_role')->orWhere('admin_role', 'owner'))->where('id', '!=', $id)->count() === 0) return back()->with('admin_err', 'Keep at least one owner.');
            DB::table('users')->where('id', $id)->update(['is_admin' => 0, 'admin_role' => null, 'updated_at' => now()]);
            AdminLog::record('users.remove', 'Removed admin access from ' . $u->email, 'user', $id);
            return back()->with('admin_ok', 'Admin access removed for ' . $u->email);
        }
        if ($id === auth()->id() && $d['role'] !== 'owner') return back()->with('admin_err', 'You cannot change your own role.');
        DB::table('users')->where('id', $id)->update(['admin_role' => $d['role'], 'updated_at' => now()]);
        AdminLog::record('users.role', $u->email . ' → ' . $d['role'], 'user', $id);
        return back()->with('admin_ok', $u->email . ' is now ' . $d['role'] . '.');
    }

    public function activity(Request $r)
    {
        $q = DB::table('admin_activity as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')->select('a.*', 'u.name as user_name');
        if ($uid = $r->query('user')) $q->where('a.user_id', $uid);
        if ($act = $r->query('action')) $q->where('a.action', 'like', $act . '%');
        if ($s = trim((string) $r->query('q', ''))) $q->where(fn ($w) => $w->where('a.label', 'like', "%$s%")->orWhere('a.subject_id', 'like', "%$s%"));
        $rows = $q->orderByDesc('a.id')->paginate(50)->withQueryString();
        $users = DB::table('users')->where('is_admin', 1)->orderBy('name')->get(['id', 'name']);
        $actions = DB::table('admin_activity')->selectRaw("SUBSTRING_INDEX(action,'.',1) a")->distinct()->orderBy('a')->pluck('a');
        return view('admin.settings.activity', compact('rows', 'users', 'actions') + ['f' => $r->query()]);
    }

    public function clearCache()
    {
        \App\Services\Admin\CatalogWriter::invalidate();
        \Illuminate\Support\Facades\Cache::forget('settings_all');
        foreach (['adm_order_tabs', 'adm_prod_tabs', 'adm_ds_tabs', 'adm_inv_counts', 'adm_cust_tabs', 'adm_attr_tax'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
        AdminLog::record('settings.cache', 'Cleared catalog and admin caches', 'settings', 'cache');
        return back()->with('admin_ok', 'Caches cleared — the storefront will re-read the catalog on the next request.');
    }
}

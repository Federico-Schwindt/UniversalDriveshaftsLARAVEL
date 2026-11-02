<?php
use Illuminate\Support\Facades\Route;

/* ---- SEO: robots and sitemap are served by the app so they can react to the
   environment and to the live catalogue. --------------------------------- */
// Driveshaft designer (port of the supplier's tool, with permission): draw, dimension, print
Route::get('/media/{w}/{key}.webp', [\App\Http\Controllers\MediaController::class, 'show'])->where(['w' => '\d+', 'key' => '[A-Za-z0-9_-]+']);
Route::get('/driveshaft-designer', [\App\Http\Controllers\DesignerController::class, 'page']);
Route::get('/driveshaft-designer/svg/{sections}', [\App\Http\Controllers\DesignerController::class, 'svg'])->where('sections', '[A-Z0-9+]+')->middleware('throttle:30,1');
Route::post('/driveshaft-designer/save', [\App\Http\Controllers\DesignerController::class, 'save'])->middleware('throttle:forms');
Route::get('/driveshaft-designer/{style}/{sections?}', [\App\Http\Controllers\DesignerController::class, 'page'])->where(['style' => '[12]', 'sections' => '[A-Z0-9+]*']);

Route::get('/robots.txt', [\App\Http\Controllers\SeoController::class, 'robots']);
Route::get('/sitemap.xml', [\App\Http\Controllers\SeoController::class, 'sitemap']);
Route::get('/sitemap-{part}.xml', [\App\Http\Controllers\SeoController::class, 'sitemapPart'])->where('part', '[a-z]+');
Route::get('/', fn () => request()->filled('s') && is_string(request('s')) ? redirect('/search?q=' . urlencode(request('s')), 301) : view('home', [
    'categories'  => config('uds.categories'),
    'bestsellers' => config('uds.bestsellers'),
]));
// previous homepage (home-classic.blade.php kept on disk for reference): one homepage only, so this 301s home
Route::redirect('/home-classic', '/', 301);
Route::redirect('/about', '/about-us', 301);
Route::view('/about-us','about');
Route::view('/services','services');
Route::view('/contact','contact');
Route::redirect('/contact-us', '/contact', 301);
Route::view('/track-order','track-order');
Route::redirect('/driveshaft-shop', '/shop', 301);
Route::view('/return-policy','return-policy');
Route::view('/shipping-policy','shipping-policy');
Route::post('/fitment-inquiry', function(\Illuminate\Http\Request $r){
    $r->validate([
        'email' => 'nullable|email:rfc|max:120', 'phone' => 'nullable|string|max:40',
        'first_name' => 'nullable|string|max:80', 'last_name' => 'nullable|string|max:80', 'name' => 'nullable|string|max:120', 'full_name' => 'nullable|string|max:120', 'company' => 'nullable|string|max:120',
        'message' => 'nullable|string|max:5000', 'details' => 'nullable|string|max:5000', 'additional_info' => 'nullable|string|max:5000',
        'designer_sections' => ['nullable', 'regex:/^[A-Z0-9+]{2,20}$/'], 'designer_dims' => 'nullable|string|max:300', 'designer_url' => 'nullable|url|max:300',
    ]);
    // reCAPTCHA v3 (only when enabled) — reject obvious bots (score < 0.3)
    if (config('uds.recaptcha_enabled')) {
        try {
            $v = \Illuminate\Support\Facades\Http::asForm()->timeout(6)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('uds.recaptcha_secret'), 'response' => (string) $r->input('g-recaptcha-response'), 'remoteip' => $r->ip(),
            ]);
            if ($v->ok() && $v->json('success') && ($v->json('score') ?? 1) < 0.3) {
                return back()->with('fitment_ok', true); // silently drop bots, look normal
            }
        } catch (\Throwable $e) {}
    }
    $data=$r->except(["_token","g-recaptcha-response"]);
    $data['ts']=now()->toDateTimeString();
    $data['ip']=$r->ip();
    \Illuminate\Support\Facades\Storage::append('fitment_inquiries.jsonl', json_encode($data));
    // persist as a lead for the admin panel
    try {
        $first = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
        \Illuminate\Support\Facades\DB::table('leads')->insert([
            'source' => $data['source'] ?? 'website',
            'name' => $data['name'] ?? ($first !== '' ? $first : ($data['full_name'] ?? null)),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'] ?? ($data['details'] ?? ($data['additional_info'] ?? null)),
            'data' => json_encode($data),
            'ip' => $data['ip'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    } catch (\Throwable $e) {}
    // notify the team by email (source names the form)
    try {
        $src = $data['source'] ?? 'website form';
        $lines = collect($data)->except(['_token','source','ts','ip','sms_consent'])
            ->filter(fn($v)=>is_scalar($v) && trim((string)$v)!=='')
            ->map(fn($v,$k)=>ucfirst(str_replace('_',' ',$k)).': '.$v)->implode("\n");
        $email = $data['email'] ?? '';
        // a designer drawing travels with the message as a styled SVG with the measurements filled in
        $attachment = null;
        if (!empty($data['designer_sections']) && preg_match('/^[A-Z0-9+]{2,20}$/', $data['designer_sections'])) {
            try { $attachment = \App\Http\Controllers\DesignerController::render($data['designer_sections'], \App\Http\Controllers\DesignerController::parseDims((string) ($data['designer_dims'] ?? '')), true); } catch (\Throwable $e) { $attachment = null; }
        }
        \Illuminate\Support\Facades\Mail::raw(
            "New submission from: {$src}\n\n{$lines}\n\nReceived: {$data['ts']} | IP: {$data['ip']}",
            function($m) use ($email, $src, $attachment, $data) {
                $m->to('Inquiry@universaldriveshafts.com')
                  ->replyTo(filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'Inquiry@universaldriveshafts.com')
                  ->subject('New '.\Illuminate\Support\Str::title(str_replace('-',' ',$src)).' submission'.($attachment ? ' with driveshaft drawing' : ''));
                if ($attachment) $m->attachData($attachment, 'driveshaft-'.$data['designer_sections'].'.svg', ['mime' => 'image/svg+xml']);
            }
        );
    } catch (\Throwable $e) {}
    return back()->with('fitment_ok', true);
})->middleware('throttle:forms');
Route::view('/heavy-duty-driveshafts','heavy-duty');
Route::view('/industrial-driveshafts','industrial');
Route::view('/custom-off-road-driveshafts','offroad');
Route::get('/premade-driveshafts', function (\App\Services\CatalogService $catalog) {
    $data = $catalog->products('premade-driveshafts', [], 1, 8, 'price_asc');
    return view('premade', ['products' => $data['items'], 'total' => $data['total'],
        'certified' => $catalog->term('certifed-driveshafts')->count ?? 0, 'shortCouple' => $catalog->term('short-couple-driveshafts')->count ?? 0]);
});
Route::post('/track-order', [\App\Http\Controllers\ShopController::class, 'track'])->middleware('throttle:forms');
/* ---- RETIRED SECTIONS (2026-09-10) ---------------------------------------
   The storefront is ecommerce-only now. The custom-driveshaft landing page,
   the whole /resources library and the public appointment booking were
   removed; every one of their URLs 301s to the nearest commercial page so no
   existing link, bookmark, ad or search result lands on a 404.
   Backup: /var/www/vhosts/dev.udshafts.com/backups/uds-app-20260910-233643
   -------------------------------------------------------------------------- */
Route::redirect('/custom-driveshaft', '/online-builder', 301);
Route::redirect('/custom-driveshaft-form', '/online-builder', 301);
Route::redirect('/measuring-your-custom-driveshaft', '/online-builder', 301);
Route::redirect('/one-piece-driveshaft', '/online-builder', 301);
Route::redirect('/two-piece-driveshaft', '/online-builder/two-piece', 301);
Route::redirect('/common-terms', '/shop', 301);
Route::redirect('/troubleshooting-guide', '/shop', 301);
Route::redirect('/resources', '/shop', 301);
Route::get('/resources/{any}', fn () => redirect('/shop', 301))->where('any', '.*');
Route::redirect('/make-an-appointment', '/contact', 301);
Route::redirect('/appointment', '/contact', 301);
Route::get('/appointment/{any}', fn () => redirect('/contact', 301))->where('any', '.*');
Route::get('/appointments/{any}', fn () => redirect('/contact', 301))->where('any', '.*');

/* ---- Driveshaft online builder -------------------------------------------
   Canonical URL is /online-builder. Every historic path 301s here so no
   existing link, bookmark or button can break. ------------------------------ */
Route::get('/online-builder', [\App\Http\Controllers\DsbController::class, 'builder'])->defaults('pieces','one');
Route::get('/online-builder/two-piece', [\App\Http\Controllers\DsbController::class, 'builder'])->defaults('pieces','two');
Route::redirect('/driveshaft-builder', '/online-builder', 301);
Route::redirect('/one-piece-designer', '/online-builder', 301);
Route::redirect('/two-piece-designer', '/online-builder/two-piece', 301);
Route::get('/dsb/yokes', [\App\Http\Controllers\DsbController::class, 'yokes']);
Route::get('/dsb/premades', [\App\Http\Controllers\DsbController::class, 'premades']);
Route::post('/dsb/quote', [\App\Http\Controllers\DsbController::class, 'quote']);
Route::post('/dsb/save-design', [\App\Http\Controllers\DsbController::class, 'saveDesign']);
Route::post('/dsb/submit', [\App\Http\Controllers\DsbController::class, 'submit']);
Route::get('/cart', [\App\Http\Controllers\ShopController::class, 'cart']);
Route::get('/cart/mini', [\App\Http\Controllers\ShopController::class, 'mini']);
Route::get('/checkout/pickup-info', [\App\Http\Controllers\ShopController::class, 'pickupInfo'])->middleware('throttle:30,1');
Route::post('/cart/add', [\App\Http\Controllers\ShopController::class, 'add'])->middleware('throttle:lookup');
Route::post('/cart/update', [\App\Http\Controllers\ShopController::class, 'update']);
Route::post('/cart/remove', [\App\Http\Controllers\ShopController::class, 'remove']);
Route::post('/cart/coupon', [\App\Http\Controllers\ShopController::class, 'applyCoupon'])->middleware('throttle:20,1');
Route::post('/cart/coupon/remove', [\App\Http\Controllers\ShopController::class, 'removeCoupon']);
Route::get('/checkout', [\App\Http\Controllers\ShopController::class, 'checkout']);
Route::post('/checkout/shipping-options', [\App\Http\Controllers\ShopController::class, 'shippingOptions'])->middleware('throttle:40,1');
Route::post('/checkout/place', [\App\Http\Controllers\ShopController::class, 'place'])->middleware('throttle:10,1');
Route::get('/order-received/{number}', [\App\Http\Controllers\ShopController::class, 'received']);
Route::view('/pto-agriculture','pto');
Route::get('/shop', [\App\Http\Controllers\CatalogController::class, 'index']);
Route::get('/shop/quick-view/{id}', [\App\Http\Controllers\CatalogController::class, 'quickView'])->whereNumber('id');
Route::get('/shop/alternates/{id}', [\App\Http\Controllers\CatalogController::class, 'alternates'])->whereNumber('id');
Route::post('/shop/catalog/add', [\App\Http\Controllers\CatalogController::class, 'add'])->middleware('throttle:30,1');
Route::get('/parts/{sku}', [\App\Http\Controllers\CatalogController::class, 'detail']);
Route::get('/shop/{group}', [\App\Http\Controllers\CatalogController::class, 'group']);
Route::get('/shop/{group}/{cat}', [\App\Http\Controllers\CatalogController::class, 'category']);
// Legacy WooCommerce category URLs -> real shop table (fixed 2026-09-05; was a 'coming soon' placeholder)
Route::get('/product-category/{path}', function (string $path) {
    $slug = basename(rtrim($path, '/'));   // nested WooCommerce URLs (/a/b/) resolve by their last segment
    // a top-level group slug (driveshaft-components, small-parts…) goes straight to its group page
    if (array_key_exists($slug, config('catalog.groups', []))) return redirect('/shop/' . $slug, 301);
    $g = null;
    try { $g = app(\App\Services\CatalogService::class)->groupOf($slug); } catch (\Throwable $e) {}
    $gs = is_array($g) ? ($g['slug'] ?? ($g['key'] ?? null)) : (is_string($g) ? $g : null);
    if (!$gs) {
        foreach (config('catalog.groups', []) as $key => $def) {
            if (in_array($slug, $def['categories'] ?? [], true)) { $gs = $key; break; }
        }
    }
    if ($gs && $gs !== $slug) return redirect("/shop/{$gs}/{$slug}", 301);
    // a real WooCommerce category that has no shop page of its own: permanent redirect to the catalog; anything else is a 404
    try { if (app(\App\Services\CatalogService::class)->term($slug)) return redirect('/shop', 301); } catch (\Throwable $e) {}
    abort(404);
})->where('path', '[A-Za-z0-9._/-]+');
Route::get('/product/{slug}', [\App\Http\Controllers\CatalogController::class, 'productBySlug']);
// Legacy WordPress pages that no longer exist -> nearest page on the new site (301s keep the old inbound links and rankings)
(function () {
    $dc = '/shop/driveshaft-components/';
    $map = [
        'u-joint' => $dc.'u-joints', 'wing-bearing-u-joints' => $dc.'u-joints', 'outside-snap-ring-u-joints' => $dc.'u-joints',
        'inside-snap-ring-u-joints' => $dc.'u-joints', 'combination-u-joints' => $dc.'u-joints', 'bearing-plate-u-joints' => $dc.'u-joints',
        'universal-joint-inside-snap-ring-style' => $dc.'u-joints', 'universal-joint-outside-snap-ring-style' => $dc.'u-joints', 'universal-joint-olutside-snap-ring-style' => $dc.'u-joints',
        'end-yoke' => $dc.'end-yokes', 'splined-bore-end-yokes' => $dc.'end-yokes', 'round-bore-end-yokes' => $dc.'end-yokes',
        'outside-snap-ring-style-end-yoke' => $dc.'end-yokes', 'inside-snap-ring-style-end-yoke' => $dc.'end-yokes',
        'flange-yoke' => $dc.'flange-yokes', 'male-flange-extended-pilot-hole' => $dc.'flange-yokes', 'flange-yoke-recessed-pilot-hole-female' => $dc.'flange-yokes',
        'steel-weld-yokes' => $dc.'weld-yoke', 'aluminum-weld-yokes' => $dc.'weld-yoke',
        'transmission-slip-yoke' => $dc.'transmission-yokes', 'transmission-slip-yoke-outside-snap-ring-style' => $dc.'transmission-yokes', 'transmission-slip-yoke-inside-snap-ring-style' => $dc.'transmission-yokes',
        'companion-yoke' => $dc.'companion-flanges',
        'mercedes-center-support-bearings' => $dc.'center-support-bearing', 'bmw-center-support-bearings' => $dc.'center-support-bearing', 'toyota-center-support-bearings' => $dc.'center-support-bearing',
        'gm-center-support-bearings' => $dc.'center-support-bearing', 'subaru-center-support-bearings' => $dc.'center-support-bearing', 'center-support-bearing-size-chart' => $dc.'center-support-bearing',
        'steel-driveshaft-tubing' => '/shop/custom-cut-tubing', 'aluminum-driveshaft-tubing' => '/shop/custom-cut-tubing', 'pto-steel-driveshaft-tubing' => '/shop/custom-cut-tubing',
        'small-parts' => '/shop/small-parts', 'parts-catalog' => '/shop', 'compare' => '/shop',
        'two-piece-driveshaft-flange-measuring-guideline' => '/online-builder/two-piece', 'two-piece-driveshaft-measuring-seal' => '/online-builder/two-piece',
        'two-piece-driveshaft-end-yoke' => '/online-builder/two-piece', 'two-piece-driveshaft-rubber-bot-extension' => '/online-builder/two-piece',
        'customized-driveshaft-online' => '/online-builder', 'driveshaft-designer-choose-type' => '/online-builder', 'driveshaft-home' => '/',
        'my-account' => '/account', 'sitemap' => '/sitemap.xml', 'lets-keep-in-touch' => '/contact', 'thank-you-form-details-received' => '/contact', 'elementor-69216' => '/',
        'universal-driveshafts-inc-return-and-refund-policy' => '/return-policy',
        'view-order-msg' => '/track-order', 'view-order-msg-2' => '/track-order',
        'return-request-form-3' => '/contact', 'exchange-request-form-3' => '/contact', 'request-form' => '/contact', 'request-form-3' => '/contact', 'product-cancel-request-form-3' => '/contact',
    ];
    foreach ($map as $old => $new) {
        if (!\Illuminate\Support\Facades\Route::has('legacy.'.$old)) Route::redirect('/'.$old, $new, 301)->name('legacy.'.$old);
    }
})();
// every "<n>-series-<type>-yokes" landing page of the old site goes to its category
Route::get('/{slug}', function (string $slug) {
    preg_match('/-series-(end|flange|weld|transmission|slip)-yokes$/', $slug, $m);
    $cat = ['end' => 'end-yokes', 'flange' => 'flange-yokes', 'weld' => 'weld-yoke', 'transmission' => 'transmission-yokes', 'slip' => 'slip-yoke'][$m[1]];
    return redirect('/shop/driveshaft-components/' . $cat, 301);
})->where('slug', '[a-z0-9]+-series-(?:end|flange|weld|transmission|slip)-yokes');
Route::get('/geo', function(\Illuminate\Http\Request $r){
    $ip = $r->ip();
    return \Cache::remember('geo_'.$ip, 3600, function() use ($ip) {
        try {
            $resp = \Http::timeout(4)->get('https://ipwho.is/'.$ip);
            if ($resp->ok() && $resp->json('success') === true) {
                return ['country'=>$resp->json('country_code'),'country_name'=>$resp->json('country'),'state'=>$resp->json('region_code'),'state_name'=>$resp->json('region'),'city'=>$resp->json('city'),'zip'=>$resp->json('postal')];
            }
        } catch (\Throwable $e) {}
        return ['country'=>'US','country_name'=>'United States','state'=>'','state_name'=>'','city'=>'','zip'=>''];
    });
});
Route::get('/login', [\App\Http\Controllers\AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login'])->middleware('throttle:login');
Route::get('/register', [\App\Http\Controllers\AuthController::class, 'showRegister']);
Route::post('/register', [\App\Http\Controllers\AuthController::class, 'register'])->middleware('throttle:register');
Route::get('/register/verify', [\App\Http\Controllers\AuthController::class, 'showRegisterVerify']);
Route::post('/register/verify', [\App\Http\Controllers\AuthController::class, 'registerVerify'])->middleware('throttle:10,1');
Route::post('/register/resend', [\App\Http\Controllers\AuthController::class, 'registerResend'])->middleware('throttle:4,10');
Route::post('/auth/google', [\App\Http\Controllers\AuthController::class, 'google'])->middleware('throttle:forms');
Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout']);
Route::get('/forgot-password', [\App\Http\Controllers\AuthController::class, 'showForgot'])->name('password.request');
Route::post('/forgot-password', [\App\Http\Controllers\AuthController::class, 'sendReset'])->name('password.email');
Route::get('/reset-password/{token}', [\App\Http\Controllers\AuthController::class, 'showReset'])->name('password.reset');
Route::post('/reset-password', [\App\Http\Controllers\AuthController::class, 'resetPassword'])->name('password.update');
// DiceBear profile pictures (account + assistant); only account/bot seeds are kept on disk. 300/min: a picker shuffle loads 18
Route::get('/avatar/{style}/{seed}', [\App\Http\Controllers\AvatarController::class, 'show'])->where(['style' => '[a-z-]{2,32}', 'seed' => '[A-Za-z0-9-]{1,40}'])->middleware('throttle:300,1');
Route::get('/wholesale', [\App\Http\Controllers\AccountController::class, 'wholesale']);
Route::middleware('auth')->group(function () {
    Route::post('/wholesale', [\App\Http\Controllers\AccountController::class, 'applyWholesale'])->middleware('throttle:forms');
    Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\AuthController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verify/resend', [\App\Http\Controllers\AuthController::class, 'resendVerification'])->middleware('throttle:3,10');
    Route::post('/account/verify/send', [\App\Http\Controllers\AuthController::class, 'sendVerifyCode'])->middleware('throttle:4,10');
    Route::get('/account/verify', [\App\Http\Controllers\AuthController::class, 'showVerifyCode']);
    Route::post('/account/verify', [\App\Http\Controllers\AuthController::class, 'verifyCode'])->middleware('throttle:10,1');
    Route::get('/account/addresses', [\App\Http\Controllers\AccountController::class, 'addressesPage']);
    Route::post('/account/addresses', [\App\Http\Controllers\AccountController::class, 'addressSave']);
    Route::post('/account/addresses/{id}/delete', [\App\Http\Controllers\AccountController::class, 'addressDelete']);
    Route::post('/account/addresses/{id}/default', [\App\Http\Controllers\AccountController::class, 'addressDefault']);
    Route::post('/account/billing', [\App\Http\Controllers\AccountController::class, 'billingSave']);
    Route::post('/account/email', [\App\Http\Controllers\AccountController::class, 'emailStart'])->middleware('throttle:4,10');
    Route::get('/account/email/verify', [\App\Http\Controllers\AccountController::class, 'emailVerifyPage']);
    Route::post('/account/email/verify', [\App\Http\Controllers\AccountController::class, 'emailVerify'])->middleware('throttle:10,1');
    Route::post('/account/email/resend', [\App\Http\Controllers\AccountController::class, 'emailResend'])->middleware('throttle:4,10');
    Route::get('/account', [\App\Http\Controllers\AccountController::class, 'index']);
    Route::get('/account/orders', [\App\Http\Controllers\AccountController::class, 'ordersPage']);
    Route::get('/account/business', [\App\Http\Controllers\AccountController::class, 'business']);
    Route::get('/account/designs', [\App\Http\Controllers\AccountController::class, 'designsPage']);
    Route::get('/account/requests', [\App\Http\Controllers\AccountController::class, 'requestsPage']);
    Route::get('/account/settings', [\App\Http\Controllers\AccountController::class, 'settingsPage']);
    Route::get('/account/dropship', [\App\Http\Controllers\AccountController::class, 'dropshipPage']);
    Route::post('/account/dropship', [\App\Http\Controllers\AccountController::class, 'dropshipAdd']);
    Route::post('/account/dropship/{id}/delete', [\App\Http\Controllers\AccountController::class, 'dropshipDelete']);
    Route::get('/account/orders/{number}', [\App\Http\Controllers\AccountController::class, 'order']);
    Route::post('/account/update', [\App\Http\Controllers\AccountController::class, 'update']);
    Route::post('/account/password', [\App\Http\Controllers\AccountController::class, 'password'])->middleware('throttle:10,1');
    // Google-only accounts: a code to the current mailbox before the e-mail changes or a first password is set
    Route::post('/account/reauth/send', [\App\Http\Controllers\AccountController::class, 'reauthSend'])->middleware('throttle:4,10');
    Route::post('/account/avatar', [\App\Http\Controllers\AccountController::class, 'avatarSave']);
    Route::post('/account/cards/{pm}/remove', [\App\Http\Controllers\AccountController::class, 'cardRemove'])->where('pm', 'pm_[A-Za-z0-9]+')->middleware('throttle:10,1');
    Route::get('/account/designs/{ref}/load', [\App\Http\Controllers\AccountController::class, 'loadDesign']);
    Route::post('/account/designs/{ref}/delete', [\App\Http\Controllers\AccountController::class, 'deleteDesign']);
});
Route::view('/privacy-policy','privacy-policy');
// Legal pages (added 2026-09-05)
Route::view('/terms','terms');
Route::redirect('/terms-of-use','/terms',301);
Route::redirect('/terms-and-conditions','/terms',301);
Route::view('/copyright','copyright');
Route::redirect('/dmca','/copyright',301);
Route::redirect('/trademarks','/copyright',301);
Route::get('/vehicles/models', function(\Illuminate\Http\Request $r){
    $make = trim((string) $r->query('make'));
    $year = preg_replace('/[^0-9]/','', (string) $r->query('year'));
    if ($make === '') return response()->json(['models'=>[]]);
    $key = 'veh_'.strtolower($make).'_'.$year;
    $models = \Cache::remember($key, 86400, function() use ($make, $year) {
        $url = $year
            ? 'https://vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMakeYear/make/'.rawurlencode($make).'/modelyear/'.$year
            : 'https://vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMake/'.rawurlencode($make);
        try {
            $resp = \Http::timeout(8)->get($url, ['format'=>'json']);
            if (!$resp->ok()) return [];
            $rows = $resp->json('Results') ?: [];
            $wanted = strtoupper($make);
            $out = [];
            foreach ($rows as $row) {
                if (strtoupper((string)($row['Make_Name'] ?? '')) !== $wanted) continue; // exact make only
                $m = trim((string)($row['Model_Name'] ?? ''));
                if ($m !== '') $out[$m] = true;
            }
            $out = array_keys($out);
            natcasesort($out);
            return array_values($out);
        } catch (\Throwable $e) { return []; }
    });
    return response()->json(['models'=>$models]);
});
require __DIR__ . '/admin.php';

Route::post('/newsletter', [\App\Http\Controllers\NewsletterController::class, 'subscribe'])->middleware('throttle:forms');

// hosted payment page for an order (link from the admin / unpaid confirmations)
Route::get('/pay/{number}/{token}', [\App\Http\Controllers\PayController::class, 'show'])->where('token', '[A-Za-z0-9]{20,64}');
Route::post('/pay/{number}/{token}/intent', [\App\Http\Controllers\PayController::class, 'intent'])->middleware('throttle:lookup')->where('token', '[A-Za-z0-9]{20,64}');
Route::post('/pay/{number}/{token}/confirm', [\App\Http\Controllers\PayController::class, 'confirm'])->middleware('throttle:lookup')->where('token', '[A-Za-z0-9]{20,64}');
Route::post('/stripe/webhook', [\App\Http\Controllers\ShopController::class, 'webhook'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
Route::get('/search', [\App\Http\Controllers\SearchController::class, 'page'])->middleware('throttle:search');
Route::get('/api/search', [\App\Http\Controllers\SearchController::class, 'api'])->middleware('throttle:search');
Route::get('/quick-order', [\App\Http\Controllers\ProductController::class, 'quickOrderPage']);
Route::post('/quick-order/resolve', [\App\Http\Controllers\ProductController::class, 'quickResolve']);
Route::post('/quick-order/add', [\App\Http\Controllers\ProductController::class, 'quickAdd'])->middleware('throttle:30,1');
Route::get('/wishlist', [\App\Http\Controllers\ProductController::class, 'wishlist']);
Route::post('/wishlist/add', [\App\Http\Controllers\ProductController::class, 'wishAdd']);
Route::post('/wishlist/remove', [\App\Http\Controllers\ProductController::class, 'wishRemove']);
Route::get('/return-request-form', [\App\Http\Controllers\RmaController::class, 'page'])->defaults('type','return');
Route::get('/exchange-request-form', [\App\Http\Controllers\RmaController::class, 'page'])->defaults('type','exchange');
Route::get('/product-cancel-request-form', [\App\Http\Controllers\RmaController::class, 'page'])->defaults('type','cancel');
Route::get('/rma', [\App\Http\Controllers\RmaController::class, 'page']);
Route::get('/rma/warranty', fn () => redirect('/return-policy#warranty', 301));   // warranty claims are not an RMA type: the policy explains them
Route::get('/rma/{type}', [\App\Http\Controllers\RmaController::class, 'page'])->whereIn('type', \App\Http\Controllers\RmaController::TYPES);
Route::post('/rma', [\App\Http\Controllers\RmaController::class, 'submit'])->middleware('throttle:forms');
Route::get('/health', fn() => 'ok');

// UDS Assistant (beta) — signed-in customers only
Route::middleware(['auth', 'throttle:chatbot'])->prefix('assistant')->group(function () {
    Route::get('/', [\App\Http\Controllers\ChatbotController::class, 'show']);
    Route::post('/', [\App\Http\Controllers\ChatbotController::class, 'send']);
    Route::post('/new', [\App\Http\Controllers\ChatbotController::class, 'reset']);
    Route::post('/agent', [\App\Http\Controllers\ChatbotController::class, 'handoff']);
});
// polling and photos sit outside the per-minute chat limit (polling runs every few seconds while the panel is open)
Route::middleware(['auth', 'throttle:120,1'])->prefix('assistant')->group(function () {
    Route::get('/poll', [\App\Http\Controllers\ChatbotController::class, 'poll']);
    Route::get('/photo/{message}/{n}', [\App\Http\Controllers\ChatbotController::class, 'photo'])->whereNumber(['message', 'n']);
    Route::get('/audio/{message}', [\App\Http\Controllers\ChatbotController::class, 'audio'])->whereNumber('message');
    Route::get('/history', [\App\Http\Controllers\ChatbotController::class, 'history']);
    Route::get('/history/{id}', [\App\Http\Controllers\ChatbotController::class, 'historyShow'])->whereNumber('id');
});



/* ---- Vehicle-to-driveshaft finder (2026-09-18) ---------------------------- */
Route::get('/catalog/driveshafts', [\App\Http\Controllers\DriveshaftFinderController::class, 'page']);
Route::redirect('/find-my-driveshaft', '/catalog/driveshafts', 301);
Route::get('/api/catalog/driveshafts/makes', [\App\Http\Controllers\DriveshaftFinderController::class, 'makes'])->middleware('throttle:search');
Route::get('/api/catalog/driveshafts/options', [\App\Http\Controllers\DriveshaftFinderController::class, 'options'])->middleware('throttle:search');
Route::get('/api/catalog/driveshafts/step', [\App\Http\Controllers\DriveshaftFinderController::class, 'step'])->middleware('throttle:search');
Route::get('/api/catalog/driveshafts/search', [\App\Http\Controllers\DriveshaftFinderController::class, 'search'])->middleware('throttle:search');
Route::post('/catalog/driveshafts/add', [\App\Http\Controllers\DriveshaftFinderController::class, 'add']);
Route::get('/catalog/driveshafts/part/{pn}', [\App\Http\Controllers\DriveshaftFinderController::class, 'part'])->where('pn', '[A-Za-z0-9._ -]+');

// SEO landing pages, split per area so they can be worked on independently
require __DIR__ . '/vehicles.php';
require __DIR__ . '/series.php';
require __DIR__ . '/content.php';
require __DIR__ . '/feeds.php';
require __DIR__ . '/xref.php';
require __DIR__ . '/tracking.php';
require __DIR__ . '/commerce.php';
require __DIR__ . '/recovery.php';

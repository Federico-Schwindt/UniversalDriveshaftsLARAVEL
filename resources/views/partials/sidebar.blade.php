{{-- Site menu: a fixed sidebar on desktop (folds to an icon rail), the off-canvas menu on tablets and phones.
     Groups open on the page they belong to; the catalog lists every category so a part is two clicks away. --}}
@php
  $sbPath = '/' . ltrim(request()->path(), '/');
  $sbOn = fn (string $href) => $href === '/' ? $sbPath === '/' : ($sbPath === $href || str_starts_with($sbPath, rtrim($href, '/') . '/'));
  $sbAny = fn (array $hrefs) => (bool) array_filter($hrefs, $sbOn);
  // component + small-part categories for the catalog tree (names stay English here, translated when shown)
  $sbTree = \Illuminate\Support\Facades\Cache::remember('uds_sb_tree', 3600, function () {
      try {
          $cs = app(\App\Services\CatalogService::class);
          $out = [];
          foreach (['driveshaft-components', 'small-parts'] as $g) {
              $out[$g] = array_map(fn ($s) => ['slug' => $s['slug'], 'name' => $s['name'], 'count' => (int) $s['count']], $cs->group($g)['subcategories'] ?? []);
          }
          return $out;
      } catch (\Throwable $e) { return ['driveshaft-components' => [], 'small-parts' => []]; }
  });
  $sbDrive = ['/online-builder', '/driveshaft-designer', '/catalog/driveshafts', '/driveshafts', '/premade-driveshafts', '/custom-off-road-driveshafts', '/heavy-duty-driveshafts', '/industrial-driveshafts', '/pto-agriculture'];
  $sbHelp = ['/track-order', '/rma', '/shipping-policy', '/return-policy', '/contact', '/wholesale', '/about-us'];
  $sbAcct = ['/account', '/login', '/register'];
  $sbShopOpen = $sbAny(['/shop', '/parts', '/quick-order', '/search']) || ($sbPath === '/') || !$sbAny(array_merge($sbDrive, $sbHelp, $sbAcct, ['/services']));
  $sbAcctUrl = auth()->check() ? (auth()->user()->is_admin ? '/admin' : '/account') : '/login';
@endphp
<aside class="sb" id="sb" aria-label="{{ __('Site menu') }}">
  <div class="sb__top">
    <a class="sb__logo" href="/" aria-label="{{ __('Universal Driveshafts home') }}"><img src="{{ asset('img/uds-logo.webp') }}" alt="{{ __('Universal Driveshafts') }}" width="187" height="30"></a>
    <button type="button" class="sb__fold" data-sb-fold aria-label="{{ __('Collapse menu') }}" title="{{ __('Collapse menu') }}"><x-icon name="caret-double-left"/></button>
    <button type="button" class="sb__x" data-sb-close aria-label="{{ __('Close menu') }}"><x-icon name="x"/></button>
  </div>

  <div class="sb__scroll">
    <a class="sb__cta" href="/online-builder" title="{{ __('Build a driveshaft') }}"><x-icon name="wrench"/><span>{{ __('Build a driveshaft') }}</span></a>

    <nav class="sb__nav" aria-label="{{ __('Primary') }}">
      <div @class(['sb__grp', 'is-open' => $sbShopOpen, 'is-here' => $sbAny(['/shop', '/parts', '/quick-order', '/search'])])>
        <div class="sb__headrow"><a class="sb__head" href="{{ '/shop' }}" title="{{ __('Parts catalog') }}"><x-icon name="grid"/><span>{{ __('Parts catalog') }}</span></a><button type="button" class="sb__tog" aria-expanded="{{ $sbShopOpen ? 'true' : 'false' }}" aria-controls="sb-shop" aria-label="{{ __('Show the menu') }}: {{ __('Parts catalog') }}"><x-icon name="caret-down"/></button></div>
        <ul class="sb__list" id="sb-shop" data-title="{{ __('Parts catalog') }}">
          <li><a href="/shop" @class(['is-on' => $sbPath === '/shop'])>{{ __('All categories') }}</a></li>
          <li><a href="/shop/complete-driveshafts" @class(['is-on' => $sbOn('/shop/complete-driveshafts')])>{{ __('Complete driveshafts') }}</a></li>
          @foreach(['driveshaft-components' => __('Driveshaft components'), 'small-parts' => __('Small parts')] as $g => $gLabel)
            @php $subOpen = $sbOn('/shop/' . $g); @endphp
            <li @class(['sb__sub', 'is-open' => $subOpen])>
              <div class="sb__subhead">
                <a href="/shop/{{ $g }}" @class(['is-on' => $sbPath === '/shop/' . $g])>{{ $gLabel }}</a>
                <button type="button" class="sb__subbtn" aria-expanded="{{ $subOpen ? 'true' : 'false' }}" aria-controls="sb-{{ $g }}" aria-label="{{ __('Show categories') }}: {{ $gLabel }}"><x-icon name="caret-down"/></button>
              </div>
              <ul id="sb-{{ $g }}">
                @foreach($sbTree[$g] ?? [] as $c)
                  @php $href = '/shop/' . $g . '/' . $c['slug']; @endphp
                  <li><a href="{{ $href }}" @class(['is-on' => $sbOn($href)])>{{ \App\Support\PartGlossary::t($c['name']) }}<small>{{ number_format($c['count']) }}</small></a></li>
                @endforeach
              </ul>
            </li>
          @endforeach
          <li><a href="/shop/driveshaft-tubing/tubing" @class(['is-on' => $sbOn('/shop/driveshaft-tubing')])>{{ __('Driveshaft tubing') }}</a></li>
          <li><a href="/quick-order" @class(['is-on' => $sbOn('/quick-order')])>{{ __('Quick order by part number') }}</a></li>
        </ul>
      </div>

      @php $o = $sbAny($sbDrive); @endphp
      <div @class(['sb__grp', 'is-open' => $o, 'is-here' => $o])>
        <div class="sb__headrow"><a class="sb__head" href="{{ '/driveshafts' }}" title="{{ __('Driveshafts') }}"><x-icon name="cube"/><span>{{ __('Driveshafts') }}</span></a><button type="button" class="sb__tog" aria-expanded="{{ $o ? 'true' : 'false' }}" aria-controls="sb-drive" aria-label="{{ __('Show the menu') }}: {{ __('Driveshafts') }}"><x-icon name="caret-down"/></button></div>
        <ul class="sb__list" id="sb-drive" data-title="{{ __('Driveshafts') }}">
          <li><a href="/catalog/driveshafts" @class(['is-on' => $sbOn('/catalog/driveshafts')])>{{ __('Find by vehicle') }}</a></li>
          <li><a href="/online-builder" @class(['is-on' => $sbPath === '/online-builder'])>{{ __('Online driveshaft builder') }}</a></li>
          <li><a href="/premade-driveshafts" @class(['is-on' => $sbOn('/premade-driveshafts')])>{{ __('Premade driveshafts') }}</a></li>
          <li><a href="/driveshafts" @class(['is-on' => $sbOn('/driveshafts')])>{{ __('By make and model') }}</a></li>
          <li><a href="/driveshaft-designer" @class(['is-on' => $sbOn('/driveshaft-designer')])>{{ __('Driveshaft designer') }}</a></li>
          <li class="sb__sep" aria-hidden="true"></li>
          <li><a href="/custom-off-road-driveshafts" @class(['is-on' => $sbOn('/custom-off-road-driveshafts')])>{{ __('Off-road and performance') }}</a></li>
          <li><a href="/heavy-duty-driveshafts" @class(['is-on' => $sbOn('/heavy-duty-driveshafts')])>{{ __('Heavy duty') }}</a></li>
          <li><a href="/industrial-driveshafts" @class(['is-on' => $sbOn('/industrial-driveshafts')])>{{ __('Industrial') }}</a></li>
          <li><a href="/pto-agriculture" @class(['is-on' => $sbOn('/pto-agriculture')])>{{ __('PTO and agriculture') }}</a></li>
        </ul>
      </div>

      <a href="/services" @class(['sb__link', 'is-on' => $sbOn('/services')]) title="{{ __('Services') }}"><x-icon name="gauge"/><span>{{ __('Shop services') }}</span></a>

      @php $o = $sbAny($sbHelp); @endphp
      <div @class(['sb__grp', 'is-open' => $o, 'is-here' => $o])>
        <div class="sb__headrow"><a class="sb__head" href="{{ '/contact' }}" title="{{ __('Help') }}"><x-icon name="question"/><span>{{ __('Help') }}</span></a><button type="button" class="sb__tog" aria-expanded="{{ $o ? 'true' : 'false' }}" aria-controls="sb-help" aria-label="{{ __('Show the menu') }}: {{ __('Help') }}"><x-icon name="caret-down"/></button></div>
        <ul class="sb__list" id="sb-help" data-title="{{ __('Help') }}">
          <li><a href="/track-order" @class(['is-on' => $sbOn('/track-order')])>{{ __('Track an order') }}</a></li>
          <li><a href="/rma" @class(['is-on' => $sbOn('/rma')])>{{ __('Returns and RMA') }}</a></li>
          <li><a href="/shipping-policy" @class(['is-on' => $sbOn('/shipping-policy')])>{{ __('Shipping') }}</a></li>
          <li><a href="/contact" @class(['is-on' => $sbOn('/contact')])>{{ __('Contact') }}</a></li>
          <li><a href="/wholesale" @class(['is-on' => $sbOn('/wholesale')])>{{ __('Wholesale accounts') }}</a></li>
          <li><a href="/about-us" @class(['is-on' => $sbOn('/about-us')])>{{ __('About us') }}</a></li>
        </ul>
      </div>

      @php $o = $sbAny(array_merge($sbAcct, ['/admin'])); @endphp
      <div @class(['sb__grp', 'is-open' => $o, 'is-here' => $o])>
        <div class="sb__headrow"><a class="sb__head" href="{{ $sbAcctUrl }}" title="{{ auth()->check() ? __('My account') : __('Account') }}"><x-icon name="user"/><span>{{ auth()->check() ? __('My account') : __('Account') }}</span></a><button type="button" class="sb__tog" aria-expanded="{{ $o ? 'true' : 'false' }}" aria-controls="sb-acct" aria-label="{{ __('Show the menu') }}: {{ auth()->check() ? __('My account') : __('Account') }}"><x-icon name="caret-down"/></button></div>
        <ul class="sb__list" id="sb-acct" data-title="{{ auth()->check() ? __('My account') : __('Account') }}">
          @auth
            @if(auth()->user()->is_admin)<li><a href="/admin">{{ __('Admin') }}</a></li>@endif
            @foreach([['/account', 'Overview'], ['/account/orders', 'Orders'], ['/account/buy-again', 'Buy again'], ['/account/addresses', 'Addresses'], ['/account/business', 'Business account'], ['/account/designs', 'Driveshaft designs'], ['/account/requests', 'Custom requests'], ['/account/settings', 'Profile & security']] as [$href, $label])
              <li><a href="{{ $href }}" @class(['is-on' => $href === '/account' ? $sbPath === '/account' : $sbOn($href)])>{{ __($label) }}</a></li>
            @endforeach
            <li><form method="POST" action="/logout">@csrf<button type="submit">{{ __('Sign out') }}</button></form></li>
          @else
            <li><a href="/login" @class(['is-on' => $sbOn('/login')])>{{ __('Sign in') }}</a></li>
            <li><a href="/register" @class(['is-on' => $sbOn('/register')])>{{ __('Create an account') }}</a></li>
          @endauth
        </ul>
      </div>
    </nav>
  </div>

  <div class="sb__foot">
    <div class="sb__help">
      <span class="sb__help-t">{{ __('Talk to a driveline tech') }}</span>
      <a class="sb__tel" href="tel:18135246491" title="{{ __('Call (813) 524-6491') }}"><x-icon name="phone"/><span>(813) 524-6491</span></a>
      <a class="sb__wa" href="https://wa.me/18136445707" target="_blank" rel="noopener" title="{{ __('Chat on WhatsApp') }}"><x-icon name="whatsapp"/><span>{{ __('WhatsApp') }}</span></a>
      <span class="sb__hours">{{ __('Mon to Fri 8 am to 5 pm EST') }}</span>
    </div>
    @include('partials.lang-switch', ['class' => 'langsw--sb'])
  </div>
</aside>
<div class="sb-scrim" data-sb-close hidden></div>

<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title','Dashboard') · UDS Admin</title>
<link rel="icon" href="/favicon.ico">
<link rel="preload" as="font" type="font/woff2" href="/fonts/poppins-600.woff2" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="/fonts/hankengrotesk-var.woff2" crossorigin>
<link rel="stylesheet" href="/css/admin.css?v={{ @filemtime(public_path('css/admin.css')) ?: time() }}">
@stack('head')
</head>
@php
  $path = request()->path();
  $on = fn ($p) => $p === 'admin' ? $path === 'admin' : str_starts_with($path, 'admin/' . $p);
  $nav = [
    ['Overview', [
      ['dashboard', 'admin', 'home', 'Dashboard'],
    ]],
    ['Sales', [
      ['orders', 'admin/orders', 'orders', 'Orders', $counts['orders_open'] ?? 0],
      ['customers', 'admin/customers', 'users', 'Customers'],
      ['wholesale', 'admin/wholesale', 'handshake', 'Wholesale', $counts['wholesale_pending'] ?? 0],
      ['coupons', 'admin/coupons', 'ticket', 'Coupons'],
      ['recovery', 'admin/recovery', 'cart', 'Abandoned carts', $counts['carts_open'] ?? 0],
    ]],
    ['Catalog', [
      ['products', 'admin/products', 'box', 'Products'],
      ['categories', 'admin/categories', 'grid', 'Categories'],
      ['driveshafts', 'admin/driveshafts', 'shaft', 'Complete driveshafts'],
      ['inventory', 'admin/inventory', 'layers', 'Inventory', $counts['low_stock'] ?? 0],
      ['designs', 'admin/designs', 'ruler', 'Designer builds'],
      ['seo', 'admin/seo', 'search', 'SEO & redirects', $counts['seo_404'] ?? 0],
    ]],
    ['Support', [
      ['leads', 'admin/leads', 'inbox', 'Leads & quotes', $counts['leads_new'] ?? 0],
      ['rma', 'admin/rma', 'undo', 'Returns / RMA', $counts['rma_new'] ?? 0],
      ['chats', 'admin/chats', 'inbox', 'Assistant chats', $counts['chats_waiting'] ?? 0],
    ]],
    ['Insights', [
      ['reports', 'admin/analytics', 'dollar', 'Monthly sales'],
      ['reports', 'admin/reports', 'chart', 'Reports'],
      ['activity', 'admin/activity', 'history', 'Activity log'],
    ]],
    ['Store', [
      ['settings', 'admin/settings', 'settings', 'Settings'],
    ]],
  ];
  $u = auth()->user();
@endphp
<body class="adm-body">
@include('admin.partials.icons')
<div class="adm" id="adm">
  <a class="adm-skip" href="#adm-main">Skip to content</a>

  <aside class="adm-side" id="adm-side" aria-label="Admin navigation">
    <div class="adm-brand">
      <a href="/admin" class="adm-brand__logo" aria-label="UDS Admin home"><img src="/img/uds-logo.webp" alt="" width="132" height="34"></a>
      <button type="button" class="adm-side__close" data-drawer-close aria-label="Close menu">{!! ic('x') !!}</button>
    </div>
    <nav class="adm-nav">
      @foreach($nav as [$label, $items])
        @php $items = array_values(array_filter($items, fn ($i) => adm_can($i[0]))); @endphp
        @if($items)
        <div class="adm-nav__group"><span class="adm-nav__label">{{ $label }}</span>
          @foreach($items as $i)
            <a href="/{{ $i[1] }}" class="adm-nav__item @if($on($i[1]==='admin'?'admin':substr($i[1],6))) is-on @endif" @if($on($i[1]==='admin'?'admin':substr($i[1],6))) aria-current="page" @endif data-tip="{{ $i[3] }}">
              {!! ic($i[2]) !!}<span>{{ $i[3] }}</span>
              @if(!empty($i[4]))<b class="adm-nav__count">{{ $i[4] }}</b>@endif
            </a>
          @endforeach
        </div>
        @endif
      @endforeach
    </nav>
    <div class="adm-side__foot">
      <a href="/" target="_blank" rel="noopener" class="adm-nav__item">{!! ic('ext') !!}<span>View site</span></a>
      <button type="button" class="adm-side__collapse adm-nav__item" data-collapse aria-label="Collapse sidebar">{!! ic('chev') !!}<span>Collapse</span></button>
    </div>
  </aside>
  <div class="adm-scrim" data-drawer-close hidden></div>

  <div class="adm-main" id="adm-main">
    <header class="adm-top">
      <button type="button" class="adm-top__menu" data-drawer-open aria-label="Open menu">{!! ic('menu') !!}</button>
      <div class="adm-top__search" id="adm-search">
        {!! ic('search') !!}
        <input type="search" placeholder="Search orders, products, customers, driveshafts…  ( / )" autocomplete="off" aria-label="Search the admin" data-search-input>
        <kbd>/</kbd>
        <div class="adm-search__pop" hidden data-search-pop></div>
      </div>
      <div class="adm-top__actions">
        <div class="adm-menu" data-menu>
          <button type="button" class="btn btn--primary btn--icon-sm" data-menu-btn aria-haspopup="true">{!! ic('plus') !!}<span class="hide-m">New</span></button>
          <div class="adm-menu__pop adm-menu__pop--right" hidden>
            @if(adm_can('orders'))<a href="/admin/orders/new">{!! ic('orders') !!} Order</a>@endif
            @if(adm_can('products'))<a href="/admin/products/new">{!! ic('box') !!} Product</a>@endif
            @if(adm_can('customers'))<a href="/admin/customers/new">{!! ic('users') !!} Customer</a>@endif
            @if(adm_can('coupons'))<a href="/admin/coupons/new">{!! ic('ticket') !!} Coupon</a>@endif
          </div>
        </div>
        <div class="adm-menu" data-menu>
          <button type="button" class="adm-user" data-menu-btn aria-haspopup="true" aria-label="Account menu">
            <span class="adm-avatar">{{ strtoupper(mb_substr($u->name ?: $u->email, 0, 1)) }}</span>
            <span class="adm-user__txt hide-m"><b>{{ $u->name }}</b><small>{{ \App\Support\AdminAccess::ROLES[\App\Support\AdminAccess::role()] ?? 'Admin' }}</small></span>
          </button>
          <div class="adm-menu__pop adm-menu__pop--right" hidden>
            <div class="adm-menu__head"><b>{{ $u->name }}</b><small>{{ $u->email }}</small></div>
            <a href="/account">{!! ic('users') !!} My account</a>
            @if(adm_can('settings'))<a href="/admin/settings">{!! ic('settings') !!} Settings</a>@endif
            <form method="POST" action="/logout">@csrf<button type="submit">{!! ic('logout') !!} Sign out</button></form>
          </div>
        </div>
      </div>
    </header>

    <div class="adm-page">
      <div class="adm-head">
        <div class="adm-head__txt">
          @hasSection('crumb')<div class="adm-crumb">@yield('crumb')</div>@endif
          <h1 class="adm-title">@yield('title','Dashboard')</h1>
          @hasSection('sub')<p class="adm-sub">@yield('sub')</p>@endif
        </div>
        @hasSection('actions')<div class="adm-head__actions">@yield('actions')</div>@endif
      </div>

      @if(session('admin_ok'))<div class="flash flash--ok" data-toast>{!! ic('check') !!} {{ session('admin_ok') }}</div>@endif
      @if(session('admin_err'))<div class="flash flash--err" data-toast>{!! ic('alert') !!} {{ session('admin_err') }}</div>@endif
      @if($errors->any())<div class="flash flash--err">{!! ic('alert') !!} <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>@endif

      @yield('body')
    </div>
  </div>

  <nav class="adm-tabs" aria-label="Quick navigation">
    <a href="/admin" class=" @if($path==='admin') is-on @endif">{!! ic('home') !!}<span>Home</span></a>
    @if(adm_can('orders'))<a href="/admin/orders" class=" @if($on('orders')) is-on @endif">{!! ic('orders') !!}<span>Orders</span>@if(!empty($counts['orders_open']))<b>{{ $counts['orders_open'] }}</b>@endif</a>@endif
    @if(adm_can('products'))<a href="/admin/products" class=" @if($on('products')) is-on @endif">{!! ic('box') !!}<span>Products</span></a>@endif
    @if(adm_can('driveshafts'))<a href="/admin/driveshafts" class=" @if($on('driveshafts')) is-on @endif">{!! ic('shaft') !!}<span>Shafts</span></a>@endif
    <button type="button" data-drawer-open>{!! ic('menu') !!}<span>Menu</span></button>
  </nav>
</div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<script src="/js/admin.js?v={{ @filemtime(public_path('js/admin.js')) ?: time() }}"></script>
@stack('scripts')
</body>
</html>

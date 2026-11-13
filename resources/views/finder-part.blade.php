@extends('layouts.app')
@php
  $pn = $p['sku'] ?? \App\Services\DriveshaftFinder::sku($p['part_number']); $desc = $p['description'] ?: 'Complete driveshaft';
  $pieces = $p['pieces']; $pcWord = [1 => 'one-piece', 2 => 'two-piece', 3 => 'three-piece'][$pieces] ?? null;
  $appLine = collect($p['applications'])->take(3)->map(fn ($a) => "{$a['years']} {$a['make']} {$a['model']}")->implode(', ');
  $slug = fn ($s) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
  $loc = $p['facets']['loc']['values'] ?? []; $drive = $p['facets']['drive']['values'] ?? [];
@endphp
@php
  // title: part + first application only, years abbreviated (2002-2007 -> 2002–07), hard cap 60 chars
  $a0 = $p['applications'][0] ?? null;
  $yrs = $a0 ? preg_replace_callback('/^(\d{4})-(\d{4})$/', fn ($m) => $m[1] . '–' . substr($m[2], 2), $a0['years']) : '';
  $app0 = $a0 ? trim("$yrs {$a0['make']} {$a0['model']}") : '';
  $t = __(':pn Driveshaft', ['pn' => $pn]) . ($app0 ? ", $app0" : ''); $t = \Illuminate\Support\Str::limit($t, 52, '') . ' | UDS';
  $nApps = count($p['applications']);
  $d = ($pcWord ? __('Complete :pieces driveshaft :pn', ['pieces' => __($pcWord), 'pn' => $pn]) : __('Complete driveshaft :pn', ['pn' => $pn]))
     . ($appLine ? ' ' . __('for :apps', ['apps' => $appLine]) : '') . ($nApps > 3 ? ' ' . __('and :n more applications', ['n' => $nApps - 3]) : '')
     . (!empty($p['can_buy']) && !empty($p['price']) ? ', $' . number_format((float) $p['price'], 2) : '') . '. ' . __('Assembled and balanced, ships from Florida.');
  if (mb_strlen($d) > 158) $d = mb_substr($d, 0, 155) . '…';
@endphp
@section('title', $t)
@section('desc', $d)
@section('ogtype', 'product')
@if(!empty($p['images'][0]))@section('ogimage', str_starts_with($p['images'][0], '/') ? rtrim(config('app.url'), '/') . $p['images'][0] : $p['images'][0])@endif
@push('schema')
<script type="application/ld+json">{!! json_encode(array_filter([
  '@@context' => 'https://schema.org', '@@type' => 'Product',
  'name' => trim(($p['description'] ?? 'Complete driveshaft') . ' ' . $pn),
  'sku' => $pn, 'mpn' => $p['part_number'], 'brand' => ['@@type' => 'Brand', 'name' => 'UDS'],
  // what search engines and AI answers read about the shaft: photos, its own URL, who sells it, and its configuration
  '@@id' => url()->current() . '#product', 'url' => url()->current(),
  'image' => array_values(array_map(fn ($u) => str_starts_with($u, '/') ? rtrim(config('app.url'), '/') . $u : $u, array_slice($p['images'] ?? [], 0, 5))) ?: null,
  'additionalProperty' => array_values(array_filter([
      $pcWord ? ['@@type' => 'PropertyValue', 'name' => 'Construction', 'value' => ucfirst($pcWord)] : null,
      $loc ? ['@@type' => 'PropertyValue', 'name' => 'Position', 'value' => implode(', ', array_map('ucfirst', $loc))] : null,
      $drive ? ['@@type' => 'PropertyValue', 'name' => 'Drive', 'value' => implode(', ', $drive)] : null,
      ['@@type' => 'PropertyValue', 'name' => 'Condition', 'value' => 'New, assembled and balanced'],
  ])) ?: null,
  'category' => \App\Services\VehiclePages::CATEGORY,
  'description' => $appLine ? "Complete driveshaft $pn, fits $appLine." : "Complete driveshaft $pn.",
  'isAccessoryOrSparePartFor' => collect($p['applications'])->map(fn ($a) => array_filter([
      '@@type' => 'Vehicle', 'name' => trim("{$a['years']} {$a['make']} {$a['model']} " . ($a['drive'] ?? '')),
      'brand' => ['@@type' => 'Brand', 'name' => $a['make']], 'model' => $a['model'],
      'vehicleModelDate' => preg_match('/^\d{4}$/', (string) $a['years']) ? (string) $a['years'] : null,
      'vehicleConfiguration' => $a['drive'] ?: null,
  ]))->values()->all() ?: null,
  'offers' => !empty($p['can_buy']) && !empty($p['price']) ? ['@@type' => 'Offer', 'price' => number_format((float) $p['price'], 2, '.', ''), 'priceCurrency' => 'USD', 'availability' => 'https://schema.org/InStock', 'url' => url()->current(), 'seller' => ['@@id' => rtrim(config('app.url'), '/') . '/#organization'], 'itemCondition' => 'https://schema.org/NewCondition'] : null,
]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@include('partials.breadcrumb-schema', ['crumbs' => [['Home', url('/')], ['Complete driveshafts', url('/catalog/driveshafts')], [$pn, url()->current()]]])
@endpush
@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
.fpp{background:var(--paper-2,#f6f8fc);padding:24px 0 64px}
.fpp-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin:10px 0 18px}
.fpp-head h1{margin:0;font-size:clamp(1.6rem,3.2vw,2.4rem)}
.fpp-head h1 small{display:block;font-family:var(--font-m,monospace);font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-faint,#697687);font-weight:500;margin-bottom:6px}
.fpp-acts{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.fpp-headprice{font-family:var(--font-m,monospace);font-size:1.5rem;font-weight:600;font-variant-numeric:tabular-nums;display:flex;flex-direction:column;line-height:1.1}
.fpp-headprice small{font-size:.75rem;font-weight:500;color:var(--ink-faint,#697687)}
.fpp-fit{display:inline-flex;align-items:center;gap:.4rem;margin:.6rem 0 0;padding:.4rem .75rem;border-radius:8px;background:var(--ok-50,#e8f6ee);color:var(--ok-700,#1d6b3f);font-weight:600;font-size:.95rem}
@media (max-width:640px){.fpp-acts{width:100%}.fpp-acts .btn{flex:1;justify-content:center}}
.fpp-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:22px;align-items:start}
@media(max-width:900px){.fpp-grid{grid-template-columns:1fr}}
.fpp-card{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(16,32,64,.10);padding:26px;min-width:0}
@media(max-width:600px){.fpp-card{padding:18px}}
.fpp-card h2{margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid var(--line,#e4e8f0);font-size:1.15rem;text-transform:uppercase;letter-spacing:.03em}
.fpp-art{aspect-ratio:16/9;display:grid;place-items:center;background:linear-gradient(180deg,#fff,var(--paper-2,#f6f8fc));border:1px solid var(--line,#e4e8f0);border-radius:8px;color:var(--steel,#0b1a33);padding:18px}
.fpp-art svg{width:100%;height:auto;max-height:100%}
.fpp-art-cap{margin:10px 0 0;font-size:.8rem;color:var(--ink-faint,#697687);text-align:center}
.fpp-gal{display:flex;flex-direction:column;gap:10px}
.fpp-main{aspect-ratio:4/3;background:#fff;border:1px solid var(--line,#e4e8f0);border-radius:8px;display:grid;place-items:center;overflow:hidden;cursor:zoom-in}
.fpp-main img{width:100%;height:100%;object-fit:contain}
.fpp-thumbs{display:flex;gap:8px;flex-wrap:wrap}
.fpp-thumbs button{width:62px;height:62px;padding:0;border:1px solid var(--line,#e4e8f0);border-radius:6px;background:#fff;cursor:pointer;overflow:hidden}
.fpp-thumbs button.on{border-color:var(--blue,#1246ab);box-shadow:0 0 0 2px var(--blue-100,#dce7f8)}
.fpp-thumbs img{width:100%;height:100%;object-fit:contain}
.fpp-lb{position:fixed;inset:0;background:rgba(11,26,58,.85);display:none;place-items:center;z-index:200;cursor:zoom-out;padding:24px}
.fpp-lb.on{display:grid}
.fpp-lb img{max-width:96vw;max-height:92vh;object-fit:contain;border-radius:8px}
.fpp-dl{margin:0;display:grid;grid-template-columns:minmax(110px,34%) 1fr}
.fpp-dl dt,.fpp-dl dd{margin:0;padding:12px 0;border-bottom:1px solid var(--line,#e4e8f0)}
.fpp-dl dt{font-family:var(--font-m,monospace);font-size:.74rem;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-faint,#697687);align-self:center}
.fpp-dl dd{font-weight:600;color:var(--ink,#141b28);overflow-wrap:anywhere}
.fpp-price{font-family:var(--font-m,monospace);font-size:1.6rem;font-weight:600}
.fpp-was{font-family:var(--font-m,monospace);color:var(--ink-faint,#697687);text-decoration:line-through;font-weight:400;margin-inline-end:8px}
.fpp-buy{display:flex;gap:10px;margin-top:18px;flex-wrap:wrap}
.fpp-buy input{width:84px;padding:12px;border:1px solid var(--line-strong,#d4dae6);border-radius:10px;font:inherit;text-align:center}
.fpp-apps{margin:0;padding:0;list-style:none;display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px}
.fpp-apps a{display:flex;align-items:center;gap:10px;border:1px solid var(--line,#e4e8f0);border-radius:8px;padding:9px 12px;text-decoration:none;color:var(--ink,#141b28);font-weight:600;font-size:.9rem;min-width:0}
.fpp-apps a:hover{border-color:var(--blue,#1246ab);background:var(--blue-050,#eef3fc)}
.fpp-apps img{width:34px;height:24px;object-fit:contain;flex:none}
.fpp-apps span{font-family:var(--font-m,monospace);font-weight:500;color:var(--ink-2,#3a4557);flex:none}
.fpp-apps b{font-weight:600;overflow-wrap:anywhere}
.fpp-story{color:var(--ink-2,#3a4557);line-height:1.7;margin:0}
.fpp-story+.fpp-story{margin-top:10px}
.fpp-ids{font-family:var(--font-m,monospace);font-size:.86rem;background:var(--paper-2,#f6f8fc);border:1px solid var(--line,#e4e8f0);border-radius:8px;padding:10px 12px;margin:8px 0 0;overflow-wrap:anywhere}
#fpp-cfg td{white-space:normal;overflow-wrap:anywhere}
.fpp-more{font-size:.84rem;color:var(--ink-faint,#697687);margin:10px 0 0}
</style>

<section class="fpp"><div class="wrap">
  <div class="crumb"><a href="/">{{ __('Home') }}</a><span class="sep">/</span><a href="/catalog/driveshafts">{{ __('Complete driveshafts') }}</a><span class="sep">/</span><span>{{ $pn }}</span></div>
  <div class="fpp-head">
    <div>
      {{-- the vehicle it fits is in the h1 too: shafts are searched by year, make and model --}}
      <h1><small>{{ __('Complete driveshaft') }}@if($app0) · {{ $app0 }}@endif</small> {{ __('Part number') }} {{ $pn }}</h1>
      @if(!empty($fit))<p class="fpp-fit"><x-icon name="check"/> {{ __('Fits your :vehicle', ['vehicle' => $fit]) }}</p>@endif
    </div>
    <div class="fpp-acts">
      @if(empty($fit))<a class="btn btn-line" href="/catalog/driveshafts">{{ __('Check fit by vehicle') }}</a>@endif
      {{-- the price beside the button, in the first screen on a phone (audit 2026-11-12: it sat below the fold) --}}
      @if($p['can_buy'])<span class="fpp-headprice">${{ number_format($p['price'],2) }}@if($p['core_price'])<small>{{ __('+ :amount core', ['amount' => '$' . number_format($p['core_price'],2)]) }}</small>@endif</span>@endif
      @if($p['can_buy'])<button class="btn btn-primary" type="button" data-add-top>{{ __('Add to cart') }}</button>@else<button class="btn btn-primary" type="button" data-quote="{{ $pn }}" data-pn="{{ $p['part_number'] }}">{{ __('Get a quote') }}</button>@endif
    </div>
  </div>

  <div class="fpp-grid">
    <div class="fpp-card">
      @php $n = $pieces ?: 1; @endphp
      @if(!empty($p['images']))
      <div class="fpp-gal">
        <div class="fpp-main" id="fpp-main"><img src="{{ $p['images'][0] }}" alt="{{ $pn }} complete driveshaft" id="fpp-main-img"></div>
        @if(count($p['images']) > 1)
        <div class="fpp-thumbs" id="fpp-thumbs">
          @foreach($p['images'] as $i => $src)
            <button type="button" class="{{ $i === 0 ? 'on' : '' }}" data-src="{{ $src }}" aria-label="View photo {{ $i + 1 }}"><img src="{{ \App\Services\CartService::thumb($src) }}" alt="" width="62" height="62"></button>
          @endforeach
        </div>
        @endif
      </div>
      <p class="fpp-art-cap">{{ __('Manufacturer photo. The part you receive may vary slightly in finish or markings.') }}</p>
      @else
      <div class="fpp-art" role="img" aria-label="{{ app()->getLocale() === 'en' ? ($pcWord ? ucfirst($pcWord) : 'Complete') . ' driveshaft illustration' : __(':kind driveshaft illustration', ['kind' => __($pcWord ?: 'complete')]) }}">
        <svg viewBox="0 0 640 150" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round">
          <path d="M18 45h26v60H18z" fill="#dce7f8"/><path d="M44 55h22l14 12v16l-14 12H44z" fill="#fff"/><circle cx="31" cy="75" r="7"/>
          @for($i = 0; $i < $n; $i++)
            @php $x0 = 80 + $i * (480 / $n); $w = 480 / $n; $last = $i === $n - 1; @endphp
            <rect x="{{ $x0 }}" y="{{ $i % 2 ? 60 : 56 }}" width="{{ $w - ($last ? 0 : 46) }}" height="{{ $i % 2 ? 30 : 38 }}" rx="4" fill="#0b1a33" stroke="#0b1a33"/>
            <path d="M{{ $x0 + 14 }} {{ $i % 2 ? 66 : 62 }}h{{ $w - ($last ? 28 : 74) }}" stroke="#ffffff" stroke-opacity=".25" stroke-width="4"/>
            @unless($last)
              @php $bx = $x0 + $w - 46; @endphp
              <rect x="{{ $bx }}" y="66" width="46" height="18" fill="#b7cdf0"/>
              <path d="M{{ $bx + 6 }} 30h34v90h-34z" fill="#fff"/><path d="M{{ $bx + 6 }} 30h34M{{ $bx + 6 }} 120h34"/><circle cx="{{ $bx + 23 }}" cy="75" r="15" fill="#dce7f8"/><circle cx="{{ $bx + 23 }}" cy="75" r="6" fill="#fff"/>
            @endunless
          @endfor
          <path d="M560 67l14-12h22v40h-22l-14-12z" fill="#fff"/><path d="M596 45h26v60h-26z" fill="#dce7f8"/><circle cx="609" cy="75" r="7"/>
        </svg>
      </div>
      <p class="fpp-art-cap">{{ __('Illustration of a :kind driveshaft', ['kind' => __($pcWord ?: 'complete')]) }}{{ $n > 1 ? ($n > 2 ? __(' with :c center support bearings', ['c' => $n - 1]) : __(' with :c center support bearing', ['c' => $n - 1])) : '' }}{{ __('. Not a photo of this exact part.') }}</p>
      @endif
    </div>

    <div class="fpp-card">
      <h2>{{ __('Product details') }}</h2>
      <dl class="fpp-dl">
        <dt>{{ __('Price') }}</dt><dd>@if($p['can_buy'])@if($p['price_list'])<span class="fpp-was">${{ number_format($p['price_list'],2) }}</span>@endif<span class="fpp-price">${{ number_format($p['price'],2) }}</span>@else {{ __('Call (813) 524-6491') }} @endif</dd>
        @if($p['core_price'])<dt>{{ __('Core charge') }}</dt><dd>${{ number_format($p['core_price'],2) }} {{ __('(refunded when we receive your old shaft)') }}</dd>@endif
        <dt>{{ __('Description') }}</dt><dd>{{ $desc }}</dd>
        <dt>{{ __('Condition') }}</dt><dd>{{ ucfirst($p['condition'] ?: 'new') }}</dd>
        @if($loc)<dt>{{ __('Location') }}</dt><dd>{{ implode(' / ', $loc) }}</dd>@endif
        @if($drive)<dt>{{ __('Drive') }}</dt><dd>{{ implode(' / ', $drive) }}</dd>@endif
        @if($p['c_brg'])<dt>{{ __('Center bearing') }}</dt><dd>{{ implode(' & ', $p['c_brg']) }}</dd>@endif
        @if($p['weld_to_weld'])<dt>{{ __('Weld to weld') }}</dt><dd>{{ implode(' / ', $p['weld_to_weld']) }}</dd>@endif
        @if($p['series'])<dt>{{ __('Series') }}</dt><dd>{{ $p['series'] }}</dd>@endif
        @if($p['weight'])<dt>{{ __('Shipping weight') }}</dt><dd>{{ rtrim(rtrim(number_format($p['weight'],2),'0'),'.') }} lb</dd>@endif
      </dl>
      @if($p['can_buy'])
      <div class="fpp-buy"><input type="number" id="fpp-qty" value="1" min="1" max="20" aria-label="{{ __('Quantity') }}"><button class="btn btn-primary btn-lg" type="button" id="fpp-add" style="flex:1">{{ __('Add to cart') }}</button></div>
      @endif
      <x-google-rating variant="card"/>
      <p class="fpp-more">{{ __('Lead time is confirmed by our team when you order. Questions about fit: (813) 524-6491.') }}</p>
    </div>
  </div>

  <div class="fpp-card" style="margin-top:22px">
    <h2>{{ __('About this driveshaft') }}</h2>
    <p class="fpp-story">{{ __($pcWord ? ':pn is a :cond complete :pieces driveshaft' : ':pn is a :cond complete driveshaft', ['pn' => $pn, 'cond' => __(($p['condition'] ?: 'new') === 'new' ? 'new' : $p['condition']), 'pieces' => __((string) $pcWord)]) }}{{ $loc ? __(' for the :loc position', ['loc' => strtolower($loc[0])]) : '' }}@if(count($p['applications'])){{ __(count($p['applications']) > 1 ? ', listed for :n vehicle applications from :from to :to across :makes' : ', listed for :n vehicle application from :from to :to across :makes', ['n' => count($p['applications']), 'from' => $p['year_min'], 'to' => $p['year_max'], 'makes' => implode(', ', array_slice($p['makes'], 0, 4))]) }}{{ count($p['makes']) > 4 ? __(' and more') : '' }}@endif. {{ $n > 1 ? ($n > 2 ? __('It ships as an assembled unit with :c center support bearings installed.', ['c' => $n - 1]) : __('It ships as an assembled unit with :c center support bearing installed.', ['c' => $n - 1])) : __('It ships assembled and balanced, ready to install.') }}</p>
    <p class="fpp-story">{{ __('Before ordering, compare your old shaft: count the pieces, check the center bearing number') }}{{ $p['c_brg'] ? ' (' . implode(' or ', $p['c_brg']) . ')' : '' }} {{ __('and any ID code on the tag, and confirm the configuration in the table below.') }}</p>
    @if($p['notes'])<p class="fpp-story" style="margin-top:12px"><b>{{ __('ID codes and notes') }}</b></p>@foreach($p['notes'] as $note)<div class="fpp-ids">{{ $note }}</div>@endforeach @endif
  </div>

  @if(count($p['applications']))
  <div class="fpp-card" style="margin-top:22px">
    <h2>{{ __('Applications (:n)', ['n' => count($p['applications'])]) }}</h2>
    <ul class="fpp-apps">
      @foreach($p['applications'] as $a)
        <li><a href="{{ \App\Services\VehiclePages::modelUrl($a['make'], $a['model']) ?? $a['url'] }}">@if(in_array($slug($a['make']), $logos))<img src="{{ media_url('/img/makes/'.$slug($a['make']).'.png', 160) }}" alt="" loading="lazy">@endif<span>{{ $a['years'] }}</span><b>{{ $a['make'] }} {{ $a['model'] }}{{ $a['drive'] ? ' · ' . $a['drive'] : '' }}</b></a></li>
      @endforeach
    </ul>
  </div>
  @endif

  @if(count($p['configs']) && count($p['config_cols']))
  <div class="fpp-card" style="margin-top:22px">
    <h2>{{ __('Configurations this part fits') }}</h2>
    <div class="ptbl-wrap"><table class="ptbl" id="fpp-cfg">
      <thead><tr><th class="l">{{ __('Vehicle') }}</th><th>{{ __('Drive') }}</th>@foreach($p['config_cols'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
      <tbody>
      @foreach($p['configs'] as $cfg)
        <tr><td data-label="Vehicle" class="l">{{ $cfg['vehicle'] }}</td><td data-label="Drive">{{ $cfg['drive'] ?: __('Any') }}</td>@foreach($cfg['values'] as $i => $v)<td data-label="{{ $p['config_cols'][$i] }}">{{ $v ?: __('Any') }}</td>@endforeach</tr>
      @endforeach
      </tbody>
    </table></div>
    <p class="fpp-more">{{ __('"Any" means the part is not restricted by that detail.') }}@if($p['config_total'] > count($p['configs'])) {{ __('Showing :a of :b configurations, use the vehicle finder for the rest.', ['a' => count($p['configs']), 'b' => $p['config_total']]) }}@endif</p>
  </div>
  @endif

  <p class="xref-note" style="margin-top:22px">{{ __('Vehicle makes, logos, models and manufacturer part numbers are trademarks of their respective owners, used for identification and fitment reference only. Universal Driveshafts is not affiliated with them. Fitment data may contain errors, verify before ordering.') }} <a href="/terms">{{ __('Terms of use') }}</a></p>
</div></section>
<script>
(function(){
  var main=document.getElementById('fpp-main-img'), thumbs=document.getElementById('fpp-thumbs'); if(!main)return;
  var lb=document.createElement('div'); lb.className='fpp-lb'; lb.innerHTML='<img alt="">'; document.body.appendChild(lb);
  var lbImg=lb.querySelector('img');
  if(thumbs)thumbs.addEventListener('click',function(e){var b=e.target.closest('button[data-src]'); if(!b)return;
    main.src=b.getAttribute('data-src'); thumbs.querySelectorAll('button').forEach(function(x){x.classList.toggle('on',x===b);});});
  document.getElementById('fpp-main').addEventListener('click',function(){lbImg.src=main.src; lb.classList.add('on');});
  lb.addEventListener('click',function(){lb.classList.remove('on');});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')lb.classList.remove('on');});
})();
</script>
@if(app()->getLocale() !== 'en')
<script>window.UDS_T=Object.assign(window.UDS_T||{},{!! json_encode(collect(['Could not add this part.'])->mapWithKeys(fn ($k) => [$k => __($k)]), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) !!});</script>
@endif
<script>var TT=function(s){return (window.UDS_T||{})[s]||s;};
(function(){
  var CSRF=document.querySelector('meta[name="csrf-token"]').content, btn=document.getElementById('fpp-add'); if(!btn)return;
  function add(b){window.udsAdd(b,'/catalog/driveshafts/add',{part_number:@json($pn),qty:document.getElementById('fpp-qty').value||1});}   // the same response as every Add button (uds.js udsAdd)
  btn.onclick=function(){add(btn);}; var top=document.querySelector('[data-add-top]'); if(top)top.onclick=function(){add(top);};
})();
</script>
<script src="/js/alerts.js?v={{ @filemtime(public_path('js/alerts.js')) }}" defer></script>   {{-- quote modal for unpriced driveshafts (recovery agent) --}}
@endsection
@push('scripts')
@include('partials.track', ['ev' => 'view_item', 'p' => ['value' => round((float) ($p['price'] ?? 0), 2),
  'items' => [\App\Support\Funnel::item(null, (string) $pn, (string) $desc, (float) ($p['price'] ?? 0), 1, 'Complete driveshafts', 'driveshaft')]]])
@endpush

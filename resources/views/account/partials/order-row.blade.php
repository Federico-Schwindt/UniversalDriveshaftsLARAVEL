@php
  [$sk, $sl] = \App\Http\Controllers\AccountController::orderState($o);
  $parts = $parts ?? collect(); $q = $q ?? '';
  // split the raw text, escape each piece: matching on escaped HTML could cut through an entity (&amp; → &a<mark>m</mark>p;)
  $mark = function ($t) use ($q) {
    $t = (string) $t;
    if ($q === '') return e($t);
    $bits = preg_split('/(' . preg_quote($q, '/') . ')/iu', $t, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($bits === false) return e($t);
    return implode('', array_map(fn ($b, $i) => $i % 2 ? '<mark>' . e($b) . '</mark>' : e($b), $bits, array_keys($bits)));
  };
  $summary = $parts->take(3)->map(fn ($l) => $l->qty . ' × ' . ($l->sku ?: \Illuminate\Support\Str::limit($l->name, 40)))->implode(', ') . ($parts->count() > 3 ? ' +' . ($parts->count() - 3) : '');
@endphp
<a class="orow" href="{{ \App\Support\OrderEvents::statusUrl($o, false) }}">
  <span class="no">{!! $mark($o->order_number) !!}@if($summary)<small class="oparts">{!! $mark($summary) !!}</small>@endif</span>
  <span class="po">@if($o->po_number)<span class="po-l">{{ __('PO') }}</span> {!! $mark($o->po_number) !!}@else<span class="muted">—</span>@endif</span>
  <span class="dt">{{ \Carbon\Carbon::parse($o->created_at)->isoFormat('ll') }}</span>
  <span class="st"><span class="pill pill-{{ $sk }}">{{ __($sl) }}</span>@if(!empty($rma))<small class="orma">{{ __(\App\Support\Rma::typeLabel($rma->type)) }}: {{ \App\Support\Rma::customerState($rma)[0] }}</small>@endif</span>
  <span class="trk">@if($o->tracking_number){{ strtoupper((string) $o->tracking_carrier) }} {{ $o->tracking_number }}@else{{ $o->shipped_at ? __('Shipped') : __('Not shipped yet') }}@endif</span>
  <span class="tot">${{ number_format($o->total, 2) }}</span>
</a>

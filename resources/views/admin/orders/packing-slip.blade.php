<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Packing slip {{ $order->order_number }}</title>
<style>
body{font:13px/1.45 -apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#141b28;margin:0;padding:28px}
.top{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #1246ab;padding-bottom:14px;margin-bottom:18px}
h1{font-size:22px;margin:0;color:#1246ab}h1 small{display:block;font-size:11px;color:#5c6879;letter-spacing:2px;font-weight:600}
.meta{text-align:right}.meta b{font-size:18px;font-family:ui-monospace,Menlo,monospace}
.cols{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}.h{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8a94a6;font-weight:700;margin-bottom:4px}
table{width:100%;border-collapse:collapse}th{text-align:left;background:#f1f4fa;font-size:10.5px;letter-spacing:1px;text-transform:uppercase;padding:8px}td{padding:9px 8px;border-bottom:1px solid #e4e8f0;vertical-align:top}
.logo{display:block;height:40px;width:auto;margin-bottom:6px}.ph{width:64px;height:64px;object-fit:contain;border:1px solid #e4e8f0;border-radius:6px;background:#fff;display:block}.ph--none{background:#f1f4fa}
.qty{font-size:20px;font-weight:800;text-align:center;width:60px}.chk{width:26px;height:26px;border:2px solid #cfd6e3;border-radius:5px;display:inline-block}
.warn{background:#fdf3e2;border:1px solid #f1d9a5;padding:10px 12px;border-radius:8px;margin-bottom:14px}
.box{border:1px solid #e4e8f0;border-radius:8px;padding:10px 12px;margin-top:14px;font-size:12px}
.foot{margin-top:26px;font-size:11px;color:#5c6879;text-align:center}
@media print{body{padding:10px}}
</style></head><body onload="window.print()">
<div class="top"><div><img class="logo" src="{{ url('/img/email/logo.png') }}" alt="Universal Driveshafts"><h1><small>PACKING SLIP</small></h1><div style="margin-top:6px;color:#5c6879">Florida, USA<br>{{ config('uds.phone', config('uds.pickup.phone')) }} · {{ config('uds.email') }}</div></div>
<div class="meta"><b>{{ $order->order_number }}</b><br>{{ \Carbon\Carbon::parse($order->created_at)->format('M j, Y') }}@if($order->po_number)<br>PO {{ $order->po_number }}@endif<br>{{ $order->shipping_label }}</div></div>
@if($order->shipping_method===\App\Services\CartService::PICKUP_ID)<div class="warn"><b>LOCAL PICKUP — DO NOT SHIP.</b>@if($order->payment_method==='pay_at_pickup') Collect {{ money($order->total) }} at pickup. @endif</div>@endif
@if($order->ship_instructions || $order->signature_required)<div class="warn">@if($order->signature_required)<b>Signature required.</b> @endif{{ $order->ship_instructions }}</div>@endif
@php $s = $order->ship_to_different ? 'shipping' : 'billing'; @endphp
<div class="cols">
  <div><div class="h">Ship to</div><b>{{ $order->{$s.'_first_name'} }} {{ $order->{$s.'_last_name'} }}</b>@if($order->billing_company)<br>{{ $order->billing_company }}@endif<br>{{ $order->{$s.'_address_1'} }} {{ $order->{$s.'_address_2'} }}<br>{{ $order->{$s.'_city'} }}, {{ $order->{$s.'_state'} }} {{ $order->{$s.'_postcode'} }} {{ $order->{$s.'_country'} ?: $order->billing_country }}<br>{{ $order->phone }}</div>
  <div><div class="h">Bill to</div>{{ $order->billing_first_name }} {{ $order->billing_last_name }}<br>{{ $order->billing_address_1 }}<br>{{ $order->billing_city }}, {{ $order->billing_state }} {{ $order->billing_postcode }}<br>{{ $order->email }}</div>
</div>
<table><thead><tr><th style="width:30px"></th><th style="width:72px"></th><th>Qty</th><th>Item</th><th>SKU</th></tr></thead><tbody>
@foreach($items as $it)<tr><td><span class="chk"></span></td><td>@if(!empty($thumbs[$it->wp_product_id]))<img class="ph" src="{{ $thumbs[$it->wp_product_id] }}" alt="">@else<span class="ph ph--none"></span>@endif</td><td class="qty">{{ $it->qty }}</td><td><b>{{ $it->name }}</b>@if(!empty($it->metaArr['summary']))<br><span style="color:#1f9e88">{{ $it->metaArr['summary'] }}</span>@endif @if(!empty($it->metaArr['config']))<div style="margin-top:4px;font-size:11.5px;color:#3a4557">@foreach(\App\Support\BuildConfig::rows($it->metaArr['config']) as $rw){{ $rw['label'] }}: <b>{{ $rw['value'] }}</b>@if(!$loop->last) · @endif @endforeach</div>@endif</td><td style="font-family:ui-monospace,Menlo,monospace">{{ $it->sku }}</td></tr>@endforeach
</tbody></table>
@if($pack)<div class="box"><b>{{ $pack['packages'] }} package{{ $pack['packages']==1?'':'s' }}</b> · {{ rtrim(rtrim(number_format($pack['weight_lb'],1),'0'),'.') }} lb total — @foreach($pack['boxes'] as $b){{ $b['kind'] ?? 'box' }} {{ rtrim(rtrim(number_format($b['weight'] ?? 0,1),'0'),'.') }} lb @if(!$loop->last), @endif @endforeach</div>@endif
@if($order->customer_note)<div class="box"><b>Customer note:</b> {{ $order->customer_note }}</div>@endif
<div class="foot">Thank you for your order! Questions: {{ config('uds.phone', config('uds.pickup.phone')) }} · Returns within 14 days with RMA — see universaldriveshafts.com/returns</div>
</body></html>

<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Packing slip {{ $order->order_number }}</title>
{{-- 4" × 6" thermal label stock (owner, 2026-11-14) --}}
<style>
@page{size:4in 6in;margin:.16in}
*{box-sizing:border-box}
body{font:9.5px/1.35 -apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#000;margin:0;padding:0;width:3.68in}
.top{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;border-bottom:2px solid #000;padding-bottom:5px;margin-bottom:6px}
.logo{display:block;height:20px;width:auto;margin-bottom:2px}.kick{font-size:8px;letter-spacing:1.5px;font-weight:700}
.meta{text-align:right;line-height:1.3}.meta b{font-size:12px;font-family:ui-monospace,Menlo,monospace}
.h{font-size:7px;letter-spacing:1px;text-transform:uppercase;font-weight:700;color:#444;margin-bottom:1px}
.to{font-size:10.5px;line-height:1.3;margin-bottom:4px}.bill{font-size:8.5px;color:#333;margin-bottom:6px}
table{width:100%;border-collapse:collapse}th{text-align:left;font-size:7px;letter-spacing:.8px;text-transform:uppercase;padding:3px 3px;border-bottom:1px solid #000}
td{padding:4px 3px;border-bottom:1px solid #ccc;vertical-align:top}tr{break-inside:avoid;page-break-inside:avoid}
.chk{width:12px;height:12px;border:1.5px solid #000;border-radius:2px;display:inline-block}
.ph{width:34px;height:34px;object-fit:contain;display:block}.ph--none{display:block;width:34px;height:34px;background:#eee}
.qty{font-size:14px;font-weight:800;text-align:center;width:26px}.sku{font-family:ui-monospace,Menlo,monospace;font-size:9px}
.cfg{margin-top:2px;font-size:8px;color:#333}
.warn{border:1.5px solid #000;padding:4px 6px;margin-bottom:6px;font-size:9.5px}
.box{border:1px solid #999;padding:4px 6px;margin-top:6px;font-size:8.5px}
.foot{margin-top:8px;font-size:7.5px;color:#444;text-align:center}
@media screen{body{margin:16px auto;padding:.16in;width:4in;min-height:6in;box-shadow:0 0 0 1px #ccc}}
</style></head><body onload="window.print()">
<div class="top"><div><img class="logo" src="{{ url('/img/email/logo.png') }}" alt="Universal Driveshafts"><div class="kick">PACKING SLIP</div><div>{{ config('uds.phone', config('uds.pickup.phone')) }}</div></div>
<div class="meta"><b>{{ $order->order_number }}</b><br>{{ \Carbon\Carbon::parse($order->created_at)->format('M j, Y') }}@if($order->po_number)<br>PO {{ $order->po_number }}@endif<br>{{ $order->shipping_label }}</div></div>
@if($order->shipping_method===\App\Services\CartService::PICKUP_ID)<div class="warn"><b>LOCAL PICKUP — DO NOT SHIP.</b>@if($order->payment_method==='pay_at_pickup') Collect {{ money($order->total) }} at pickup.@endif</div>@endif
@if($order->ship_instructions || $order->signature_required)<div class="warn">@if($order->signature_required)<b>Signature required.</b> @endif{{ $order->ship_instructions }}</div>@endif
@php $s = $order->ship_to_different ? 'shipping' : 'billing'; @endphp
<div class="h">Ship to</div>
<div class="to"><b>{{ $order->{$s.'_first_name'} }} {{ $order->{$s.'_last_name'} }}</b>@if($order->billing_company)<br>{{ $order->billing_company }}@endif<br>{{ $order->{$s.'_address_1'} }} {{ $order->{$s.'_address_2'} }}<br>{{ $order->{$s.'_city'} }}, {{ $order->{$s.'_state'} }} {{ $order->{$s.'_postcode'} }} {{ $order->{$s.'_country'} ?: $order->billing_country }}@if($order->phone)<br>{{ $order->phone }}@endif</div>
<div class="bill"><b>Bill to:</b> {{ $order->billing_first_name }} {{ $order->billing_last_name }} · {{ $order->billing_city }}, {{ $order->billing_state }} · {{ $order->email }}</div>
<table><thead><tr><th style="width:16px"></th><th style="width:38px"></th><th>Qty</th><th>Item</th></tr></thead><tbody>
@foreach($items as $it)<tr><td><span class="chk"></span></td><td>@if(!empty($thumbs[$it->wp_product_id]))<img class="ph" src="{{ $thumbs[$it->wp_product_id] }}" alt="">@else<span class="ph--none"></span>@endif</td><td class="qty">{{ $it->qty }}</td><td><span class="sku">{{ $it->sku }}</span><br><b>{{ $it->name }}</b>@if(!empty($it->metaArr['summary']))<br>{{ $it->metaArr['summary'] }}@endif @if(!empty($it->metaArr['config']))<div class="cfg">@foreach(\App\Support\BuildConfig::rows($it->metaArr['config']) as $rw){{ $rw['label'] }}: <b>{{ $rw['value'] }}</b>@if(!$loop->last) · @endif @endforeach</div>@endif</td></tr>@endforeach
</tbody></table>
@if($pack)<div class="box"><b>{{ $pack['packages'] }} package{{ $pack['packages']==1?'':'s' }}</b> · {{ rtrim(rtrim(number_format($pack['weight_lb'],1),'0'),'.') }} lb total — @foreach($pack['boxes'] as $b){{ $b['kind'] ?? 'box' }} {{ rtrim(rtrim(number_format($b['weight'] ?? 0,1),'0'),'.') }} lb @if(!$loop->last), @endif @endforeach</div>@endif
@if($order->customer_note)<div class="box"><b>Customer note:</b> {{ $order->customer_note }}</div>@endif
<div class="foot">Thank you for your order! {{ config('uds.phone', config('uds.pickup.phone')) }} · Returns within 14 days with an RMA: universaldriveshafts.com/returns</div>
</body></html>

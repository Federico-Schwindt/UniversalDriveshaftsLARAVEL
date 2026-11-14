<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Packing slips</title>
{{-- 4" × 6" thermal label stock, one slip per page (owner, 2026-11-14) --}}
<style>
@page{size:4in 6in;margin:.16in}
*{box-sizing:border-box}
body{font:9.5px/1.35 -apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#000;margin:0}
.slip{width:3.68in;break-after:page;page-break-after:always}.slip:last-child{break-after:auto;page-break-after:auto}
.top{display:flex;justify-content:space-between;gap:8px;border-bottom:2px solid #000;padding-bottom:5px;margin-bottom:6px}
.logo{display:block;height:20px;width:auto;margin-bottom:2px}.kick{font-size:8px;letter-spacing:1.5px;font-weight:700}
.mono{font-family:ui-monospace,Menlo,monospace}.to{font-size:10.5px;line-height:1.3;margin:0 0 6px}
.h{font-size:7px;letter-spacing:1px;text-transform:uppercase;font-weight:700;color:#444;margin-bottom:1px}
table{width:100%;border-collapse:collapse}th{text-align:left;font-size:7px;text-transform:uppercase;padding:3px;border-bottom:1px solid #000}
td{padding:4px 3px;border-bottom:1px solid #ccc;vertical-align:top}tr{break-inside:avoid;page-break-inside:avoid}
.qty{font-size:14px;font-weight:800;text-align:center;width:26px}.ph{width:34px;height:34px;object-fit:contain;display:block}.ph--none{display:block;width:34px;height:34px;background:#eee}
.warn{border:1.5px solid #000;padding:4px 6px;margin-bottom:6px}
@media screen{.slip{margin:16px auto;padding:.16in;width:4in;min-height:6in;box-shadow:0 0 0 1px #ccc}}
</style></head><body onload="window.print()">
@foreach($orders as $o) @php $s = $o->ship_to_different ? 'shipping' : 'billing'; @endphp
<div class="slip"><div class="top"><div><img class="logo" src="{{ url('/img/email/logo.png') }}" alt="Universal Driveshafts"><div class="kick">PACKING SLIP</div>{{ config('uds.phone', config('uds.pickup.phone')) }}</div><div style="text-align:right"><b class="mono" style="font-size:12px">{{ $o->order_number }}</b><br>{{ \Carbon\Carbon::parse($o->created_at)->format('M j, Y') }}<br>{{ $o->shipping_label }}</div></div>
@if($o->shipping_method===\App\Services\CartService::PICKUP_ID)<div class="warn"><b>LOCAL PICKUP — DO NOT SHIP</b></div>@endif
<div class="h">Ship to</div>
<p class="to"><b>{{ $o->{$s.'_first_name'} }} {{ $o->{$s.'_last_name'} }}</b>@if($o->billing_company)<br>{{ $o->billing_company }}@endif<br>{{ $o->{$s.'_address_1'} }} {{ $o->{$s.'_address_2'} }}<br>{{ $o->{$s.'_city'} }}, {{ $o->{$s.'_state'} }} {{ $o->{$s.'_postcode'} }}@if($o->phone)<br>{{ $o->phone }}@endif</p>
<table><thead><tr><th style="width:38px"></th><th>Qty</th><th>Item</th></tr></thead><tbody>@foreach($items[$o->id] ?? [] as $it)<tr><td>@if(!empty($thumbs[$it->wp_product_id]))<img class="ph" src="{{ $thumbs[$it->wp_product_id] }}" alt="">@else<span class="ph--none"></span>@endif</td><td class="qty">{{ $it->qty }}</td><td><span class="mono">{{ $it->sku }}</span><br>{{ $it->name }}</td></tr>@endforeach</tbody></table>
@if($o->customer_note)<p style="margin:6px 0 0"><b>Note:</b> {{ $o->customer_note }}</p>@endif</div>
@endforeach
</body></html>

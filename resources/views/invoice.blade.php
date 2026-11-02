@php
  // Dompdf: tables for layout (no flex), DejaVu Sans, logo embedded as base64 (remote loading is off)
  $logo = is_file(public_path('img/uds-logo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('img/uds-logo.png'))) : null;
  $paid = $order->payment_status === 'paid';
  $shipTo = $order->ship_to_different ? 'shipping' : 'billing';
  $name = fn ($p) => trim(($order->{$p . '_first_name'} ?? '') . ' ' . ($order->{$p . '_last_name'} ?? ''));
  $addr = fn ($p) => array_filter([
    trim(($order->{$p . '_address_1'} ?? '') . ' ' . ($order->{$p . '_address_2'} ?? '')),
    trim(($order->{$p . '_city'} ?? '') . ' ' . ($order->{$p . '_state'} ?? '') . ' ' . ($order->{$p . '_postcode'} ?? '')),
    $order->{$p . '_country'} ?? null,
  ]);
  $pay = $order->payment_method ? __(ucfirst(str_replace('_', ' ', $order->payment_method))) : null;
  $due = $paid ? 0 : max(0, (float) $order->total - (float) ($order->refund_total ?? 0));
@endphp
<!doctype html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><style>
  @page{margin:0}
  *{margin:0;padding:0;box-sizing:border-box}
  /* page margins as body padding: Dompdf drops @page margin shorthands it can't parse, this it always honours */
  body{font-family:DejaVu Sans,sans-serif;color:#1a2230;font-size:11px;line-height:1.5;padding:30px 40px 80px}
  table{border-collapse:collapse}
  .hd{width:100%;margin-bottom:10px}
  .hd td{vertical-align:top}
  .logo img{height:44px}
  .brand-sub{font-size:9px;letter-spacing:2.2px;color:#8a94a6;margin-top:7px;text-transform:uppercase}
  .inv{text-align:right}
  .inv .t{font-size:24px;font-weight:bold;letter-spacing:3px;color:#0b1a33}
  .inv .n{font-size:12px;color:#1246ab;font-weight:bold;margin-top:2px}
  .rule{border-top:3px solid #1246ab;margin:8px 0 16px}
  .meta{width:100%;margin-bottom:18px;border:1px solid #e3e9f2;background:#f7f9fc}
  .meta td{padding:8px 12px;border-right:1px solid #e3e9f2;vertical-align:top}
  .meta td:last-child{border-right:0}
  .k{font-size:8.5px;text-transform:uppercase;letter-spacing:1px;color:#8a94a6;font-weight:bold;margin-bottom:2px}
  .v{font-size:11px;color:#0b1a33;font-weight:bold}
  .cols{width:100%;margin-bottom:20px}
  .cols td{width:33.33%;vertical-align:top;padding-right:16px;font-size:10.5px;line-height:1.6}
  .cols .k{margin-bottom:5px;padding-bottom:4px;border-bottom:1px solid #e3e9f2}
  table.items{width:100%;margin-bottom:14px}
  table.items th{background:#0b1a33;color:#fff;text-align:left;padding:8px 10px;font-size:9px;text-transform:uppercase;letter-spacing:.8px}
  table.items td{padding:8px 10px;border-bottom:1px solid #e3e9f2;font-size:10.5px;vertical-align:top}
  table.items tr.alt td{background:#fafbfd}
  table.items td.r,table.items th.r{text-align:right;white-space:nowrap}
  table.items td.c,table.items th.c{text-align:center;white-space:nowrap}
  .sku{font-family:DejaVu Sans Mono,monospace;font-size:9.5px;color:#3d4a5c}
  .cfg{color:#8a94a6;font-size:9px;margin-top:3px}
  table.bsheet{width:100%;margin:5px 0 2px;font-size:9px}
  table.bsheet td{padding:1px 6px 1px 0;border:0;color:#1a2230}
  table.bsheet td.k{color:#8a94a6;width:120px;white-space:nowrap;font-weight:normal;text-transform:none;letter-spacing:0;font-size:9px;margin:0}
  .bottom{width:100%}
  .bottom td{vertical-align:top}
  .totals{width:100%}
  .totals td{padding:5px 10px;font-size:10.5px}
  .totals td.r{text-align:right;white-space:nowrap}
  .totals tr.g td{border-top:2px solid #1246ab;font-weight:bold;font-size:13px;color:#1246ab;padding-top:8px}
  .totals tr.due td{background:#fdf0d5;color:#a15c00;font-weight:bold;font-size:12px}
  .totals tr.paidrow td{background:#dcfce7;color:#166534;font-weight:bold}
  .pill{display:inline-block;padding:3px 10px;border-radius:12px;font-size:9px;font-weight:bold;letter-spacing:1px}
  .paid{background:#dcfce7;color:#166534}.unpaid{background:#fdf0d5;color:#a15c00}
  .note{border:1px solid #e3e9f2;padding:9px 12px;font-size:10px;color:#3d4a5c;line-height:1.6;margin-right:18px}
  .note .k{margin-bottom:3px}
  .terms{margin-top:22px;padding:11px 14px;border:1px solid #e3e9f2;border-left:3px solid #1246ab;font-size:9.5px;color:#3d4a5c;line-height:1.7}
  .terms b{display:block;color:#0b1a33;font-size:10px;margin-bottom:2px}
  .terms a{color:#1246ab;text-decoration:none}
  .foot{position:fixed;bottom:22px;left:40px;right:40px;border-top:1px solid #e3e9f2;padding-top:9px;font-size:8.5px;color:#8a94a6;text-align:center;line-height:1.6}
  .foot .pg:after{content:counter(page)}
</style></head><body>
  <table class="hd"><tr>
    <td class="logo">
      @if($logo)<img src="{{ $logo }}" alt="Universal Driveshafts">@else<div style="font-size:24px;font-weight:bold;color:#1246ab">UNIVERSAL DRIVESHAFTS</div>@endif
      <div class="brand-sub">{{ __('Custom & replacement driveshafts · Built in the USA') }}</div>
    </td>
    <td class="inv">
      <div class="t">{{ __('INVOICE') }}</div>
      <div class="n">{{ $order->order_number }}</div>
      <div style="margin-top:6px"><span class="pill {{ $paid ? 'paid' : 'unpaid' }}">{{ mb_strtoupper(__(ucfirst($order->payment_status))) }}</span></div>
    </td>
  </tr></table>
  <div class="rule"></div>

  <table class="meta"><tr>
    <td><div class="k">{{ __('Invoice date') }}</div><div class="v">{{ \Carbon\Carbon::parse($order->created_at)->isoFormat('LL') }}</div></td>
    @if($paid && $order->paid_at)<td><div class="k">{{ __('Paid on') }}</div><div class="v">{{ \Carbon\Carbon::parse($order->paid_at)->isoFormat('LL') }}</div></td>@endif
    @if($pay)<td><div class="k">{{ __('Payment method') }}</div><div class="v">{{ $pay }}</div></td>@endif
    @if($order->po_number)<td><div class="k">{{ __('PO number') }}</div><div class="v">{{ $order->po_number }}</div></td>@endif
    @if($order->payment_method === 'net_terms' && $order->terms_due_at)<td><div class="k">{{ __('Terms') }}</div><div class="v">{{ __('Net :days, due :date', ['days' => \App\Services\B2bAccounts::termsDays(), 'date' => \Carbon\Carbon::parse($order->terms_due_at)->format('M j, Y')]) }}</div></td>@endif
    <td><div class="k">{{ __('Shipping') }}</div><div class="v">{{ $order->shipping_label ? __($order->shipping_label) : __('Standard') }}</div></td>
  </tr></table>

  <table class="cols"><tr>
    <td><div class="k">{{ __('From') }}</div><b>Universal Driveshafts LLC</b><br>Florida, USA<br>(813) 524-6491<br>Inquiry@universaldriveshafts.com<br>universaldriveshafts.com</td>
    <td><div class="k">{{ __('Bill To') }}</div><b>{{ $name('billing') }}</b>@if($order->billing_company)<br>{{ $order->billing_company }}@endif
      @foreach($addr('billing') as $l)<br>{{ $l }}@endforeach<br>{{ $order->email }}@if($order->phone)<br>{{ $order->phone }}@endif</td>
    <td><div class="k">{{ __('Ship To') }}</div>
      @if($order->shipping_method === 'pickup' || str_contains(strtolower((string) $order->shipping_label), 'pickup'))
        <b>{{ __('Local pickup') }}</b><br>{{ __('Universal Driveshafts, Florida') }}
      @else
        <b>{{ $name($shipTo) ?: $name('billing') }}</b>@if($shipTo === 'billing' && $order->billing_company)<br>{{ $order->billing_company }}@endif
        @foreach($addr($shipTo) ?: $addr('billing') as $l)<br>{{ $l }}@endforeach
      @endif
      @if($order->tracking_number)<br><span class="cfg">{{ __('Tracking') }}: {{ strtoupper((string) $order->tracking_carrier) }} {{ $order->tracking_number }}</span>@endif
    </td>
  </tr></table>

  <table class="items">
    <thead><tr><th style="width:52%">{{ __('Item') }}</th><th>{{ __('Part #') }}</th><th class="c">{{ __('Qty') }}</th><th class="r">{{ __('Unit price') }}</th><th class="r">{{ __('Amount') }}</th></tr></thead>
    <tbody>
    @foreach($items as $it)
      @php $meta = json_decode($it->meta ?? '{}', true); @endphp
      <tr class="{{ $loop->even ? 'alt' : '' }}">
        <td><b>{{ $it->name }}</b>
          @if(!empty($meta['summary']))<div class="cfg">{{ $meta['summary'] }}</div>@endif
          @if(!empty($meta['config']))
            <table class="bsheet">
              @foreach(\App\Support\BuildConfig::rows($meta['config']) as $r)
                <tr><td class="k">{{ __($r['label']) }}</td><td>{{ $r['value'] }}</td></tr>
              @endforeach
            </table>
          @endif</td>
        <td class="sku">{{ $it->sku ?: '—' }}</td>
        <td class="c">{{ (int) $it->qty }}</td>
        <td class="r">${{ number_format($it->price, 2) }}</td>
        <td class="r">${{ number_format($it->line_total, 2) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>

  <table class="bottom"><tr>
    <td style="width:56%">
      @if($order->customer_note || $order->ship_instructions)
        <div class="note">
          @if($order->ship_instructions)<div class="k">{{ __('Shipping instructions') }}</div><div>{{ $order->ship_instructions }}</div>@endif
          @if($order->customer_note)<div class="k" @if($order->ship_instructions) style="margin-top:6px" @endif>{{ __('Order note') }}</div><div>{{ $order->customer_note }}</div>@endif
        </div>
      @endif
    </td>
    <td style="width:44%">
      <table class="totals">
        <tr><td>{{ __('Subtotal') }}</td><td class="r">${{ number_format($order->subtotal, 2) }}</td></tr>
        @if($order->discount_total > 0)<tr><td>{{ __('Discount') }}@if($order->coupon_code) ({{ $order->coupon_code }})@elseif(($order->tier_discount ?? 0) > 0) ({{ \App\Services\B2bAccounts::label($order->b2b_tier) }})@endif</td><td class="r">-${{ number_format($order->discount_total, 2) }}</td></tr>@endif
        <tr><td>{{ __('Shipping') }}</td><td class="r">@if($order->shipping_total > 0)${{ number_format($order->shipping_total, 2) }}@else{{ __('Free') }}@endif</td></tr>
        @if(($order->tax_total ?? 0) > 0)<tr><td>{{ __('Sales tax') }}</td><td class="r">${{ number_format($order->tax_total, 2) }}</td></tr>@endif
        <tr class="g"><td>{{ __('Total') }}</td><td class="r">${{ number_format($order->total, 2) }}</td></tr>
        @if(($order->refund_total ?? 0) > 0)<tr><td>{{ __('Refunded') }}</td><td class="r">-${{ number_format($order->refund_total, 2) }}</td></tr>@endif
        @if($paid)<tr class="paidrow"><td>{{ __('Amount paid') }}</td><td class="r">${{ number_format((float) $order->total - (float) ($order->refund_total ?? 0), 2) }}</td></tr>
        @else<tr class="due"><td>{{ __('Amount due') }}</td><td class="r">${{ number_format($due, 2) }}</td></tr>@endif
      </table>
    </td>
  </tr></table>

  <div class="terms">
    <b>{{ __('Terms and Conditions of Sale') }} — <a href="https://universaldriveshafts.com/terms">universaldriveshafts.com/terms</a></b>
    {{ __('All returns must be pre-authorized with an RMA number within 14 days of delivery and are subject to a restocking fee.') }}<br>
    {{ __('Custom-built, special-order and drop-ship parts are non-returnable.') }}
    {{ __('Return policy') }}: <a href="https://universaldriveshafts.com/return-policy">universaldriveshafts.com/return-policy</a>
  </div>

  <div class="foot">{{ __('Thank you for your business! Questions about this invoice? Call (813) 524-6491 or email Inquiry@universaldriveshafts.com') }}<br>
    Universal Driveshafts LLC · Florida, USA · universaldriveshafts.com · {{ __('Page') }} <span class="pg"></span></div>
</body></html>

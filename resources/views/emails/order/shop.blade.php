@extends('emails.layout')
@php $pickup = $order->shipping_method === \App\Services\CartService::PICKUP_ID; $adminUrl = rtrim(config('app.url'), '/') . '/admin/orders/' . $order->id; @endphp
@section('body')
  @if($pickup) @include('emails.parts.notice', ['kind' => 'warn', 'title' => 'LOCAL PICKUP — DO NOT SHIP', 'text' => 'Customer collects at Odessa, ' . config('uds.pickup.hours') . '.' . ($order->payment_method === 'pay_at_pickup' ? ' PAY AT PICKUP: collect $' . number_format($order->total, 2) . ' before releasing the parts.' : '')]) @endif
  @if(($order->payment_status ?? 'unpaid') !== 'paid' && $order->payment_method !== 'pay_at_pickup') @include('emails.parts.notice', ['kind' => 'err', 'title' => 'Not paid yet', 'text' => 'Payment method: ' . ucfirst(str_replace('_', ' ', $order->payment_method)) . '. The customer has a payment link; do not ship until it is paid.']) @endif
  @include('emails.parts.button', ['label' => 'Open in admin', 'url' => $adminUrl, 'margin' => '4px 0 20px'])
  @include('emails.parts.kv', ['title' => 'Customer', 'rows' => ['Name' => trim($order->billing_first_name . ' ' . $order->billing_last_name), 'Company' => $order->billing_company, 'E-mail' => $order->email, 'Phone' => $order->phone, 'PO number' => $order->po_number, 'Signature' => $order->signature_required ? 'REQUIRED' : null, 'Source' => $order->source ?? 'web']])
  @include('emails.parts.items', ['items' => $items, 'showConfig' => true])
  @include('emails.parts.totals', ['order' => $order])
  <p style="margin:0 0 6px;font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#5c6879;font-weight:700;">{{ $pickup ? 'Customer address (not shipping)' : 'Ship to' }} · {{ $order->shipping_label }}</p>
  @include('emails.parts.address', ['order' => $order, 'which' => $order->ship_to_different ? 'shipping' : 'billing'])
  @if($order->customer_note || $order->ship_instructions)<div style="margin-top:16px;">@include('emails.parts.kv', ['rows' => ['Customer note' => $order->customer_note, 'Delivery instructions' => $order->ship_instructions], 'title' => null])</div>@endif
@endsection

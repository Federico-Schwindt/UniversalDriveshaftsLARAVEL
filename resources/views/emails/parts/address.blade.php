@php $w = $which ?? 'billing'; @endphp
<div style="font-size:14.5px;line-height:1.55;color:#141b28;">
  <b>{{ $order->{$w.'_first_name'} ?: $order->billing_first_name }} {{ $order->{$w.'_last_name'} ?: $order->billing_last_name }}</b>@if($order->billing_company)<br>{{ $order->billing_company }}@endif
  <br>{{ $order->{$w.'_address_1'} }}@if($order->{$w.'_address_2'}) {{ $order->{$w.'_address_2'} }}@endif
  <br>{{ $order->{$w.'_city'} }}@if($order->{$w.'_state'}), {{ $order->{$w.'_state'} }}@endif {{ $order->{$w.'_postcode'} }} {{ $order->{$w.'_country'} ?: $order->billing_country }}
  @if($order->phone)<br><span style="color:#5c6879;">{{ $order->phone }}</span>@endif
</div>

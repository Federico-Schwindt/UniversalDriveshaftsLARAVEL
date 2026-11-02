{{-- shown inside the label / freight modals: spending on postage or a truck for an order nobody paid for is usually a mistake --}}
@if(in_array($order->status, ['cancelled', 'failed'], true))
  <div class="flash flash--err" style="margin:0 0 12px">{!! ic('alert') !!}<span>This order is <b>{{ $order->status }}</b>. Don't buy a {{ $what }} unless you are sure it still ships.</span></div>
@elseif($order->payment_status === 'unpaid' && $order->payment_method !== 'net_terms')
  <div class="flash flash--warn" style="margin:0 0 12px">{!! ic('alert') !!}<span>This order is <b>unpaid</b> ({{ $order->payment_method ?: 'no method' }}). Make sure payment is arranged before buying a {{ $what }}.</span></div>
@endif

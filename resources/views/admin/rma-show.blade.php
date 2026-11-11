@extends('admin.layout')
@section('title', $rma->ref)
@section('crumb')<a href="/admin/rma">Returns / RMA</a>@endsection
@section('sub')<span class="{{ \App\Support\Rma::badge($rma->status) }}">{{ \App\Support\Rma::label($rma->status) }}</span> <span class="badge badge--plain">{{ \App\Support\Rma::typeLabel($rma->type) }}</span> filed {{ \Carbon\Carbon::parse($rma->created_at)->format('M j, Y g:ia') }}@endsection
@section('actions')
  @if($order)<a href="/admin/orders/{{ $order->id }}" class="btn btn--sm">{!! ic('orders') !!} Order {{ $order->order_number }}</a>@endif
  <a href="mailto:{{ $rma->email }}?subject={{ rawurlencode($rma->ref) }}" class="btn btn--sm">{!! ic('mail') !!} E-mail</a>
@endsection
@section('body')
@php
  $verbs = ['approved' => 'Approve', 'received' => 'Mark the part received', 'resolved' => 'Resolve', 'rejected' => 'Reject', 'withdrawn' => 'Withdrawn by the customer', 'new' => 'Reopen'];
  $next = \App\Support\Rma::NEXT[$rma->status] ?? [];
  $refunded = $refunds->sum('amount');
@endphp
<div class="grid grid--main">
  <div class="stack">
    <div class="card"><div class="card__head"><h2>Request</h2></div><div class="card__body">
      <dl class="dl">
        <dt>Type</dt><dd>{{ \App\Support\Rma::typeLabel($rma->type) }}</dd>
        <dt>Reason</dt><dd>{{ $rma->reason ?: '—' }}</dd>
        @if($rma->product)<dt>Part</dt><dd>{{ $rma->product }}</dd>@endif
        @if($rma->details)<dt>Customer's words</dt><dd style="white-space:pre-line">{{ $rma->details }}</dd>@endif
        @if($rma->resolution)<dt>Resolution</dt><dd>{{ \App\Support\Rma::RESOLUTIONS[$rma->resolution] ?? $rma->resolution }}@if($rma->refund_amount !== null) · {{ money($rma->refund_amount) }}@endif</dd>@endif
        @if($rma->decided_at)<dt>Decided</dt><dd>{{ \Carbon\Carbon::parse($rma->decided_at)->format('M j, Y g:ia') }}</dd>@endif
        @if($rma->received_at)<dt>Part received</dt><dd>{{ \Carbon\Carbon::parse($rma->received_at)->format('M j, Y g:ia') }}</dd>@endif
        @if($rma->closed_at)<dt>Closed</dt><dd>{{ \Carbon\Carbon::parse($rma->closed_at)->format('M j, Y g:ia') }}</dd>@endif
      </dl>
    </div></div>

    @if($next)
    <form method="post" action="/admin/rma/{{ $rma->id }}" class="card" data-rma-decide>@csrf
      <div class="card__head"><h2>Next step</h2></div>
      <div class="card__body stack">
        <div class="field"><label>Action</label>
          <div class="chips">@foreach($next as $i => $s)<label class="chip"><input type="radio" name="status" value="{{ $s }}" @checked($i === 0) required> {{ $verbs[$s] ?? \App\Support\Rma::label($s) }}</label>@endforeach</div>
        </div>
        <div class="grid grid--2" data-rma-resolve hidden>
          <div class="field"><label>Resolution</label><select class="select" name="resolution"><option value="">Choose…</option>@foreach(\App\Support\Rma::RESOLUTIONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
          <div class="field"><label>Amount (refund or credit)</label><input class="input" name="refund_amount" type="number" step="0.01" min="0" placeholder="{{ $order ? number_format((float) $order->total, 2, '.', '') : '0.00' }}"></div>
          @if($order)<p class="small muted" style="grid-column:1/-1">Money moves on the order: use <a href="/admin/orders/{{ $order->id }}">Refund on order {{ $order->order_number }}</a> first, then record it here.@if($refunded > 0) Already refunded on the order: <b>{{ money($refunded) }}</b>.@endif</p>@endif
        </div>
        <div class="field"><label>Message to the customer <span class="muted">(optional, sent as written)</span></label><textarea class="input" name="message" rows="3" placeholder="e.g. Approved. Please include the old yoke and the packing slip in the box."></textarea></div>
        <label class="flex small"><input type="checkbox" name="notify" value="1" checked> E-mail the customer about this step</label>
        <div><button class="btn btn--primary">Save</button></div>
      </div>
    </form>
    @endif

    @if($order)
    <div class="card"><div class="card__head"><h2>Order {{ $order->order_number }}</h2><span class="badge badge--{{ $order->status }}">{{ $order->status }}</span></div>
      <div class="card__body">
        <dl class="dl dl--tight mb">
          <dt>Placed</dt><dd>{{ \Carbon\Carbon::parse($order->created_at)->format('M j, Y') }}</dd>
          <dt>Total</dt><dd>{{ money($order->total) }}@if($refunded > 0) · <span class="muted">{{ money($refunded) }} refunded</span>@endif</dd>
          <dt>Payment</dt><dd>{{ $order->payment_method ?: '—' }} · {{ $order->payment_status ?: '—' }}</dd>
          @if($order->tracking_number)<dt>Shipped with</dt><dd class="mono">{{ $order->tracking_carrier }} {{ $order->tracking_number }}</dd>@endif
        </dl>
      </div>
      <div class="tbl-wrap"><table class="tbl tbl--compact"><thead><tr><th>Item</th><th>SKU</th><th class="num">Qty</th><th class="num">Price</th></tr></thead><tbody>
        @foreach($items as $it)<tr><td>{{ \Illuminate\Support\Str::limit($it->name, 80) }}</td><td class="mono small">{{ $it->sku }}</td><td class="num">{{ $it->qty }}</td><td class="num">{{ money($it->price ?? 0) }}</td></tr>@endforeach
      </tbody></table></div>
      @if($refunds->count())<div class="card__body"><b class="small">Refunds on this order</b>@foreach($refunds as $f)<div class="small">{{ money($f->amount) }} · {{ \Carbon\Carbon::parse($f->created_at)->format('M j, Y') }}@if($f->reason) · {{ $f->reason }}@endif</div>@endforeach</div>@endif
    </div>
    @else
    <div class="card"><div class="card__body small muted">No order is linked{{ $rma->order_number ? ' (the customer typed "' . $rma->order_number . '", which matches no order)' : '' }}.</div></div>
    @endif

    <div class="card"><div class="card__head"><h2>History</h2></div><div class="card__body">
      <ul class="timeline">
        @forelse($events as $e)
          <li class="{{ in_array($e->type, ['status', 'created'], true) ? 'done' : '' }}"><b>@if($e->type === 'email_failed')<span class="badge badge--err">Failed</span> @elseif($e->type === 'email')✉ @endif{{ $e->label }}</b>
            <small>{{ \Carbon\Carbon::parse($e->created_at)->format('M j, g:ia') }} · {{ $e->user_name ?: ($e->type === 'created' ? 'Customer' : 'System') }}</small>
            @if($e->detail)<div class="small" style="white-space:pre-line">{{ $e->detail }}</div>@endif</li>
        @empty<li>No history yet.</li>@endforelse
      </ul>
    </div></div>
  </div>

  <div class="stack">
    <div class="card"><div class="card__head"><h2>Customer</h2></div><div class="card__body">
      <dl class="dl dl--tight">
        <dt>Name</dt><dd>{{ $rma->name }}</dd>
        <dt>E-mail</dt><dd><a href="/admin/customers/{{ rawurlencode($rma->email) }}">{{ $rma->email }}</a></dd>
        @if($rma->phone)<dt>Phone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $rma->phone) }}">{{ $rma->phone }}</a></dd>@endif
      </dl>
      @if($others->count())<p class="small muted mt">Other requests from this customer:</p>@foreach($others as $o)<div class="small"><a class="mono" href="/admin/rma/{{ $o->id }}">{{ $o->ref }}</a> · {{ \App\Support\Rma::typeLabel($o->type) }} · {{ \App\Support\Rma::label($o->status) }}</div>@endforeach @endif
    </div></div>

    @if($rma->type !== 'cancel')
    <form method="post" action="/admin/rma/{{ $rma->id }}/tracking" class="card">@csrf
      <div class="card__head"><h2>Return shipment</h2></div>
      <div class="card__body stack">
        <div class="field"><label>Carrier</label><input class="input" name="return_carrier" value="{{ $rma->return_carrier }}" placeholder="UPS, USPS, FedEx…"></div>
        <div class="field"><label>Tracking number</label><input class="input mono" name="return_tracking" value="{{ $rma->return_tracking }}"></div>
        <div class="flex"><button class="btn btn--sm">Save</button>@if($order)<a class="btn btn--sm btn--ghost" href="/admin/orders/{{ $order->id }}#labels">{!! ic('truck') !!} Buy a return label on the order</a>@endif</div>
        @if($labels->count())<div class="small muted">Labels on the order: @foreach($labels as $l)<span class="mono">{{ $l->tracking_code ?? $l->tracking_number ?? ('#' . $l->id) }}</span>@if(!$loop->last), @endif @endforeach</div>@endif
      </div>
    </form>
    @endif

    <form method="post" action="/admin/rma/{{ $rma->id }}/note" class="card">@csrf
      <div class="card__head"><h2>Internal note</h2></div>
      <div class="card__body stack"><textarea class="input" name="body" rows="3" required placeholder="Only the team sees this."></textarea><div><button class="btn btn--sm">Add note</button></div></div>
    </form>
  </div>
</div>
<script>
(function(){
  /* resolution fields only when "Resolve" is the chosen step */
  var f = document.querySelector('[data-rma-decide]'); if (!f) return;
  var box = f.querySelector('[data-rma-resolve]');
  function sync(){ var v = (f.querySelector('input[name=status]:checked') || {}).value; box.hidden = v !== 'resolved'; box.querySelector('select').required = v === 'resolved'; }
  f.addEventListener('change', sync); sync();
})();
</script>
@endsection

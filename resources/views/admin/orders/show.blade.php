@extends('admin.layout')
@section('title', $order->order_number)
@section('crumb')<a href="/admin/orders">Orders</a> › {{ \Carbon\Carbon::parse($order->created_at)->format('M j, Y g:i A') }}@endsection
@php $isPickup = $order->shipping_method === \App\Services\CartService::PICKUP_ID; $shipTo = $order->ship_to_different ? 'shipping' : 'billing'; $remaining = round((float)$order->total - (float)$order->refund_total, 2); @endphp
@section('sub')
  <span class="badge badge--{{ $order->status }}">{{ ucfirst($order->status) }}</span>
  <span class="badge badge--{{ $order->payment_status }} badge--plain">{{ \App\Http\Controllers\Admin\OrdersController::PAY[$order->payment_status] ?? ucfirst($order->payment_status) }}</span>
  @if($isPickup)<span class="badge badge--warn">Local pickup</span>@endif
  @if($order->source && $order->source!=='web')<span class="badge badge--plain">{{ ucfirst($order->source) }}</span>@endif
  @if($order->tags) @foreach(explode(',', $order->tags) as $t)<span class="pill">{{ trim($t) }}</span>@endforeach @endif
@endsection
@section('actions')
  <a href="/admin/orders/{{ $order->id }}/invoice" target="_blank" class="btn btn--sm">{!! ic('download') !!} Invoice</a>
  <a href="/admin/orders/{{ $order->id }}/packing-slip" target="_blank" class="btn btn--sm">{!! ic('print') !!} Packing slip</a>
  <div class="adm-menu" data-menu>
    <button type="button" class="btn btn--sm" data-menu-btn>{!! ic('dots') !!} More</button>
    <div class="adm-menu__pop adm-menu__pop--right" hidden>
      <a href="#" data-post="/admin/orders/{{ $order->id }}/email" data-fields='{"which":"confirmation"}'>{!! ic('mail') !!} Resend confirmation</a>
      <a href="#" data-post="/admin/orders/{{ $order->id }}/email" data-fields='{"which":"invoice"}'>{!! ic('send') !!} E-mail invoice PDF</a>
      @if($order->tracking_number)<a href="#" data-post="/admin/orders/{{ $order->id }}/email" data-fields='{"which":"shipped"}'>{!! ic('truck') !!} Resend shipping e-mail</a>@endif
      <a href="/admin/orders/new?reorder={{ $order->id }}">{!! ic('copy') !!} Duplicate as new order</a>
      <a href="{{ \App\Support\OrderEvents::statusUrl($order, false) }}" target="_blank">{!! ic('eye') !!} Customer status page</a>
      @if($order->tracking_number && !$order->delivered_at)<a href="#" data-post="/admin/orders/{{ $order->id }}/delivered" data-confirm="Mark this order delivered and notify the customer?">{!! ic('check') !!} Mark delivered</a>@endif
      <div class="adm-menu__sep"></div>
      @if($remaining > 0 && adm_can('refunds'))<button type="button" data-modal="refund" class="danger">{!! ic('undo') !!} Refund…</button>@endif
    </div>
  </div>
@endsection
@section('body')
@foreach($rmas ?? [] as $rq)
  <div class="flash {{ in_array($rq->status, \App\Support\Rma::OPEN, true) ? 'flash--warn' : 'flash--info' }}">{!! ic('undo') !!}<div><b>{{ \App\Support\Rma::typeLabel($rq->type) }} request <a href="/admin/rma/{{ $rq->id }}" class="mono">{{ $rq->ref }}</a></b> · <span class="{{ \App\Support\Rma::badge($rq->status) }}">{{ \App\Support\Rma::label($rq->status) }}</span> · filed {{ \Carbon\Carbon::parse($rq->created_at)->format('M j') }}@if($rq->reason) · {{ $rq->reason }}@endif <a href="/admin/rma/{{ $rq->id }}">Open the request</a></div></div>
@endforeach
@if($isPickup)<div class="flash flash--warn">{!! ic('alert') !!}<div><b>Local pickup — do not ship.</b> Customer collects at Odessa, {{ config('uds.pickup.hours') }}. @if($order->payment_method==='pay_at_pickup') <b style="color:var(--err)">Pay at pickup: collect {{ money($order->total) }} before releasing the parts.</b>@endif</div></div>@endif
@if($order->customer_note)<div class="flash flash--info">{!! ic('info') !!}<div><b>Customer note:</b> {{ $order->customer_note }}</div></div>@endif
@if($order->ship_instructions || $order->signature_required)<div class="flash flash--info">{!! ic('truck') !!}<div>@if($order->signature_required)<b>Signature required.</b> @endif{{ $order->ship_instructions }}</div></div>@endif

<div class="grid grid--main">
  <div class="stack">
    {{-- items --}}
    <div class="card">
      <div class="card__head"><h2>Items <span class="muted">({{ $items->sum('qty') }})</span></h2><button type="button" class="btn btn--sm" data-modal="add-item">{!! ic('plus') !!} Add item</button></div>
      <div class="tbl-wrap"><table class="tbl tbl--wide">
        <thead><tr><th class="w-img"></th><th>Product</th><th class="right">Price</th><th class="center">Qty</th><th class="right">Total</th><th></th></tr></thead>
        <tbody>
        @foreach($items as $it)
          <tr>
            <td class="w-img">@if($it->image)<img src="{{ $it->image }}" class="thumb" alt="">@endif</td>
            <td><div class="title">{{ $it->name }}</div>
              <div class="small"><span class="mono muted">{{ $it->sku ?: '—' }}</span> <span class="badge badge--plain tiny">{{ str_replace('_',' ',$it->type) }}</span>
                @if(!empty($it->part_number))
                  @if($specs[$it->part_number] ?? null)<a href="/admin/driveshafts/{{ rawurlencode($it->part_number) }}" class="badge badge--{{ $specs[$it->part_number]->status==='verified'?'ok':'warn' }}">{!! ic('ruler','ic--s') !!} Build spec {{ $specs[$it->part_number]->status }}</a>
                  @else<a href="/admin/driveshafts/{{ rawurlencode($it->part_number) }}" class="badge badge--err">{!! ic('ruler','ic--s') !!} No build spec yet</a>@endif
                @endif
              </div>
              @if(!empty($it->metaArr['summary']))<div class="small" style="color:var(--teal)">{{ $it->metaArr['summary'] }}</div>@endif
              @if(!empty($it->metaArr['vehicle']))<div class="small muted">Vehicle: {{ is_array($it->metaArr['vehicle']) ? implode(' ', $it->metaArr['vehicle']) : $it->metaArr['vehicle'] }}</div>@endif
              @if(!empty($it->metaArr['config']))<details class="small" style="margin-top:4px"><summary style="cursor:pointer;color:var(--blue)">Build sheet</summary><dl class="dl dl--tight mt">@foreach(\App\Support\BuildConfig::rows($it->metaArr['config']) as $rw)<dt>{{ $rw['label'] }}</dt><dd>{{ $rw['value'] }}</dd>@endforeach</dl></details>@endif
            </td>
            <td class="right num">{{ money($it->price) }}</td>
            <td class="center num">{{ $it->qty }}</td>
            <td class="right num"><b>{{ money($it->line_total) }}</b></td>
            <td class="actions">
              <button type="button" class="btn btn--xs btn--icon" title="Edit line" data-modal="edit-item-{{ $it->id }}">{!! ic('edit','ic--s') !!}</button>
              <form method="post" action="/admin/orders/{{ $order->id }}/items" style="display:inline" data-confirm="Remove {{ $it->sku ?: $it->name }} from this order?">@csrf<input type="hidden" name="action" value="remove"><input type="hidden" name="item_id" value="{{ $it->id }}"><button class="btn btn--xs btn--icon btn--danger" title="Remove">{!! ic('trash','ic--s') !!}</button></form>
              <template data-modal-src="edit-item-{{ $it->id }}">
                <form method="post" action="/admin/orders/{{ $order->id }}/items">@csrf<input type="hidden" name="action" value="update"><input type="hidden" name="item_id" value="{{ $it->id }}">
                  <div class="modal__head"><h3>Edit line — {{ $it->sku ?: $it->name }}</h3><button type="button" class="btn btn--xs btn--icon" data-modal-close>{!! ic('x') !!}</button></div>
                  <div class="modal__body form-grid"><div class="field"><label>Quantity</label><input class="input" type="number" name="qty" min="1" value="{{ $it->qty }}"></div><div class="field"><label>Unit price</label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" min="0" name="price" value="{{ $it->price }}"></div></div></div>
                  <div class="modal__foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn--primary">Save</button></div>
                </form>
              </template>
            </td>
          </tr>
        @endforeach
        </tbody></table></div>
      <div class="card__foot" style="display:block">
        <div style="max-width:380px;margin-left:auto">
          <div class="kv"><span>Subtotal</span><b>{{ money($order->subtotal) }}</b></div>
          @if($order->discount_total>0)<div class="kv"><span>Discount @if($order->coupon_code)<span class="mono muted">({{ $order->coupon_code }})</span>@elseif(($order->tier_discount ?? 0) > 0)<span class="muted">({{ \App\Services\B2bAccounts::label($order->b2b_tier) }})</span>@endif</span><b>-{{ money($order->discount_total) }}</b></div>@endif
          <div class="kv"><span>Shipping <span class="muted small">{{ $order->shipping_label }}</span></span><b>{{ money($order->shipping_total) }}</b></div>
          @if($order->tax_total>0)<div class="kv"><span>Tax</span><b>{{ money($order->tax_total) }}</b></div>@endif
          <div class="kv kv--total"><span>Total</span><b>{{ money($order->total) }}</b></div>
          @if($order->refund_total>0)<div class="kv" style="color:var(--err)"><span>Refunded</span><b>-{{ money($order->refund_total) }}</b></div>@endif
          <div class="right mt"><button type="button" class="btn btn--xs" data-modal="totals">{!! ic('edit','ic--s') !!} Edit shipping / discount / tax</button></div>
        </div>
      </div>
    </div>

    {{-- customer + addresses --}}
    <div class="grid grid--2">
      <div class="card">
        <div class="card__head"><h2>Customer</h2>@if($customer)<a href="/admin/customers/{{ $customer->id }}" class="btn btn--ghost btn--sm">Profile</a>@else<a href="/admin/customers/{{ urlencode($order->email) }}" class="btn btn--ghost btn--sm">Guest history</a>@endif</div>
        <div class="card__body">
          <div class="flex gap-2 mb"><span class="avatar">{{ strtoupper(substr($order->billing_first_name,0,1)) }}</span><div><b>{{ $order->billing_first_name }} {{ $order->billing_last_name }}</b>@if($order->billing_company)<br><span class="muted small">{{ $order->billing_company }}</span>@endif<br><span class="muted small">{{ $custStats->n }} order{{ $custStats->n==1?'':'s' }} · {{ money($custStats->t) }} lifetime</span>@if($customer && $customer->wholesale_status==='approved') <span class="badge badge--ok tiny">Wholesale {{ rtrim(rtrim($customer->wholesale_discount,'0'),'.') }}%</span>@endif</div></div>
          <dl class="dl dl--tight">
            <dt>E-mail</dt><dd><a href="mailto:{{ $order->email }}">{{ $order->email }}</a> <button type="button" class="btn btn--xs btn--icon" data-copy="{{ $order->email }}" title="Copy">{!! ic('copy','ic--s') !!}</button></dd>
            <dt>Phone</dt><dd>@if($order->phone)<a href="tel:{{ preg_replace('/[^0-9+]/','',$order->phone) }}">{{ $order->phone }}</a> <span class="badge badge--plain tiny {{ $order->sms_opt_in?'badge--ok':'' }}">SMS {{ $order->sms_opt_in?'on':'off' }}</span>@else—@endif</dd>
            @if($order->po_number)<dt>PO #</dt><dd class="mono">{{ $order->po_number }}</dd>@endif
            <dt>IP</dt><dd class="mono muted">{{ $order->ip ?: '—' }}</dd>
          </dl>
          @if($customerOrders->count())<div class="mt"><div class="label mb">Other orders</div>@foreach($customerOrders as $co)<a href="/admin/orders/{{ $co->id }}" class="pill" style="margin:0 4px 4px 0">{{ $co->order_number }} <span class="muted">{{ money($co->total) }}</span></a>@endforeach</div>@endif
        </div>
      </div>
      <div class="card">
        <div class="card__head"><h2>Addresses</h2></div>
        <div class="card__body stack">
          <div><div class="flex" style="justify-content:space-between"><span class="label">Billing</span><button type="button" class="btn btn--xs" data-modal="addr-billing">{!! ic('edit','ic--s') !!} Edit</button></div>
            <div class="addr">{{ $order->billing_first_name }} {{ $order->billing_last_name }}@if($order->billing_company) · {{ $order->billing_company }}@endif{{ "\n" }}{{ $order->billing_address_1 }} {{ $order->billing_address_2 }}
{{ $order->billing_city }}{{ $order->billing_state ? ', '.$order->billing_state : '' }} {{ $order->billing_postcode }} {{ $order->billing_country }}</div></div>
          <div><div class="flex" style="justify-content:space-between"><span class="label">Ship to @if(!$order->ship_to_different)<span class="muted">(same as billing)</span>@endif</span><button type="button" class="btn btn--xs" data-modal="addr-shipping">{!! ic('edit','ic--s') !!} Edit</button></div>
            @if($isPickup)<div class="addr muted">Local pickup — Odessa, FL</div>
            @else<div class="addr">{{ $order->{$shipTo.'_first_name'} }} {{ $order->{$shipTo.'_last_name'} }}
{{ $order->{$shipTo.'_address_1'} }} {{ $order->{$shipTo.'_address_2'} }}
{{ $order->{$shipTo.'_city'} }}{{ $order->{$shipTo.'_state'} ? ', '.$order->{$shipTo.'_state'} : '' }} {{ $order->{$shipTo.'_postcode'} }} {{ $order->{$shipTo.'_country'} ?: $order->billing_country }}</div>
            <a class="small" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query={{ urlencode(trim($order->{$shipTo.'_address_1'}.' '.$order->{$shipTo.'_city'}.' '.$order->{$shipTo.'_state'}.' '.$order->{$shipTo.'_postcode'})) }}">Map ↗</a>@endif
          </div>
        </div>
      </div>
    </div>

    {{-- notes --}}
    <div class="card">
      <div class="card__head"><h2>Notes</h2></div>
      <div class="card__body stack">
        <form method="post" action="/admin/orders/{{ $order->id }}/note" class="stack" style="gap:8px">@csrf
          <textarea class="input" name="body" rows="2" placeholder="Add a note… internal notes stay private; customer notes are e-mailed and shown on the status page." required></textarea>
          <div class="flex flex-wrap"><label class="check"><input type="radio" name="kind" value="internal" checked> Internal</label><label class="check"><input type="radio" name="kind" value="customer"> Send to customer</label><button class="btn btn--sm btn--primary" style="margin-left:auto">Add note</button></div>
        </form>
        @foreach($notes as $n)
          <div class="note {{ $n->kind==='internal'?'note--internal':'' }}"><div class="note__meta"><span><b>{{ $n->user_name ?: 'System' }}</b> · {{ $n->kind==='customer' ? 'sent to customer' : 'internal' }} · {{ \Carbon\Carbon::parse($n->created_at)->format('M j, g:ia') }}</span><form method="post" action="/admin/orders/{{ $order->id }}/note/{{ $n->id }}/delete" data-confirm="Delete this note?">@csrf<button class="btn btn--xs btn--icon" title="Delete">{!! ic('x','ic--s') !!}</button></form></div>{!! nl2br(e($n->body)) !!}</div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- side --}}
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2>Status</h2></div>
      <form method="post" action="/admin/orders/{{ $order->id }}" class="card__body stack">@csrf
        <div class="field"><label>Order status</label><select class="select" name="status">@foreach(\App\Http\Controllers\Admin\OrdersController::STATUSES as $k=>$l)<option value="{{ $k }}" @selected($order->status===$k)>{{ $l }}</option>@endforeach</select></div>
        {{-- refund states move money only through the Refund modal, so they are shown, never picked --}}
        @if(in_array($order->payment_status, ['unpaid', 'paid'], true))
        <div class="field"><label>Payment</label><select class="select" name="payment_status">@foreach(\App\Http\Controllers\Admin\OrdersController::MANUAL_PAY as $k=>$l)<option value="{{ $k }}" @selected($order->payment_status===$k)>{{ $l }}</option>@endforeach</select></div>
        @else
        <div class="field"><label>Payment</label><div><span class="badge badge--{{ $order->payment_status }}">{{ \App\Http\Controllers\Admin\OrdersController::PAY[$order->payment_status] ?? ucfirst(str_replace('_', ' ', $order->payment_status)) }}</span> <span class="small muted">· {{ money($order->refund_total) }} refunded @if(adm_can('refunds') && $remaining > 0) — use <a href="#" data-modal="refund">Refund…</a> for more @endif</span></div></div>
        @endif
        <div class="field"><label>Payment method</label><select class="select" name="payment_method">@if(!array_key_exists((string) $order->payment_method, \App\Http\Controllers\Admin\OrdersController::PAY_METHODS))<option value="{{ $order->payment_method }}" selected>{{ $order->payment_method ? ucfirst(str_replace('_', ' ', $order->payment_method)) : '—' }}</option>@endif @foreach(\App\Http\Controllers\Admin\OrdersController::PAY_METHODS as $k=>$l)<option value="{{ $k }}" @selected($order->payment_method===$k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>Tags</label><div class="tagbox" data-tags><input type="hidden" name="tags" value="{{ $order->tags }}"><input type="text" placeholder="add tag…"></div></div>
        @if($isPickup)<label class="check"><input type="checkbox" name="notify" value="1" checked> Notify customer when marked completed (ready for pickup)</label>@endif
        <button class="btn btn--primary btn--block">Save</button>
        @if($order->paid_at)<div class="muted small center">Paid {{ \Carbon\Carbon::parse($order->paid_at)->format('M j, Y g:ia') }}</div>@endif
      </form>
    </div>

    @if(\App\Support\PayLink::payable($order))
    @php $payUrl = \App\Support\PayLink::url($order); @endphp
    <div class="card" style="border-color:var(--warn-line)">
      <div class="card__head"><h2>{!! ic('dollar') !!} Collect {{ money($remaining) }}</h2>@if($order->pay_link_sent_at)<span class="muted small">link sent {{ \Carbon\Carbon::parse($order->pay_link_sent_at)->diffForHumans() }}</span>@endif</div>
      <form method="post" action="/admin/orders/{{ $order->id }}/pay-link" class="card__body stack">@csrf
        <div class="field"><label>Payment link <span class="muted">(charges the current balance — edit the order first if it changes)</span></label><div class="input-group"><input class="input input--sm mono" value="{{ $payUrl }}" readonly onclick="this.select()"><button type="button" class="btn btn--sm" data-copy="{{ $payUrl }}">{!! ic('copy','ic--s') !!} Copy</button></div></div>
        <div class="field"><input class="input input--sm" name="note" placeholder="Optional note to include in the e-mail"></div>
        @if(\App\Services\SmsService::on())<label class="check"><input type="checkbox" name="sms" value="1" @checked($order->sms_opt_in && $order->phone) @disabled(!$order->phone)> Also text it to {{ $order->phone ?: 'no phone on file' }}</label>@endif
        <div class="flex"><button class="btn btn--primary btn--sm">{!! ic('send') !!} E-mail payment link</button><a href="{{ $payUrl }}" target="_blank" class="btn btn--ghost btn--sm">Preview page ↗</a></div>
        @if(!\App\Support\PayLink::stripeReady())<div class="small" style="color:var(--warn)">{!! ic('alert','ic--s') !!} Stripe is off on this server (STRIPE_ENABLED) — the page shows the order and asks the customer to call until it is enabled.</div>@endif
      </form>
    </div>
    @endif
    @if(!$isPickup)
    <div class="card">
      <div class="card__head"><h2>Shipping</h2>@if($order->tracking_number)<a target="_blank" rel="noopener" href="{{ \App\Support\OrderEvents::carrierUrl($order->tracking_carrier, $order->tracking_number) }}" class="btn btn--ghost btn--sm">Track ↗</a>@endif</div>
      <form method="post" action="/admin/orders/{{ $order->id }}/tracking" class="card__body stack">@csrf
        @if($pack)<div class="small muted">{{ $pack['packages'] }} package{{ $pack['packages']==1?'':'s' }} · {{ rtrim(rtrim(number_format($pack['weight_lb'],1),'0'),'.') }} lb — {{ $pack['note'] ?? '' }}</div>@endif
        <div class="field"><label>Carrier</label><select class="select" name="tracking_carrier">@foreach(['UPS','FedEx','USPS','DHL','Freight'] as $c)<option @selected(($order->tracking_carrier ?: 'UPS')===$c)>{{ $c }}</option>@endforeach</select></div>
        <div class="field"><label>Service <span class="muted">(optional)</span></label><input class="input" name="tracking_service" value="{{ $order->tracking_service }}" placeholder="Ground, 2nd Day Air…"></div>
        <div class="field"><label>Tracking number</label><input class="input mono" name="tracking_number" value="{{ $order->tracking_number }}" placeholder="1Z…"></div>
        <label class="check"><input type="checkbox" name="notify" value="1" checked> E-mail + text the customer</label>
        <button class="btn btn--dark btn--block">{!! ic('truck') !!} {{ $order->tracking_number ? 'Update tracking' : 'Mark shipped' }}</button>
        <div class="small muted">
          Buy the label under <b>Labels</b> below and the tracking fills itself in.
          @if($order->delivered_at)<br>Delivered {{ \Carbon\Carbon::parse($order->delivered_at)->format('M j, g:ia') }}. @elseif($order->last_tracking_status)<br>Carrier: {{ $order->last_tracking_status }} ({{ \Carbon\Carbon::parse($order->last_tracking_checked_at)->diffForHumans() }}) @endif
        </div>
      </form>
    </div>
    @endif

    @if(!$isPickup)
    <div class="card">
      <div class="card__head"><h2>Labels</h2>
        @if($ship['enabled'] && ($ship['test'] || adm_can('labels')))<button type="button" class="btn btn--primary btn--sm" data-modal="label">{!! ic('plus') !!} Create label</button>@endif
      </div>
      <div class="card__body stack">
        @if(!$ship['enabled'])
          <div class="small" style="color:var(--err)">No shipping account is configured, so labels cannot be bought here.</div>
        @else
          @if($ship['test'])<div class="flash flash--info" style="margin:0">{!! ic('alert') !!}<span><b>{{ strtoupper(app()->environment()) }}</b> — @if($ship['test_labels'])labels created here are free {{ $ship['provider'] }} <b>test</b> labels. They are not real postage and cannot be used to ship.@else{{ $ship['provider'] }} cannot make free test labels on this key, so a label bought here is <b>real postage</b> and is charged. Voiding it refunds the postage.@endif</span></div>@endif
          @forelse($labels as $l)
            <div class="flex" style="justify-content:space-between;gap:10px;align-items:flex-start;{{ $l->voided_at ? 'opacity:.55' : '' }}">
              <div style="min-width:0">
                <b>{{ $l->kind === 'return' ? 'Return' : 'Outbound' }}</b>
                <span class="muted small">{{ $l->service_label ?: $l->service_code }}</span>
                @if($l->is_test)<span class="pill">TEST</span>@endif
                @if($l->voided_at)<span class="pill">Voided</span>@endif
                <div class="small mono">{{ $l->tracking_number ?: 'no tracking' }}</div>
                <div class="small muted">
                  {{ rtrim(rtrim(number_format((float)$l->weight_lb, 2), '0'), '.') }} lb
                  @if($l->length_in) · {{ rtrim(rtrim(number_format((float)$l->length_in,1),'0'),'.') }}×{{ rtrim(rtrim(number_format((float)$l->width_in,1),'0'),'.') }}×{{ rtrim(rtrim(number_format((float)$l->height_in,1),'0'),'.') }} in @endif
                  · {{ money((float) $l->cost) }} · {{ \Carbon\Carbon::parse($l->created_at)->format('M j, g:ia') }}
                </div>
              </div>
              <div class="flex" style="gap:6px;flex:0 0 auto">
                @if($l->pdf_path)<a class="btn btn--xs" href="/admin/labels/{{ $l->id }}/pdf" target="_blank" rel="noopener">{!! ic('print') !!} PDF</a>@endif
                @if(!$l->voided_at)<a class="btn btn--xs" href="#" data-post="/admin/labels/{{ $l->id }}/void" data-confirm="Void this label with the carrier? The postage is refunded.">Void</a>@endif
              </div>
            </div>
          @empty
            <div class="small muted">No labels yet. Measure the box, then create one — the tracking number goes onto the order automatically.</div>
          @endforelse
        @endif
      </div>
    </div>
    @endif

    @if(!$isPickup && $freight['show'])
    <div class="card">
      <div class="card__head"><h2>Freight (LTL)</h2>
        @if($freight['enabled'] && adm_can('purchases'))<button type="button" class="btn btn--primary btn--sm" data-modal="freight">{!! ic('truck') !!} Book freight</button>@endif
      </div>
      <div class="card__body stack">
        @if(!$freight['enabled'])
          <div class="small" style="color:var(--err)">No Warp key is configured, so freight cannot be booked here.</div>
        @else
          @if($freight['test'])<div class="flash flash--info" style="margin:0">{!! ic('alert') !!}<span><b>{{ strtoupper(app()->environment()) }}</b> — @if($freight['test_key'])freight booked here is a <b>Warp sandbox</b> booking: no charge and no truck.@else no Warp sandbox key is set, so a booking here would be <b>real</b>.@endif</span></div>@endif
          @if($freight['delivery_answer'])<div class="small muted">Customer said at checkout: <b>{{ ['dock'=>'business with a loading dock','liftgate'=>'business without a dock (liftgate)','residential'=>'home / residential (liftgate)'][$freight['delivery_answer']] ?? $freight['delivery_answer'] }}</b>.</div>@endif
          @forelse($freight['bookings'] as $fb)
            <div class="flex" style="justify-content:space-between;gap:10px;align-items:flex-start;{{ $fb->status === 'cancelled' ? 'opacity:.55' : '' }}">
              <div style="min-width:0">
                <b>{{ $fb->carrier ?: 'LTL' }}</b> <span class="muted small">{{ $fb->service_level }}</span>
                @if($fb->is_test)<span class="pill">SANDBOX</span>@endif
                <span class="pill">{{ str_replace('_', ' ', ucfirst($fb->status)) }}</span>
                <div class="small mono">{{ $fb->tracking_number ?: $fb->shipment_number ?: '—' }}</div>
                <div class="small muted">
                  {{ $fb->pallets }} pallet{{ $fb->pallets == 1 ? '' : 's' }} · {{ rtrim(rtrim(number_format((float) $fb->weight_lb, 1), '0'), '.') }} lb each
                  · {{ money((float) $fb->price) }}
                  · pickup {{ $fb->pickup_date ? \Carbon\Carbon::parse($fb->pickup_date)->format('D M j') : '—' }} {{ $fb->pickup_ready }}–{{ $fb->pickup_close }} EST
                </div>
                @if($fb->status_checked_at)<div class="small muted">Checked {{ \Carbon\Carbon::parse($fb->status_checked_at)->diffForHumans() }}</div>@endif
              </div>
              <div class="flex" style="gap:6px;flex:0 0 auto;flex-wrap:wrap;justify-content:flex-end">
                @if($fb->bol_url)<a class="btn btn--xs" href="{{ $fb->bol_url }}" target="_blank" rel="noopener">{!! ic('print') !!} BOL</a>@endif
                @foreach((json_decode((string) $fb->raw, true)['documents'] ?? []) as $doc)
                  @if(($doc['url'] ?? '') !== $fb->bol_url)<a class="btn btn--xs" href="{{ $doc['url'] }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($doc['type'], 14) }}</a>@endif
                @endforeach
                <a class="btn btn--xs" href="#" data-post="/admin/freight/{{ $fb->id }}/refresh">{!! ic('history') !!} Status</a>
              </div>
            </div>
          @empty
            <div class="small muted">Not booked yet. Confirm the pallet, pick a carrier and a pickup window — the PRO / tracking number goes onto the order.</div>
          @endforelse
          @if($freight['bookings']->count())
            <div class="small muted">To cancel or change a booking, Warp handles it by phone or e-mail — <a href="tel:+12132671373">+1 213 267 1373</a> (dispatch, if pickup is close) or <a href="mailto:support@wearewarp.com">support@wearewarp.com</a>. Their API does not allow cancelling.</div>
          @endif
        @endif
      </div>
    </div>
    @endif

    @if($ptiDs)
    @php $ptiCount = collect($ptiDs['lines'])->whereNotNull('pti_part')->count(); $ptiTubing = collect($ptiDs['lines'])->where('tubing', true)->count(); @endphp
    @if($ptiTubing < count($ptiDs['lines']) || $ptiDs['rows']->count())
    <div class="card">
      <div class="card__head"><h2>Powertrain Industries</h2>
        <button type="button" class="btn btn--primary btn--sm" data-modal="pti-dropship" @disabled(!$ptiCount || !adm_can('purchases')) @if(!adm_can('purchases')) title="Your role cannot place supplier orders" @endif>{!! ic('truck') !!} DROPSHIP FROM PTI</button>
      </div>
      <div class="card__body stack">
        @forelse($ptiDs['rows'] as $ds)
          @php $dl = json_decode($ds->lines, true) ?: []; @endphp
          <div class="flex" style="justify-content:space-between;gap:10px;align-items:flex-start">
            <div style="min-width:0">
              <b>{{ $ds->status === 'placed' ? 'PTI order #' . ($ds->pti_order_id ?: '?') : 'Prepared, not ordered' }}</b>
              <span class="pill">{{ ucfirst($ds->status) }}</span>
              <div class="small">{{ implode(', ', array_map(fn ($l) => $l['qty'] . ' × ' . $l['part_number'], $dl)) }}</div>
              <div class="small muted">
                @if($ds->status === 'placed'){{ $ds->shipping_name }} · {{ \App\Services\PtiDropship::STORES[$ds->ship_from_store_id] ?? '' }}@if($ds->pti_total) · parts {{ money((float) $ds->pti_total) }}@endif @if($ds->shipping_cost !== null) + ship {{ money((float) $ds->shipping_cost) }}@endif @if($ds->card_last_four) · card …{{ $ds->card_last_four }}@endif · {{ \Carbon\Carbon::parse($ds->placed_at)->format('M j, g:ia') }}@else Prepared {{ \Carbon\Carbon::parse($ds->created_at)->diffForHumans() }} — finish or cancel it in the dropship window.@endif
              </div>
              @if($ds->tracking_number)<div class="small mono">{{ $ds->tracking_number }}</div>@elseif($ds->status === 'placed')<div class="small muted">No tracking from PTI yet @if($ds->tracking_checked_at)(checked {{ \Carbon\Carbon::parse($ds->tracking_checked_at)->diffForHumans() }})@endif — checked every hour.</div>@endif
            </div>
            @if($ds->status === 'placed')<a class="btn btn--xs" href="#" data-post="/admin/pti-dropship/{{ $ds->id }}/tracking">{!! ic('history') !!} Tracking</a>@endif
          </div>
        @empty
          <div class="small muted">@if($ptiCount){{ $ptiCount }} of {{ count($ptiDs['lines']) - $ptiTubing }} line(s) can ship straight from PTI to the customer @if($ptiTubing)(tubing ships from our shop)@endif. Nothing is ordered until you review it and click <b>Place order at PTI</b>.@else None of these parts is linked to a PTI part number, so nothing here can be dropshipped.@endif</div>
        @endforelse
      </div>
    </div>
    @endif
    @endif

    <div class="card">
      <div class="card__head"><h2>Timeline</h2></div>
      <div class="card__body">
        <ul class="timeline">
          @forelse($events as $ev)
            @php $isNotify = str_starts_with($ev->type, 'notify:'); @endphp
            <li class="{{ $isNotify ? '' : 'done' }}"><b class="{{ $isNotify?'muted':'' }}">{{ $isNotify ? '✉ ' : '' }}{{ $ev->label }}</b><small>{{ \Carbon\Carbon::parse($ev->created_at)->format('M j, g:ia') }}@if($ev->detail) · {{ $ev->detail }}@endif</small></li>
          @empty<li>No events recorded (order predates the timeline).</li>@endforelse
        </ul>
      </div>
    </div>

    @if($activity->count())
    <div class="card"><div class="card__head"><h2>Admin changes</h2></div><div class="card__body"><ul class="timeline">@foreach($activity as $a)<li class="done"><b>{{ $a->label }}</b><small>{{ $a->user_name ?: 'System' }} · {{ \Carbon\Carbon::parse($a->created_at)->format('M j, g:ia') }}</small></li>@endforeach</ul></div></div>
    @endif
  </div>
</div>

{{-- modals --}}
<template data-modal-src="add-item">
  <form method="post" action="/admin/orders/{{ $order->id }}/items">@csrf<input type="hidden" name="action" value="add">
    <div class="modal__head"><h3>Add item</h3><button type="button" class="btn btn--xs btn--icon" data-modal-close>{!! ic('x') !!}</button></div>
    <div class="modal__body stack">
      <div class="field"><label>Search catalog &amp; driveshafts</label><div class="combo"><input class="input" placeholder="SKU, name or part number…" autocomplete="off" data-item-search></div></div>
      <div data-picked hidden class="flash flash--info" style="margin:0">{!! ic('check') !!}<span></span></div>
      <input type="hidden" name="type" value="custom"><input type="hidden" name="id"><input type="hidden" name="sku">
      <div class="field"><label>Or a custom line (service, freight, core charge…)</label><input class="input" name="name" placeholder="Description"></div>
      <div class="form-grid"><div class="field"><label>Qty</label><input class="input" type="number" name="qty" value="1" min="1"></div><div class="field"><label>Unit price <span class="muted">(blank = catalog price)</span></label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" min="0" name="price"></div></div></div>
    </div>
    <div class="modal__foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn--primary">Add to order</button></div>
  </form>
</template>
@if($ptiDs)
<template data-modal-src="pti-dropship" data-wide>
  @php $st = $ptiDs['ship_to']; @endphp
  <form data-pti-form data-order="{{ $order->id }}" class="ptid">@csrf
    @foreach(['company','street','unit','city','st','zip','country','phone'] as $f)<input type="hidden" name="{{ $f }}" value="{{ $st[$f] }}">@endforeach
    <input type="hidden" name="residential" value="1">
    <div class="modal__head"><h3>Dropship from PTI</h3><button type="button" class="btn btn--xs btn--icon" data-modal-close>{!! ic('x') !!}</button></div>
    <div class="modal__body">
      <div class="ptid-to">{!! ic('truck') !!}<div><span class="ptid-k">Ships to</span><b>{{ $st['company'] }}</b> · {{ $st['street'] }}{{ $st['unit'] ? ' ' . $st['unit'] : '' }}, {{ $st['city'] }}, {{ $st['st'] }} {{ $st['zip'] }}</div></div>

      <div class="ptid-list">
      @foreach($ptiDs['lines'] as $l)
        @php $v = $l['verify']; $ok = $v && $v['status'] === 'ok'; $stock = $l['stock_total']; @endphp
        <div class="ptid-row {{ $l['pti_part'] ? '' : 'is-off' }}">
          <div class="ptid-part">
            <div class="ptid-pn"><b class="mono">{{ $l['sku'] ?: '—' }}</b>@if($l['pti_part'])<span class="ptid-arrow">→</span><span class="mono">PTI {{ $l['pti_part'] }}</span>@endif</div>
            <div class="ptid-name">{{ \Illuminate\Support\Str::limit($l['name'], 70) }}</div>
          </div>
          @if($l['pti_part'])
            <div class="ptid-tags">
              @if($ok)<span class="ptid-tag ok">✓ Same part</span>
              @else<span class="ptid-tag bad">{{ $v && $v['status'] === 'mismatch' ? '✗ ' . collect($v['diffs'])->map(fn ($d) => $d['label'] . ': ours ' . $d['ours'] . ', PTI ' . $d['pti'])->implode(' · ') : '? Can\'t compare measurements' }}</span>@endif
              @if($stock === null)<span class="ptid-tag">Stock unknown</span>
              @elseif($stock >= $l['qty'])<span class="ptid-tag ok">In stock at PTI: {{ $stock }}</span>
              @else<span class="ptid-tag bad">{{ $stock ? "Only $stock at PTI" : 'Out of stock at PTI' }}</span>@endif
              @if(!$ok)<label class="ptid-ovr"><input type="checkbox" name="override[{{ $l['item_id'] }}]" value="1"> It's the same part, send anyway</label>@endif
            </div>
            <label class="ptid-qty">Send <input class="input input--sm num center" type="number" min="0" max="{{ $l['qty'] }}" name="qty[{{ $l['item_id'] }}]" value="{{ $l['qty'] }}"> of {{ $l['qty'] }}</label>
          @else
            <div class="ptid-tags"><span class="ptid-tag">{{ !empty($l['tubing']) ? 'Tubing — ships from our shop' : 'Not a PTI part — ships from us' }}</span></div>
          @endif
        </div>
      @endforeach
      </div>
      <p class="ptid-hint">Set "Send" to 0 for anything you have in the warehouse. Out of stock? An identical PTI alternate is used, or the shop gets an e-mail.</p>
      <button type="button" class="btn btn--primary btn--block" data-pti-prepare>Check price &amp; shipping at PTI</button>
      <div data-pti-review></div>
    </div>
    <div class="modal__foot"><button type="button" class="btn" data-pti-cancel>Close</button></div>
  </form>
</template>
@endif
@if(!$isPickup && $freight['show'] && $freight['enabled'])
<template data-modal-src="freight" data-xl>
  <form method="post" action="/admin/orders/{{ $order->id }}/freight" data-freight-form>@csrf
    <input type="hidden" name="quote_id"><input type="hidden" name="carrier"><input type="hidden" name="service_level"><input type="hidden" name="price"><input type="hidden" name="transit_days">
    <div class="modal__head"><h3>Book LTL freight</h3><button type="button" class="btn btn--xs btn--icon" data-modal-close>{!! ic('x') !!}</button></div>
    <div class="modal__body">
      @include('admin.orders.partials.ship-warning', ['what' => 'freight'])
      <div class="lbl">
        <div class="lbl__col">
          <p class="lbl__h">Load</p>
          <div class="lbl__box">
            <div class="field"><label>Pallets</label><input class="input" type="number" name="pallets" min="1" max="26" value="{{ $freight['pallets'] }}" required data-fr-load></div>
            <div class="field"><label>Lb / pallet</label><input class="input" type="number" step="0.1" min="1" max="2500" name="weight_lb" value="{{ $freight['weight'] }}" required data-fr-load></div>
            <div class="field"><label>L (in)</label><input class="input" type="number" step="0.1" min="1" max="300" name="length_in" value="{{ $freight['dims'][0] }}" required data-fr-load></div>
            <div class="field"><label>W (in)</label><input class="input" type="number" step="0.1" min="1" max="108" name="width_in" value="{{ $freight['dims'][1] }}" required data-fr-load></div>
          </div>
          <div class="lbl__box">
            <div class="field"><label>H (in)</label><input class="input" type="number" step="0.1" min="1" max="108" name="height_in" value="{{ $freight['dims'][2] }}" required data-fr-load></div>
            <div class="field" style="grid-column:span 3"><label>Density · class</label><div class="input" data-fr-class style="background:var(--paper-2)">—</div></div>
          </div>
          <p class="lbl__h" style="margin-top:14px">Pickup from Odessa</p>
          <div class="form-grid">
            <div class="field"><label>Pickup date</label><select class="select" name="pickup_date" data-fr-load>
              @foreach($freight['days'] as $i => $day)<option value="{{ $day->toDateString() }}" @selected($i===0)>{{ $day->format('D, M j') }}</option>@endforeach
            </select></div>
            <div class="field"><label>Dock hours (EST)</label><div class="flex" style="gap:6px">
              <select class="select" name="pickup_ready">@foreach(['08:00','09:00','10:00','11:00','12:00','13:00'] as $t)<option @selected($t==='10:00')>{{ $t }}</option>@endforeach</select>
              <select class="select" name="pickup_close">@foreach(['13:00','14:00','15:00','16:00','17:00','18:00'] as $t)<option @selected($t==='17:00')>{{ $t }}</option>@endforeach</select>
            </div></div>
            <div class="field"><label>Pickup contact</label><input class="input" name="pickup_contact" value="{{ auth()->user()->name ?? 'Universal Driveshafts' }}" required></div>
            <div class="field"><label>Pickup phone</label><input class="input" name="pickup_phone" value="(813) 524-6491" required></div>
          </div>
          <div class="field"><label>Pickup notes <span class="muted">(dock, gate, forklift…)</span></label><input class="input" name="pickup_note" maxlength="300" placeholder="e.g. Rear dock, ring the bell"></div>
          <div class="lbl__opts">
            <label class="check"><input type="checkbox" name="pickup_acc[]" value="liftgate-pickup" data-fr-load><span>Pickup needs a liftgate (no dock or forklift here)</span></label>
            <label class="check"><input type="checkbox" name="pickup_acc[]" value="pickup-appointment" data-fr-load><span>Pickup by appointment</span></label>
          </div>
          <p class="lbl__h" style="margin-top:14px">Delivery</p>
          <div class="lbl__opts">
            @foreach(['residential-delivery'=>'Residential address','liftgate-delivery'=>'Liftgate at delivery','limited-access-delivery'=>'Limited access (school, farm, construction…)','inside-delivery'=>'Inside delivery','delivery-appointment'=>'Delivery appointment'] as $v=>$l)
              <label class="check"><input type="checkbox" name="delivery_acc[]" value="{{ $v }}" @checked(in_array($v, $freight['delivery'], true)) data-fr-load><span>{{ $l }}</span></label>
            @endforeach
          </div>
          <div class="form-grid">
            <div class="field"><label>Receiver</label><input class="input" name="to_name" value="{{ $freight['to']['company'] ?? ($freight['to']['name'] ?? '') }}" required></div>
            <div class="field"><label>Receiver phone</label><input class="input" name="to_phone" value="{{ $freight['to']['phone'] ?? '' }}" required></div>
            <div class="field" style="grid-column:1/-1"><label>Street</label><input class="input" name="to_street" value="{{ trim(($freight['to']['street1'] ?? '') . ' ' . ($freight['to']['street2'] ?? '')) }}" required></div>
            <div class="field"><label>City</label><input class="input" name="to_city" value="{{ $freight['to']['city'] ?? '' }}" required></div>
            <div class="field"><div class="flex" style="gap:6px"><div style="flex:1"><label>State</label><input class="input" name="to_state" value="{{ $freight['to']['state'] ?? '' }}" required></div><div style="flex:1"><label>ZIP</label><input class="input" name="to_zip" value="{{ $freight['to']['zip'] ?? '' }}" required readonly title="Quotes are for this ZIP — change the order address to change it"></div></div></div>
            <div class="field" style="grid-column:1/-1"><label>Receiver e-mail</label><input class="input" type="email" name="to_email" value="{{ $freight['to']['email'] ?? '' }}"></div>
          </div>
          <div class="field"><label>Delivery notes</label><input class="input" name="delivery_note" maxlength="300" placeholder="e.g. Call 30 min before"></div>
        </div>
        <div class="lbl__col">
          <p class="lbl__h">Carrier <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:500">· live, bookable Warp quotes (held ~15 min)</span></p>
          <button type="button" class="btn btn--block" data-fr-get>{!! ic('dollar') !!} Get freight prices</button>
          <div class="lbl__rates" data-fr-rates style="margin-top:10px"><div class="lbl__empty">Confirm the load and pickup date, then get prices. Polling every carrier takes about 15 seconds.</div></div>
          <div class="lbl__opts">
            <label class="check" data-label-notify><input type="checkbox" name="notify" value="1" checked><span>Mark the order shipped and e-mail + text the customer the freight tracking</span></label>
          </div>
          @if($freight['test'])
            <div class="lbl__real"><label class="check"><input type="checkbox" name="allow_real" value="1" data-fr-load><span><b>Staging:</b> book a <b>real</b> truck instead of a sandbox booking. This <b>charges the card on file</b> and dispatches the carrier.</span></label></div>
          @endif
          @if($freight['bookings']->where('status', '!=', 'cancelled')->count())<label class="check"><input type="checkbox" name="confirm_duplicate" value="1"><span>Book another truck anyway — this order already has a booking</span></label>@endif
          <div class="lbl__real" style="background:var(--warn-bg)"><label class="check"><input type="checkbox" name="confirm" value="1" data-fr-confirm><span data-fr-confirm-text>I confirm: book this freight. It is charged to the shop's Warp account and the carrier is dispatched to Odessa on the pickup date.</span></label></div>
        </div>
      </div>
    </div>
    <div class="modal__foot"><span class="lbl__sum" data-fr-sum>Get prices, pick a carrier</span><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn--primary" data-fr-book disabled>Book freight</button></div>
  </form>
</template>
@endif
@if(!$isPickup && $ship['enabled'])
<template data-modal-src="label" data-xl>
  <form method="post" action="/admin/orders/{{ $order->id }}/labels" data-label-form data-confirm="Buy this label now?">@csrf
    <div class="modal__head"><h3>Create shipping label</h3><button type="button" class="btn btn--xs btn--icon" data-modal-close aria-label="Close">{!! ic('x') !!}</button></div>
    <div class="modal__body">
      @include('admin.orders.partials.ship-warning', ['what' => 'label'])
      <div class="lbl">
        <div class="lbl__col">
          <p class="lbl__h">Package</p>
          <div class="lbl__seg" data-label-kind-seg>
            <label><input type="radio" name="kind" value="outbound" checked data-label-kind> Ship to customer</label>
            <label><input type="radio" name="kind" value="return" data-label-kind> Customer return</label>
          </div>
          @if($pack && $pack['packages'] > 1)<div class="small muted">This order packs as {{ $pack['packages'] }} parcels — create one label per box.</div>@endif
          <div class="lbl__box">
            <div class="field"><label>Weight (lb)</label><input class="input" type="number" step="0.01" min="0.1" max="150" name="weight_lb" value="{{ $pack ? round($pack['boxes'][0]['weight'] ?? $pack['weight_lb'], 2) : '' }}" required data-label-box></div>
            <div class="field"><label>Length (in)</label><input class="input" type="number" step="0.1" min="0" max="108" name="length_in" data-label-box></div>
            <div class="field"><label>Width (in)</label><input class="input" type="number" step="0.1" min="0" max="108" name="width_in" data-label-box></div>
            <div class="field"><label>Height (in)</label><input class="input" type="number" step="0.1" min="0" max="108" name="height_in" data-label-box></div>
          </div>
          <input type="hidden" name="carrier_code" data-label-carrier value="">
          <div class="form-grid">
            <div class="field"><label>Packaging</label><select class="select" name="package_code"><option value="parcel">My own box</option></select></div>
            @php $sigOver = (float) \App\Support\Settings::get('shipping.signature_over', 500); $sigDefault = $sigOver > 0 && (float) $order->total > $sigOver; @endphp
            <div class="field"><label>Confirmation</label><select class="select" name="confirmation">
              <option value="none" @selected(!$sigDefault)>None</option><option value="delivery">Delivery</option>
              <option value="signature" @selected($sigDefault)>Signature</option><option value="adult_signature">Adult signature</option>
            </select>@if($sigDefault)<span class="hint">Signature preselected: this order is over {{ money($sigOver) }} (Settings → Shipping).</span>@endif</div>
            <div class="field"><label>Insure for <span class="muted">(optional)</span></label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" min="0" name="insure_value" placeholder="0.00"></div></div>
          </div>
        </div>
        <div class="lbl__col">
          <p class="lbl__h">Choose a service <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:500">· every carrier, live prices for this box</span></p>
          @if($ship['paid_method'])
            <p class="small muted" style="margin:-4px 0 8px">Customer paid <b>{{ money($ship['paid_total']) }}</b> for {{ $ship['paid_label'] ?: $ship['paid_method'] }} — that service is preselected below when it is still available.</p>
          @endif
          <div class="lbl__rates" data-label-services data-paid="{{ $ship['paid_method'] }}"><div class="lbl__empty">Loading prices…</div></div>
          <div class="lbl__opts">
            @if($ship['writeback'])
              <label class="check"><input type="checkbox" name="save_dims" value="1" checked><span>Save this weight and box size to <b>{{ $ship['writeback'] }}</b> so future shipping quotes are accurate</span></label>
            @endif
            <label class="check" data-label-notify><input type="checkbox" name="notify" value="1" checked><span>Mark the order shipped and e-mail + text the customer the tracking</span></label>
            @if($labels->whereNull('voided_at')->where('kind', 'outbound')->count())<label class="check"><input type="checkbox" name="confirm_duplicate" value="1"><span>Buy another outbound label anyway — this order already has one</span></label>@endif
          </div>
          @if($ship['test'] && adm_can('labels'))
            <div class="lbl__real">
              <label class="check"><input type="checkbox" name="allow_real" value="1"><span><b>Staging:</b> free test labels are not available here. Tick to buy a <b>real</b> label and charge the {{ $ship['provider'] }} balance (voiding it refunds the postage).</span></label>
            </div>
          @endif
        </div>
      </div>
    </div>
    <div class="modal__foot"><span class="lbl__sum" data-label-sum>Pick a service</span><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn--primary" data-label-buy>Buy label</button></div>
  </form>
</template>
@endif
<template data-modal-src="totals">
  <form method="post" action="/admin/orders/{{ $order->id }}/totals">@csrf
    <div class="modal__head"><h3>Shipping, discount &amp; tax</h3><button type="button" class="btn btn--xs btn--icon" data-modal-close>{!! ic('x') !!}</button></div>
    <div class="modal__body form-grid">
      <div class="field"><label>Shipping charge</label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" min="0" name="shipping_total" value="{{ $order->shipping_total }}"></div></div>
      <div class="field"><label>Shipping label</label><input class="input" name="shipping_label" value="{{ $order->shipping_label }}"></div>
      <div class="field"><label>Discount</label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" min="0" name="discount_total" value="{{ $order->discount_total }}"></div></div>
      <div class="field"><label>Coupon code</label><input class="input" name="coupon_code" value="{{ $order->coupon_code }}"></div>
      <div class="field"><label>Tax</label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" min="0" name="tax_total" value="{{ $order->tax_total }}"></div></div>
    </div>
    <div class="modal__foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn--primary">Save &amp; recalculate</button></div>
  </form>
</template>
@foreach(['billing','shipping'] as $w)
<template data-modal-src="addr-{{ $w }}">
  <form method="post" action="/admin/orders/{{ $order->id }}/address">@csrf<input type="hidden" name="which" value="{{ $w }}">
    <div class="modal__head"><h3>{{ ucfirst($w) }} address</h3><button type="button" class="btn btn--xs btn--icon" data-modal-close>{!! ic('x') !!}</button></div>
    <div class="modal__body form-grid">
      <div class="field"><label>First name</label><input class="input" name="first_name" value="{{ $order->{$w.'_first_name'} ?: $order->billing_first_name }}" required></div>
      <div class="field"><label>Last name</label><input class="input" name="last_name" value="{{ $order->{$w.'_last_name'} ?: $order->billing_last_name }}"></div>
      @if($w==='billing')<div class="field"><label>Company</label><input class="input" name="company" value="{{ $order->billing_company }}"></div><div class="field"><label>E-mail</label><input class="input" type="email" name="email" value="{{ $order->email }}"></div><div class="field"><label>Phone</label><input class="input" type="tel" name="phone" value="{{ $order->phone }}"></div>@endif
      <div class="field span-2"><label>Address</label><input class="input" name="address_1" value="{{ $order->{$w.'_address_1'} }}"></div>
      <div class="field span-2"><label>Address line 2</label><input class="input" name="address_2" value="{{ $order->{$w.'_address_2'} }}"></div>
      <div class="field"><label>City</label><input class="input" name="city" value="{{ $order->{$w.'_city'} }}"></div>
      <div class="field"><label>State</label><input class="input" name="state" value="{{ $order->{$w.'_state'} }}"></div>
      <div class="field"><label>ZIP</label><input class="input" name="postcode" value="{{ $order->{$w.'_postcode'} }}"></div>
      <div class="field"><label>Country</label><input class="input" name="country" value="{{ $order->{$w.'_country'} ?: 'US' }}" maxlength="2" required></div>
    </div>
    <div class="modal__foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn--primary">Save address</button></div>
  </form>
</template>
@endforeach
<template data-modal-src="refund">
  <form method="post" action="/admin/orders/{{ $order->id }}/refund" data-confirm="Record this refund?">@csrf
    <div class="modal__head"><h3>Refund</h3><button type="button" class="btn btn--xs btn--icon" data-modal-close>{!! ic('x') !!}</button></div>
    <div class="modal__body stack">
      <div class="field"><label>Amount <span class="muted">(up to {{ money($remaining) }})</span></label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" min="0.01" max="{{ $remaining }}" name="amount" value="{{ $remaining }}" required></div></div>
      <div class="field"><label>Reason</label><input class="input" name="reason" placeholder="Returned part, cancelled, price adjustment…"></div>
      <label class="check"><input type="checkbox" name="restock" value="1"> Return the items to manual inventory</label>
      <div class="small muted">@if($order->payment_method==='stripe' && $order->stripe_payment_intent)This refund is sent to Stripe and the card is credited. @else Card was not charged through Stripe here — the refund is recorded only; return the money the way it was received. @endif</div>
    </div>
    <div class="modal__foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn--danger">Refund</button></div>
  </form>
</template>
@endsection
@push('scripts')
<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-modal="add-item"]'); if (!b) return;
  setTimeout(function () {
    var inp = document.querySelector('.modal [data-item-search]'); if (!inp) return;
    var f = inp.closest('form');
    admCombo(inp, { url: '/admin/api/products?q=', clear: false,
      render: function (it) { return (it.image ? '<img src="' + admEsc(it.image) + '" alt="">' : '<svg class="ic"><use href="#i-box"/></svg>') + '<span class="t"><b>' + admEsc(it.sku || '') + '</b><small>' + admEsc(it.name) + '</small></span><span class="p">$' + Number(it.price).toFixed(2) + (it.stock !== null && it.stock !== undefined ? ' <small class="muted">· ' + it.stock + ' in stock</small>' : '') + '</span>'; },
      onPick: function (it) { f.type.value = it.type; f.id.value = it.id || ''; f.sku.value = it.sku || ''; f.name.value = ''; inp.value = it.sku + ' — ' + it.name; var p = f.querySelector('[data-picked]'); p.hidden = false; p.querySelector('span').textContent = 'Adding ' + it.sku + ' at $' + Number(it.price).toFixed(2); }
    });
    f.name.addEventListener('input', function () { if (f.name.value) { f.type.value = 'custom'; f.id.value = ''; f.sku.value = ''; } });
  }, 30);
});

/* ---------- label modal: live prices from every carrier for the box typed in ---------- */
document.addEventListener('click', function (e) {
  if (!e.target.closest('[data-modal="label"]')) return;
  setTimeout(function () {
    var form = document.querySelector('.modal [data-label-form]'); if (!form) return;
    var carrier = form.querySelector('[data-label-carrier]');
    var list = form.querySelector('[data-label-services]');
    var kinds = form.querySelectorAll('[data-label-kind]');
    var notify = form.querySelector('[data-label-notify]');
    var sum = form.querySelector('[data-label-sum]');
    var buy = form.querySelector('[data-label-buy]');
    var paid = list.getAttribute('data-paid') || '';
    var rateT = null, rates = [], freight = [], picked = '';

    function key(r) { return r.carrier + '|' + r.serviceCode; }

    function updateSum() {
      var r = form.querySelector('[name=service_code]:checked');
      if (!r) { sum.textContent = 'Pick a service'; buy.disabled = true; carrier.value = ''; return; }
      picked = r.getAttribute('data-key') || '';
      carrier.value = r.getAttribute('data-carrier') || '';
      buy.disabled = false;
      var row = rates.filter(function (x) { return key(x) === picked; })[0];
      sum.innerHTML = row ? admEsc(row.carrierName + ' ' + row.serviceName) + ' · <b>$' + row.cost.toFixed(2) + '</b>' : '';
    }

    function render() {
      if (!rates.length) {
        list.innerHTML = '<div class="lbl__empty">No prices for this box and address. Check the weight and size.</div>';
        updateSum(); return;
      }
      var html = '';
      rates.forEach(function (r, i) {
        var k = key(r);
        var isPaid = paid && r.quoteId === paid;
        var sel = picked ? picked === k : (isPaid || i === 0);
        html += '<label class="lbl__rate"><input type="radio" name="service_code" value="' + admEsc(r.serviceCode) + '" data-carrier="' + admEsc(r.carrier) + '" data-key="' + admEsc(k) + '" required' + (sel ? ' checked' : '') + '>'
          + '<b>' + admEsc(r.carrierName + ' ' + r.serviceName) + '</b>'
          + (isPaid ? '<i>Customer paid this</i>' : (i === 0 ? '<i>Cheapest</i>' : ''))
          + '<em>$' + r.cost.toFixed(2) + '</em></label>';
      });
      if (freight.length) {
        html += '<p class="lbl__h" style="margin-top:14px">LTL freight · quote only</p>';
        freight.forEach(function (f) {
          html += '<div class="lbl__rate" style="opacity:.75;cursor:default"><b>' + admEsc(f.label) + '</b>'
            + (f.transit_days ? '<i>' + f.transit_days + ' days</i>' : '')
            + '<em>$' + Number(f.cost).toFixed(2) + '</em></div>';
        });
        html += '<p class="small muted" style="margin:6px 0 0">Freight is booked with Warp, not bought here — these prices are what the pallet really costs.</p>';
      }
      list.innerHTML = html;
      // the radios were just rebuilt: read the chosen one back off the DOM
      var cur = form.querySelector('[name=service_code]:checked');
      picked = cur ? cur.getAttribute('data-key') : '';
      updateSum();
    }

    function loadRates(now) {
      clearTimeout(rateT);
      rateT = setTimeout(function () {
        if (!form.querySelector('[name=weight_lb]').value) { rates = []; render(); return; }
        list.innerHTML = '<div class="lbl__empty">Loading prices…</div>';
        var body = new FormData();
        body.append('_token', document.querySelector('meta[name=csrf-token]').content);
        ['weight_lb', 'length_in', 'width_in', 'height_in'].forEach(function (n) {
          var el = form.querySelector('[name=' + n + ']'); if (el && el.value) body.append(n, el.value);
        });
        fetch('{{ url('/admin/orders/'.$order->id.'/labels/rates') }}', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (d) { rates = d.rates || []; freight = d.freight || []; render(); })
          .catch(function () { rates = []; freight = []; render(); });
      }, now === true ? 0 : 450);
    }

    list.addEventListener('change', function (e) {
      var r = e.target.closest('[name=service_code]');
      picked = r ? r.getAttribute('data-key') : picked;
      updateSum();
    });
    form.querySelectorAll('[data-label-box]').forEach(function (i) { i.addEventListener('input', function () { picked = ''; loadRates(); }); });
    kinds.forEach(function (k) { k.addEventListener('change', function () { var ret = form.querySelector('[data-label-kind]:checked').value === 'return'; if (notify) notify.hidden = ret; }); });
    loadRates(true);
  }, 30);
});
</script>
@endpush
@if($ptiDs)
@push('scripts')
<script>
/* ---------- dropship from PTI: prepare (PTI cart + address + rates, nothing ordered) → review → place ---------- */
document.addEventListener('click', function (e) {
  if (!e.target.closest('[data-modal="pti-dropship"]')) return;
  setTimeout(function () {
    var form = document.querySelector('.modal [data-pti-form]'); if (!form) return;
    var review = form.querySelector('[data-pti-review]'), prepBtn = form.querySelector('[data-pti-prepare]');
    var CSRF = form.querySelector('[name=_token]').value, dsId = null;
    var STORES = @json(\App\Services\PtiDropship::STORES);
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var money = function (n) { return '$' + Number(n || 0).toFixed(2); };
    function post(url, data) {
      return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data })
        .then(function (r) { return r.json().then(function (j) { j._ok = r.ok; return j; }); });
    }
    function fail(msg) { review.innerHTML = '<div class="flash flash--err" style="margin:0;white-space:pre-line">' + esc(msg) + '</div>'; }
    prepBtn.addEventListener('click', function () {
      prepBtn.disabled = true; prepBtn.classList.add('is-loading');
      review.innerHTML = '<div class="ptid-hint">Asking PTI… about 10 seconds.</div>';
      var fd = new FormData(form); fd.delete('confirm');
      post('/admin/orders/' + form.dataset.order + '/pti-dropship/prepare', fd).then(function (j) {
        prepBtn.disabled = false; prepBtn.classList.remove('is-loading');
        if (!j._ok || !j.ok) return fail(j.error || j.message || 'PTI did not answer.');
        dsId = j.dropship_id; prepBtn.hidden = true;
        var a = j.address || {}, c = j.cart || {};
        var rates = (j.rates || []).map(function (r, i) { return '<option value="' + esc(r.method) + '" data-name="' + esc(r.name) + '" data-price="' + r.price + '"' + (i === 0 ? ' selected' : '') + '>' + esc(r.name) + ' — ' + money(r.price) + (r.date ? ' · ' + esc(r.date) : '') + '</option>'; }).join('');
        var cards = Object.keys(j.cards || {}).map(function (id) { return '<option value="' + esc(id) + '">' + esc(j.cards[id] || id) + '</option>'; }).join('');
        var stores = Object.keys(STORES).map(function (k) { return '<option value="' + k + '">' + esc(STORES[k]) + '</option>'; }).join('');
        var subs = (j.substitutions || []).length ? '<div class="ptid-note">' + j.substitutions.map(esc).join('<br>') + '</div>' : '';
        var items = (c.items || []).map(function (i) { return '<div class="ptid-sum-row"><span class="mono">' + esc(i.qty + ' × ' + i.part_number) + '</span><span>' + esc(i.price) + '</span></div>'; }).join('');
        review.innerHTML = '<div class="ptid-step2">' + subs +
          '<div class="ptid-sum">' + items + '<div class="ptid-sum-row tot"><span>Parts at PTI</span><b>' + esc(c.total) + '</b></div></div>' +
          '<div class="form-grid">' +
            '<div class="field" style="grid-column:1/-1"><label>Shipping</label><select class="select" name="shipping_method">' + (rates || '<option value="">PTI returned no rate</option>') + '</select></div>' +
            '<div class="field"><label>Pay with</label><select class="select" name="card">' + cards + '</select></div>' +
            '<div class="field"><label>Ships from</label><select class="select" name="store">' + stores + '</select></div>' +
          '</div>' +
          '<label class="ptid-confirm"><input type="checkbox" name="confirm" value="1"> Place a real order at PTI and charge the card</label>' +
          '<input type="checkbox" name="signature" value="1" hidden>' +
          '<button type="button" class="btn btn--primary btn--block" data-pti-place disabled>Place order at PTI</button></div>';
        var placeBtn = review.querySelector('[data-pti-place]'), conf = review.querySelector('[name=confirm]');
        conf.addEventListener('change', function () { placeBtn.disabled = !conf.checked || !rates; });
        placeBtn.addEventListener('click', function () {
          var sel = review.querySelector('[name=shipping_method]'), opt = sel.options[sel.selectedIndex];
          var fd2 = new FormData();
          fd2.append('shipping_method', sel.value); fd2.append('shipping_name', opt.dataset.name || sel.value); fd2.append('shipping_cost', opt.dataset.price || 0);
          fd2.append('store', review.querySelector('[name=store]').value); fd2.append('card', review.querySelector('[name=card]').value);
          if (review.querySelector('[name=signature]').checked) fd2.append('signature', '1');
          fd2.append('confirm', '1');
          placeBtn.disabled = true; placeBtn.classList.add('is-loading');
          post('/admin/pti-dropship/' + dsId + '/place', fd2).then(function (k) {
            if (!k._ok || !k.ok) { placeBtn.disabled = false; placeBtn.classList.remove('is-loading'); return alert(k.error || k.message || 'PTI refused the order.'); }
            dsId = null; location.reload();
          });
        });
      }).catch(function () { prepBtn.disabled = false; prepBtn.classList.remove('is-loading'); fail('Could not reach PTI.'); });
    });
    // closing without placing takes our lines back out of PTI's cart
    function cleanup() { if (dsId) { var fd = new FormData(); post('/admin/pti-dropship/' + dsId + '/cancel', fd); dsId = null; } }
    form.querySelector('[data-pti-cancel]').addEventListener('click', function () { cleanup(); window.admModalClose(); });
    form.closest('.modal').querySelectorAll('[data-modal-close]').forEach(function (b) { b.addEventListener('click', cleanup); });
    form.closest('.modal').addEventListener('click', function (ev) { if (ev.target === ev.currentTarget) cleanup(); });
    window.addEventListener('beforeunload', cleanup);
  }, 0);
});
</script>
@endpush
@endif
@push('scripts')
<script>
/* ---------- freight modal: fresh Warp quotes for the confirmed load, then a confirmed booking ---------- */
document.addEventListener('click', function (e) {
  if (!e.target.closest('[data-modal="freight"]')) return;
  setTimeout(function () {
    var form = document.querySelector('.modal [data-freight-form]'); if (!form) return;
    var list = form.querySelector('[data-fr-rates]'), sum = form.querySelector('[data-fr-sum]'), book = form.querySelector('[data-fr-book]');
    var getBtn = form.querySelector('[data-fr-get]'), confirm = form.querySelector('[data-fr-confirm]'), cls = form.querySelector('[data-fr-class]');
    var rates = [], fetchedAt = 0, HOLD_MS = 14 * 60 * 1000, busy = false;
    var v = function (n) { var el = form.querySelector('[name=' + n + ']'); return el ? el.value : ''; };

    // NMFC density bands — the same table WarpFreightService::freightClass uses
    var BANDS = [[50,50],[35,55],[30,60],[22.5,65],[15,70],[13.5,85],[12,92.5],[10.5,100],[9,110],[8,125],[7,150],[6,175],[5,200],[4,250],[3,300],[2,400]];
    function density() {
      var w = +v('weight_lb'), cf = (+v('length_in')) * (+v('width_in')) * (+v('height_in')) / 1728;
      if (!w || !cf) { cls.textContent = '—'; return; }
      var d = w / cf, c = 500; for (var i = 0; i < BANDS.length; i++) if (d >= BANDS[i][0]) { c = BANDS[i][1]; break; }
      cls.textContent = d.toFixed(2) + ' lb/ft³ · class ' + c + ' · ' + cf.toFixed(1) + ' ft³';
    }

    function stale() { rates = []; fetchedAt = 0; setPick(null); list.innerHTML = '<div class="lbl__empty">The load, date or services changed — get fresh prices.</div>'; }

    function setPick(r) {
      ['quote_id', 'carrier', 'service_level', 'price', 'transit_days'].forEach(function (n) { form.querySelector('[name=' + n + ']').value = r ? (r[n] == null ? '' : r[n]) : ''; });
      if (!r) { sum.textContent = 'Get prices, pick a carrier'; }
      else {
        var pallets = +v('pallets');
        sum.innerHTML = admEsc(r.carrier + (r.service_level ? ' ' + r.service_level : '')) + ' · <b>$' + r.price.toFixed(2) + '</b>' + ' · ' + pallets + ' pallet' + (pallets === 1 ? '' : 's') + ' · pickup ' + admEsc(form.querySelector('[name=pickup_date] option:checked').textContent);
        form.querySelector('[data-fr-confirm-text]').textContent = 'I confirm: book ' + r.carrier + ' for $' + r.price.toFixed(2) + '. It is charged to the shop\'s Warp account and the carrier is dispatched to Odessa on ' + form.querySelector('[name=pickup_date] option:checked').textContent + ', ' + v('pickup_ready') + '–' + v('pickup_close') + ' ET.';
      }
      gate();
    }

    function gate() {
      var fresh = fetchedAt && (Date.now() - fetchedAt) < HOLD_MS;
      if (fetchedAt && !fresh) { list.insertAdjacentHTML('afterbegin', '<div class="lbl__empty" style="color:var(--err)">These prices have expired — get fresh ones.</div>'); fetchedAt = 0; }
      var picked = !!form.querySelector('[name=quote_id]').value;
      book.disabled = !(picked && confirm.checked && fresh);
      // say what is missing instead of leaving a grey button to guess at
      var why = !fetchedAt && !picked ? 'Step 1: press “Get freight prices”'
        : !picked ? 'Step 2: pick a carrier'
        : !fresh ? 'Prices expired — get fresh ones'
        : !confirm.checked ? 'Last step: tick the yellow “I confirm” box to enable booking' : '';
      book.title = why;
      var hint = form.querySelector('[data-fr-why]');
      if (!hint) { hint = document.createElement('span'); hint.setAttribute('data-fr-why', ''); hint.className = 'lbl__why'; book.parentNode.insertBefore(hint, book); }
      hint.textContent = why;
      hint.hidden = !why;
      if (why && confirm.checked === false && picked && fresh) confirm.closest('.lbl__real').classList.add('is-attn'); else confirm.closest('.lbl__real').classList.remove('is-attn');
    }

    function render() {
      if (!rates.length) return;
      var html = '';
      rates.forEach(function (r, i) {
        html += '<label class="lbl__rate"><input type="radio" name="_fr" value="' + i + '"' + (i === 0 ? ' checked' : '') + '>'
          + '<b>' + admEsc(r.carrier) + (r.service_level ? ' <span class="muted" style="font-weight:500">' + admEsc(r.service_level) + '</span>' : '') + '</b>'
          + (r.fedex ? '<i>FedEx Freight</i>' : (i === 0 ? '<i>Cheapest</i>' : ''))
          + (r.transit_days != null ? '<span class="muted small" style="margin-left:auto;margin-right:10px">' + r.transit_days + ' day' + (r.transit_days === 1 ? '' : 's') + '</span>' : '')
          + '<em>$' + r.price.toFixed(2) + '</em></label>';
      });
      list.innerHTML = html;
      setPick(rates[0]);
    }

    getBtn.addEventListener('click', function () {
      if (busy) return;
      if (!form.reportValidity()) return;
      busy = true; getBtn.disabled = true; list.innerHTML = '<div class="lbl__empty">Asking every LTL carrier on this lane… about 15 seconds.</div>';
      var body = new FormData(form); body.delete('confirm');
      fetch('{{ url('/admin/orders/'.$order->id.'/freight/quotes') }}', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) { list.innerHTML = '<div class="lbl__empty" style="color:var(--err)">' + admEsc(d.error || (d.message) || 'No quote.') + '</div>'; setPick(null); return; }
          rates = d.options || []; fetchedAt = Date.now();
          if (!rates.length) { list.innerHTML = '<div class="lbl__empty">' + admEsc(d.error || 'No bookable carrier for this lane and date.') + '</div>'; setPick(null); return; }
          render();
          if (d.test) list.insertAdjacentHTML('afterbegin', '<div class="lbl__empty" style="color:var(--ink-2)"><b>Sandbox prices</b> — booking is simulated: no charge, no truck.' + (d.note ? '<br><span style="color:var(--warn,#9a6b12)">' + admEsc(d.note) + '</span>' : '') + '</div>');
        })
        .catch(function () { list.innerHTML = '<div class="lbl__empty" style="color:var(--err)">Could not reach Warp. Try again.</div>'; })
        .then(function () { busy = false; getBtn.disabled = false; });
    });

    list.addEventListener('change', function (e) { var r = e.target.closest('[name=_fr]'); if (r) setPick(rates[+r.value]); });
    form.querySelectorAll('[data-fr-load]').forEach(function (el) { el.addEventListener('input', function () { density(); if (fetchedAt) stale(); }); el.addEventListener('change', function () { density(); if (fetchedAt) stale(); }); });
    ['pickup_ready', 'pickup_close'].forEach(function (n) { form.querySelector('[name=' + n + ']').addEventListener('change', function () { if (form.querySelector('[name=quote_id]').value) setPick(rates.filter(function (x) { return x.quote_id === v('quote_id'); })[0]); }); });
    confirm.addEventListener('change', gate);
    form.addEventListener('submit', function (e) { gate(); if (book.disabled) { e.preventDefault(); return; } book.disabled = true; book.textContent = 'Booking…'; });
    setInterval(gate, 30000);
    density();
  }, 30);
});
</script>
@endpush

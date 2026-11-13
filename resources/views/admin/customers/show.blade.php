@extends('admin.layout')
@section('title', $user->name ?? ($orders->first()->billing_first_name ?? 'Guest') . ' ' . ($user ? '' : ($orders->first()->billing_last_name ?? '')))
@section('crumb')<a href="/admin/customers">Customers</a>@endsection
@section('sub'){{ $email }} @if($user && $user->wholesale_status==='approved')<span class="badge badge--ok">B2B {{ \App\Services\B2bAccounts::label(\App\Services\B2bAccounts::tierOf($user)) }}</span>@elseif(!$user)<span class="badge badge--plain">Guest — no account</span>@endif @if($user && $user->tags) @foreach(explode(',',$user->tags) as $t)<span class="pill">{{ trim($t) }}</span>@endforeach @endif @endsection
@section('actions')
  <a href="/admin/orders/new?{{ $user ? 'customer='.$user->id : '' }}" class="btn btn--primary btn--sm">{!! ic('plus') !!} New order</a>
  @if($user)<div class="adm-menu" data-menu><button type="button" class="btn btn--sm" data-menu-btn>{!! ic('dots') !!}</button><div class="adm-menu__pop adm-menu__pop--right" hidden><a href="mailto:{{ $email }}">{!! ic('mail') !!} E-mail</a><a href="#" data-post="/admin/customers/{{ $user->id }}/reset-link" data-confirm="Send a password reset link to {{ $email }}?">{!! ic('send') !!} Send password reset</a><a href="/admin/orders?q={{ urlencode($email) }}">{!! ic('orders') !!} All orders</a></div></div>@endif
@endsection
@section('body')
<div class="kpis mb">
  <div class="kpi"><span class="kpi__label">Orders</span><span class="kpi__value">{{ $stats['n'] }}</span></div>
  <div class="kpi"><span class="kpi__label">Lifetime</span><span class="kpi__value">{{ money($stats['spent']) }}</span>@if($stats['refunds']>0)<span class="kpi__delta down">{{ money($stats['refunds']) }} refunded</span>@endif</div>
  <div class="kpi"><span class="kpi__label">Avg. order</span><span class="kpi__value">{{ money($stats['aov']) }}</span></div>
  <div class="kpi"><span class="kpi__label">Customer since</span><span class="kpi__value" style="font-size:1.1rem">{{ $stats['first'] ? \Carbon\Carbon::parse($stats['first'])->format('M Y') : ($user ? \Carbon\Carbon::parse($user->created_at)->format('M Y') : '—') }}</span><span class="kpi__delta">last order {{ $stats['last'] ? \Carbon\Carbon::parse($stats['last'])->diffForHumans() : '—' }}</span></div>
</div>
<div class="grid grid--main">
  <div class="stack">
    <div class="card"><div class="card__head"><h2>Orders</h2></div><div class="tbl-wrap"><table class="tbl tbl--cards tbl--compact"><thead><tr><th>Order</th><th>Status</th><th>Payment</th><th class="right">Total</th><th>Date</th></tr></thead><tbody>
      @forelse($orders as $o)<tr data-href="/admin/orders/{{ $o->id }}"><td class="primary"><span class="title mono">{{ $o->order_number }}</span><small>{{ \Illuminate\Support\Str::limit($o->shipping_label,40) }}</small></td><td class="status-cell"><span class="badge badge--{{ $o->status }}">{{ ucfirst($o->status) }}</span></td><td data-label="Payment" class="small">{{ ucfirst(str_replace('_',' ',$o->payment_status)) }}</td><td class="right num" data-label="Total"><b>{{ money($o->total) }}</b></td><td data-label="Date" class="small muted">{{ \Carbon\Carbon::parse($o->created_at)->format('M j, Y') }}</td></tr>
      @empty<tr><td colspan="5" class="muted" style="padding:16px">No orders on the new store yet.</td></tr>@endforelse</tbody></table></div></div>
    @if($wpHistory->count())<div class="card"><div class="card__head"><h2>Old store history <span class="muted">(WooCommerce)</span></h2></div><div class="tbl-wrap"><table class="tbl tbl--compact"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="right">Items</th><th class="right">Total</th></tr></thead><tbody>@foreach($wpHistory as $h)<tr><td class="mono">#{{ $h->order_id }}</td><td class="small">{{ \Carbon\Carbon::parse($h->date_created)->format('M j, Y') }}</td><td class="small">{{ str_replace('wc-','',$h->status) }}</td><td class="right num">{{ $h->num_items_sold }}</td><td class="right num">{{ money($h->total_sales) }}</td></tr>@endforeach</tbody></table></div></div>@endif
    @if($items->count())<div class="card"><div class="card__head"><h2>What they buy</h2></div><div class="card__body card__body--flush">@foreach($items as $i)<a href="{{ $i->wp_product_id ? '/admin/products/'.$i->wp_product_id : '#' }}" class="stat-row" style="padding:8px 16px;color:inherit;text-decoration:none"><span><b class="mono">{{ $i->sku }}</b> <span class="muted small">{{ \Illuminate\Support\Str::limit($i->name,50) }}</span></span><span class="right small"><b>{{ $i->q }}×</b> · {{ money($i->t) }}<br><span class="muted">last {{ \Carbon\Carbon::parse($i->last)->format('M j, Y') }}</span></span></a>@endforeach</div></div>@endif
    <div class="card"><div class="card__head"><h2>Notes</h2></div><div class="card__body stack">
      <form method="post" action="/admin/customers/{{ rawurlencode($email) }}/note" class="flex" style="align-items:flex-start">@csrf<textarea class="input grow" name="body" rows="2" placeholder="Prefers UPS Ground, has a fleet of 12 F-250s, net-30 approved by…" required></textarea><button class="btn btn--primary btn--sm">Add</button></form>
      @foreach($notes as $n)<div class="note note--internal"><div class="note__meta"><span><b>{{ $n->author ?: 'Admin' }}</b> · {{ \Carbon\Carbon::parse($n->created_at)->format('M j, Y g:ia') }}</span><form method="post" action="/admin/customers/note/{{ $n->id }}/delete" data-confirm="Delete note?">@csrf<button class="btn btn--xs btn--icon">{!! ic('x','ic--s') !!}</button></form></div>{!! nl2br(e($n->body)) !!}</div>@endforeach
    </div></div>
    @if($leads->count() || $alerts->count() || $carts->count() || $designs->count())
    <div class="grid grid--2">
      @if($leads->count())<div class="card"><div class="card__head"><h2>Leads &amp; quotes</h2></div><div class="card__body card__body--flush">@foreach($leads as $l)<a href="/admin/leads/{{ $l->id }}" class="stat-row" style="padding:8px 16px;color:inherit;text-decoration:none"><span><span class="badge badge--plain tiny">{{ $l->source }}</span> {{ \Illuminate\Support\Str::limit($l->message,50) }}</span><span class="badge badge--{{ $l->status }}">{{ $l->status }}</span></a>@endforeach</div></div>@endif
      @if($carts->count())<div class="card"><div class="card__head"><h2>Abandoned carts</h2></div><div class="card__body card__body--flush">@foreach($carts as $c)<div class="stat-row" style="padding:8px 16px"><span>{{ money($c->subtotal) }} · {{ count(json_decode($c->items ?: '[]', true) ?: []) }} line(s)</span><span class="small muted">{{ \Carbon\Carbon::parse($c->last_seen_at)->diffForHumans() }} · {{ $c->recovered_at ? 'recovered' : ($c->reminders_sent.' reminder(s)') }}</span></div>@endforeach</div></div>@endif
      @if($alerts->count())<div class="card"><div class="card__head"><h2>Stock alerts</h2></div><div class="card__body card__body--flush">@foreach($alerts as $a)<div class="stat-row" style="padding:8px 16px"><span><span class="badge badge--plain tiny">{{ $a->kind }}</span> {{ $a->label }} <span class="mono muted">{{ $a->ref }}</span></span><span class="small muted">{{ $a->notified_at ? 'notified' : 'waiting' }}</span></div>@endforeach</div></div>@endif
      @if($designs->count())<div class="card"><div class="card__head"><h2>Saved designs</h2></div><div class="card__body card__body--flush">@foreach($designs as $d)<div class="stat-row" style="padding:8px 16px"><span><b>{{ $d->title ?: $d->ref }}</b> <span class="mono muted small">{{ $d->ref }}</span></span><span class="small">{{ $d->price ? money($d->price) : '' }} <span class="muted">{{ \Carbon\Carbon::parse($d->created_at)->format('M j') }}</span></span></div>@endforeach</div></div>@endif
    </div>
    @endif
  </div>
  <div class="stack">
    @if($user)
    <form method="post" action="/admin/customers/{{ $user->id }}" class="card">@csrf
      <div class="card__head"><h2>Profile</h2></div>
      <div class="card__body stack">
        <div class="field"><label>Name</label><input class="input" name="name" value="{{ $user->name }}" required></div>
        <div class="field"><label>E-mail</label><input class="input" type="email" name="email" value="{{ $user->email }}" required></div>
        <div class="field"><label>Phone</label><input class="input" type="tel" name="phone" value="{{ $user->phone }}"></div>
        <div class="field"><label>Company</label><input class="input" name="company" value="{{ $user->company }}"></div>
        <div class="field"><label>Tags</label><div class="tagbox" data-tags><input type="hidden" name="tags" value="{{ $user->tags }}"><input type="text" placeholder="fleet, vip, net30…"></div></div>
        @if(adm_can('wholesale'))
        <div class="field"><label>Wholesale</label><select class="select" name="wholesale_status">@foreach(['none'=>'Retail','pending'=>'Application pending','approved'=>'Approved'] as $k=>$l)<option value="{{ $k }}" @selected($user->wholesale_status===$k)>{{ $l }}</option>@endforeach</select></div>
        <div class="form-grid"><div class="field"><label>Group</label><input class="input" name="wholesale_group" value="{{ $user->wholesale_group }}" placeholder="shop, fleet, dealer…"></div><div class="field"><label>Extra catalog discount %</label><input class="input num" type="number" step="0.5" min="0" max="90" name="wholesale_discount" value="{{ $user->wholesale_discount }}"><span class="hint">On top of the tier. Usually 0.</span></div></div>
        @php $bt = \App\Services\B2bAccounts::tierOf($user); [$tOk, $tOpen, $tOver, $tLimit, $tWhy] = \App\Services\B2bAccounts::terms($user); @endphp
        @if($bt)<p class="small" style="margin:0 0 8px">Tier: <b>{{ \App\Services\B2bAccounts::label($bt) }}</b> · this month ${{ number_format(\App\Services\B2bAccounts::spend($user->id, now('America/New_York')), 2) }} · open invoices ${{ number_format($tOpen, 2) }}@if($tOver) · <b style="color:var(--err,#c0392b)">{{ $tOver }} overdue</b>@endif</p>@endif
        <div class="form-grid"><div class="field"><label class="check"><input type="checkbox" name="net_terms" value="1" @checked($user->net_terms ?? true)> Pay by invoice (Net {{ \App\Services\B2bAccounts::termsDays() }})</label></div><div class="field"><label>Credit limit $</label><input class="input num" type="number" step="100" min="0" name="credit_limit" value="{{ $user->credit_limit }}" placeholder="{{ \App\Support\Settings::get('b2b.credit_limit', 10000) }} (default)"></div></div>
        @elseif($user->wholesale_status !== 'none')<p class="small muted" style="margin:0">Wholesale: <b>{{ $user->wholesale_status }}</b>@if((float) $user->wholesale_discount) · {{ (float) $user->wholesale_discount }}% extra @endif — a manager changes pricing, net terms and credit.</p>
        @endif
        <button class="btn btn--primary btn--block">Save</button>
        <div class="muted small">Joined {{ \Carbon\Carbon::parse($user->created_at)->format('M j, Y') }}@if($user->last_login_at) · last login {{ \Carbon\Carbon::parse($user->last_login_at)->diffForHumans() }}@endif @if($user->google_id) · Google sign-in @endif</div>
      </div>
    </form>
    {{-- Florida resale / exemption certificate (App\Support\SalesTax) --}}
    @php $txExp = $user->tax_cert_expires && \Carbon\Carbon::parse($user->tax_cert_expires)->endOfDay()->isPast(); @endphp
    <form method="post" action="/admin/customers/{{ $user->id }}/tax" class="card" id="tax" enctype="multipart/form-data">@csrf
      <div class="card__head"><h2>Florida tax exemption</h2>@if($user->tax_exempt_status === 'approved' && !$txExp)<span class="badge badge--ok">Tax exempt</span>@elseif($user->tax_exempt_status === 'pending')<span class="badge badge--warn">To review</span>@elseif($txExp)<span class="badge badge--warn">Expired</span>@endif</div>
      <div class="card__body stack">
        @if($user->tax_cert_file)<p class="small" style="margin:0"><a href="/admin/customers/{{ $user->id }}/tax-certificate" target="_blank" rel="noopener">{!! ic('ext') !!} Open the certificate they sent</a> · <a href="https://floridarevenue.com/taxes/certificates" target="_blank" rel="noopener">Verify the number</a></p>@else<p class="small muted" style="margin:0">No copy on file. Florida requires the DR-13 copy (or a verification number from <a href="https://floridarevenue.com/taxes/certificates" target="_blank" rel="noopener">floridarevenue.com</a>) for every tax-free sale.</p>@endif
        @if(adm_can('wholesale'))
        <div class="form-grid"><div class="field"><label>Status</label><select class="select" name="tax_exempt_status">@foreach(['none'=>'Pays tax','pending'=>'Sent, to review','approved'=>'Approved — tax exempt','rejected'=>'Not accepted'] as $k=>$l)<option value="{{ $k }}" @selected(($user->tax_exempt_status ?? 'none')===$k)>{{ $l }}</option>@endforeach</select></div>
          <div class="field"><label>Type</label><select class="select" name="tax_cert_kind"><option value="resale" @selected($user->tax_cert_kind !== 'exempt')>Annual Resale Certificate (DR-13)</option><option value="exempt" @selected($user->tax_cert_kind === 'exempt')>Certificate of Exemption</option></select></div></div>
        <div class="form-grid"><div class="field"><label>Certificate number</label><input class="input mono" name="tax_cert_number" value="{{ old('tax_cert_number', $user->tax_cert_number) }}" maxlength="40">@error('tax_cert_number')<span class="hint" style="color:var(--err,#c0392b)">{{ $message }}</span>@enderror</div>
          <div class="field"><label>Expires</label><input class="input" type="date" name="tax_cert_expires" value="{{ $user->tax_cert_expires }}"><span class="hint">Annual resale certificates end Dec 31.</span></div></div>
        <div class="field"><label>Note to the customer</label><input class="input" name="tax_cert_note" value="{{ $user->tax_cert_note }}" maxlength="255" placeholder="Shown on their account when not accepted"></div>
        <div class="field"><label>Replace the copy</label><input class="input" type="file" name="tax_cert_file" accept=".pdf,image/jpeg,image/png,image/webp"></div>
        <button class="btn btn--primary btn--block">Save exemption</button>
        @else
        <p class="small" style="margin:0">Status: <b>{{ $user->tax_exempt_status ?: 'pays tax' }}</b>@if($user->tax_cert_number) · <span class="mono">{{ $user->tax_cert_number }}</span>@endif — a manager approves exemptions.</p>
        @endif
      </div>
    </form>
    @else
    <div class="card"><div class="card__body"><p class="small">This customer checked out as a guest. <a href="/admin/customers/new?email={{ urlencode($email) }}">Create an account</a> to track them, approve wholesale pricing and let them see order history.</p></div></div>
    @endif
    @if($addresses->count())<div class="card"><div class="card__head"><h2>Addresses</h2></div><div class="card__body stack">@foreach($addresses as $a)<div class="addr small">{{ $a['name'] }}@if($a['company']) · {{ $a['company'] }}@endif
{{ $a['a1'] }} {{ $a['a2'] }}
{{ $a['city'] }}, {{ $a['state'] }} {{ $a['zip'] }} {{ $a['country'] }}@if($a['phone'])
{{ $a['phone'] }}@endif</div>@endforeach</div></div>@endif
    @if($dropship->count())<div class="card"><div class="card__head"><h2>Saved addresses</h2></div><div class="card__body stack">@foreach($dropship->sortBy(fn ($d) => $d->kind === 'dropship') as $d)<div class="addr small"><b>{{ ($d->kind ?? '') === 'dropship' ? 'Dropship' : 'Own' }}@if($d->is_default) · default @endif</b> {{ $d->label ?? '' }} {{ $d->name ?? '' }} {{ $d->company ?? '' }}
{{ $d->street ?? '' }} {{ $d->city ?? '' }} {{ $d->state ?? '' }} {{ $d->postcode ?? '' }}</div>@endforeach</div></div>@endif
    @if($activity->count())<div class="card"><div class="card__head"><h2>Admin changes</h2></div><div class="card__body"><ul class="timeline">@foreach($activity as $a)<li class="done"><b>{{ $a->label }}</b><small>{{ $a->user_name }} · {{ \Carbon\Carbon::parse($a->created_at)->format('M j, g:ia') }}</small></li>@endforeach</ul></div></div>@endif
  </div>
</div>
@endsection

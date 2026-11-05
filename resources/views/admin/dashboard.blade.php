@extends('admin.layout')
@section('title','Dashboard')
@section('sub')Here's what's happening at Universal Driveshafts — last {{ $days }} days. @endsection
@section('actions')
  <div class="btn-group">
    @foreach([7=>'7d',30=>'30d',90=>'90d',365=>'1y'] as $d=>$l)<a href="/admin?range={{ $d }}" class="btn btn--sm @if($days===$d) btn--dark @endif">{{ $l }}</a>@endforeach
  </div>
  <a href="/admin/orders/new" class="btn btn--primary btn--sm">{!! ic('plus') !!} New order</a>
@endsection
@section('body')
<div class="kpis kpis--6 mb">
  <div class="kpi"><span class="kpi__label" title="Products after coupons, less refunds, without shipping or tax (the WooCommerce Analytics figure). Refunds count on the day they were made; Eastern days. Cancelled, failed and unpaid orders are left out.">Net sales</span><span class="kpi__value">{{ money($k['sales']) }}</span>@include('admin.partials.delta',['cur'=>$k['sales'],'prev'=>$k['sales_prev'],'days'=>$days])</div>
  <div class="kpi"><span class="kpi__label">Orders</span><span class="kpi__value">{{ $k['orders'] }}</span>@include('admin.partials.delta',['cur'=>$k['orders'],'prev'=>$k['orders_prev'],'days'=>$days])</div>
  <div class="kpi"><span class="kpi__label">Avg. order</span><span class="kpi__value">{{ money($k['aov']) }}</span>@include('admin.partials.delta',['cur'=>$k['aov'],'prev'=>$k['aov_prev'],'days'=>$days])</div>
  <a href="/admin/orders?tab=unpaid" class="kpi {{ $k['unpaid_n'] ? 'kpi--warn' : '' }}"><span class="kpi__label">Awaiting payment</span><span class="kpi__value">{{ money($k['unpaid']) }}</span><span class="kpi__delta">{{ $k['unpaid_n'] }} open order{{ $k['unpaid_n']==1?'':'s' }}</span></a>
  <a href="/admin/orders?tab=to_ship" class="kpi {{ $k['to_ship'] ? 'kpi--warn' : '' }}"><span class="kpi__label">To ship</span><span class="kpi__value">{{ $k['to_ship'] }}</span><span class="kpi__delta">{{ $k['pickup'] }} for pickup</span></a>
  <a href="/admin/inventory?status=out" class="kpi {{ $k['out_stock'] ? 'kpi--err' : '' }}"><span class="kpi__label">Out of stock</span><span class="kpi__value">{{ $k['out_stock'] }}</span><span class="kpi__delta">{{ $k['low_stock'] }} at reorder point</span></a>
</div>

@if(adm_can('reports'))@include('admin.partials.traffic')@endif

<div class="grid grid--main">
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2>Sales</h2><div class="legend"><span><i style="background:var(--blue)"></i>Revenue</span></div></div>
      <div class="card__body"><svg class="chart" data-chart='{{ json_encode(['type'=>$days<=31?'bar':'line','labels'=>$labels,'series'=>[['name'=>'Sales','data'=>$sales]],'money'=>true,'height'=>220]) }}' role="img" aria-label="Sales over time"></svg></div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Recent orders</h2><a href="/admin/orders" class="btn btn--ghost btn--sm">All orders {!! ic('chev','ic--s') !!}</a></div>
      <div class="tbl-wrap"><table class="tbl tbl--cards">
        <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th class="right">Total</th><th class="hide-m">Placed</th></tr></thead>
        <tbody>
        @forelse($recentOrders as $o)
          <tr data-href="/admin/orders/{{ $o->id }}">
            <td class="primary"><span class="title mono">{{ $o->order_number }}</span></td>
            <td data-label="Customer">{{ $o->billing_first_name }} {{ $o->billing_last_name }}@if($o->billing_company) <span class="muted">· {{ $o->billing_company }}</span>@endif</td>
            <td class="status-cell"><span class="badge badge--{{ $o->status }}">{{ ucfirst($o->status) }}</span></td>
            <td data-label="Payment"><span class="badge badge--{{ $o->payment_status }} badge--plain">{{ ucfirst(str_replace('_',' ',$o->payment_status)) }}</span></td>
            <td class="right num" data-label="Total"><b>{{ money($o->total) }}</b></td>
            <td class="hide-m muted small">{{ \Carbon\Carbon::parse($o->created_at)->diffForHumans() }}</td>
          </tr>
        @empty<tr><td colspan="6"><div class="empty">{!! ic('orders') !!}<b>No orders yet</b>New orders show up here.</div></td></tr>@endforelse
        </tbody></table></div>
    </div>

    <div class="grid grid--2">
      <div class="card">
        <div class="card__head"><h2>Top products</h2><a href="/admin/reports" class="btn btn--ghost btn--sm">Reports</a></div>
        <div class="card__body card__body--flush">
          @forelse($topProducts as $p)
            <a class="stat-row" style="padding:9px 16px;text-decoration:none;color:inherit" href="{{ $p->wp_product_id ? '/admin/products/'.$p->wp_product_id : '/admin/search?q='.urlencode($p->sku) }}">
              <span class="flex gap-2">@if($p->image)<img src="{{ $p->image }}" class="thumb" alt="" width="40" height="40" style="width:40px;height:40px;object-fit:contain;border:1px solid var(--line);border-radius:7px">@endif<span><b class="mono">{{ $p->sku ?: '—' }}</b><br><span class="muted small">{{ \Illuminate\Support\Str::limit($p->name, 42) }}</span></span></span>
              <span class="right"><b>{{ money($p->t) }}</b><br><span class="muted small">{{ $p->q }} sold</span></span>
            </a>
          @empty<div class="empty">No sales in this period.</div>@endforelse
        </div>
      </div>
      <div class="card">
        <div class="card__head"><h2>Needs attention</h2></div>
        <div class="card__body card__body--flush">
          <a class="stat-row" style="padding:10px 16px;color:inherit;text-decoration:none" href="/admin/leads?tab=new"><span>{!! ic('inbox') !!} New leads & quote requests</span><b class="badge {{ $k['leads_new']?'badge--warn':'' }}">{{ $k['leads_new'] }}</b></a>
          <a class="stat-row" style="padding:10px 16px;color:inherit;text-decoration:none" href="/admin/rma"><span>{!! ic('undo') !!} Open returns / RMA</span><b class="badge {{ $k['rma_new']?'badge--warn':'' }}">{{ $k['rma_new'] }}</b></a>
          <a class="stat-row" style="padding:10px 16px;color:inherit;text-decoration:none" href="/admin/wholesale"><span>{!! ic('handshake') !!} Wholesale applications</span><b class="badge {{ $k['wholesale_pending']?'badge--warn':'' }}">{{ $k['wholesale_pending'] }}</b></a>
          <a class="stat-row" style="padding:10px 16px;color:inherit;text-decoration:none" href="/admin/recovery"><span>{!! ic('cart') !!} Abandoned carts (7d)</span><b>{{ $k['carts'] }} <span class="muted small">· {{ money($k['carts_value']) }}</span></b></a>
          <a class="stat-row" style="padding:10px 16px;color:inherit;text-decoration:none" href="/admin/customers?tab=new"><span>{!! ic('users') !!} New customers</span><b>{{ $k['customers_new'] }}</b></a>
          @if($pendingSpecs->count())
          <div style="padding:10px 16px;border-top:1px solid var(--line-2)">
            <div class="label" style="margin-bottom:6px">{!! ic('shaft','ic--s') !!} Driveshafts in open orders without a build spec</div>
            @foreach($pendingSpecs as $s)<a href="/admin/driveshafts/{{ rawurlencode(substr($s->sku,1)) }}" class="pill" style="margin:2px 4px 2px 0">{{ $s->sku }} <span class="muted">· {{ $s->order_number }}</span></a>@endforeach
          </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="stack">
    @if($lowStock->count())
    <div class="card">
      <div class="card__head"><h2>Reorder now</h2><a href="/admin/inventory?status=critical" class="btn btn--ghost btn--sm">Inventory</a></div>
      <div class="card__body card__body--flush">
        @foreach($lowStock as $i)<div class="stat-row" style="padding:8px 16px"><span><b class="mono">{{ $i->sku }}</b><br><span class="muted small">{{ \Illuminate\Support\Str::limit($i->name,40) }}</span></span><span class="badge badge--{{ $i->stock<=0?'err':'warn' }}">{{ $i->stock }} left</span></div>@endforeach
      </div>
    </div>
    @endif
    <div class="card">
      <div class="card__head"><h2>Latest leads</h2><a href="/admin/leads" class="btn btn--ghost btn--sm">All</a></div>
      <div class="card__body card__body--flush">
        @forelse($recentLeads as $l)
          <a class="stat-row" style="padding:9px 16px;color:inherit;text-decoration:none;align-items:flex-start" href="/admin/leads/{{ $l->id }}">
            <span><b>{{ $l->name ?: $l->email }}</b> <span class="badge badge--plain tiny">{{ $l->source }}</span><br><span class="muted small">{{ \Illuminate\Support\Str::limit($l->message ?: json_decode($l->data ?: '{}', true)['vehicle'] ?? '', 60) }}</span></span>
            <span class="badge badge--{{ $l->status }}">{{ $l->status }}</span>
          </a>
        @empty<div class="empty">No leads yet.</div>@endforelse
      </div>
    </div>
    <div class="card">
      <div class="card__head"><h2>Activity</h2><a href="/admin/activity" class="btn btn--ghost btn--sm">Log</a></div>
      <div class="card__body">
        <ul class="timeline">
          @forelse($activity as $a)<li class="done"><b>{{ $a->label }}</b><small>{{ $a->user_name ?: 'System' }} · {{ \Carbon\Carbon::parse($a->created_at)->diffForHumans() }}</small></li>
          @empty<li>No admin activity recorded yet.</li>@endforelse
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection

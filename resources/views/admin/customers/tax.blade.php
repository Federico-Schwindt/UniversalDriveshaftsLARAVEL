@extends('admin.layout')
@section('title','Sales tax')
@section('crumb')<a href="/admin/customers">Customers</a>@endsection
@section('sub')Florida sales tax collected on the site, for the DR-15 return, and the resale / exemption certificates that take it off an account's orders. @endsection
@section('body')
<div class="card mb"><div class="card__head"><h2>Collected by month</h2><span class="small muted">Paid orders (and invoice orders) delivered in Florida or picked up · Eastern time · refunds not subtracted</span></div>
<div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Month</th><th class="num">Orders</th><th class="num">Taxable sales</th><th class="num">Exempt sales</th><th class="num">Tax collected</th><th></th></tr></thead><tbody>
  @forelse($months as $m)
  <tr><td class="primary">{{ \Carbon\Carbon::createFromFormat('Y-m', $m->m)->format('F Y') }}</td><td class="num" data-label="Orders">{{ $m->n }}</td><td class="num" data-label="Taxable">{{ money($m->taxable) }}</td><td class="num" data-label="Exempt">{{ money($m->exempt) }}</td><td class="num" data-label="Tax"><b>{{ money($m->tax) }}</b></td><td class="actions"><a class="btn btn--sm" href="/admin/tax-exemptions?csv={{ $m->m }}">{!! ic('download') !!} CSV</a></td></tr>
  @empty<tr><td colspan="6"><div class="empty">{!! ic('dollar') !!}<b>No Florida sales yet</b><span>Orders delivered in Florida or picked up at the shop show here once paid.</span></div></td></tr>@endforelse
  </tbody></table></div>
  @if($counties->count())<div class="card__body"><p class="small" style="margin:0 0 6px"><b>{{ \Carbon\Carbon::createFromFormat('Y-m', $months->first()->m)->format('F Y') }} by county</b> (the surtax is reported per county)</p>
  <p class="small muted" style="margin:0">@foreach($counties as $c){{ $c->c }} {{ rtrim(rtrim(number_format($c->rate, 3), '0'), '.') }}%: {{ money($c->taxable) }} taxable, {{ money($c->tax) }} tax @if(!$loop->last)· @endif @endforeach</p></div>@endif
</div>
<p class="small muted" style="margin:0 0 8px">An approved, unexpired certificate takes Florida tax off that account's orders. Check a number at <a href="https://floridarevenue.com/taxes/certificates" target="_blank" rel="noopener">floridarevenue.com/taxes/certificates</a> or 877-357-3725.</p>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Customer</th><th>Certificate</th><th>Expires</th><th>Status</th></tr></thead><tbody>
  @forelse($rows as $u)
  @php $expired = $u->tax_cert_expires && \Carbon\Carbon::parse($u->tax_cert_expires)->endOfDay()->isPast(); @endphp
  <tr><td class="primary"><a href="/admin/customers/{{ $u->id }}#tax" class="title" style="color:inherit">{{ $u->name }}</a><small>{{ $u->email }}@if($u->company) · {{ $u->company }}@endif</small></td>
    <td data-label="Certificate" class="small"><span class="mono">{{ $u->tax_cert_number ?: '—' }}</span> · {{ $u->tax_cert_kind === 'exempt' ? 'Exemption' : 'Resale (DR-13)' }}</td>
    <td data-label="Expires" class="small">{{ $u->tax_cert_expires ? \Carbon\Carbon::parse($u->tax_cert_expires)->format('M j, Y') : '—' }}@if($expired) <span class="badge badge--warn">expired</span>@endif</td>
    <td class="status-cell"><span class="badge {{ $u->tax_exempt_status === 'approved' && !$expired ? 'badge--ok' : ($u->tax_exempt_status === 'pending' ? 'badge--warn' : 'badge--plain') }}">{{ ucfirst($u->tax_exempt_status) }}</span></td>
  </tr>
  @empty<tr><td colspan="4"><div class="empty">{!! ic('dollar') !!}<b>No certificates yet</b><span>Customers send them from Account → Settings; staff can add one on the customer page.</span></div></td></tr>@endforelse
  </tbody></table></div></div>
@endsection

@extends('admin.layout')
@section('title','New customer')
@section('crumb')<a href="/admin/customers">Customers</a>@endsection
@section('body')
<form method="post" action="/admin/customers" class="card" style="max-width:720px">@csrf
  <div class="card__body form-grid">
    <div class="field"><label>Name</label><input class="input" name="name" value="{{ old('name') }}" required></div>
    <div class="field"><label>E-mail</label><input class="input" type="email" name="email" value="{{ old('email', request('email')) }}" required></div>
    <div class="field"><label>Phone</label><input class="input" type="tel" name="phone" value="{{ old('phone') }}"></div>
    <div class="field"><label>Company</label><input class="input" name="company" value="{{ old('company') }}"></div>
    <div class="field span-2"><label>Tags</label><div class="tagbox" data-tags><input type="hidden" name="tags" value="{{ old('tags') }}"><input type="text" placeholder="fleet, vip…"></div></div>
    @if(adm_can('wholesale'))
    <div class="field"><label>Wholesale</label><select class="select" name="wholesale_status"><option value="none">Retail</option><option value="pending">Pending</option><option value="approved">Approved</option></select></div>
    <div class="field"><label>Discount %</label><input class="input" type="number" step="0.5" min="0" max="90" name="wholesale_discount" value="0"></div>
    <div class="field"><label>Group</label><input class="input" name="wholesale_group"></div>
    @endif
    <label class="check span-2"><input type="checkbox" name="send_welcome" value="1" checked> E-mail a welcome message with a temporary password</label>
  </div>
  <div class="card__foot"><a href="/admin/customers" class="btn">Cancel</a><button class="btn btn--primary">Create customer</button></div>
</form>
@endsection

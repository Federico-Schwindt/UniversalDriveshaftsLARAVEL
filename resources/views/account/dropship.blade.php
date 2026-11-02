@extends('account.layout')
@section('active','dropship')
@section('atitle','Dropship Addresses')
@section('acct')
<style>
.ds-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.ds-card{border:1px solid var(--line,#e3e8f0);border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 1px 3px rgba(16,32,64,.05)}
.ds-card .ds-h{display:flex;align-items:center;justify-content:space-between;gap:8px;background:#22b8d6;color:#fff;padding:11px 14px}
.ds-card .ds-h b{font-size:.92rem;font-weight:800;text-transform:uppercase;letter-spacing:.01em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ds-card .ds-h .acts{display:flex;gap:6px;flex:none}
.ds-card .ds-h button{width:30px;height:30px;border:0;border-radius:7px;cursor:pointer;font-size:.85rem;color:#fff}
.ds-card .ds-h .del{background:#e0503a}
.ds-card .ds-b{padding:14px 16px;color:var(--ink-soft,#4a5568);font-size:.9rem;line-height:1.6}
.ds-card .ds-b .res{display:inline-block;margin-top:6px;font-size:.72rem;background:var(--blue-soft,#eaf0fc);color:var(--blue,#1246ab);border-radius:20px;padding:2px 10px;font-weight:700}
.ds-modal{position:fixed;inset:0;background:rgba(15,27,51,.55);z-index:200;display:none;align-items:flex-start;justify-content:center;padding:40px 16px;overflow:auto}
.ds-modal.on{display:flex}
.ds-modal .box{background:#fff;border-radius:16px;max-width:680px;width:100%;box-shadow:0 30px 80px rgba(0,0,0,.35);position:relative}
.ds-modal h3{margin:0;padding:20px 24px 0;font-size:1.3rem}
.ds-modal .x{position:absolute;top:16px;left:18px;background:none;border:0;font-size:1.5rem;color:#8a94a6;cursor:pointer}
.ds-modal form{padding:16px 24px 24px}
.ds-modal hr{border:0;border-top:1px solid var(--line);margin:14px 0 18px}
.dsg{display:grid;grid-template-columns:2fr 1fr;gap:14px}
.dsg .full{grid-column:1/-1}
.dsg .g4{display:grid;grid-template-columns:2fr .7fr .9fr 1fr;gap:12px;grid-column:1/-1}
.ds-modal label{display:block;font-size:.78rem;font-weight:700;color:var(--ink-soft);margin:0 0 5px}
.ds-modal input,.ds-modal select{width:100%;padding:10px 12px;border:1px solid var(--line-2,#d3dcea);border-radius:9px;font-size:.92rem;font-family:inherit}
.ds-chk{display:flex;align-items:center;gap:8px;margin-top:14px;font-size:.9rem;cursor:pointer}
@media(max-width:620px){.dsg,.dsg .g4{grid-template-columns:1fr}}
</style>
<div class="acct-h">
  <div><h1>{{ __('Dropship Addresses') }}</h1><p>{{ __('Save the addresses you regularly ship to, pick any of them at checkout.') }}</p></div>
  <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('ds-modal').classList.add('on')"><x-icon name="plus"/> {{ __('Add dropship address') }}</button>
</div>

@if(count($addresses))
<div class="ds-grid">
  @foreach($addresses as $a)
    <div class="ds-card">
      <div class="ds-h"><b>{{ $a->company }}</b>
        <div class="acts">
          <form method="POST" action="/account/dropship/{{ $a->id }}/delete" onsubmit="return confirm({{ json_encode(__('Delete this address?')) }})" style="margin:0">@csrf<button class="del" title="{{ __('Delete') }}"><x-icon name="x"/></button></form>
        </div>
      </div>
      <div class="ds-b">
        {{ $a->street }}@if($a->unit), {{ $a->unit }}@endif<br>
        {{ $a->city }}, {{ $a->state }} {{ $a->postcode }} · {{ $a->country }}<br>
        @if($a->phone){{ $a->phone }}@endif
        @if($a->residential)<span class="res">{{ __('Residential') }}</span>@endif
      </div>
    </div>
  @endforeach
</div>
@else
<div class="apanel"><div class="aempty"><span class="ei"><x-icon name="package"/></span>{{ __('No dropship addresses yet.') }}<br><a href="#" onclick="document.getElementById('ds-modal').classList.add('on');return false;">{{ __('Add your first one') }} <x-icon name="arrow-right"/></a></div></div>
@endif

<div class="ds-modal" id="ds-modal">
  <div class="box">
    <button type="button" class="x" onclick="document.getElementById('ds-modal').classList.remove('on')"><x-icon name="x"/></button>
    <h3>{{ __('Add Dropship Address') }}</h3>
    <form method="POST" action="/account/dropship">@csrf
      <hr>
      <div class="dsg">
        <div class="full"><label>{{ __('Company / Name *') }}</label><input type="text" name="company" required></div>
        <div class="full"><label>{{ __('Street *') }}</label><input type="text" name="street" required></div>
        <div class="g4">
          <div><label>{{ __('City *') }}</label><input type="text" name="city" required></div>
          <div><label>{{ __('ST') }}</label><input type="text" name="state" placeholder="FL" maxlength="2"></div>
          <div><label>{{ __('Zip *') }}</label><input type="text" name="postcode" required></div>
          <div><label>{{ __('Country') }}</label><select name="country">@foreach(config('countries') as $cc=>$cn)<option value="{{ $cc }}" @selected($cc==='US')>{{ $cc }}</option>@endforeach</select></div>
        </div>
        <div><label>{{ __('Unit') }}</label><input type="text" name="unit"></div>
        <div><label>{{ __('Phone') }}</label><input type="tel" name="phone"></div>
        <label class="ds-chk full"><input type="checkbox" name="residential" value="1" style="width:16px;height:16px;accent-color:var(--blue)"> {{ __('Residential address') }}</label>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('ds-modal').classList.remove('on')">{{ __('Cancel') }}</button>
        <button type="submit" class="btn btn-primary">{{ __('Add Address') }}</button>
      </div>
    </form>
  </div>
</div>
@endsection

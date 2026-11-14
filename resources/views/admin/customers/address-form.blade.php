{{-- one saved address (Account → Addresses book), edited by the shop: $user, $a (null = new) --}}
<form method="post" action="/admin/customers/{{ $user->id }}/addresses" class="stack" style="margin-top:8px">@csrf
  @if($a)<input type="hidden" name="address_id" value="{{ $a->id }}">@endif
  <div class="form-grid">
    <div class="field"><label>Contact name</label><input class="input" name="name" value="{{ $a->name ?? $user->name }}"></div>
    <div class="field"><label>Company</label><input class="input" name="company" value="{{ $a->company ?? '' }}"></div>
  </div>
  <div class="field"><label>Street</label><input class="input" name="street" value="{{ $a->street ?? '' }}" required></div>
  <div class="form-grid">
    <div class="field"><label>Unit / suite</label><input class="input" name="unit" value="{{ $a->unit ?? '' }}"></div>
    <div class="field"><label>City</label><input class="input" name="city" value="{{ $a->city ?? '' }}" required></div>
    <div class="field"><label>State</label><input class="input" name="state" value="{{ $a->state ?? '' }}" maxlength="60"></div>
    <div class="field"><label>ZIP</label><input class="input" name="postcode" value="{{ $a->postcode ?? '' }}" required></div>
    <div class="field"><label>Country</label><input class="input" name="country" value="{{ $a->country ?? 'US' }}" maxlength="2" required></div>
    <div class="field"><label>Phone</label><input class="input" type="tel" name="phone" value="{{ $a->phone ?? '' }}"></div>
  </div>
  <div class="form-grid">
    <div class="field"><label>Type</label><select class="select" name="kind"><option value="shipping" @selected(($a->kind ?? 'shipping') !== 'dropship')>Their own address</option><option value="dropship" @selected(($a->kind ?? '') === 'dropship')>Dropship (their customer)</option></select></div>
    <div class="field"><label>Label</label><input class="input" name="label" value="{{ $a->label ?? '' }}" placeholder="Shop, warehouse…"></div>
  </div>
  <label class="check"><input type="checkbox" name="residential" value="1" @checked($a->residential ?? true)> Residential</label>
  <label class="check"><input type="checkbox" name="is_default" value="1" @checked($a->is_default ?? false)> Default shipping address (checkout prefills it)</label>
  <button class="btn btn--primary btn--sm">{{ $a ? 'Save address' : 'Add address' }}</button>
</form>

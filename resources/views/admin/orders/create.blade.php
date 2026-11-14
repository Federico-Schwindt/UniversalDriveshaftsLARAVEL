@extends('admin.layout')
@section('title','New order')
@section('crumb')<a href="/admin/orders">Orders</a> › Create @endsection
@section('sub')Phone, counter and wholesale orders. Prices default to the catalog; override any line. @endsection
@php $v = fn($k, $d='') => old($k, $prefill[$k] ?? ($customer ? ($k==='email'?$customer->email:($k==='phone'?$customer->phone:($k==='billing_company'?$customer->company:($k==='billing_first_name'?explode(' ',$customer->name)[0]:($k==='billing_last_name'?trim(strstr($customer->name,' ') ?: ''):$d))))) : $d)); @endphp
@section('body')
<form method="post" action="/admin/orders/new" id="ob" class="grid grid--main">@csrf
  <div class="stack">
    <div class="card">
      <div class="card__head"><h2>1 · Customer</h2><a href="/admin/customers/new" class="btn btn--ghost btn--sm" target="_blank">New account ↗</a></div>
      <div class="card__body stack">
        <div class="field"><label>Find an existing customer</label><div class="combo"><input class="input" placeholder="Name, e-mail, phone or company…" autocomplete="off" id="ob-cust"></div><span class="hint">Picks fill the fields below; you can still edit them. Guests from past orders are included.</span></div>
        <input type="hidden" name="user_id" id="ob-uid" value="{{ $customer->id ?? '' }}">
        <div class="form-grid">
          <div class="field"><label>First name</label><input class="input" name="billing_first_name" value="{{ $v('billing_first_name') }}" required></div>
          <div class="field"><label>Last name</label><input class="input" name="billing_last_name" value="{{ $v('billing_last_name') }}"></div>
          <div class="field"><label>E-mail</label><input class="input" type="email" name="email" value="{{ $v('email') }}" required></div>
          <div class="field"><label>Phone</label><input class="input" type="tel" name="phone" value="{{ $v('phone') }}"></div>
          <div class="field span-2"><label>Company</label><input class="input" name="billing_company" value="{{ $v('billing_company') }}"></div>
        </div>
        <label class="check"><input type="checkbox" name="create_account" value="1"> Create a customer account for this e-mail if none exists</label>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>2 · Items</h2></div>
      <div class="card__body stack">
        <div class="field"><div class="combo"><input class="input" placeholder="Add a product or complete driveshaft — SKU, name, part number…" autocomplete="off" id="ob-item"></div></div>
        <div class="tbl-wrap"><table class="tbl tbl--compact" id="ob-lines">
          <thead><tr><th></th><th>Item</th><th style="width:90px">Qty</th><th style="width:130px">Unit price</th><th class="right">Line</th><th></th></tr></thead>
          <tbody></tbody>
        </table></div>
        <div class="flex flex-wrap"><button type="button" class="btn btn--sm" id="ob-custom">{!! ic('plus') !!} Custom line (service, freight, core…)</button><span class="muted small">Catalog prices are applied automatically; wholesale discounts are not — set the price per line.</span></div>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>3 · Shipping address</h2></div>
      <div class="card__body stack">
        <label class="check"><input type="checkbox" name="ship_to_different" value="1" id="ob-diff"> Ship to a different address than billing</label>
        <div class="form-grid" id="ob-bill">
          <div class="field span-2"><label>Billing address</label><input class="input" name="billing_address_1" value="{{ $v('billing_address_1') }}" placeholder="Street"></div>
          <div class="field span-2"><input class="input" name="billing_address_2" value="{{ $v('billing_address_2') }}" placeholder="Apt, suite, unit…"></div>
          <div class="field"><label>City</label><input class="input" name="billing_city" value="{{ $v('billing_city') }}"></div>
          <div class="field"><label>State</label><input class="input" name="billing_state" value="{{ $v('billing_state') }}" placeholder="FL"></div>
          <div class="field"><label>ZIP</label><input class="input" name="billing_postcode" value="{{ $v('billing_postcode') }}"></div>
          <div class="field"><label>Country</label><input class="input" name="billing_country" value="{{ $v('billing_country','US') }}" maxlength="2"></div>
        </div>
        <div class="form-grid" id="ob-ship" hidden>
          <div class="field"><label>First name</label><input class="input" name="shipping_first_name" value="{{ $v('shipping_first_name') }}"></div>
          <div class="field"><label>Last name</label><input class="input" name="shipping_last_name" value="{{ $v('shipping_last_name') }}"></div>
          <div class="field span-2"><label>Address</label><input class="input" name="shipping_address_1" value="{{ $v('shipping_address_1') }}"></div>
          <div class="field span-2"><input class="input" name="shipping_address_2" value="{{ $v('shipping_address_2') }}"></div>
          <div class="field"><label>City</label><input class="input" name="shipping_city" value="{{ $v('shipping_city') }}"></div>
          <div class="field"><label>State</label><input class="input" name="shipping_state" value="{{ $v('shipping_state') }}"></div>
          <div class="field"><label>ZIP</label><input class="input" name="shipping_postcode" value="{{ $v('shipping_postcode') }}"></div>
          <div class="field"><label>Country</label><input class="input" name="shipping_country" value="{{ $v('shipping_country','US') }}" maxlength="2"></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>4 · Shipping method</h2><button type="button" class="btn btn--sm" id="ob-quote">{!! ic('truck') !!} Get live rates</button></div>
      <div class="card__body stack" id="ob-shipopts">
        <div class="muted small">Add items and a ZIP, then get rates. Or choose a manual option below.</div>
        <label class="check"><input type="radio" name="shipping_method" value="none" data-cost="0" data-label="No shipping charge" checked> No shipping charge</label>
        <label class="check"><input type="radio" name="shipping_method" value="{{ \App\Services\CartService::PICKUP_ID }}" data-cost="0" data-label="{{ config('uds.pickup.label') }}"> {{ config('uds.pickup.label') }}</label>
        <label class="check"><input type="radio" name="shipping_method" value="manual" data-cost="" data-label="Shipping"> Custom amount</label>
      </div>
      <div class="card__foot"><div class="form-grid" style="width:100%"><div class="field"><label>Shipping charge</label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" min="0" name="shipping_total" id="ob-shipcost" value="0"></div></div><div class="field"><label>Label on the order</label><input class="input" name="shipping_label" id="ob-shiplabel" value="No shipping charge"></div></div></div>
    </div>

    <div class="card">
      <div class="card__head"><h2>5 · Notes &amp; options</h2></div>
      <div class="card__body form-grid">
        <div class="field"><label>PO number</label><input class="input" name="po_number" value="{{ $v('po_number') }}"></div>
        <div class="field"><label>Tags</label><div class="tagbox" data-tags><input type="hidden" name="tags" value="{{ $v('tags') }}"><input type="text" placeholder="add tag…"></div></div>
        <div class="field span-2"><label>Shipping instructions <span class="muted">(printed on the packing slip)</span></label><input class="input" name="ship_instructions" value="{{ $v('ship_instructions') }}"></div>
        <div class="field span-2"><label>Customer note <span class="muted">(visible to the customer)</span></label><textarea class="input" name="customer_note" rows="2">{{ $v('customer_note') }}</textarea></div>
        <div class="field span-2"><label>Internal note</label><textarea class="input" name="internal_note" rows="2"></textarea></div>
        <label class="check"><input type="checkbox" name="signature_required" value="1"> Signature required</label>
        @if(\App\Services\SmsService::on())<label class="check"><input type="checkbox" name="sms_opt_in" value="1"> Customer wants SMS updates</label>@endif
      </div>
    </div>
  </div>

  <div class="stack sticky">
    <div class="card">
      <div class="card__head"><h2>Summary</h2></div>
      <div class="card__body stack">
        <div class="kv"><span>Subtotal</span><b id="ob-sub">$0.00</b></div>
        <div class="field"><label>Discount</label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" min="0" name="discount_total" id="ob-disc" value="0"><input class="input" name="coupon_code" placeholder="code" style="max-width:110px"></div></div>
        <div class="field"><label>Tax</label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" min="0" name="tax_total" id="ob-tax" value="0"></div></div>
        <div class="kv"><span>Shipping</span><b id="ob-shipsum">$0.00</b></div>
        <div class="kv kv--total"><span>Total</span><b id="ob-total">$0.00</b></div>
      </div>
    </div>
    <div class="card">
      <div class="card__head"><h2>Payment &amp; status</h2></div>
      <div class="card__body stack">
        <div class="field"><label>Payment status</label><select class="select" name="payment_status"><option value="unpaid">Unpaid — collect later</option><option value="paid">Paid</option></select></div>
        <div class="field"><label>Payment method</label><select class="select" name="payment_method">@foreach(\App\Http\Controllers\Admin\OrdersController::PAY_METHODS as $k=>$l) @continue($k==='stripe' || $k==='net30')<option value="{{ $k }}" @selected($k==='call')>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>Order status</label><select class="select" name="status"><option value="pending">Pending</option><option value="processing">Processing</option><option value="completed">Completed</option></select></div>
        <div class="field"><label>Source</label><select class="select" name="source"><option value="phone">Phone</option><option value="admin">Counter / in person</option><option value="wholesale">Wholesale</option></select></div>
        <label class="check"><input type="checkbox" name="send_email" value="1" checked> E-mail the confirmation to the customer</label>
        <label class="check"><input type="checkbox" name="send_pay_link" value="1" checked> E-mail a <b>payment link</b> if the order is unpaid <small>Also texted when "SMS updates" is on. The link always charges the current balance.</small></label>
        <button class="btn btn--primary btn--block" type="submit">{!! ic('check') !!} Create order</button>
      </div>
    </div>
  </div>
</form>
@endsection
@push('scripts')
<script>
(function () {
  var f = document.getElementById('ob'), tb = document.querySelector('#ob-lines tbody'), lines = {{ \Illuminate\Support\Js::from(old('lines', $prefill['lines'] ?? [])) }};
  var money = function (v) { return '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  function render() {
    tb.innerHTML = lines.map(function (l, i) {
      var n = 'lines[' + i + ']';
      return '<tr><td class="w-img">' + (l.image ? '<img src="' + admEsc(l.image) + '" class="thumb" alt="">' : '') + '</td>' +
        '<td><input type="hidden" name="' + n + '[type]" value="' + admEsc(l.type) + '"><input type="hidden" name="' + n + '[id]" value="' + admEsc(l.id || '') + '"><input type="hidden" name="' + n + '[sku]" value="' + admEsc(l.sku || '') + '">' +
        (l.type === 'custom' ? '<input class="input input--sm" name="' + n + '[name]" value="' + admEsc(l.name) + '" placeholder="Description" data-i="' + i + '" data-k="name">' : '<div class="title">' + admEsc(l.sku) + '<small>' + admEsc(l.name) + '</small></div>') + '</td>' +
        '<td><input class="input input--sm num" type="number" min="1" name="' + n + '[qty]" value="' + (l.qty || 1) + '" data-i="' + i + '" data-k="qty"></td>' +
        '<td><div class="input-group"><span class="addon">$</span><input class="input input--sm num" type="number" step="0.01" min="0" name="' + n + '[price]" value="' + Number(l.price || 0).toFixed(2) + '" data-i="' + i + '" data-k="price"></div></td>' +
        '<td class="right num"><b>' + money((l.qty || 1) * (l.price || 0)) + '</b></td>' +
        '<td class="actions"><button type="button" class="btn btn--xs btn--icon btn--danger" data-rm="' + i + '"><svg class="ic ic--s"><use href="#i-trash"/></svg></button></td></tr>';
    }).join('') || '<tr><td colspan="6" class="muted center" style="padding:18px">No items yet — search above.</td></tr>';
    totals();
  }
  function totals() {
    var sub = lines.reduce(function (s, l) { return s + (l.qty || 1) * (l.price || 0); }, 0);
    var ship = parseFloat(document.getElementById('ob-shipcost').value) || 0, disc = parseFloat(document.getElementById('ob-disc').value) || 0, tax = parseFloat(document.getElementById('ob-tax').value) || 0;
    document.getElementById('ob-sub').textContent = money(sub); document.getElementById('ob-shipsum').textContent = money(ship); document.getElementById('ob-total').textContent = money(Math.max(0, sub - disc) + tax + ship);
  }
  tb.addEventListener('input', function (e) { var i = e.target.dataset.i, k = e.target.dataset.k; if (i === undefined) return; lines[i][k] = k === 'name' ? e.target.value : parseFloat(e.target.value) || 0; if (k !== 'name') { var row = e.target.closest('tr'); row.querySelector('td.right b').textContent = money((lines[i].qty || 1) * (lines[i].price || 0)); totals(); } });
  tb.addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (b) { lines.splice(+b.dataset.rm, 1); render(); } });
  ['ob-shipcost', 'ob-disc', 'ob-tax'].forEach(function (id) { document.getElementById(id).addEventListener('input', totals); });
  document.getElementById('ob-custom').addEventListener('click', function () { lines.push({ type: 'custom', name: '', qty: 1, price: 0 }); render(); var last = tb.querySelector('tr:last-child input[data-k=name]'); if (last) last.focus(); });
  admCombo(document.getElementById('ob-item'), { url: '/admin/api/products?q=',
    render: function (it) { return (it.image ? '<img src="' + admEsc(it.image) + '" alt="">' : '<svg class="ic"><use href="#i-' + (it.type === 'driveshaft' ? 'shaft' : 'box') + '"/></svg>') + '<span class="t"><b>' + admEsc(it.sku) + '</b><small>' + admEsc(it.name) + '</small></span><span class="p">' + money(it.price) + (it.stock !== null && it.stock !== undefined ? ' <small class="muted">· ' + it.stock + ' in stock</small>' : '') + '</span>'; },
    onPick: function (it) { var ex = lines.find(function (l) { return l.sku === it.sku && l.type === it.type; }); if (ex) ex.qty = (ex.qty || 1) + 1; else lines.push({ type: it.type, id: it.id, sku: it.sku, name: it.name, qty: 1, price: it.price, image: it.image }); render(); }
  });
  admCombo(document.getElementById('ob-cust'), { url: '/admin/api/customers?q=', clear: true,
    render: function (c) { return '<span class="avatar" style="width:30px;height:30px;font-size:12px">' + admEsc((c.name || c.email || '?').charAt(0).toUpperCase()) + '</span><span class="t"><b>' + admEsc(c.name || c.email) + (c.company ? ' · ' + admEsc(c.company) : '') + '</b><small>' + admEsc(c.email) + (c.phone ? ' · ' + admEsc(c.phone) : '') + '</small></span><span class="p">' + (c.wholesale ? '<span class="badge badge--ok tiny">Wholesale ' + c.wholesale + '%</span>' : (c.source === 'order' ? '<span class="badge badge--plain tiny">guest</span>' : '')) + '</span>'; },
    onPick: function (c) {
      var set = function (n, v) { var el = f.elements[n]; if (el && v != null) el.value = v; };
      document.getElementById('ob-uid').value = c.id || '';
      var parts = (c.name || '').split(' '); set('billing_first_name', parts.shift()); set('billing_last_name', parts.join(' ')); set('email', c.email); set('phone', c.phone); set('billing_company', c.company);
      var apply = function (a) { if (!a) return; set('billing_first_name', a.first_name); set('billing_last_name', a.last_name); set('billing_company', a.company); set('billing_address_1', a.address_1); set('billing_address_2', a.address_2); set('billing_city', a.city); set('billing_state', a.state); set('billing_postcode', a.postcode); set('billing_country', a.country || 'US'); };
      if (c.address) apply(c.address); else if (c.id) fetch('/admin/api/customers/' + c.id + '/address', { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) { apply(d.address); }).catch(function () {});
      if (c.wholesale) f.elements.source.value = 'wholesale';
      admToast('Customer loaded: ' + (c.name || c.email), 'ok');
    }
  });
  document.getElementById('ob-diff').addEventListener('change', function () { document.getElementById('ob-ship').hidden = !this.checked; });
  function shipChange() { var r = f.querySelector('input[name=shipping_method]:checked'); if (!r) return; if (r.dataset.cost !== '') document.getElementById('ob-shipcost').value = Number(r.dataset.cost).toFixed(2); document.getElementById('ob-shiplabel').value = r.dataset.label || ''; totals(); }
  document.getElementById('ob-shipopts').addEventListener('change', shipChange);
  document.getElementById('ob-quote').addEventListener('click', function () {
    var btn = this; btn.classList.add('is-loading');
    var pre = document.getElementById('ob-diff').checked ? 'shipping' : 'billing';
    var addr = { country: f.elements[pre + '_country'].value, state: f.elements[pre + '_state'].value, postcode: f.elements[pre + '_postcode'].value };
    admPost('/admin/orders/quote', { lines: lines, address: addr }).then(function (d) {
      btn.classList.remove('is-loading');
      var box = document.getElementById('ob-shipopts');
      box.innerHTML = '<div class="muted small">Rates for ' + admEsc(addr.postcode || 'no ZIP') + ' · parts subtotal counting toward free shipping: ' + money(d.free_base) + '</div>' + d.options.map(function (o) {
        return '<label class="check"><input type="radio" name="shipping_method" value="' + admEsc(o.id) + '" data-cost="' + (o.manual ? '' : o.cost) + '" data-label="' + admEsc(o.label) + '"> ' + admEsc(o.label) + (o.manual ? '' : ' — <b>' + money(o.cost) + '</b>') + '</label>';
      }).join('');
      var first = box.querySelector('input'); if (first) { first.checked = true; shipChange(); }
    }).catch(function (e) { btn.classList.remove('is-loading'); admToast((e && e.message) || 'Could not get rates', 'err'); });
  });
  render();
})();
</script>
@endpush

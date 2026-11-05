@extends('admin.layout')
@section('title', 'Chat with ' . ($customer->name ?? 'customer'))
@section('crumb')<a href="/admin/chats">Assistant chats</a>@endsection
@section('actions')
  @if($c->status === 'open')
    @if($c->mode === 'human')
      <a href="#" class="btn btn--sm" data-post="/admin/chats/{{ $c->id }}/release">{!! ic('undo') !!} Return to AI</a>
    @else
      <a href="#" class="btn btn--primary btn--sm" data-post="/admin/chats/{{ $c->id }}/takeover">{!! ic('users') !!} Take over</a>
    @endif
    <a href="#" class="btn btn--sm" data-post="/admin/chats/{{ $c->id }}/close" data-confirm="Close this chat? The customer starts a new one next time.">{!! ic('x') !!} Close</a>
  @endif
@endsection
@section('body')
<style>
.achat{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;align-items:start}
.achat-log{display:flex;flex-direction:column;gap:10px;padding:16px;height:58vh;min-height:360px;overflow-y:auto;background:var(--paper-2)}
.am{max-width:78%;padding:10px 14px;border-radius:14px;font-size:14px;line-height:1.5;white-space:pre-line;background:#fff;border:1px solid var(--line)}
.am.user{align-self:flex-start;background:#fff}
.am.assistant{align-self:flex-end;background:var(--blue-050);border-color:var(--blue-100)}
.am.agent{align-self:flex-end;background:#fff8e6;border-color:#f1d9a5}
.am.system{align-self:center;background:none;border:0;color:var(--ink-3);font-size:12px;padding:2px}
.am .who{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-3);margin-bottom:3px}
.am .when{display:block;font-size:11px;color:var(--ink-3);margin-top:4px}
.am .ph{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}.am .ph img{width:140px;height:140px;object-fit:cover;border-radius:10px;border:1px solid var(--line)}
.am .cards{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}.am .cards a{display:flex;gap:8px;align-items:center;font-size:12px;padding:5px 8px 5px 5px;border-radius:9px;background:#fff;border:1px solid var(--line);text-decoration:none;color:var(--ink)}
.am .cards img{width:34px;height:34px;object-fit:contain;border-radius:6px;background:var(--paper-2)}
.am .wtrack{margin-top:8px;padding:8px 10px;border-radius:10px;background:#fff;border:1px solid var(--line);font-size:12.5px;white-space:normal}
/* composer */
.achat-reply{border-top:1px solid var(--line);padding:10px 12px;display:flex;flex-direction:column;gap:8px}
.achat-reply textarea{width:100%;min-height:52px;max-height:180px;resize:vertical}
.achat-tools{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.achat-tools .grow{flex:1}
.achat-tray{display:flex;gap:6px;flex-wrap:wrap}
.achat-tray:empty{display:none}
.achat-chip{display:inline-flex;align-items:center;gap:6px;padding:4px 6px 4px 4px;border:1px solid var(--line);border-radius:9px;background:var(--paper-2);font-size:12.5px}
.achat-chip img{width:30px;height:30px;object-fit:cover;border-radius:6px;background:#fff}
.achat-chip button{border:0;background:none;cursor:pointer;color:var(--ink-3);font-size:15px;line-height:1;padding:2px 4px}
.achat-chip button:hover{color:var(--err)}
.achat-pick{border:1px solid var(--line);border-radius:12px;background:#fff;box-shadow:var(--sh-2,0 8px 24px rgba(11,26,51,.12));padding:10px}
.achat-pick[hidden]{display:none}
.achat-pick ul{list-style:none;margin:8px 0 0;padding:0;max-height:260px;overflow-y:auto}
.achat-pick li button{display:flex;gap:10px;align-items:center;width:100%;text-align:left;border:0;background:none;padding:6px;border-radius:8px;cursor:pointer;font:inherit;color:var(--ink)}
.achat-pick li button:hover,.achat-pick li button:focus-visible{background:var(--blue-050)}
.achat-pick li img{width:38px;height:38px;object-fit:contain;border-radius:6px;background:var(--paper-2);flex:none}
.achat-pick .pn{font-weight:700;font-family:var(--font-m,monospace);font-size:13px}
.achat-pick .nm{font-size:12px;color:var(--ink-3)}
.achat-pick .pr{margin-left:auto;font-weight:700;font-size:13px;white-space:nowrap}
/* side panel */
.side-kv{display:grid;grid-template-columns:auto 1fr;gap:4px 10px;font-size:13px}
.side-kv dt{color:var(--ink-3)}.side-kv dd{margin:0;min-width:0;overflow-wrap:anywhere}
.now{display:flex;gap:10px;align-items:flex-start}
.now .dot{margin-top:6px;flex:none}
.now a{font-weight:600;overflow-wrap:anywhere}
.act{list-style:none;margin:0;padding:0;max-height:340px;overflow-y:auto;font-size:12.5px}
.act li{display:grid;grid-template-columns:auto 1fr;gap:2px 8px;padding:6px 12px;border-top:1px solid var(--line)}
.act li:first-child{border-top:0}
.act .k{font:700 10px/1.6 var(--font-h);letter-spacing:.04em;text-transform:uppercase;padding:0 6px;border-radius:5px;background:var(--muted-bg);color:var(--ink-2);align-self:start;margin-top:2px}
.act .k--action{background:var(--info-bg);color:var(--blue-700)}
.act .k--error,.act .k--client_error{background:var(--err-bg);color:var(--err)}
.act .p{min-width:0;overflow-wrap:anywhere}
.act .t{grid-column:2;color:var(--ink-3);font-size:11.5px}
.ord{display:flex;justify-content:space-between;gap:8px;padding:7px 0;border-top:1px solid var(--line);font-size:13px}
.ord:first-child{border-top:0}
.ord .tr{font-size:12px;color:var(--ink-3)}
@media(max-width:1100px){.achat{grid-template-columns:1fr}.achat-log{height:52vh}}
</style>
<div class="achat">
  <div class="card">
    <div class="card__head"><h2>Conversation</h2>
      <span class="badge {{ $c->mode === 'human' ? 'badge--info' : 'badge--plain' }}" data-chat-mode>{{ $c->status !== 'open' ? 'Closed' : ($c->mode === 'human' ? 'You are answering' . ($agent ? ' (' . $agent . ')' : '') : 'AI is answering · ' . ucfirst($c->provider)) }}</span>
    </div>
    <div class="achat-log" data-chat-log aria-live="polite"></div>
    @if($c->status === 'open')
    <form class="achat-reply" data-chat-reply>
      <div class="achat-tray" data-tray aria-label="Attachments"></div>
      <textarea class="input" name="text" placeholder="{{ $c->mode === 'human' ? 'Write to the customer…' : 'Write to the customer — sending takes the chat over from the AI' }}" maxlength="2000" aria-label="Message"></textarea>
      <div class="achat-pick" data-pick hidden>
        <input class="input input--sm" type="search" placeholder="Part number or description (e.g. 1310 u-joint)" data-pick-q aria-label="Search the catalog">
        <ul data-pick-list></ul>
      </div>
      <div class="achat-tools">
        <label class="btn btn--sm" title="Send photos (up to {{ \App\Support\ChatPhotos::MAX_PER_MESSAGE }})">{!! ic('image') !!} Photo<input type="file" accept="image/*" multiple hidden data-photo></label>
        <button type="button" class="btn btn--sm" data-pick-open>{!! ic('box') !!} Part</button>
        @if($orders->count())
        <select class="input input--sm" data-track aria-label="Send tracking of an order" style="max-width:190px">
          <option value="">{{ 'Tracking…' }}</option>
          @foreach($orders as $o)<option value="{{ $o->id }}">#{{ $o->order_number }} · {{ $o->tracking_number ? ($o->tracking_carrier ?: 'Tracking') : ucfirst($o->status) }}</option>@endforeach
        </select>
        @endif
        <span class="grow"></span>
        <span class="small muted">Ctrl+Enter</span>
        <button class="btn btn--primary">{!! ic('send') !!} Send</button>
      </div>
    </form>
    @endif
  </div>

  <div class="stack">
    <div class="card"><div class="card__head"><h2>Right now</h2><span class="small muted" data-errs></span></div><div class="card__body" data-now><span class="muted small">Loading…</span></div></div>

    <div class="card"><div class="card__head"><h2>Customer</h2>@if($customer)<a class="small" href="/admin/customers/{{ $customer->id }}">Open</a>@endif</div><div class="card__body">
      @if($customer)
        <div style="margin-bottom:8px"><b>{{ $customer->name }}</b>@if($customer->company) <span class="muted">· {{ $customer->company }}</span>@endif</div>
        <dl class="side-kv">
          <dt>E-mail</dt><dd><a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a></dd>
          @if($customer->phone)<dt>Phone</dt><dd><a href="tel:{{ preg_replace('/[^\d+]/', '', $customer->phone) }}">{{ $customer->phone }}</a></dd>@endif
          @if($customer->billing_city)<dt>Location</dt><dd>{{ $customer->billing_city }}, {{ $customer->billing_state }} {{ $customer->billing_country }}</dd>@endif
          <dt>Account</dt><dd>{{ \Carbon\Carbon::parse($customer->created_at)->format('M Y') }}@if($customer->wp_user_id) <span class="muted">· from the old site</span>@endif</dd>
          <dt>Business</dt><dd>@if($customer->wholesale_status === 'approved')<span class="badge badge--info badge--plain">{{ \App\Services\B2bAccounts::label(\App\Services\B2bAccounts::tierOf($customer) ?? 'bronze') }}</span>@if($customer->net_terms) <span class="muted small">· Net terms</span>@endif @elseif($customer->wholesale_status === 'pending')<span class="badge badge--warn">Application pending</span>@else<span class="muted">Retail</span>@endif</dd>
          @if($stats)<dt>Bought</dt><dd>{{ money($stats->t) }} <span class="muted">· {{ $stats->n }} order{{ $stats->n == 1 ? '' : 's' }}</span></dd>@endif
          @if($customer->last_login_at)<dt>Last sign-in</dt><dd>{{ \Carbon\Carbon::parse($customer->last_login_at)->diffForHumans() }}</dd>@endif
        </dl>
      @endif
      @if($c->needs_human_at)<div class="flash flash--warn" style="margin-top:10px">{!! ic('alert') !!}<span>Asked for a person {{ \Carbon\Carbon::parse($c->needs_human_at)->diffForHumans() }}</span></div>@endif
    </div></div>

    <div class="card"><div class="card__head"><h2>Cart</h2></div><div class="card__body small" data-cart><span class="muted">—</span></div></div>

    <div class="card"><div class="card__head"><h2>Orders</h2></div><div class="card__body">
      @forelse($orders as $o)
        <div class="ord"><div><a href="/admin/orders/{{ $o->id }}" class="mono">#{{ $o->order_number }}</a> <span class="badge badge--{{ $o->status }}">{{ ucfirst($o->status) }}</span>
          <div class="tr">{{ \Carbon\Carbon::parse($o->created_at)->format('M j, Y') }}@if($o->tracking_number) · {{ $o->tracking_carrier }} <a href="{{ \App\Support\OrderEvents::carrierUrl($o->tracking_carrier, $o->tracking_number) }}" target="_blank" rel="noopener" class="mono">{{ $o->tracking_number }}</a>@endif</div></div>
          <div class="right"><b>{{ money($o->total) }}</b>@if($c->status === 'open')<div><button type="button" class="btn btn--sm" style="margin-top:4px" data-send-track="{{ $o->id }}" title="Send this order's status and tracking in the chat">{!! ic('truck') !!} Send</button></div>@endif</div></div>
      @empty<span class="muted small">No orders</span>@endforelse
    </div></div>

    <div class="card"><div class="card__head"><h2>Activity</h2><span class="small muted">pages, actions, errors · last 30 days</span></div><ul class="act" data-act><li><span class="muted">Loading…</span></li></ul></div>

    <div class="card"><div class="card__body small muted">Chat started {{ \Carbon\Carbon::parse($c->created_at)->format('M j, g:ia') }} · {{ $c->turns }} messages · {{ number_format($c->input_tokens + $c->output_tokens) }} tokens</div></div>
  </div>
</div>
@push('scripts')
<script>
(function () {
  var ID = {{ (int) $c->id }};
  var log = document.querySelector('[data-chat-log]'), form = document.querySelector('[data-chat-reply]'), modeEl = document.querySelector('[data-chat-mode]');
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '', last = 0;
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
  var money = function (n) { return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var get = function (u) { return fetch(u, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); };

  /* ---------- transcript ---------- */
  function render(m) {
    if (m.id <= last) return; last = m.id;
    var d = document.createElement('div'); d.className = 'am ' + m.role;
    var who = m.role === 'user' ? 'Customer' : (m.role === 'agent' ? (m.agent || 'UDS') + ' (shop)' : (m.role === 'assistant' ? 'AI' : ''));
    var w = (m.widgets || []).filter(function (x) { return x.type === 'tracking'; }).map(function (t) {
      return '<div class="wtrack"><b>Order #' + esc(t.number) + '</b> · ' + esc(t.status_label) + (t.tracking_number ? '<br>' + esc(t.carrier) + ' ' + esc(t.tracking_number) : '') + (t.eta ? '<br>Due ' + esc(t.eta) : '') + '</div>';
    }).join('');
    // the assistant writes **bold**; nothing else is rendered (the text is escaped first)
    d.innerHTML = (who ? '<span class="who">' + esc(who) + '</span>' : '') + esc(m.text === '(photo)' ? '' : m.text).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>') +
      ((m.photos || []).length ? '<div class="ph">' + m.photos.map(function (u) { return '<a href="' + esc(u) + '" target="_blank" rel="noopener"><img src="' + esc(u) + '" alt="Photo"></a>'; }).join('') + '</div>' : '') +
      ((m.cards || []).length ? '<div class="cards">' + m.cards.map(function (c) { return '<a href="' + esc(c.url) + '" target="_blank">' + (c.image ? '<img src="' + esc(c.image) + '" alt="">' : '') + '<span><b>' + esc(c.sku) + '</b>' + (c.price_usd ? ' · ' + money(c.price_usd) : '') + '</span></a>'; }).join('') + '</div>' : '') + w +
      (m.role !== 'system' ? '<span class="when">' + esc((m.at || '').slice(5, 16)) + '</span>' : '');
    log.appendChild(d); log.scrollTop = log.scrollHeight;
  }
  (@json($messages)).forEach(render);
  function poll() {
    get('/admin/chats/' + ID + '/messages?after=' + last)
      .then(function (d) { (d.messages || []).forEach(render); if (modeEl && d.mode) modeEl.textContent = d.status !== 'open' ? 'Closed' : (d.mode === 'human' ? 'You are answering' + (d.agent ? ' (' + d.agent + ')' : '') : 'AI is answering'); })
      .catch(function () {}).then(function () { setTimeout(poll, 4000); });
  }
  setTimeout(poll, 4000);

  /* ---------- side panel: where they are, what they did, their cart ---------- */
  var nowEl = document.querySelector('[data-now]'), actEl = document.querySelector('[data-act]'), cartEl = document.querySelector('[data-cart]'), errEl = document.querySelector('[data-errs]');
  var KIND = { page: 'Page', action: 'Action', error: 'Error', client_error: 'Browser' };
  function side(d) {
    var p = d.presence;
    nowEl.innerHTML = p ? '<div class="now"><span class="dot ' + (p.online ? 'dot--ok' : '') + '" aria-hidden="true"></span><div><div class="small muted">' + (p.online ? 'On the site now' : 'Last seen ' + esc(p.ago)) + '</div>' +
      '<a href="' + esc(p.url) + '" target="_blank" rel="noopener">' + esc((p.title || p.url || '').replace(/\s*[·|–-]\s*Universal Driveshafts.*$/, '') || p.url) + '</a><div class="small muted mono">' + esc(p.url) + '</div>' +
      (p.device ? '<div class="small muted">' + esc(p.device) + (p.ip ? ' · ' + esc(p.ip) : '') + '</div>' : '') + '</div></div>' : '<span class="muted small">No visits recorded yet.</span>';
    errEl.textContent = d.errors ? d.errors + ' error' + (d.errors > 1 ? 's' : '') + ' today' : '';
    errEl.style.color = d.errors ? 'var(--err)' : '';
    actEl.innerHTML = (d.activity || []).length ? d.activity.map(function (a) {
      var what = a.kind === 'page' ? (a.title || a.path) : (a.method && a.kind !== 'client_error' ? a.method + ' ' : '') + a.path;
      return '<li><span class="k k--' + esc(a.kind) + '">' + esc(KIND[a.kind] || a.kind) + '</span><span class="p">' + esc(what) +
        (a.status && a.status >= 400 ? ' <b style="color:var(--err)">' + a.status + '</b>' : '') + (a.detail ? '<br><span style="color:var(--err)">' + esc(a.detail) + '</span>' : '') +
        (a.kind === 'page' && a.title ? '<br><span class="muted mono">' + esc(a.path) + '</span>' : '') + '</span><span class="t">' + esc(a.at) + (a.ms ? ' · ' + a.ms + ' ms' : '') + '</span></li>';
    }).join('') : '<li><span class="muted">Nothing yet. Pages they open while signed in show up here.</span></li>';
    cartEl.innerHTML = d.cart && d.cart.items.length ? d.cart.items.map(function (i) { return '<div class="ord"><span><b class="mono">' + esc(i.sku) + '</b> × ' + i.qty + '<br><span class="muted">' + esc(i.name) + '</span></span><b>' + money(i.price * i.qty) + '</b></div>'; }).join('') +
      '<div class="muted" style="margin-top:6px">' + money(d.cart.subtotal) + ' · updated ' + esc(d.cart.at) + '</div>' : '<span class="muted">Empty</span>';
  }
  side(@json($context));
  (function refresh() { setTimeout(function () { get('/admin/chats/' + ID + '/context').then(side).catch(function () {}).then(refresh); }, 10000); })();

  if (!form) return;
  /* ---------- reply with photos, parts, tracking ---------- */
  var tray = form.querySelector('[data-tray]'), att = { photos: [], skus: [], track: null };
  var TRUCK = {!! json_encode(ic('truck')) !!};
  function drawTray() {
    tray.innerHTML = att.photos.map(function (p, i) { return '<span class="achat-chip"><img src="' + p + '" alt="">Photo<button type="button" data-rm="photo:' + i + '" aria-label="Remove photo">×</button></span>'; }).join('') +
      att.skus.map(function (s, i) { return '<span class="achat-chip">' + (s.image ? '<img src="' + esc(s.image) + '" alt="">' : '') + '<b class="mono">' + esc(s.sku) + '</b><button type="button" data-rm="sku:' + i + '" aria-label="Remove ' + esc(s.sku) + '">×</button></span>'; }).join('') +
      (att.track ? '<span class="achat-chip">' + TRUCK + ' Tracking #' + esc(att.track.label) + '<button type="button" data-rm="track" aria-label="Remove tracking">×</button></span>' : '');
  }
  tray.addEventListener('click', function (e) {
    var b = e.target.closest('[data-rm]'); if (!b) return;
    var k = b.getAttribute('data-rm').split(':');
    if (k[0] === 'photo') att.photos.splice(+k[1], 1); else if (k[0] === 'sku') att.skus.splice(+k[1], 1); else att.track = null;
    drawTray();
  });
  // photos: shrunk in the browser to a JPEG of at most 1600 px, like the customer's widget
  var MAXP = {{ \App\Support\ChatPhotos::MAX_PER_MESSAGE }};
  form.querySelector('[data-photo]').addEventListener('change', function (e) {
    Array.prototype.slice.call(e.target.files || []).forEach(function (f) {
      if (att.photos.length >= MAXP || !/^image\//.test(f.type)) return;
      var img = new Image(), url = URL.createObjectURL(f);
      img.onload = function () {
        var s = Math.min(1, 1600 / Math.max(img.width, img.height)), c = document.createElement('canvas');
        c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url);
        if (att.photos.length < MAXP) { att.photos.push(c.toDataURL('image/jpeg', 0.85)); drawTray(); }
      };
      img.src = url;
    });
    e.target.value = '';
  });
  // parts from the catalog
  var pick = form.querySelector('[data-pick]'), pq = form.querySelector('[data-pick-q]'), pl = form.querySelector('[data-pick-list]'), pt = null, found = [];
  form.querySelector('[data-pick-open]').addEventListener('click', function () { pick.hidden = !pick.hidden; if (!pick.hidden) pq.focus(); });
  pq.addEventListener('input', function () {
    clearTimeout(pt);
    pt = setTimeout(function () {
      if (pq.value.trim().length < 2) { pl.innerHTML = ''; return; }
      get('/admin/chats/' + ID + '/products?q=' + encodeURIComponent(pq.value.trim())).then(function (d) {
        found = d.parts || [];
        pl.innerHTML = found.length ? found.map(function (p, i) { return '<li><button type="button" data-add="' + i + '">' + (p.image ? '<img src="' + esc(p.image) + '" alt="">' : '<span style="width:38px"></span>') + '<span><span class="pn">' + esc(p.sku) + '</span><br><span class="nm">' + esc(p.name) + '</span></span><span class="pr">' + (p.price_usd ? money(p.price_usd) : 'Quote') + '<br><span class="nm">' + esc(p.stock) + '</span></span></button></li>'; }).join('') : '<li class="muted small" style="padding:6px">No parts match.</li>';
      });
    }, 220);
  });
  pq.addEventListener('keydown', function (e) { if (e.key === 'Escape') { pick.hidden = true; } if (e.key === 'Enter') e.preventDefault(); });
  pl.addEventListener('click', function (e) {
    var b = e.target.closest('[data-add]'); if (!b) return;
    var p = found[+b.getAttribute('data-add')];
    if (p && att.skus.length < 6 && !att.skus.some(function (s) { return s.sku === p.sku; })) { att.skus.push(p); drawTray(); }
  });
  // tracking of one of their orders
  function setTrack(id, label) { att.track = id ? { id: id, label: label } : null; drawTray(); }
  var trk = form.querySelector('[data-track]');
  if (trk) trk.addEventListener('change', function () { var o = trk.options[trk.selectedIndex]; setTrack(trk.value, o.text.split(' · ')[0].replace('#', '')); trk.value = ''; });
  document.querySelectorAll('[data-send-track]').forEach(function (b) {
    b.addEventListener('click', function () { setTrack(b.getAttribute('data-send-track'), b.closest('.ord').querySelector('a.mono').textContent.replace('#', '')); form.querySelector('textarea').focus(); });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var t = form.querySelector('textarea'), b = form.querySelector('button.btn--primary');
    if (!t.value.trim() && !att.photos.length && !att.skus.length && !att.track) return t.focus();
    b.disabled = true;
    var body = { text: t.value, photos: att.photos, skus: att.skus.map(function (s) { return s.sku; }) };
    if (att.track) body.tracking_order = +att.track.id;
    fetch('/admin/chats/' + ID + '/reply', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json(); }).then(function (d) {
        b.disabled = false;
        if (d.error || d.message) return alert(d.error || d.message);
        t.value = ''; att = { photos: [], skus: [], track: null }; drawTray(); pick.hidden = true;
        (d.messages || []).forEach(render); if (modeEl) modeEl.textContent = 'You are answering';
      })
      .catch(function () { b.disabled = false; alert('Not sent — check the connection and try again.'); });
  });
  form.querySelector('textarea').addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) form.requestSubmit(); });
})();
</script>
@endpush
@endsection

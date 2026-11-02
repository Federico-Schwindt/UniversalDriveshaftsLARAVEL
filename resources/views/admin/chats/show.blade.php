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
.achat{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:18px;align-items:start}
.achat-log{display:flex;flex-direction:column;gap:10px;padding:16px;max-height:62vh;overflow-y:auto;background:var(--paper-2)}
.am{max-width:78%;padding:10px 14px;border-radius:14px;font-size:14px;line-height:1.5;white-space:pre-line;background:#fff;border:1px solid var(--line)}
.am.user{align-self:flex-start;background:#fff}
.am.assistant{align-self:flex-end;background:var(--blue-050);border-color:var(--blue-100)}
.am.agent{align-self:flex-end;background:#fff8e6;border-color:#f1d9a5}
.am.system{align-self:center;background:none;border:0;color:var(--ink-3);font-size:12px;padding:2px}
.am .who{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-3);margin-bottom:3px}
.am .when{display:block;font-size:11px;color:var(--ink-3);margin-top:4px}
.am .ph{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}.am .ph img{width:140px;height:140px;object-fit:cover;border-radius:10px;border:1px solid var(--line)}
.am .cards{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}.am .cards a{font-size:12px;padding:4px 8px;border-radius:8px;background:#fff;border:1px solid var(--line);text-decoration:none}
.achat-reply{display:flex;gap:8px;padding:12px;border-top:1px solid var(--line)}
.achat-reply textarea{flex:1;min-height:44px;max-height:160px;resize:vertical}
@media(max-width:900px){.achat{grid-template-columns:1fr}}
</style>
<div class="achat">
  <div class="card">
    <div class="card__head"><h2>Conversation</h2>
      <span class="badge {{ $c->mode === 'human' ? 'badge--info' : 'badge--plain' }}" data-chat-mode>{{ $c->status !== 'open' ? 'Closed' : ($c->mode === 'human' ? 'You are answering' . ($agent ? ' (' . $agent . ')' : '') : 'AI is answering · ' . ucfirst($c->provider)) }}</span>
    </div>
    <div class="achat-log" data-chat-log></div>
    @if($c->status === 'open')
    <form class="achat-reply" data-chat-reply>@csrf
      <textarea class="input" name="text" placeholder="{{ $c->mode === 'human' ? 'Write to the customer…' : 'Write to the customer — sending takes the chat over from the AI' }}" required maxlength="2000"></textarea>
      <button class="btn btn--primary">Send</button>
    </form>
    @endif
  </div>
  <div class="stack">
    <div class="card"><div class="card__head"><h2>Customer</h2></div><div class="card__body small">
      @if($customer)<b>{{ $customer->name }}</b><br>{{ $customer->email }}@if($customer->phone)<br>{{ $customer->phone }}@endif
        @if($customer->wholesale_status === 'approved')<br><span class="badge badge--info tiny">Wholesale</span>@endif
        <div style="margin-top:8px"><a href="/admin/customers/{{ $customer->id }}">Open customer</a></div>@endif
      @if($c->needs_human_at)<div class="flash flash--warn" style="margin-top:10px">{!! ic('alert') !!}<span>Asked for a person {{ \Carbon\Carbon::parse($c->needs_human_at)->diffForHumans() }}</span></div>@endif
    </div></div>
    <div class="card"><div class="card__head"><h2>Recent orders</h2></div><div class="card__body small">
      @forelse($orders as $o)<div><a href="/admin/orders/{{ $o->id }}" class="mono">{{ $o->order_number }}</a> · {{ money($o->total) }} · {{ $o->status }}</div>@empty<span class="muted">No orders</span>@endforelse
    </div></div>
    <div class="card"><div class="card__body small muted">Started {{ \Carbon\Carbon::parse($c->created_at)->format('M j, g:ia') }} · {{ $c->turns }} messages · {{ number_format($c->input_tokens + $c->output_tokens) }} tokens</div></div>
  </div>
</div>
@push('scripts')
<script>
(function () {
  var log = document.querySelector('[data-chat-log]'), form = document.querySelector('[data-chat-reply]'), modeEl = document.querySelector('[data-chat-mode]');
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '', last = 0;
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
  function render(m) {
    if (m.id <= last) return; last = m.id;
    var d = document.createElement('div'); d.className = 'am ' + m.role;
    var who = m.role === 'user' ? 'Customer' : (m.role === 'agent' ? (m.agent || 'UDS') + ' (shop)' : (m.role === 'assistant' ? 'AI' : ''));
    d.innerHTML = (who ? '<span class="who">' + esc(who) + '</span>' : '') + esc(m.text === '(photo)' ? '' : m.text) +
      ((m.photos || []).length ? '<div class="ph">' + m.photos.map(function (u) { return '<a href="' + esc(u) + '" target="_blank" rel="noopener"><img src="' + esc(u) + '" alt=""></a>'; }).join('') + '</div>' : '') +
      ((m.cards || []).length ? '<div class="cards">' + m.cards.map(function (c) { return '<a href="' + esc(c.url) + '" target="_blank">' + esc(c.sku) + (c.price_usd ? ' · $' + Number(c.price_usd).toFixed(2) : '') + '</a>'; }).join('') + '</div>' : '') +
      (m.role !== 'system' ? '<span class="when">' + esc((m.at || '').slice(5, 16)) + '</span>' : '');
    log.appendChild(d); log.scrollTop = log.scrollHeight;
  }
  (@json($messages)).forEach(render);
  function poll() {
    fetch('/admin/chats/{{ $c->id }}/messages?after=' + last, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); })
      .then(function (d) { (d.messages || []).forEach(render); if (modeEl && d.mode) modeEl.textContent = d.status !== 'open' ? 'Closed' : (d.mode === 'human' ? 'You are answering' + (d.agent ? ' (' + d.agent + ')' : '') : 'AI is answering'); })
      .catch(function () {}).then(function () { setTimeout(poll, 4000); });
  }
  setTimeout(poll, 4000);
  if (form) form.addEventListener('submit', function (e) {
    e.preventDefault();
    var t = form.querySelector('textarea'), b = form.querySelector('button'); if (!t.value.trim()) return;
    b.disabled = true;
    fetch('/admin/chats/{{ $c->id }}/reply', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ text: t.value }) })
      .then(function (r) { return r.json(); }).then(function (d) { b.disabled = false; if (d.error) return alert(d.error); t.value = ''; (d.messages || []).forEach(render); if (modeEl) modeEl.textContent = 'You are answering'; })
      .catch(function () { b.disabled = false; });
  });
  form && form.querySelector('textarea').addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) form.requestSubmit(); });
})();
</script>
@endpush
@endsection

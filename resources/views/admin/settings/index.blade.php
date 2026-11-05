@extends('admin.layout')
@section('title','Settings')
@section('crumb','Store')
@section('actions')<a href="#" data-post="/admin/settings/cache" class="btn btn--sm">{!! ic('sparkle') !!} Clear caches</a>@endsection
@section('body')
<div class="tabs">@foreach(['store'=>'Store','shipping'=>'Shipping & pickup','b2b'=>'B2B accounts','storefront'=>'Storefront','email'=>'E-mail','notifications'=>'Notifications','reviews'=>'Reviews','assistant'=>'AI assistant','users'=>'Admin users','integrations'=>'Integrations'] as $k=>$l)<a href="/admin/settings?tab={{ $k }}" class=" @if($tab===$k) is-on @endif">{{ $l }}</a>@endforeach</div>
@if($tab==='email')
<div class="grid grid--2 mb">
  <div class="card"><div class="card__head"><h2>Transactional — SMTP</h2><span class="badge badge--{{ $mail['mailer']==='smtp'?'ok':'warn' }}">{{ $mail['mailer'] }}</span></div><div class="card__body"><dl class="dl dl--tight"><dt>Server</dt><dd class="mono">{{ $mail['host'] }}:{{ $mail['port'] }}</dd><dt>Account</dt><dd class="mono">{{ $mail['user'] }}</dd><dt>Sends as</dt><dd class="mono">{{ $mail['from'] }}</dd></dl><p class="small muted mt">Order confirmations, shipping, payment links, RMA, account e-mails. Credentials live in the server <code>.env</code>.</p></div></div>
  <div class="card"><div class="card__head"><h2>Marketing — Brevo</h2>@if($brevo && $brevo['ok'])<span class="badge badge--ok">connected</span>@else<span class="badge badge--err">{{ $brevo['error'] ?? 'off' }}</span>@endif</div><div class="card__body">
    @if($brevo && $brevo['ok'])<dl class="dl dl--tight"><dt>Account</dt><dd>{{ $brevo['email'] }} @if($brevo['company'])· {{ $brevo['company'] }}@endif</dd><dt>Credits</dt><dd>{{ number_format($brevo['credits'] ?? 0) }} e-mails</dd><dt>Senders</dt><dd>@foreach($brevo['senders'] as $sn)<span class="pill" style="margin:0 4px 4px 0"><span class="dot {{ $sn['active']?'dot--ok':'dot--warn' }}"></span>{{ $sn['email'] }}</span>@endforeach</dd><dt>Lists</dt><dd>@foreach($brevo['lists'] as $l)<span class="pill" style="margin:0 4px 4px 0">{{ $l['name'] }} <b>#{{ $l['id'] }}</b>@if($l['count']!==null) · {{ $l['count'] }}@endif</span>@endforeach</dd></dl>
    @php $cur = \App\Support\Settings::get('brevo.from_address') ?: config('services.brevo.from'); $verified = collect($brevo['senders'])->contains(fn($x)=>strcasecmp($x['email'],$cur)===0 && $x['active']); @endphp
    @if(!$verified)<form method="post" action="/admin/settings/brevo-sender" class="flash flash--warn mt" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">@csrf{!! ic('alert') !!}<span class="grow small"><b>{{ $cur }}</b> is not a verified sender in Brevo yet, so marketing e-mails cannot go out from it.</span><input type="hidden" name="email" value="{{ $cur }}"><input type="hidden" name="name" value="Universal Driveshafts"><button class="btn btn--sm">Request verification</button></form>@endif
    @else<p class="small muted">Set <code>BREVO_API_KEY</code> in the server <code>.env</code>.</p>@endif
  </div></div>
</div>
<div class="grid grid--2 mb">
  <form method="post" action="/admin/settings/mail-test" class="card">@csrf<div class="card__head"><h2>Send a test</h2></div><div class="card__body stack"><div class="field"><label>To</label><input class="input" type="email" name="email" value="{{ auth()->user()->email }}" required></div><div class="field"><label>Template</label><select class="select" name="template">@foreach($templates as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div><div class="field"><label>Send through</label><select class="select" name="via"><option value="smtp">SMTP (transactional)</option><option value="brevo">Brevo (marketing sender)</option></select></div><button class="btn btn--primary">{!! ic('send') !!} Send test</button></div></form>
  <div class="card"><div class="card__head"><h2>Preview templates</h2><span class="muted small">opens in a new tab</span></div><div class="card__body card__body--flush">@foreach($templates as $k=>$l)<a class="stat-row" style="padding:9px 16px;color:inherit;text-decoration:none" href="/admin/settings/email-preview/{{ $k }}" target="_blank"><span>{{ $l }}</span>{!! ic('ext','ic--s') !!}</a>@endforeach</div></div>
</div>
@endif
@if(isset($fields[$tab]))
<form method="post" action="/admin/settings" class="card" style="max-width:820px" data-savebar="#savebar">@csrf<input type="hidden" name="tab" value="{{ $tab }}">
  <div class="card__body stack stack--l">
    @foreach($fields[$tab] as $key=>[$label,$type,$default,$hint]) @php $n = str_replace('.','__',$key); $val = $values[$key]; @endphp
      <div class="field">
        @if($type==='bool')<label class="switch"><input type="checkbox" name="{{ $n }}" value="1" @checked($val)><i></i> {{ $label }}</label>
        @else<label>{{ $label }}</label>
          @if($type==='select')<select class="select" name="{{ $n }}">@foreach(($fields[$tab][$key][4] ?? []) as $ov => $ol)<option value="{{ $ov }}" @selected((string) $val === (string) $ov)>{{ $ol }}</option>@endforeach</select>
          @elseif($type==='textarea')<textarea class="input" name="{{ $n }}" rows="3">{{ $val }}</textarea>
          @else<input class="input" type="{{ $type }}" name="{{ $n }}" value="{{ $val }}" @if($type==='number') step="any" @endif>@endif
        @endif
        @if($hint)<span class="hint">{{ $hint }}</span>@endif
      </div>
    @endforeach
  </div>
  <div class="card__foot"><span class="muted small">Changes apply to the storefront immediately.</span><button class="btn btn--primary">Save</button></div>
  <div class="savebar" id="savebar"><span>Unsaved settings</span><button class="btn btn--primary btn--sm">Save</button></div>
</form>
@elseif($tab==='users')
<div class="grid grid--main">
  <div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards"><thead><tr><th>User</th><th>Role</th><th>Last login</th><th></th></tr></thead><tbody>
    @foreach($users as $u) @php $role = $u->admin_role ?: 'owner'; @endphp
    <tr><td class="primary"><span class="flex gap-2"><span class="avatar" style="width:32px;height:32px;font-size:13px">{{ strtoupper(mb_substr($u->name,0,1)) }}</span><span><span class="title">{{ $u->name }}</span><small>{{ $u->email }}</small></span></span></td>
      <td data-label="Role"><span class="badge badge--{{ $role==='owner'?'info':'plain' }}">{{ \App\Support\AdminAccess::ROLES[$role] }}</span></td>
      <td data-label="Last login" class="small muted">{{ $u->last_login_at ? \Carbon\Carbon::parse($u->last_login_at)->diffForHumans() : '—' }}</td>
      <td class="actions">@if(\App\Support\AdminAccess::role()==='owner')<form method="post" action="/admin/settings/users/{{ $u->id }}" class="flex" style="justify-content:flex-end">@csrf<select class="select select--sm" name="role" style="width:auto">@foreach(\App\Support\AdminAccess::ROLES as $k=>$l)<option value="{{ $k }}" @selected($role===$k)>{{ $l }}</option>@endforeach<option value="remove">Remove access</option></select><button class="btn btn--sm">Apply</button></form>@endif</td></tr>
    @endforeach</tbody></table></div>
    <div class="card__foot small muted" style="display:block"><b>Owner</b> — everything. <b>Manager</b> — everything except settings and admin users. <b>Staff</b> — orders and shipping labels, driveshafts, inventory, customers, leads, products (no refunds, freight bookings, exports, coupons, reports or settings).</div></div>
  @if(\App\Support\AdminAccess::role()==='owner')<form method="post" action="/admin/settings/users" class="card">@csrf<div class="card__head"><h2>Add admin</h2></div><div class="card__body stack"><div class="field"><label>E-mail</label><input class="input" type="email" name="email" required><span class="hint">Existing customers are upgraded; new e-mails get an account and a temporary password.</span></div><div class="field"><label>Name <span class="muted">(new accounts)</span></label><input class="input" name="name"></div><div class="field"><label>Role</label><select class="select" name="role">@foreach(\App\Support\AdminAccess::ROLES as $k=>$l)<option value="{{ $k }}" @selected($k==='staff')>{{ $l }}</option>@endforeach</select></div><button class="btn btn--primary">Grant access</button></div></form>@endif
</div>
@elseif($tab==='integrations')
<div class="grid grid--2">
  @foreach($integrations as [$name,$on,$env,$desc])<div class="card"><div class="card__body flex" style="align-items:flex-start"><span class="dot {{ $on===true?'dot--ok':($on===false?'dot--err':'dot--warn') }}" style="margin-top:6px"></span><div><b>{{ $name }}</b> <span class="badge badge--plain tiny">{{ $on===true?'enabled':($on===false?'off':$on) }}</span><div class="small muted">{{ $desc }}</div><div class="tiny mono muted mt">{{ $env }}</div></div></div></div>@endforeach
  <div class="card" style="grid-column:1/-1"><div class="card__head"><h2>Scheduled jobs</h2></div><div class="tbl-wrap"><table class="tbl tbl--compact"><thead><tr><th>Command</th><th>Runs</th><th>Does</th></tr></thead><tbody>@foreach($schedule as [$c,$w,$d])<tr><td class="mono">{{ $c }}</td><td>{{ $w }}</td><td class="small">{{ $d }}</td></tr>@endforeach</tbody></table></div><div class="card__foot small muted">Integrations are switched in the server <code>.env</code> (then <code>php artisan optimize</code>). Ask your developer to change these.</div></div>
</div>
@endif
@endsection

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadsController extends Controller
{
    public function index(Request $r)
    {
        $q = DB::table('leads');
        $tab = $r->query('tab', 'open');
        match ($tab) { 'open' => $q->whereIn('status', ['new', 'contacted']), 'new' => $q->where('status', 'new'), 'quotes' => $q->where('source', 'quote'), 'closed' => $q->where('status', 'closed'), default => null };
        if ($src = $r->query('source')) $q->where('source', $src);
        if ($s = trim((string) $r->query('q', ''))) { $like = "%$s%"; $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('message', 'like', $like)->orWhere('data', 'like', $like)); }
        $rows = $q->orderByDesc('id')->paginate(30)->withQueryString();
        foreach ($rows as $l) $l->d = json_decode($l->data ?: '{}', true) ?: [];
        $sources = DB::table('leads')->select('source')->distinct()->orderBy('source')->pluck('source');
        $counts = ['open' => DB::table('leads')->whereIn('status', ['new', 'contacted'])->count(), 'new' => DB::table('leads')->where('status', 'new')->count(), 'quotes' => DB::table('leads')->where('source', 'quote')->count(), 'closed' => DB::table('leads')->where('status', 'closed')->count(), 'all' => DB::table('leads')->count()];
        return view('admin.leads.index', compact('rows', 'sources', 'tab', 'counts') + ['f' => $r->query()]);
    }

    public function show(int $id)
    {
        $lead = DB::table('leads')->find($id); abort_unless($lead, 404);
        $lead->d = json_decode($lead->data ?: '{}', true) ?: [];
        $customer = DB::table('users')->where('email', $lead->email)->first();
        $orders = DB::table('orders')->where('email', $lead->email)->orderByDesc('id')->limit(5)->get();
        $others = DB::table('leads')->where('email', $lead->email)->where('id', '!=', $id)->orderByDesc('id')->limit(5)->get();
        $part = null;
        $ref = $lead->d['part'] ?? $lead->d['sku'] ?? $lead->d['part_number'] ?? null;
        if ($ref) { $pn = \App\Services\DriveshaftFinder::pn((string) $ref); $part = DB::table('ds_parts')->where('part_number', $pn)->first(); if ($part) $part->spec = DB::table('ds_specs')->where('part_number', $pn)->first(); }
        $activity = AdminLog::for('lead', $id, 10);
        return view('admin.leads.show', compact('lead', 'customer', 'orders', 'others', 'part', 'activity'));
    }

    public function update(Request $r, int $id)
    {
        $lead = DB::table('leads')->find($id); abort_unless($lead, 404);
        $d = $r->validate(['status' => 'required|in:new,contacted,closed']);
        DB::table('leads')->where('id', $id)->update(['status' => $d['status'], 'updated_at' => now()]);
        AdminLog::record('lead.status', "Lead #{$id} ({$lead->source}) → {$d['status']}", 'lead', $id);
        \Illuminate\Support\Facades\Cache::forget('adm_nav_counts');
        return back()->with('admin_ok', 'Lead marked ' . $d['status'] . '.');
    }

    public function reply(Request $r, int $id)
    {
        $lead = DB::table('leads')->find($id); abort_unless($lead, 404);
        $d = $r->validate(['subject' => 'required|string|max:160', 'body' => 'required|string|max:6000']);
        try { \App\Support\Mailer::simple($lead->email, $d['subject'], ['kicker' => 'Universal Driveshafts', 'title' => $d['subject'], 'paragraphs' => [$d['body']], 'signoff' => 'Reply to this e-mail to continue the conversation, or call (813) 524-6491.'], ['replyTo' => [config('uds.email')]]); }
        catch (\Throwable $e) { return back()->with('admin_err', 'Could not send: ' . $e->getMessage()); }
        $data = json_decode($lead->data ?: '{}', true) ?: []; $data['replies'][] = ['at' => now()->toDateTimeString(), 'by' => auth()->user()->name, 'subject' => $d['subject'], 'body' => $d['body']];
        DB::table('leads')->where('id', $id)->update(['status' => $lead->status === 'new' ? 'contacted' : $lead->status, 'data' => json_encode($data), 'updated_at' => now()]);
        AdminLog::record('lead.reply', "Replied to lead #{$id} ({$lead->email})", 'lead', $id);
        \Illuminate\Support\Facades\Cache::forget('adm_nav_counts');
        return back()->with('admin_ok', 'Reply sent to ' . $lead->email);
    }

    public function destroy(int $id)
    {
        $l = DB::table('leads')->find($id);
        DB::table('leads')->where('id', $id)->delete();
        if ($l) \App\Support\AdminLog::record('lead.delete', 'Deleted lead from ' . ($l->email ?: '#' . $id), 'lead', $id, ['source' => $l->source ?? null, 'message' => mb_substr((string) ($l->message ?? ''), 0, 500)]);
        return redirect('/admin/leads')->with('admin_ok', 'Lead deleted.');
    }

    /* ---------- designer builds ---------- */
    public function designs(Request $r)
    {
        $q = DB::table('saved_designs as d')->leftJoin('users as u', 'u.id', '=', 'd.user_id')->select('d.*', 'u.name as user_name', 'u.email as user_email');
        if ($s = trim((string) $r->query('q', ''))) { $like = "%$s%"; $q->where(fn ($w) => $w->where('d.title', 'like', $like)->orWhere('d.ref', 'like', $like)->orWhere('u.name', 'like', $like)->orWhere('u.email', 'like', $like)); }
        $rows = $q->orderByDesc('d.id')->paginate(30)->withQueryString();
        foreach ($rows as $d) { $d->cfg = json_decode($d->config ?: '{}', true) ?: []; $d->kind = $d->cfg['kind'] ?? 'builder'; }
        return view('admin.leads.designs', compact('rows') + ['f' => $r->query()]);
    }
}

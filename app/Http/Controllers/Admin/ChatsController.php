<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\ChatbotService;
use App\Support\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UDS Assistant conversations in the admin: read any chat (photos and part cards included), take it over
 * so the AI stops answering and a person replies live, then hand it back to the AI or close it.
 * Beside the chat (owner, 2026-11-04): where the customer is right now, their activity trail (pages, actions,
 * server and browser errors), cart, orders with tracking; the reply can carry photos, catalog parts and tracking.
 */
class ChatsController extends Controller
{
    public function __construct(private ChatbotService $bot) {}

    public function index(Request $r)
    {
        $f = (string) $r->query('f', 'open');
        $q = DB::table('chat_conversations as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->select('c.*', 'u.name as customer', 'u.email as customer_email');
        if ($f === 'waiting') $q->where('c.status', 'open')->where(fn ($w) => $w->whereNotNull('c.needs_human_at')->orWhere('c.mode', 'human'));
        elseif ($f === 'open') $q->where('c.status', 'open');
        $rows = $q->orderByRaw('c.needs_human_at IS NULL')->orderByDesc('c.updated_at')->paginate(40)->withQueryString();
        $ids = collect($rows->items())->pluck('id');
        $last = DB::table('chat_messages')->whereIn('conversation_id', $ids)->whereNotNull('display')
            ->whereIn('id', DB::table('chat_messages')->whereIn('conversation_id', $ids)->whereNotNull('display')->selectRaw('MAX(id)')->groupBy('conversation_id'))
            ->get(['conversation_id', 'role', 'display', 'agent_id', 'created_at'])->keyBy('conversation_id');
        $photos = DB::table('chat_messages')->whereIn('conversation_id', $ids)->whereNotNull('photos')->selectRaw('conversation_id, COUNT(*) n')->groupBy('conversation_id')->pluck('n', 'conversation_id');
        return view('admin.chats.index', compact('rows', 'last', 'f', 'photos'));
    }

    public function show(int $id)
    {
        $c = $this->conv($id);
        DB::table('chat_conversations')->where('id', $id)->update(['admin_seen_id' => (int) DB::table('chat_messages')->where('conversation_id', $id)->max('id')]);
        $customer = DB::table('users')->find($c->user_id);
        $mine = fn ($q) => $q->where('user_id', $customer->id)->when($customer->email_verified_at, fn ($w) => $w->orWhere('email', $customer->email));
        $orders = $customer ? DB::table('orders')->where($mine)->orderByDesc('id')->limit(8)
            ->get(['id', 'order_number', 'total', 'status', 'payment_status', 'created_at', 'tracking_number', 'tracking_carrier', 'shipped_at', 'delivered_at', 'source']) : collect();
        $stats = $customer ? \App\Support\SalesStats::counted(DB::table('orders')->where($mine))
            ->selectRaw('COUNT(*) n, COALESCE(SUM(total - shipping_total - tax_total - COALESCE(refund_total,0)),0) t, MIN(created_at) first')->first() : null;
        return view('admin.chats.show', ['c' => $c, 'customer' => $customer, 'orders' => $orders, 'stats' => $stats, 'context' => $this->contextFor($c),
            'messages' => $this->bot->transcript($c), 'agent' => $this->bot->agentName($c)]);
    }

    /** Live side panel: presence, activity trail, cart. Polled by the chat page. */
    public function context(int $id)
    {
        return response()->json($this->contextFor($this->conv($id)));
    }

    private function contextFor(object $c): array
    {
        $uid = (int) $c->user_id;
        $rows = DB::table('customer_activity')->where('user_id', $uid)->orderByDesc('id')->limit(60)->get(['kind', 'method', 'path', 'title', 'status', 'ms', 'detail', 'created_at']);
        $cart = DB::table('abandoned_carts')->where('user_id', $uid)->whereNull('recovered_at')->orderByDesc('last_seen_at')->first(['items', 'subtotal', 'last_seen_at']);
        $items = $cart ? array_map(fn ($i) => ['sku' => (string) ($i['sku'] ?? ''), 'name' => (string) ($i['name'] ?? ''), 'qty' => (int) ($i['qty'] ?? 1), 'price' => (float) ($i['price'] ?? 0)],
            (array) (json_decode((string) $cart->items, true) ?: [])) : [];
        $tz = 'America/New_York';
        return [
            'presence' => \App\Support\CustomerActivity::presence($uid),
            'activity' => $rows->map(fn ($r) => ['kind' => $r->kind, 'method' => $r->method, 'path' => $r->path, 'title' => $r->title ? preg_replace('/\s*[·|–-]\s*Universal Driveshafts.*$/u', '', $r->title) : null,
                'status' => $r->status, 'ms' => $r->ms, 'detail' => $r->detail, 'at' => \Carbon\Carbon::parse($r->created_at, 'UTC')->timezone($tz)->format('M j, g:i:s A')])->all(),
            'errors' => DB::table('customer_activity')->where('user_id', $uid)->whereIn('kind', ['error', 'client_error'])->where('created_at', '>=', now()->subDay())->count(),
            'cart' => $cart ? ['items' => $items, 'subtotal' => (float) $cart->subtotal, 'at' => \Carbon\Carbon::parse($cart->last_seen_at, 'UTC')->diffForHumans()] : null,
        ];
    }

    /** Catalog parts for the reply's "Send a part" picker, priced as this customer sees them. */
    public function products(Request $r, int $id)
    {
        $c = $this->conv($id);
        $q = trim((string) $r->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json(['parts' => []]);
        $u = DB::table('users')->find($c->user_id);
        $parts = array_map(fn ($p) => $this->bot->partRow($p, $u), app(\App\Services\ProductService::class)->search($q, 8));
        return response()->json(['parts' => array_values(array_filter($parts, fn ($p) => $p['sku'] !== ''))]);
    }

    public function messages(Request $r, int $id)
    {
        $c = $this->conv($id);
        $m = $this->bot->transcript($c, (int) $r->query('after', 0));
        if ($m) DB::table('chat_conversations')->where('id', $id)->update(['admin_seen_id' => max(array_column($m, 'id'))]);
        return response()->json(['messages' => $m, 'mode' => $c->mode, 'status' => $c->status, 'agent' => $this->bot->agentName($c)]);
    }

    /** How long a shop reply waits for an AI turn in flight to close (the turn sees the takeover and stops at its next step). */
    public const LOCK_WAIT = 30;

    public function reply(Request $r, int $id)
    {
        $c = $this->conv($id);
        $d = $r->validate([
            'text' => 'nullable|string|max:2000',
            'photos' => 'nullable|array|max:' . \App\Support\ChatPhotos::MAX_PER_MESSAGE, 'photos.*' => 'string|max:' . (8 * 1024 * 1024),
            'skus' => 'nullable|array|max:6', 'skus.*' => 'string|max:80',
            'tracking_order' => 'nullable|integer',
        ]);
        if ($c->status !== 'open') return response()->json(['error' => 'This chat is closed.'], 422);
        // what goes with the words: photos (stored with the conversation), part cards, a tracking card of one of their orders
        $attach = ['photos' => [], 'cards' => [], 'widgets' => []];
        try {
            foreach ((array) ($d['photos'] ?? []) as $ph) $attach['photos'][] = \App\Support\ChatPhotos::storeDataUrl($ph, (int) $c->id);
        } catch (\Throwable $e) { return response()->json(['error' => "That photo couldn't be read. Use a JPG or PNG."], 422); }
        $u = DB::table('users')->find($c->user_id);
        foreach (array_unique((array) ($d['skus'] ?? [])) as $sku) {
            $p = app(\App\Services\ProductService::class)->bySku((string) $sku);
            if ($p) $attach['cards'][] = $this->bot->partRow($p, $u);
        }
        if (!empty($d['tracking_order'])) {
            $o = DB::table('orders')->where('id', (int) $d['tracking_order'])->where(fn ($q) => $q->where('user_id', $c->user_id)->when($u?->email_verified_at, fn ($w) => $w->orWhere('email', $u->email)))->first();
            if (!$o) return response()->json(['error' => 'That order is not on this customer\'s account.'], 422);
            $attach['widgets'][] = \App\Services\Chatbot\ChatWidgets::tracking($o);
        }
        $text = trim(strip_tags((string) ($d['text'] ?? '')));
        if ($text === '' && !$attach['photos'] && !$attach['cards'] && !$attach['widgets']) return response()->json(['error' => 'Write something or attach a photo, a part or tracking.'], 422);
        // replying is taking over: the AI must not answer on top of a person. The flag goes first so a turn in
        // flight stops at its next step; the row is written under the customer's send lock, never between a
        // tool call and its result
        $took = $c->mode !== 'human';
        if ($took) $this->flag($c, $r->user()->id);
        try {
            $mid = $this->locked($c, function () use ($c, $r, $text, $attach, $took, $id) {
                if ($took) $this->note($c, 'human', $r->user()->id);
                $mid = $this->bot->agentSay($this->conv($id), $r->user(), $text, $attach);
                DB::table('chat_conversations')->where('id', $id)->update(['needs_human_at' => null, 'admin_seen_id' => $mid]);
                return $mid;
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            if ($took) $this->note($c, 'human', $r->user()->id);
            return response()->json(['error' => 'The assistant is still finishing an answer. You have the chat now — send your reply again in a few seconds.'], 409);
        }
        return response()->json(['ok' => true, 'messages' => $this->bot->transcript($this->conv($id), $mid - 1)]);
    }

    public function takeover(Request $r, int $id)
    {
        $c = $this->conv($id);
        $this->flag($c, $r->user()->id);
        try {
            $this->locked($c, fn () => $this->note($c, 'human', $r->user()->id));
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            // the flag already stops the AI; the note is a system row, which the AI's history skips
            $this->note($c, 'human', $r->user()->id);
        }
        AdminLog::record('chat.takeover', "Took over chat #$id", 'chats', (string) $id);
        return $r->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'You have the chat. The AI will not answer until you return it.');
    }

    /** Run $fn holding the customer's chat lock (the same one ChatbotController::send takes). */
    private function locked(object $c, \Closure $fn): mixed
    {
        return cache()->lock(\App\Http\Controllers\ChatbotController::lockKey((int) $c->user_id), \App\Http\Controllers\ChatbotController::LOCK_SECONDS)->block(self::LOCK_WAIT, $fn);
    }

    private function flag(object $c, ?int $agentId): void
    {
        DB::table('chat_conversations')->where('id', $c->id)->update(['mode' => 'human', 'agent_id' => $agentId, 'updated_at' => now()]);
    }

    public function release(Request $r, int $id)
    {
        $c = $this->conv($id);
        $this->setMode($c, 'bot', null);
        DB::table('chat_conversations')->where('id', $id)->update(['needs_human_at' => null]);
        AdminLog::record('chat.release', "Returned chat #$id to the AI", 'chats', (string) $id);
        return $r->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'The AI answers this chat again.');
    }

    public function close(Request $r, int $id)
    {
        $this->conv($id);
        DB::table('chat_conversations')->where('id', $id)->update(['status' => 'closed', 'mode' => 'bot', 'needs_human_at' => null, 'updated_at' => now()]);
        return back()->with('ok', 'Chat closed. The customer starts a new one next time.');
    }

    private function setMode(object $c, string $mode, ?int $agentId): void
    {
        DB::table('chat_conversations')->where('id', $c->id)->update(['mode' => $mode, 'agent_id' => $agentId, 'updated_at' => now()]);
        $this->note($c, $mode, $agentId);
    }

    private function note(object $c, string $mode, ?int $agentId): void
    {
        // a short system line in the customer's chat so they know who is answering
        $note = $mode === 'human'
            ? (\Illuminate\Support\Str::before(trim((string) DB::table('users')->where('id', $agentId)->value('name')), ' ') ?: 'Someone') . ' from Universal Driveshafts joined the chat.'
            : 'The UDS Assistant is answering again.';
        DB::table('chat_messages')->insert(['conversation_id' => $c->id, 'role' => 'system', 'content' => '[]', 'display' => $note, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function conv(int $id): object
    {
        $c = DB::table('chat_conversations')->find($id);
        abort_unless($c, 404);
        return $c;
    }
}

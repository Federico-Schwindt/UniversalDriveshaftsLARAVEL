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
        $orders = $customer ? DB::table('orders')->where('email', $customer->email)->orderByDesc('id')->limit(5)->get(['id', 'order_number', 'total', 'status', 'created_at']) : collect();
        return view('admin.chats.show', ['c' => $c, 'customer' => $customer, 'orders' => $orders, 'messages' => $this->bot->transcript($c), 'agent' => $this->bot->agentName($c)]);
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
        $d = $r->validate(['text' => 'required|string|max:2000']);
        if ($c->status !== 'open') return response()->json(['error' => 'This chat is closed.'], 422);
        // replying is taking over: the AI must not answer on top of a person. The flag goes first so a turn in
        // flight stops at its next step; the row is written under the customer's send lock, never between a
        // tool call and its result
        $took = $c->mode !== 'human';
        if ($took) $this->flag($c, $r->user()->id);
        try {
            $mid = $this->locked($c, function () use ($c, $r, $d, $took, $id) {
                if ($took) $this->note($c, 'human', $r->user()->id);
                $mid = $this->bot->agentSay($this->conv($id), $r->user(), trim(strip_tags($d['text'])));
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

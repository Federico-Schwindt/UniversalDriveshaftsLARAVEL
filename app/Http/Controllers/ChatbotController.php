<?php

namespace App\Http\Controllers;

use App\Services\Chatbot\ChatbotService;
use App\Support\Mailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The store assistant's endpoints. Every route sits behind `auth`: an account is the first
 * line against bots, and the per-account limits below are the second.
 */
class ChatbotController extends Controller
{
    /** A brand-new account has to wait this long before it can chat — scripted sign-ups give up. */
    private const MIN_ACCOUNT_AGE_MIN = 2;
    /** Customers see their chats from this many days back. */
    public const HISTORY_DAYS = 15;
    /** One answer at a time per account; outlasts a whole turn (ChatbotService::TURN_SECONDS + transcription). Admin replies wait on the same lock. */
    public const LOCK_SECONDS = 180;

    public static function lockKey(int $userId): string
    {
        return 'chatbot:' . $userId;
    }

    public function __construct(private ChatbotService $bot) {}

    public function show(Request $r)
    {
        if (!$this->bot->enabled()) return response()->json(['enabled' => false]);
        $u = $r->user();
        $conv = DB::table('chat_conversations')->where('user_id', $u->id)->where('status', 'open')->orderByDesc('id')->first();
        return response()->json([
            'enabled' => true,
            'name' => Str::before(trim((string) $u->name), ' ') ?: null,
            'email' => $u->email,
            'avatar' => \App\Support\Avatar::url($u),
            'bot_avatar' => \App\Support\Avatar::botUrl(),
            'voice' => \App\Support\ChatVoice::enabled(),
            'messages' => $conv ? $this->bot->transcript($conv) : [],
            'mode' => $conv->mode ?? 'bot', 'agent' => $conv ? $this->bot->agentName($conv) : null,
            'left_today' => $this->leftToday($u->id),
        ]);
    }

    public function send(Request $r)
    {
        if (!$this->bot->enabled()) return response()->json(['error' => 'The assistant is not available right now.'], 503);
        $u = $r->user();
        $max = (int) config('services.anthropic.max_chars', 800);
        $d = $r->validate([
            'message' => 'nullable|string|max:' . ($max * 2),
            // photos arrive as base64 data URLs inside the JSON body (the browser has already shrunk them):
            // the server keeps refusing multipart uploads outside the admin
            'photos' => 'nullable|array|max:' . \App\Support\ChatPhotos::MAX_PER_MESSAGE,
            'photos.*' => 'string|max:' . (8 * 1024 * 1024),
            // a voice message, the same way: a base64 data URL of the browser's recording
            'voice' => 'nullable|string|max:' . (int) (\App\Support\ChatVoice::MAX_BYTES * 1.4),
            // the order summary's Yes button: the draft's nonce (AssistantOrders::confirmTap)
            'confirm' => 'nullable|string|max:64',
        ]);
        // no strip_tags: "<50in" is a measurement, and every output escapes; spaces collapse, line breaks stay (two at most)
        $text = trim(preg_replace(["/[^\S\n]+/u", "/ ?\n ?/u", "/\n{3,}/u"], [' ', "\n", "\n\n"], (string) ($d['message'] ?? '')));
        $files = array_values(array_filter((array) ($d['photos'] ?? [])));
        $voice = (string) ($d['voice'] ?? '');
        if ($voice !== '' && !\App\Support\ChatVoice::enabled()) return response()->json(['error' => 'Voice messages are not available right now. Please type your question.'], 503);

        if ($text === '' && !$files && $voice === '') return response()->json(['error' => 'Type a question first.'], 422);
        if ($voice === '' && mb_strlen($text) > $max) return response()->json(['error' => "Please keep it under {$max} characters."], 422);
        if (!$u->is_admin && $u->created_at && now()->diffInMinutes($u->created_at, true) < self::MIN_ACCOUNT_AGE_MIN) {
            return response()->json(['error' => 'Your account is brand new — give it a couple of minutes and try again.'], 429);
        }
        $conv = $this->bot->conversation($u);
        $human = ($conv->mode ?? 'bot') === 'human';
        // admins test the assistant all day: no daily or length limit for them (owner, 2026-10-24)
        $staff = (bool) $u->is_admin;
        if (!$human && !$staff && $this->leftToday($u->id) <= 0) {
            return response()->json(['error' => "You've reached today's limit for the assistant. Use \"Talk to a person\" or call (813) 524-6491."], 429);
        }

        if (!$human && !$staff && (int) $conv->turns >= (int) config('services.anthropic.max_turns', 30)) {
            return response()->json(['error' => 'This chat is getting long. Start a new chat to keep going.', 'restart' => true], 409);
        }
        // the same line over and over is a script, not a customer
        $recent = DB::table('chat_messages')->where('conversation_id', $conv->id)->where('role', 'user')->whereNotNull('display')->orderByDesc('id')->limit(3)->pluck('display')->all();
        if (count($recent) === 3 && count(array_unique(array_map('mb_strtolower', array_merge($recent, [$text])))) === 1) {
            return response()->json(['error' => 'Looks like the same question again — try rewording it, or tap "Talk to a person".'], 429);
        }

        // one answer at a time per account: two tabs firing at once would interleave the history
        $lock = cache()->lock(self::lockKey($u->id), self::LOCK_SECONDS);
        if (!$lock->get()) return response()->json(['error' => 'Still answering your last message…'], 429);
        try {
            // only the Yes button of the current summary counts as a tap; any other message clears an earlier one
            \App\Services\Chatbot\AssistantOrders::confirmTap((int) $conv->id, ($d['confirm'] ?? null) ?: null);
            $paths = [];
            foreach ($files as $f) {
                try { $paths[] = \App\Support\ChatPhotos::storeDataUrl($f, (int) $conv->id); }
                catch (\Throwable $e) { return response()->json(['error' => "We couldn't read that photo. Try a JPG or PNG."], 422); }
            }
            if ($voice !== '') {
                try { $clip = \App\Support\ChatVoice::storeDataUrl($voice, (int) $conv->id); }
                catch (\Throwable $e) { return response()->json(['error' => "We couldn't use that recording. Hold the mic a little longer, or type your question."], 422); }
                $heard = \App\Support\ChatVoice::transcribe($clip, $why);
                if ($heard === null) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($clip);
                    if ($why === 'down') return response()->json(['error' => 'Voice messages are unavailable right now — please type your question.', 'voice' => false], 503);
                    return response()->json(['error' => "Sorry, we couldn't make that out. Try again somewhere quieter, or type your question."], 422);
                }
                $text = trim(mb_substr($heard, 0, $max * 2) . ($text !== '' ? ' ' . $text : ''));
                $this->bot->voicePath = $clip;
            }
            try {
                $out = $this->bot->turn($u, $conv, $text, $paths);
            } finally {
                $this->bot->voicePath = null;
            }
        } finally {
            $lock->release();
        }
        if ($voice !== '' && !empty($out['user_id'])) {
            // what we heard, so the customer can see it was understood, and the answer spoken back
            $out['me'] = ['id' => (int) $out['user_id'], 'text' => $text, 'audio' => '/assistant/audio/' . $out['user_id']];
        }
        // the client draws exactly $out['ids'] and polls from user_id on, so a shop line written meanwhile still shows
        return response()->json($out + ['left_today' => $this->leftToday($u->id)] + ($this->bot->cartCount !== null ? ['cart_count' => $this->bot->cartCount] : []));
    }

    /** New messages since $after (someone from the shop writing, or the chat being taken over / handed back). */
    public function poll(Request $r)
    {
        $conv = DB::table('chat_conversations')->where('user_id', $r->user()->id)->where('status', 'open')->orderByDesc('id')->first();
        if (!$conv) return response()->json(['messages' => [], 'mode' => 'bot']);
        return response()->json(['messages' => $this->bot->transcript($conv, (int) $r->query('after', 0)), 'mode' => $conv->mode ?? 'bot', 'agent' => $this->bot->agentName($conv)]);
    }

    /**
     * The widget's heartbeat (every ~45 s while the tab is visible): where the customer is, for the admin chat page.
     * Answers whether the shop wrote since the last message this browser drew, so a closed panel can show a badge.
     */
    public function ping(Request $r)
    {
        $d = $r->validate(['url' => 'nullable|string|max:500', 'title' => 'nullable|string|max:190', 'after' => 'nullable|integer|min:0']);
        $u = $r->user();
        if (!$u->is_admin) \App\Support\CustomerActivity::ping((int) $u->id, (string) ($d['url'] ?? ''), (string) ($d['title'] ?? ''), (string) $r->ip(), (string) $r->userAgent());
        $conv = DB::table('chat_conversations')->where('user_id', $u->id)->where('status', 'open')->orderByDesc('id')->first(['id']);
        $unread = $conv ? DB::table('chat_messages')->where('conversation_id', $conv->id)->where('id', '>', (int) ($d['after'] ?? 0))->whereNotNull('agent_id')->count() : 0;
        return response()->json(['unread' => $unread]);
    }

    /** A JavaScript error in the customer's browser, kept on their activity trail (a few per page at most, enforced by the widget and the route limit). */
    public function clientLog(Request $r)
    {
        $d = $r->validate(['message' => 'required|string|max:500', 'source' => 'nullable|string|max:300', 'line' => 'nullable|integer', 'url' => 'nullable|string|max:500']);
        $u = $r->user();
        if (!$u->is_admin) \App\Support\CustomerActivity::record((int) $u->id, 'client_error', (string) ($d['url'] ?? '/'), [
            'detail' => trim($d['message'] . (!empty($d['source']) ? ' · ' . basename(parse_url($d['source'], PHP_URL_PATH) ?: $d['source']) . (!empty($d['line']) ? ':' . $d['line'] : '') : '')),
            'ip' => $r->ip(), 'ua' => (string) $r->userAgent(),
        ]);
        return response()->noContent();
    }

    /** A photo from a chat, for the customer who sent it or an admin. */
    public function photo(Request $r, int $message, int $n)
    {
        $m = DB::table('chat_messages as m')->join('chat_conversations as c', 'c.id', '=', 'm.conversation_id')->where('m.id', $message)->first(['m.photos', 'c.user_id']);
        abort_unless($m && ((int) $m->user_id === (int) $r->user()->id || $r->user()->is_admin), 404);
        $path = (json_decode((string) $m->photos, true) ?: [])[$n] ?? null;
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);
        return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($path), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * A voice message's recording, or an answer read aloud. Answers are spoken the first time someone presses
     * play and kept, so replaying costs nothing; new ones are capped per account per hour.
     */
    public function audio(Request $r, int $message)
    {
        $m = DB::table('chat_messages as m')->join('chat_conversations as c', 'c.id', '=', 'm.conversation_id')->where('m.id', $message)
            ->first(['m.id', 'm.role', 'm.display', 'm.audio', 'm.conversation_id', 'c.user_id']);
        abort_unless($m && ((int) $m->user_id === (int) $r->user()->id || $r->user()->is_admin), 404);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $path = $m->audio;
        if (!$path || !$disk->exists($path)) {
            abort_unless($m->role === 'assistant' && trim((string) $m->display) !== '' && \App\Support\ChatVoice::enabled(), 404);
            $key = 'chat-tts:' . $r->user()->id;
            if (!$r->user()->is_admin && RateLimiter::tooManyAttempts($key, 40)) abort(429);
            RateLimiter::hit($key, 3600);
            $lock = cache()->lock('chat-tts-msg:' . $m->id, 30);
            $lock->block(25);
            try {
                $path = DB::table('chat_messages')->where('id', $m->id)->value('audio');
                if (!$path || !$disk->exists($path)) {
                    $path = \App\Support\ChatVoice::speak((string) $m->display, (int) $m->conversation_id);
                    abort_unless($path, 503);
                    DB::table('chat_messages')->where('id', $m->id)->update(['audio' => $path]);
                }
            } finally {
                $lock->release();
            }
        }
        return response()->file($disk->path($path), ['Content-Type' => \App\Support\ChatVoice::mime($path), 'Cache-Control' => 'private, max-age=86400']);
    }

    /** Past chats from the last HISTORY_DAYS days, newest first (the open one included). */
    public function history(Request $r)
    {
        $rows = DB::table('chat_conversations as c')->where('c.user_id', $r->user()->id)->where('c.updated_at', '>=', now()->subDays(self::HISTORY_DAYS))
            ->orderByDesc('c.updated_at')->limit(30)->get(['c.id', 'c.status', 'c.created_at', 'c.updated_at']);
        $first = DB::table('chat_messages')->whereIn('conversation_id', $rows->pluck('id'))->where('role', 'user')->whereNotNull('display')
            ->selectRaw('conversation_id, MIN(id) as id, COUNT(*) as n')->groupBy('conversation_id')->get()->keyBy('conversation_id');
        $titles = DB::table('chat_messages')->whereIn('id', $first->pluck('id'))->pluck('display', 'conversation_id');
        return response()->json(['days' => self::HISTORY_DAYS, 'chats' => $rows->filter(fn ($c) => isset($first[$c->id]))->map(fn ($c) => [
            'id' => (int) $c->id, 'open' => $c->status === 'open',
            'title' => Str::limit((string) ($titles[$c->id] ?? ''), 70),
            'messages' => (int) $first[$c->id]->n,
            'when' => \Carbon\Carbon::parse($c->updated_at)->diffForHumans(),
            'today' => \Carbon\Carbon::parse($c->updated_at)->isToday(),
            'date' => \Carbon\Carbon::parse($c->updated_at)->format('M j'),
        ])->values()]);
    }

    /** One past chat, read-only. */
    public function historyShow(Request $r, int $id)
    {
        $conv = DB::table('chat_conversations')->where('id', $id)->where('user_id', $r->user()->id)->where('updated_at', '>=', now()->subDays(self::HISTORY_DAYS))->first();
        abort_unless($conv, 404);
        return response()->json(['id' => (int) $conv->id, 'open' => $conv->status === 'open', 'messages' => $this->bot->transcript($conv)]);
    }

    public function reset(Request $r)
    {
        $this->bot->close($r->user());
        return response()->json(['ok' => true]);
    }

    /** "Talk to a person": the customer's details and request, e-mailed to the shop's inbox. */
    public function handoff(Request $r)
    {
        $u = $r->user();
        // honeypot + a form filled faster than a person can read it
        if ($r->filled('website') || (int) $r->input('t', 0) < 3) return response()->json(['ok' => true]);
        $key = 'chatbot-handoff:' . $u->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['error' => 'We already have your request — someone will reply by e-mail.'], 429);
        }
        $d = $r->validate([
            'email' => 'required|email:rfc|max:190',
            'name' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:40',
            'request' => 'required|string|min:10|max:2000',
        ]);
        RateLimiter::hit($key, 3600);

        $conv = DB::table('chat_conversations')->where('user_id', $u->id)->where('status', 'open')->orderByDesc('id')->first();
        DB::table('chat_handoffs')->insert([
            'conversation_id' => $conv?->id, 'user_id' => $u->id, 'email' => $d['email'],
            'name' => $d['name'] ?? null, 'phone' => $d['phone'] ?? null, 'request' => strip_tags($d['request']),
            'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($conv) DB::table('chat_conversations')->where('id', $conv->id)->update(['handed_off_at' => now(), 'needs_human_at' => now(), 'updated_at' => now()]);

        $transcript = $conv ? collect($this->bot->transcript($conv))->take(-16)
            ->map(fn ($m) => ($m['role'] === 'user' ? 'Customer: ' : 'Assistant: ') . $m['text'])->implode("\n\n") : '';
        try {
            Mailer::simple((string) config('services.anthropic.inbox'), 'Assistant: ' . Str::limit($d['request'], 60), [
                'kicker' => 'UDS Assistant · request for a person',
                'title' => ($d['name'] ?? '') ?: $u->name ?: $d['email'],
                'intro' => strip_tags($d['request']),
                'kvTitle' => 'Customer',
                'kv' => array_filter([
                    'Reply to' => $d['email'],
                    'Name' => $d['name'] ?? $u->name,
                    'Phone' => $d['phone'] ?? $u->phone ?? null,
                    'Account' => $u->email . ' (#' . $u->id . ')',
                    'Wholesale' => $u->wholesale_status === 'approved' ? 'Approved' : null,
                ]),
                'pre' => $transcript ?: null,
            ], ['replyTo' => [$d['email'], $d['name'] ?? $u->name]]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => "We couldn't send that just now — please call (813) 524-6491."], 500);
        }
        return response()->json(['ok' => true]);
    }

    /** Messages left today; null = no limit (admins). */
    private function leftToday(int $userId): ?int
    {
        if (DB::table('users')->where('id', $userId)->value('is_admin')) return null;
        $used = DB::table('chat_messages as m')->join('chat_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->where('c.user_id', $userId)->where('m.role', 'user')->whereNotNull('m.display')
            ->where('m.created_at', '>=', now()->startOfDay())->count();
        return max(0, (int) config('services.anthropic.per_day', 60) - $used);
    }
}

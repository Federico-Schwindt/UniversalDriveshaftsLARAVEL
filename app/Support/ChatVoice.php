<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Voice in the store chat, through ElevenLabs: a customer's recorded message is transcribed (Scribe) and the
 * assistant's answer can be spoken back. Recordings and spoken answers are kept privately under
 * storage/app/chat-audio/{conversation}/ and only served to the customer in that chat and to admins.
 */
class ChatVoice
{
    public const MAX_BYTES = 4 * 1024 * 1024;   // about 2 minutes of browser audio
    public const MAX_SPOKEN_CHARS = 900;         // long answers are cut at a sentence; the text stays complete

    private const MIMES = ['webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'mp4' => 'audio/mp4', 'mpeg' => 'audio/mpeg', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'x-m4a' => 'audio/mp4', 'aac' => 'audio/aac'];

    /** Set for a while after ElevenLabs refuses us for the account (bad key, no credits, rate limit, outage). */
    public const DOWN_KEY = 'elevenlabs:down';
    private const DOWN_MINUTES = 30;

    public static function enabled(): bool
    {
        // while ElevenLabs is down the mic is hidden and answers are text, instead of failing every voice message
        return (bool) config('services.elevenlabs.key') && !Cache::get(self::DOWN_KEY);
    }

    /** 401 / 402 / 429 / 5xx are account- or service-wide: stop offering voice for a while. Returns whether it was. */
    private static function noteFailure(\Illuminate\Http\Client\Response $r): bool
    {
        $s = $r->status();
        if (!in_array($s, [401, 402, 429], true) && $s < 500) return false;
        Cache::put(self::DOWN_KEY, $s, now()->addMinutes(self::DOWN_MINUTES));
        Log::warning("voice: ElevenLabs answered $s, voice is off for " . self::DOWN_MINUTES . ' min');
        return true;
    }

    /** A "data:audio/…;base64,…" recording from the chat widget, stored as-is. Returns the storage path. */
    public static function storeDataUrl(string $dataUrl, int $conversationId): string
    {
        if (!preg_match('#^data:audio/([a-z0-9.+-]+)(?:;[a-z0-9=._-]+)*;base64,([A-Za-z0-9+/=\s]+)$#i', $dataUrl, $m)) throw new \RuntimeException('Not a recording.');
        $type = strtolower($m[1]);
        if (!isset(self::MIMES[$type])) throw new \RuntimeException('Unsupported recording format.');
        $bytes = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
        if ($bytes === false || strlen($bytes) < 800 || strlen($bytes) > self::MAX_BYTES) throw new \RuntimeException('Recording too short or too long.');
        $ext = ['mpeg' => 'mp3', 'x-m4a' => 'm4a', 'mp4' => 'm4a'][$type] ?? $type;
        $path = "chat-audio/$conversationId/" . Str::random(24) . '.' . $ext;
        Storage::disk('local')->put($path, $bytes);
        return $path;
    }

    /**
     * What the customer said, or null when it could not be understood / the service is down.
     * $why says which: 'down' (voice unavailable, ask them to type) or 'unclear' (try again).
     */
    public static function transcribe(string $path, ?string &$why = null): ?string
    {
        $why = null;
        if (!self::enabled()) { $why = 'down'; return null; }
        try {
            $r = Http::timeout(40)->withHeaders(['xi-api-key' => config('services.elevenlabs.key')])
                ->attach('file', Storage::disk('local')->get($path), basename($path))
                ->post('https://api.elevenlabs.io/v1/speech-to-text', ['model_id' => config('services.elevenlabs.stt_model'), 'tag_audio_events' => 'false']);
        } catch (\Throwable $e) {
            Log::warning('voice transcribe: ' . $e->getMessage());
            $why = 'down';
            return null;
        }
        if (!$r->successful()) {
            Log::warning('voice transcribe ' . $r->status() . ': ' . Str::limit($r->body(), 300));
            $why = self::noteFailure($r) ? 'down' : 'unclear';
            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $r->json('text')));
        if ($text === '') $why = 'unclear';
        return $text !== '' ? $text : null;
    }

    /** The assistant's answer as speech (MP3), stored next to the chat. Returns the storage path or null. */
    public static function speak(string $text, int $conversationId): ?string
    {
        if (!self::enabled()) return null;
        $say = self::speakable($text);
        if ($say === '') return null;
        $audio = self::tts($say, (string) config('services.elevenlabs.voice'));
        if ($audio === null) return null;
        $path = "chat-audio/$conversationId/" . Str::random(24) . '.mp3';
        Storage::disk('local')->put($path, $audio);
        return $path;
    }

    private static function tts(string $text, string $voice): ?string
    {
        $fallback = (string) config('services.elevenlabs.fallback_voice');
        // the chosen library voice needs a paid ElevenLabs plan; while it is refused, use the premade one
        if ($fallback && Cache::get('elevenlabs:voice_refused:' . $voice)) $voice = $fallback;
        try {
            $r = Http::timeout(30)->withHeaders(['xi-api-key' => config('services.elevenlabs.key'), 'Accept' => 'audio/mpeg'])
                ->post('https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($voice) . '?output_format=mp3_44100_64', [
                    'text' => $text, 'model_id' => config('services.elevenlabs.tts_model'),
                ]);
        } catch (\Throwable $e) {
            Log::warning('voice tts: ' . $e->getMessage());
            return null;
        }
        // only "this voice needs a paid plan" falls back; any other 402 (no credits) is the account, not the voice
        if ($r->status() === 402 && $voice !== $fallback && $fallback && in_array('paid_plan_required', [$r->json('detail.code'), $r->json('detail.status')], true)) {
            Cache::put('elevenlabs:voice_refused:' . $voice, 1, 3600);
            return self::tts($text, $fallback);
        }
        if (!$r->successful() || !str_starts_with((string) $r->header('Content-Type'), 'audio/')) {
            Log::warning('voice tts ' . $r->status() . ': ' . Str::limit($r->body(), 300));
            if (!$r->successful()) self::noteFailure($r);
            return null;
        }
        return $r->body();
    }

    public const VOICE_MAX_CHARS = 320;   // about 20 seconds of speech

    /**
     * Voice note or text for an answer. The assistant suggests ([[voice]] / [[text]]); these rules decide, so a
     * link, a list, a table of prices or a long answer is never only spoken:
     *  - off, no key, or something to read or copy (link, list, several part numbers or prices, long) → text;
     *  - the assistant asked for text → text; asked for voice → voice;
     *  - no suggestion → voice only for a short reply to a customer who spoke.
     * Setting assistant.voice_replies: auto | spoken (voice notes only answer voice messages) | off.
     */
    public static function decide(string $text, ?string $hint, bool $customerSpoke, string $mode = 'auto'): string
    {
        if ($mode === 'off' || !self::enabled() || !self::speakableShort($text)) return 'text';
        if ($mode === 'spoken' && !$customerSpoke) return 'text';
        if ($hint === 'text') return 'text';
        if ($hint === 'voice') return 'voice';
        return $customerSpoke && mb_strlen($text) <= 220 ? 'voice' : 'text';
    }

    /** Short, plain sentences that lose nothing when heard instead of read. */
    public static function speakableShort(string $text): bool
    {
        $t = trim($text);
        if ($t === '' || mb_strlen($t) > self::VOICE_MAX_CHARS) return false;
        if (preg_match('#https?://|www\.|/pay/|@[a-z0-9-]+\.[a-z]#i', $t)) return false;                 // links, e-mail addresses
        if (preg_match('#(^|\s)/[a-z]#i', $t)) return false;                                              // site paths ("/checkout")
        if (preg_match('/^\s*([-•*]|\d+[.)])\s+/m', $t)) return false;                                   // lists
        if (substr_count($t, "\n") >= 3) return false;                                                    // several paragraphs / lines
        if (preg_match_all('/\$\s?\d/', $t) >= 3) return false;                                           // a price table
        if (preg_match_all('/\b(?=[A-Z0-9-]*\d)(?=[A-Z0-9-]*[A-Z])[A-Z0-9]+(?:-[A-Z0-9]+)+\b/', $t) >= 3) return false;   // part / order numbers
        if (preg_match('/\b1Z[0-9A-Z]{16}\b|\b\d{12,22}\b/', $t)) return false;                           // tracking numbers to copy
        if (preg_match('/\|.*\|/', $t)) return false;                                                     // tables
        return true;
    }

    /** Plain sentences for the voice: no markdown, links or long part tables read out character by character. */
    public static function speakable(string $text): string
    {
        $t = preg_replace('#https?://\S+#', '', $text);
        $t = preg_replace('/\*\*(.+?)\*\*/u', '$1', $t);
        $t = preg_replace('/^\s*[-•]\s+/mu', '', $t);
        $t = str_replace(['#', '*', '`', '|'], '', $t);
        $t = trim(preg_replace('/\s+/u', ' ', $t));
        if (mb_strlen($t) > self::MAX_SPOKEN_CHARS) {
            $cut = mb_substr($t, 0, self::MAX_SPOKEN_CHARS);
            $end = max(mb_strrpos($cut, '. ') ?: 0, mb_strrpos($cut, '? ') ?: 0, mb_strrpos($cut, '! ') ?: 0);
            $t = ($end > 200 ? mb_substr($cut, 0, $end + 1) : $cut) . ' The rest is in the chat.';
        }
        return $t;
    }

    public static function mime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'aac' => 'audio/aac'][$ext] ?? 'application/octet-stream';
    }
}

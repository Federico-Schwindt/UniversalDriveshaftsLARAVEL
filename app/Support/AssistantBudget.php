<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Daily spend cap for UDS Assistant (owner, 2026-11-14: "máximo $30 en api y unos 10 mensajes por día por customer").
 * Every Claude answer adds its cost (tokens × the model's price, plus web searches) to today's row (Eastern time);
 * once today reaches Settings → Assistant "Daily API budget", the bot stops answering until midnight and the shop
 * gets one e-mail. Chats a person has taken over keep working: they cost nothing.
 */
class AssistantBudget
{
    /** USD per million tokens: input, output, cache read (cache writes bill 1.25 × input). Unknown models price as the dearest. */
    private const PRICES = [
        'claude-haiku-5-5' => [0.10, 0.50, 0.01],
        'claude-sonnet-5-5' => [2.00, 10.00, 0.20],
        'claude-sonnet-5' => [2.00, 10.00, 0.20],
        'claude-opus-5-5' => [4.00, 20.00, 0.20],
        'claude-opus-5' => [5.00, 25.00, 0.50],
        'claude-fable-5-1' => [10.00, 50.00, 0.25],
    ];
    private const DEAREST = [10.00, 50.00, 1.00];
    private const WEB_SEARCH_USD = 0.01;

    private static function today(): string
    {
        return now('America/New_York')->toDateString();
    }

    public static function limit(): float
    {
        return (float) Settings::get('assistant.daily_budget', 30);
    }

    public static function spentToday(): float
    {
        return (float) DB::table('assistant_spend')->where('day', self::today())->value('cost_usd');
    }

    public static function exhausted(): bool
    {
        $limit = self::limit();
        return $limit > 0 && self::spentToday() >= $limit;
    }

    /** Cost of one API answer from its usage block (SDK object or array). */
    public static function cost(string $model, object|array $u): float
    {
        $u = (object) $u;
        $p = self::PRICES[$model] ?? null;
        if (!$p) foreach (self::PRICES as $id => $row) if (str_starts_with($model, $id)) { $p = $row; break; }
        [$in, $out, $read] = $p ?? self::DEAREST;
        $searches = (int) ($u->serverToolUse->webSearchRequests ?? 0);
        return ((int) ($u->inputTokens ?? 0) * $in + (int) ($u->cacheCreationInputTokens ?? 0) * $in * 1.25
            + (int) ($u->cacheReadInputTokens ?? 0) * $read + (int) ($u->outputTokens ?? 0) * $out) / 1e6 + $searches * self::WEB_SEARCH_USD;
    }

    public static function record(string $model, object|array $usage): void
    {
        $cost = round(self::cost($model, $usage), 6);
        $day = self::today();
        DB::table('assistant_spend')->insertOrIgnore(['day' => $day, 'cost_usd' => 0, 'calls' => 0]);
        DB::table('assistant_spend')->where('day', $day)->update(['cost_usd' => DB::raw('cost_usd + ' . $cost), 'calls' => DB::raw('calls + 1')]);
        $limit = self::limit();
        if ($limit <= 0 || self::spentToday() < $limit) return;
        // one e-mail a day when the budget runs out
        if (!DB::table('assistant_spend')->where('day', $day)->whereNull('notified_at')->update(['notified_at' => now()])) return;
        try {
            Mailer::simple(config('uds.email'), 'UDS Assistant paused for today: daily budget reached', ['kicker' => 'Assistant', 'title' => 'The assistant reached its daily budget.',
                'intro' => sprintf('UDS Assistant spent $%.2f on the Claude API today (budget $%.2f). It stops answering until midnight Eastern; customers are offered "Talk to a person" and the phone number.', self::spentToday(), $limit),
                'after' => ['Raise or lower the budget in Admin → Settings → Assistant.'], 'signoff' => false]);
        } catch (\Throwable $e) { report($e); }
    }
}

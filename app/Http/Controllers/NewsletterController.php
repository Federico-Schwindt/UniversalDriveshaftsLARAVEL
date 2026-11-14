<?php

namespace App\Http\Controllers;

use App\Http\Controllers\RecoveryController;
use App\Services\BrevoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

class NewsletterController extends Controller
{
    public function subscribe(Request $r, BrevoService $brevo)
    {
        $d = $r->validate(['email' => 'required|email|max:190', 'name' => 'nullable|string|max:80', 'website' => 'nullable|max:0']); // website = honeypot
        $first = trim((string) ($d['name'] ?? '')) ?: strstr($d['email'], '@', true);
        $ok = $brevo->upsertContact($d['email'], ['FIRSTNAME' => $first, 'SIGNUP_SOURCE' => 'website-footer'], ['newsletter']);
        $email = strtolower($d['email']);
        // the form is anonymous, so anyone can type anyone's address: only the signed-in, verified owner of the
        // address lifts an earlier unsubscribe, and a suppressed address gets no welcome mail
        $mine = ($u = auth()->user()) && !empty($u->email_verified_at) && strcasecmp((string) $u->email, $email) === 0;
        if ($ok && $mine) DB::table('marketing_suppressions')->where('email', $email)->where('channel', 'newsletter')->delete();
        $suppressed = DB::table('marketing_suppressions')->where('email', $email)->where('channel', 'newsletter')->exists();
        if ($ok && !$suppressed && \Illuminate\Support\Facades\RateLimiter::attempt('nl-welcome:' . sha1($email), 1, fn () => true, 86400)) {
            // Brevo's {{ unsubscribe }} tag only works in campaigns, not in this transactional send: use our own signed link
            $unsub = self::unsubscribeUrl($d['email']);
            try { $brevo->send($d['email'], 'Welcome to Universal Driveshafts', view('emails.marketing.welcome', ['title' => 'Welcome to Universal Driveshafts', 'hero' => ['kicker' => 'Welcome aboard', 'title' => 'Thanks for joining.', 'text' => 'Driveline parts, custom shafts and straight answers from a shop that builds them every day.'], 'preheader' => 'What to expect from us', 'unsubscribe' => $unsub])->render(), $first, config('uds.email'), \App\Services\RecoveryMailer::unsubscribeHeaders($unsub)); } catch (\Throwable $e) {}
        }
        $msg = $ok ? 'You are on the list — check your inbox for a welcome note.' : 'Thanks! We could not reach the mailing service right now, but your address is noted.';
        if (!$ok) { try { DB::table('leads')->insert(['source' => 'newsletter', 'name' => $first, 'email' => $d['email'], 'message' => 'Newsletter signup (Brevo unavailable)', 'status' => 'new', 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {} }
        if ($r->expectsJson()) return response()->json(['ok' => true, 'message' => $msg]);
        return back()->with('newsletter_ok', $msg);
    }

    /** /newsletter/unsubscribe/{token}: token = 32-hex HMAC + base64url(e-mail), so it can't be forged for someone else's address. */
    public static function unsubscribeUrl(string $email): string
    {
        $email = strtolower(trim($email));
        return url('/newsletter/unsubscribe/' . self::sig($email) . rtrim(strtr(base64_encode($email), '+/', '-_'), '='));
    }

    private static function sig(string $email): string
    {
        return substr(hash_hmac('sha256', 'newsletter-unsub|' . $email, (string) config('app.key')), 0, 32);
    }

    /** GET from the footer link, POST from the mail client's one-click button. */
    public function unsubscribe(Request $r, string $token, BrevoService $brevo)
    {
        $email = strtolower((string) base64_decode(strtr(substr($token, 32), '-_', '+/'), true));
        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL) && hash_equals(self::sig($email), substr($token, 0, 32)), 404);
        RecoveryController::suppress($email, 'newsletter');
        $brevo->unsubscribe($email);
        if ($r->isMethod('post')) return response('Unsubscribed', 200);
        return response(Blade::render(<<<'BLADE'
@extends('layouts.app')
@section('title', __('Unsubscribed | Universal Driveshafts'))
@section('robots', 'noindex,nofollow')
@section('content')
<section class="section"><div class="wrap rcv-page"><div class="aempty">
  <b>{{ __('You are unsubscribed') }}</b>
  <p style="margin:.2rem 0 1rem">{{ __('We will not send newsletters to :email again. Order confirmations and anything you ask for still arrive as usual.', ['email' => $email]) }}</p>
  <div class="btn-row" style="justify-content:center"><a class="btn btn-primary" href="/">{{ __('Back to the site') }}</a></div>
</div></div></section>
@endsection
BLADE, ['email' => $email]));
    }
}

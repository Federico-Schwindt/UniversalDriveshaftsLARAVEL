<?php

namespace App\Http\Controllers;

use App\Support\AdminLog;
use App\Support\AdminTwoFactor;
use App\Support\EmailOtp;
use Illuminate\Http\Request;

/** The e-mailed code step between signing in and the admin (AdminTwoFactor). */
class AdminTwoFactorController extends Controller
{
    private function view(object $u)
    {
        return view('auth.verify-code', [
            'purpose' => 'admin', 'email' => $u->email, 'masked' => EmailOtp::mask($u->email),
            'action' => '/admin/verify', 'resend' => '/admin/verify/send', 'cooldown' => EmailOtp::cooldown('admin', $u->email),
            'back' => ['/', 'Back to the store'],
        ]);
    }

    public function show(Request $r)
    {
        $u = $r->user();
        abort_unless($u && $u->is_admin, 403);
        if (!AdminTwoFactor::enabled() || AdminTwoFactor::passed($r)) return redirect()->intended('/admin');
        // the first visit sends the code; a reload within its 10 minutes does not send another
        $sent = (int) $r->session()->get('admin_2fa_sent', 0);
        if (!$r->session()->has('errors') && now()->timestamp - $sent > EmailOtp::TTL_MIN * 60) {
            if ($err = EmailOtp::send('admin', $u->email, 'en')) return $this->view($u)->withErrors(['code' => $err]);
            $r->session()->put('admin_2fa_sent', now()->timestamp);
        }
        return $this->view($u);
    }

    public function verify(Request $r)
    {
        $u = $r->user();
        abort_unless($u && $u->is_admin, 403);
        $r->validate(['code' => 'required|string|max:12']);
        if ($err = EmailOtp::check('admin', $u->email, (string) $r->input('code'))) return back()->withErrors(['code' => $err]);
        $r->session()->regenerate();
        $r->session()->put('admin_2fa', $u->id);
        $r->session()->forget('admin_2fa_sent');
        AdminLog::record('admin.2fa', 'Signed in with an e-mailed code' . ($r->boolean('trust') ? ' (computer trusted for ' . AdminTwoFactor::DAYS . ' days)' : ''), 'user', $u->id);
        $res = redirect()->intended('/admin');
        return $r->boolean('trust') ? $res->withCookie(AdminTwoFactor::trustCookie($u)) : $res;
    }

    public function send(Request $r)
    {
        $u = $r->user();
        abort_unless($u && $u->is_admin, 403);
        if ($err = EmailOtp::send('admin', $u->email, 'en')) return back()->withErrors(['code' => $err]);
        $r->session()->put('admin_2fa_sent', now()->timestamp);
        return back()->with('code_sent', 'A new code is on its way to ' . EmailOtp::mask($u->email) . '.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function showLogin(Request $r)
    {
        if (Auth::check()) return redirect('/account');
        // ?redirect=/local/path brings the customer back where they were (designer save, wishlist…)
        $to = (string) $r->query('redirect', '');
        if ($to !== '' && str_starts_with($to, '/') && !str_starts_with($to, '//')) $r->session()->put('url.intended', $to);
        return view('auth.login', ['googleClient' => config('uds.google_client_id')]);
    }

    public function login(Request $r)
    {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required']);
        // customers imported from the old WordPress store sign in with their old password
        \App\Support\WpPassword::upgrade($cred['email'], (string) $cred['password']);
        // "Keep me signed in" is ticked by default; unticked on a shared computer, the session ends with the browser
        if (Auth::attempt($cred, $r->boolean('remember'))) {
            self::seedLocale(Auth::id());
            $r->session()->regenerate();
            return redirect()->intended(Auth::user()->is_admin ? '/admin' : '/account');
        }
        return back()->withErrors(['email' => __('Invalid email or password.')])->onlyInput('email');
    }

    /** E-mails follow the language the account was set up in; a later visit in another language never overrides the setting. */
    private static function seedLocale(int $id): void
    {
        \Illuminate\Support\Facades\DB::table('users')->where('id', $id)->where(fn ($q) => $q->whereNull('locale')->orWhere('locale', ''))->update(['locale' => app()->getLocale()]);
    }

    public function showRegister(Request $r)
    {
        if (Auth::check()) return redirect('/account');
        $to = (string) $r->query('redirect', '');
        if ($to !== '' && str_starts_with($to, '/') && !str_starts_with($to, '//')) $r->session()->put('url.intended', $to);
        // "Use a different e-mail" from the code step comes back here: keep the name and phone already typed
        $p = (array) $r->session()->get('pending_registration', []);
        // signed time stamp: a human needs a few seconds to fill the form, a script does not
        return view('auth.register', ['googleClient' => config('uds.google_client_id'), 'formTs' => encrypt(time()),
            'prefill' => ['name' => $p['name'] ?? '', 'phone' => $p['phone'] ?? '', 'email' => $p['email'] ?? '']]);
    }

    /**
     * Step 1: check the details, then e-mail a 6-digit code. No account exists until the code comes
     * back (registerVerify), so bots and mistyped addresses never leave accounts behind.
     */
    public function register(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:190|unique:users,email',
            'phone' => 'nullable|string|max:40',
            'password' => 'required|string|min:8|confirmed',
        ]);
        // bot traps: a hidden field people never see, and a form sent faster than anyone can type
        $tooFast = true;
        try { $tooFast = (time() - (int) decrypt((string) $r->input('_ts'))) < 3; } catch (\Throwable $e) {}
        if (filled($r->input('website')) || $tooFast) {
            \Illuminate\Support\Facades\Log::info('register: bot trap tripped', ['ip' => $r->ip(), 'honeypot' => filled($r->input('website')), 'fast' => $tooFast]);
            return back()->withInput($r->except('password', 'password_confirmation'))->withErrors(['email' => __('Please try again.')]);
        }
        $email = strtolower(trim($data['email']));
        $r->session()->put('pending_registration', [
            'name' => trim($data['name']), 'email' => $email, 'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']), 'locale' => app()->getLocale(), 'at' => time(),
        ]);
        if ($err = \App\Support\EmailOtp::send('register', $email, app()->getLocale())) {
            return back()->withInput($r->except('password', 'password_confirmation'))->withErrors(['email' => $err]);
        }
        return redirect('/register/verify');
    }

    public function showRegisterVerify(Request $r)
    {
        $p = $r->session()->get('pending_registration');
        if (!$p || time() - $p['at'] > 3600) return redirect('/register');
        return view('auth.verify-code', [
            'purpose' => 'register', 'email' => $p['email'], 'masked' => \App\Support\EmailOtp::mask($p['email']),
            'action' => '/register/verify', 'resend' => '/register/resend', 'cooldown' => \App\Support\EmailOtp::cooldown('register', $p['email']),
            'back' => ['/register', __('Use a different e-mail')],
        ]);
    }

    /** Step 2: the code matches, so the mailbox is real: create the account already verified and sign in. */
    public function registerVerify(Request $r)
    {
        $p = $r->session()->get('pending_registration');
        if (!$p) return redirect('/register');
        $r->validate(['code' => 'required|string|max:12']);
        if ($err = \App\Support\EmailOtp::check('register', $p['email'], (string) $r->input('code'))) return back()->withErrors(['code' => $err]);
        // the address may have been taken while the code was in the inbox
        if (User::whereRaw('LOWER(email) = ?', [$p['email']])->exists()) {
            $r->session()->forget('pending_registration');
            return redirect('/login')->withErrors(['email' => __('An account with this e-mail already exists. Sign in instead.')]);
        }
        $user = User::create(['name' => $p['name'], 'email' => $p['email'], 'phone' => $p['phone'], 'password' => $p['password']]);
        \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update(['email_verified_at' => now(), 'locale' => $p['locale'], 'last_login_at' => now()]);
        $r->session()->forget('pending_registration');
        Auth::login($user, true);
        $r->session()->regenerate();   // url.intended (a designer save, the wishlist…) survives the regenerate
        return redirect()->intended('/account')->with('acct_ok', __('Welcome to Universal Driveshafts! Your e-mail is confirmed and your account is ready.'));
    }

    public function registerResend(Request $r)
    {
        $p = $r->session()->get('pending_registration');
        if (!$p) return redirect('/register');
        if ($err = \App\Support\EmailOtp::send('register', $p['email'], $p['locale'] ?? null)) return back()->withErrors(['code' => $err]);
        return back()->with('code_sent', __('A new code is on its way to :email.', ['email' => \App\Support\EmailOtp::mask($p['email'])]));
    }

    /** Older accounts that never confirmed their address: confirm it with a code instead of a link. */
    public function sendVerifyCode(Request $r)
    {
        $u = Auth::user();
        if ($u->email_verified_at) return redirect('/account');
        if ($err = \App\Support\EmailOtp::send('verify', $u->email, $u->locale ?: null)) return back()->withErrors(['code' => $err]);
        return redirect('/account/verify')->with('code_sent', __('We sent a 6-digit code to :email.', ['email' => \App\Support\EmailOtp::mask($u->email)]));
    }

    public function showVerifyCode()
    {
        $u = Auth::user();
        if ($u->email_verified_at) return redirect('/account');
        return view('auth.verify-code', [
            'purpose' => 'verify', 'email' => $u->email, 'masked' => \App\Support\EmailOtp::mask($u->email),
            'action' => '/account/verify', 'resend' => '/account/verify/send', 'cooldown' => \App\Support\EmailOtp::cooldown('verify', $u->email),
            'back' => ['/account', __('Back to my account')],
        ]);
    }

    public function verifyCode(Request $r)
    {
        $u = Auth::user();
        $r->validate(['code' => 'required|string|max:12']);
        if ($err = \App\Support\EmailOtp::check('verify', $u->email, (string) $r->input('code'))) return back()->withErrors(['code' => $err]);
        \Illuminate\Support\Facades\DB::table('users')->where('id', $u->id)->update(['email_verified_at' => now(), 'updated_at' => now()]);
        return redirect('/account')->with('acct_ok', __('E-mail confirmed — your order history is now linked to this account.'));
    }

    public static function verifyUrl(object $user): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute('verification.verify', now()->addDays(3), ['id' => $user->id, 'hash' => sha1(strtolower($user->email))]);
    }

    private function sendVerification(object $user): void
    {
        try {
            \App\Support\Mailer::simple($user->email, 'Confirm your e-mail — Universal Driveshafts', ['kicker' => 'Account', 'title' => 'Confirm your e-mail address.', 'intro' => 'Tap the button to confirm this address. Once confirmed, your past orders placed with this e-mail appear in your account.', 'button' => ['Confirm my e-mail', self::verifyUrl($user)], 'after' => ['If you did not create an account, you can ignore this e-mail.'], 'signoff' => false]);
        } catch (\Throwable $e) { report($e); }
    }

    public function verifyEmail(Request $r, int $id, string $hash)
    {
        $user = Auth::user();
        abort_unless($user && $user->id === $id && hash_equals(sha1(strtolower($user->email)), $hash), 403);
        if (!$user->email_verified_at) \Illuminate\Support\Facades\DB::table('users')->where('id', $id)->update(['email_verified_at' => now(), 'updated_at' => now()]);
        return redirect('/account')->with('acct_ok', __('E-mail confirmed — your order history is now linked to this account.'));
    }

    public function resendVerification()
    {
        $u = Auth::user();
        if ($u && !$u->email_verified_at) $this->sendVerification($u);
        return back()->with('acct_ok', __('Confirmation e-mail sent to :email.', ['email' => $u->email]));
    }

    /** POST /auth/google — Google Identity Services credential (JWT) verification. */
    public function google(Request $r)
    {
        $clientId = config('uds.google_client_id');
        abort_unless($clientId, 404);
        $token = (string) $r->input('credential');
        $resp = Http::timeout(6)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $token]);
        if (!$resp->ok() || $resp->json('aud') !== $clientId || !$resp->json('email')
            || !in_array($resp->json('iss'), ['accounts.google.com', 'https://accounts.google.com'], true)
            || !in_array($resp->json('email_verified'), [true, 'true'], true)) {
            return redirect('/login')->withErrors(['email' => __('Google sign-in failed. Please try again.')]);
        }
        $email = strtolower($resp->json('email'));
        // the Google account itself first; the e-mail only links an account that has no Google id yet
        $user = User::where('google_id', (string) $resp->json('sub'))->first() ?? User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            $user = User::create([
                'name' => $resp->json('name') ?: $email,
                'email' => $email,
                'google_id' => $resp->json('sub'),
                'password' => null,
            ]);
        } elseif (!$user->google_id) {
            $user->google_id = $resp->json('sub');
            $user->save();
        }
        if (!$user->email_verified_at) { $user->forceFill(['email_verified_at' => now()])->save(); }   // Google already verified this mailbox
        Auth::login($user, true);
        self::seedLocale($user->id);
        $r->session()->regenerate();
        return redirect()->intended($user->is_admin ? '/admin' : '/account');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect('/');
    }

    // ---- PASSWORD RESET ----------------------------------------------

    public function showForgot()
    {
        return view('auth.forgot');
    }

    /** Always answers the same way, so the form cannot be used to find out which e-mails have accounts. */
    public function sendReset(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        $key = 'pwreset:' . strtolower($r->input('email')) . ':' . $r->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('reset_sent', true);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 900);
        \Illuminate\Support\Facades\Password::sendResetLink($r->only('email'));
        return back()->with('reset_sent', true);
    }

    public function showReset(Request $r, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => (string) $r->query('email', '')]);
    }

    public function resetPassword(Request $r)
    {
        $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => 'required|min:8|confirmed']);
        $status = \Illuminate\Support\Facades\Password::reset($r->only('email', 'password', 'password_confirmation', 'token'), function ($user, $password) {
            $user->forceFill(['password' => \Illuminate\Support\Facades\Hash::make($password), 'remember_token' => \Illuminate\Support\Str::random(60), 'email_verified_at' => $user->email_verified_at ?? now(), 'wp_password' => null])->save();
            \Auth::login($user);
        });
        if ($status === \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            $r->session()->regenerate();   // new session id after sign-in, so a pre-planted cookie can't be reused
            return redirect('/account')->with('ok', __('Your password has been changed. You are signed in.'));
        }
        return back()->withInput($r->only('email'))->withErrors(['email' => __('That reset link is invalid or has expired. Request a new one below.')]);
    }
}

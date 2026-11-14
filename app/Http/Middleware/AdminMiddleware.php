<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect('/login?admin=1');
        }
        abort_unless(Auth::user()->is_admin, 403, 'Admins only.');
        // the e-mailed sign-in code (AdminTwoFactor), whichever way the admin signed in
        if (\App\Support\AdminTwoFactor::enabled() && !\App\Support\AdminTwoFactor::passed($request)) {
            if ($request->expectsJson() || !$request->isMethod('GET')) abort(403, 'Confirm the sign-in code sent to your e-mail first.');
            $request->session()->put('url.intended', $request->fullUrl());
            return redirect('/admin/verify');
        }
        return $next($request);
    }
}

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
        return $next($request);
    }
}

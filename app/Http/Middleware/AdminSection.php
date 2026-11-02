<?php

namespace App\Http\Middleware;

use App\Support\AdminAccess;
use Closure;
use Illuminate\Http\Request;

class AdminSection
{
    public function handle(Request $request, Closure $next, string $section)
    {
        abort_unless(AdminAccess::can($section), 403, 'Your admin role does not include this section.');
        return $next($request);
    }
}

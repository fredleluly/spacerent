<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /** Pemakaian: ->middleware('role:seller') atau 'role:admin,seller'. */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest($request->is('admin*') ? route('admin.login') : route('login'));
        }

        abort_unless(in_array($user->role, $roles, true), 403, 'Anda tidak punya akses ke halaman ini.');

        return $next($request);
    }
}

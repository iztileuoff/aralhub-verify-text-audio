<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Let the request through only when the user's current role grants one of
     * the given permissions (RoleEnum::getPermissionsArray(), so "admin" also
     * covers the super admin).
     *
     * Checked against the role on the user row rather than the token's
     * abilities: a token keeps the abilities it was issued with, so a user
     * whose role changed would otherwise keep the old access.
     *
     * Usage: ->middleware('role:trimmer') or ->middleware('role:admin,moderator').
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $granted = $request->user()?->role?->getPermissionsArray() ?? [];

        if (array_intersect($permissions, $granted) === []) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return $next($request);
    }
}

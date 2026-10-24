<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserLacksRole
{
    /**
     * Reject the request when the user's current role grants one of the given
     * permissions. Guards the existing admin and verify routes against roles
     * that must see only their own section (the call trimmers), without
     * changing who else can reach those routes today.
     *
     * Usage: ->middleware('role.deny:trimmer').
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $granted = $request->user()?->role?->getPermissionsArray() ?? [];

        if (array_intersect($permissions, $granted) !== []) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return $next($request);
    }
}

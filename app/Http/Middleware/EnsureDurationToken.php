<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDurationToken
{
    /**
     * Let through only the external duration script: it sends the shared
     * secret from config('dataset.duration_token') in the X-Duration-Token
     * header. The endpoints it calls rewrite converted files and durations,
     * which the .tsv export is built from, so they stay closed while no
     * secret is configured.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('dataset.duration_token');
        $given = (string) $request->header('X-Duration-Token');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}

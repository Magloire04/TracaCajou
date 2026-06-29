<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyCooperativeAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        $cooperativeId = $request->route('cooperativeId');

        if ($request->user()->cooperative_id !== $cooperativeId) {
            app(\App\Services\SecurityLogger::class)->log403($request, 'COOPERATIVE_ACCESS_DENIED');
            return response()->json([
                'error' => [
                    'code'    => 'COOPERATIVE_ACCESS_DENIED',
                    'message' => "Vous n'avez pas accès à cette coopérative.",
                    'status'  => 403,
                ],
            ], 403);
        }

        return $next($request);
    }
}

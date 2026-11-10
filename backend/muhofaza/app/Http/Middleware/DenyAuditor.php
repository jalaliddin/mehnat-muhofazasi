<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Auditor faqat Monitoring bo'limini ko'radi — qolgan himoyalangan API'lar unga yopiq.
 */
class DenyAuditor
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->isAuditor()) {
            return response()->json(['message' => "Auditor faqat Monitoring bo'limini ko'radi"], 403);
        }

        return $next($request);
    }
}

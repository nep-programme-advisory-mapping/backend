<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNepAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isNepAdmin()) {
            return response()->json([
                'message' => 'Only NEP Admin can perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
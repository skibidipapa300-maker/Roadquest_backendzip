<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // User is already attached by AuthenticateWithToken middleware
        $user = $request->user();

        // Allow if user is admin OR staff
        if (!$user || ($user->role !== 'admin' && $user->role !== 'staff')) {
            return response()->json(['message' => 'Unauthorized. Staff or Admin access required.'], 403);
        }

        return $next($request);
    }
}



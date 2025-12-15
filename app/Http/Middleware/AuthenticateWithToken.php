<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\UserToken;
use Carbon\Carbon;

class AuthenticateWithToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization');

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $token = str_replace('Bearer ', '', $token);

        $userToken = UserToken::where('token', $token)
            ->where('expires_at', '>', Carbon::now())
            ->with('user')
            ->first();

        if (!$userToken) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        }

        // Attach user to request so we can access it in controllers
        $request->merge(['user' => $userToken->user]);
        $request->setUserResolver(function () use ($userToken) {
            return $userToken->user;
        });

        return $next($request);
    }
}



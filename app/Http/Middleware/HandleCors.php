<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }

        // Get the origin from the request
        $origin = $request->headers->get('Origin');
        
        // Allowed origins - include Cordova apps (null origin) and web origins
        $allowedOrigins = [
            'https://skibidipapa300-maker.github.io',
            'http://localhost:5500',
            'http://127.0.0.1:5500',
            'http://localhost:8000',
            'http://127.0.0.1:8000',
        ];
        
        // Determine which origin to allow
        // For Cordova apps, origin is typically null, empty, or file://
        // Allow wildcard for Cordova apps and unknown origins
        $allowOrigin = '*';
        
        // If origin is in allowed list, use it
        if ($origin && in_array($origin, $allowedOrigins)) {
            $allowOrigin = $origin;
        }
        
        // Note: When using wildcard (*), credentials cannot be true
        // So we'll set credentials to false when using wildcard
        $allowCredentials = ($allowOrigin !== '*');

        // Add CORS headers to all responses
        return $response
            ->header('Access-Control-Allow-Origin', $allowOrigin)
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN, Accept')
            ->header('Access-Control-Allow-Credentials', $allowCredentials ? 'true' : 'false')
            ->header('Access-Control-Max-Age', '3600');
    }
}

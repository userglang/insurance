<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stevebauman\Location\Facades\Location;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class RestrictToPhilippines
{
    protected $allowedIps = [
        '123.45.67.89', // Replace with real IP(s)
        '127.0.0.1',    // Localhost
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // Allow access for specific IPs
        if (in_array($ip, $this->allowedIps)) {
            return $next($request);
        }

        // Get location based on IP
        $location = Location::get($ip);

        if ($location && $location->countryCode === 'PH') {
            return $next($request);
        }

        // Log denied access only
        Log::warning('Access denied (outside PH)', [
            'ip' => $ip,
            'country' => $location->countryCode ?? 'unknown',
            'city' => $location->cityName ?? 'unknown',
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
        ]);

        // Block access
        abort(403);
    }
}

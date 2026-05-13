<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stevebauman\Location\Facades\Location;
use Symfony\Component\HttpFoundation\Response;

class RestrictToPhilippines
{
    private const CACHE_TTL = 86400; // 24 hours per IP

    protected $allowedIps = [
        '123.45.67.89', // Replace with real IP(s)
        '127.0.0.1',    // Localhost
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        if (in_array($ip, $this->allowedIps)) {
            return $next($request);
        }

        // Cached per IP for 24 hours to avoid repeated external calls
        $location = Cache::remember("ip_location:{$ip}", self::CACHE_TTL, fn() => Location::get($ip));

        if ($location && $location->countryCode === 'PH') {
            return $next($request);
        }

        Log::warning('Access denied (outside PH)', [
            'ip'         => $ip,
            'country'    => $location->countryCode ?? 'unknown',
            'city'       => $location->cityName ?? 'unknown',
            'user_agent' => $request->userAgent(),
            'path'       => $request->path(),
        ]);

        abort(403);
    }
}

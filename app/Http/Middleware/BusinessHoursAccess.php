<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BusinessHoursAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $now = Carbon::now('Asia/Manila');

        $dayOfWeek = $now->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
        $hour = $now->hour;

        if ($dayOfWeek === Carbon::SUNDAY) {
            abort(403);
        }

        if ($dayOfWeek === Carbon::SATURDAY) {
            if ($hour < 8 || $hour >= 12) {
                abort(403);
            }
        }

        if ($dayOfWeek >= Carbon::MONDAY && $dayOfWeek <= Carbon::FRIDAY) {
            if ($hour < 8 || $hour >= 17) {
                abort(403);
            }
        }

        return $next($request);
    }
}

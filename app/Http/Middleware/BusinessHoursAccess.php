<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BusinessHoursAccess
{
    private const TIMEZONE = 'Asia/Manila';

    private const WEEKDAY_START = 7;  // 7:00 AM
    private const WEEKDAY_END   = 21; // 9:00 PM

    private const SATURDAY_START = 7;  // 7:00 AM
    private const SATURDAY_END   = 15; // 3:00 PM

    public function handle(Request $request, Closure $next): Response
    {
        $now = Carbon::now(self::TIMEZONE);

        if (!$this->isWithinBusinessHours($now)) {
            return $this->denyAccess();
        }

        return $next($request);
    }

    private function isWithinBusinessHours(Carbon $now): bool
    {
        $day  = $now->dayOfWeek;
        $hour = $now->hour;

        return match (true) {
            $day === Carbon::SUNDAY                                => false,
            $day === Carbon::SATURDAY                             => $hour >= self::SATURDAY_START && $hour < self::SATURDAY_END,
            $day >= Carbon::MONDAY && $day <= Carbon::FRIDAY      => $hour >= self::WEEKDAY_START  && $hour < self::WEEKDAY_END,
            default                                               => false,
        };
    }

    private function denyAccess(): Response
    {
        $message = 'Access is only allowed during business hours (Mon–Fri 8AM–8PM, Sat 8AM–12PM, Philippine Time).';

        if (request()->expectsJson()) {
            return response()->json(
                ['error' => $message],
                Response::HTTP_FORBIDDEN
            );
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInputMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Recursively sanitize input
        $sanitized = $this->sanitize($request->all());

        // Replace original input with sanitized version
        $request->merge($sanitized);

        return $next($request);
    }

    private function sanitize(array $data): array
    {
        return collect($data)->map(function ($value) {
            if (is_array($value)) {
                return $this->sanitize($value); // Recurse into nested arrays
            }

            if (is_string($value)) {
                return trim(strip_tags($value));
            }

            return $value;
        })->toArray();
    }
}

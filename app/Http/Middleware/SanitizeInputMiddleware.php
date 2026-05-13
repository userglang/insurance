<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInputMiddleware
{
    /**
     * Fields that should never be sanitized (passwords, tokens, etc.)
     */
    private const EXCLUDED_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        '_token',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $sanitized = $this->sanitize($request->all());

        $request->merge($sanitized);

        return $next($request);
    }

    private function sanitize(array $data, string $prefix = ''): array
    {
        return collect($data)->map(function ($value, $key) use ($prefix) {
            $field = $prefix ? "{$prefix}.{$key}" : (string) $key;

            // Skip sensitive fields entirely
            if (in_array($key, self::EXCLUDED_FIELDS, true)) {
                return $value;
            }

            if (is_array($value)) {
                return $this->sanitize($value, $field);
            }

            if (is_string($value)) {
                return $this->sanitizeString($value);
            }

            return $value;
        })->toArray();
    }

    private function sanitizeString(string $value): string
    {
        // 1. Trim whitespace
        // 2. Strip HTML tags
        // 3. Encode special characters to prevent XSS
        return htmlspecialchars(
            trim(strip_tags($value)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }
}

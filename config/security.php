<?php

return [
    // Default passwords from the .env file, or an empty array if not provided
    'default_passwords' => explode(',', env('DEFAULT_PASSWORDS', '')),

    // Minimum password length requirement
    'min_password_length' => env('MIN_PASSWORD_LENGTH', 8),

    // Regex pattern for password strength (e.g., require at least one number and one uppercase letter)
    'password_strength_regex' => env('PASSWORD_STRENGTH_REGEX', '/^(?=.*[A-Z])(?=.*\d)/'),

    // Max failed login attempts (for rate limiting, brute-force protection)
    'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),

    'default_passwords' => array_filter(
        array_map('trim', explode(',', env('DEFAULT_PASSWORDS', '')))
    ),
];

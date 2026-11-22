<?php

namespace App\Services;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SecurityService
{
    /**
     * Configure rate limiting for critical endpoints.
     */
    public static function configureRateLimits(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->ip());
        });
    }

    /**
     * Validate file upload for security concerns.
     */
    public static function validateUpload(Request $request, string $field = 'file'): array
    {
        $rules = [
            $field => [
                'required',
                'file',
                'max:10240', // 10MB max
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,mp4,mp3,zip',
            ],
        ];

        return $request->validate($rules);
    }

    /**
     * Sanitize user input to prevent XSS.
     */
    public static function sanitize(string $input): string
    {
        return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate a secure random token.
     */
    public static function generateToken(int $length = 64): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Check if a password meets strength requirements.
     */
    public static function checkPasswordStrength(string $password): array
    {
        $checks = [
            'min_length' => strlen($password) >= 8,
            'has_uppercase' => preg_match('/[A-Z]/', $password) === 1,
            'has_lowercase' => preg_match('/[a-z]/', $password) === 1,
            'has_number' => preg_match('/[0-9]/', $password) === 1,
            'has_special' => preg_match('/[^A-Za-z0-9]/', $password) === 1,
        ];

        $score = array_sum($checks);
        $strength = match (true) {
            $score >= 5 => 'strong',
            $score >= 3 => 'medium',
            default => 'weak',
        };

        return [
            'checks' => $checks,
            'score' => $score,
            'strength' => $strength,
            'is_acceptable' => $score >= 3,
        ];
    }

    /**
     * Get security headers for the application.
     */
    public static function getSecurityHeaders(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=()',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        ];
    }

    /**
     * Audit the current security configuration.
     */
    public static function auditConfiguration(): array
    {
        return [
            'debug_mode' => config('app.debug') ? 'WARNING: Debug mode is ON' : 'OK',
            'app_env' => config('app.env'),
            'https_forced' => config('app.url') && str_starts_with(config('app.url'), 'https') ? 'OK' : 'WARNING: Not using HTTPS',
            'session_driver' => config('session.driver'),
            'session_secure' => config('session.secure_cookie') ? 'OK' : 'WARNING: Secure cookie not enforced',
            'csrf_protection' => 'Enabled (Laravel default)',
            'password_hashing' => 'bcrypt (Laravel default)',
            'rate_limiting' => 'Configured',
        ];
    }
}

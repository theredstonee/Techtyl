<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class XssProtection
{
    /**
     * Handle an incoming request and sanitize input to prevent XSS attacks.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize all input data
        $input = $request->all();

        array_walk_recursive($input, function (&$value) {
            if (is_string($value)) {
                // Remove script tags and encode special characters
                $value = $this->sanitizeInput($value);
            }
        });

        $request->merge($input);

        $response = $next($request);

        // Add security headers
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self';"
        );

        return $response;
    }

    /**
     * Sanitize input to prevent XSS attacks
     */
    private function sanitizeInput(string $value): string
    {
        // Remove null bytes
        $value = str_replace(chr(0), '', $value);

        // Remove script tags
        $value = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value);

        // Remove dangerous event handlers
        $value = preg_replace('/\s*on\w+\s*=\s*["\'].*?["\']/i', '', $value);

        // Remove javascript: protocol
        $value = preg_replace('/javascript:/i', '', $value);

        // Encode special HTML characters
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);

        return $value;
    }
}

<?php
/**
 * Space Cadet CMS — Security Headers + CORS
 */

function sc_send_headers(bool $isContentApi = false): void {
    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

    // CORS — content API allows any origin (read-only public data)
    // Admin API is same-origin only
    if ($isContentApi) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
    } else {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        if ($origin && parse_url($origin, PHP_URL_HOST) === $host) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
        }
    }

    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

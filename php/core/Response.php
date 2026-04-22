<?php
/**
 * Space Cadet CMS — HTTP Response Helpers
 */

class Response {

    public static function json(mixed $data, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, int $status = 200): never {
        self::json(['data' => $data, 'ok' => true], $status);
    }

    public static function created(mixed $data): never {
        self::json(['data' => $data, 'ok' => true], 201);
    }

    public static function noContent(): never {
        http_response_code(204);
        exit;
    }

    public static function error(string $message, int $status = 400, string $code = ''): never {
        self::json([
            'ok'    => false,
            'error' => array_filter([
                'message' => $message,
                'code'    => $code ?: null,
            ]),
        ], $status);
    }

    public static function notFound(string $message = 'Not found'): never {
        self::error($message, 404, 'NOT_FOUND');
    }

    public static function unauthorized(string $message = 'Unauthorized'): never {
        self::error($message, 401, 'UNAUTHORIZED');
    }

    public static function forbidden(string $message = 'Forbidden'): never {
        self::error($message, 403, 'FORBIDDEN');
    }

    public static function tooManyRequests(): never {
        http_response_code(429);
        header('Retry-After: 60');
        self::error('Rate limit exceeded. Please slow down.', 429, 'RATE_LIMITED');
    }

    public static function validationError(array $errors): never {
        self::json([
            'ok'     => false,
            'error'  => ['message' => 'Validation failed', 'code' => 'VALIDATION_ERROR'],
            'errors' => $errors,
        ], 422);
    }

    /**
     * Paginated list response.
     */
    public static function paginated(array $rows, int $total, int $page, int $perPage): never {
        self::json([
            'data' => $rows,
            'meta' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                'has_next'    => ($page * $perPage) < $total,
            ],
            'ok' => true,
        ]);
    }
}

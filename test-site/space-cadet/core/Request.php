<?php
/**
 * Space Cadet CMS — HTTP Request Wrapper
 */

class Request {
    private array  $get;
    private array  $post;
    private array  $files;
    private array  $server;
    private ?array $jsonBody = null;
    private bool   $jsonParsed = false;

    public function __construct() {
        $this->get    = $_GET    ?? [];
        $this->post   = $_POST   ?? [];
        $this->files  = $_FILES  ?? [];
        $this->server = $_SERVER ?? [];
    }

    // ── Query string ──────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed {
        return isset($this->get[$key]) ? $this->sanitizeString($this->get[$key]) : $default;
    }

    public function getInt(string $key, int $default = 0): int {
        return isset($this->get[$key]) ? (int) $this->get[$key] : $default;
    }

    public function getAll(): array {
        return $this->get;
    }

    // ── POST body ─────────────────────────────────────────────

    public function post(string $key, mixed $default = null): mixed {
        // Try JSON body first, then form body
        $json = $this->json();
        if ($json !== null && array_key_exists($key, $json)) {
            $val = $json[$key];
            return is_string($val) ? $this->sanitizeString($val) : $val;
        }
        return isset($this->post[$key]) ? $this->sanitizeString($this->post[$key]) : $default;
    }

    public function postRaw(string $key, mixed $default = null): mixed {
        $json = $this->json();
        if ($json !== null && array_key_exists($key, $json)) {
            return $json[$key];
        }
        return $this->post[$key] ?? $default;
    }

    public function postInt(string $key, int $default = 0): int {
        return (int) ($this->postRaw($key) ?? $default);
    }

    public function postBool(string $key, bool $default = false): bool {
        $val = $this->postRaw($key);
        if ($val === null) return $default;
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    // ── JSON body ─────────────────────────────────────────────

    public function json(): ?array {
        if (!$this->jsonParsed) {
            $this->jsonParsed = true;
            $ct = $this->header('Content-Type') ?? '';
            if (str_contains($ct, 'application/json')) {
                $raw = file_get_contents('php://input');
                if ($raw !== '' && $raw !== false) {
                    $decoded = json_decode($raw, true);
                    $this->jsonBody = is_array($decoded) ? $decoded : null;
                }
            }
        }
        return $this->jsonBody;
    }

    public function body(): string {
        return file_get_contents('php://input') ?: '';
    }

    // ── Files ─────────────────────────────────────────────────

    public function file(string $key): ?array {
        return $this->files[$key] ?? null;
    }

    // ── Headers ───────────────────────────────────────────────

    public function header(string $name): ?string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    public function bearerToken(): ?string {
        $auth = $this->header('Authorization') ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    // ── Request metadata ──────────────────────────────────────

    public function method(): string {
        // Allow method override via query param for environments that strip verbs
        return strtoupper($this->get['method'] ?? $this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function action(): string {
        return $this->get['action'] ?? '';
    }

    public function ip(): string {
        // Respect forwarded IP from trusted proxy
        $forwarded = $this->server['HTTP_X_FORWARDED_FOR'] ?? null;
        if ($forwarded) {
            return trim(explode(',', $forwarded)[0]);
        }
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function isJson(): bool {
        $ct = $this->header('Content-Type') ?? '';
        return str_contains($ct, 'application/json');
    }

    public function userAgent(): string {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    // ── Helpers ───────────────────────────────────────────────

    private function sanitizeString(mixed $val): mixed {
        if (!is_string($val)) return $val;
        return htmlspecialchars(strip_tags($val), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

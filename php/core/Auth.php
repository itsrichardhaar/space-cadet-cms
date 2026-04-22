<?php
/**
 * Space Cadet CMS — Authentication & Authorization
 */

class Auth {
    private static ?array $currentUser = null;
    private static ?string $csrfToken  = null;

    // Role hierarchy (higher index = more permissions)
    private const ROLES = [
        'free_member'  => 0,
        'paid_member'  => 1,
        'editor'       => 2,
        'developer'    => 3,
        'admin'        => 4,
        'super_admin'  => 5,
    ];

    // ── Initialise (call once per request) ────────────────────

    public static function init(Request $req): void {
        // 1. Try session cookie
        if (self::fromSession($req)) return;
        // 2. Try Bearer API key
        $token = $req->bearerToken();
        if ($token) {
            self::fromApiKey($token);
        }
    }

    // ── Session auth ──────────────────────────────────────────

    private static function fromSession(Request $req): bool {
        $cookieName = SC_SESSION_NAME;
        if (empty($_COOKIE[$cookieName])) return false;

        $sessionId = $_COOKIE[$cookieName];
        $now       = time();

        $row = Database::queryOne(
            "SELECT s.id, s.user_id, s.expires_at, u.id as uid, u.email, u.display_name,
                    u.role, u.status, u.password_hash
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.id = ? AND s.expires_at > ? AND u.status = 'active'",
            [$sessionId, $now]
        );

        if (!$row) {
            self::clearCookie();
            return false;
        }

        // Slide session expiry
        Database::execute(
            "UPDATE sessions SET expires_at = ? WHERE id = ?",
            [$now + SC_SESSION_TTL, $sessionId]
        );

        self::$currentUser = $row;
        return true;
    }

    // ── API key auth ──────────────────────────────────────────

    private static function fromApiKey(string $token): bool {
        if (!str_starts_with($token, 'sc_')) return false;

        $prefix = substr($token, 0, 12); // "sc_" + 9 chars
        $row    = Database::queryOne(
            "SELECT k.*, u.id as uid, u.email, u.display_name, u.role, u.status
             FROM api_keys k
             JOIN users u ON u.id = k.user_id
             WHERE k.key_prefix = ? AND u.status = 'active'
               AND (k.expires_at IS NULL OR k.expires_at > ?)",
            [$prefix, time()]
        );

        if (!$row) return false;
        if (!password_verify($token, $row['key_hash'])) return false;

        Database::execute("UPDATE api_keys SET last_used_at = ? WHERE id = ?", [time(), $row['id']]);
        self::$currentUser = $row;
        return true;
    }

    // ── Session management ────────────────────────────────────

    public static function createSession(array $user, Request $req): string {
        $id  = bin2hex(random_bytes(32));
        $now = time();

        Database::execute(
            "INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$id, $user['id'], $req->ip(), $req->userAgent(), $now + SC_SESSION_TTL, $now]
        );

        // Update last login
        Database::execute("UPDATE users SET last_login_at = ? WHERE id = ?", [$now, $user['id']]);

        self::setCookie($id);
        return $id;
    }

    public static function destroySession(): void {
        $cookieName = SC_SESSION_NAME;
        if (!empty($_COOKIE[$cookieName])) {
            Database::execute("DELETE FROM sessions WHERE id = ?", [$_COOKIE[$cookieName]]);
        }
        self::clearCookie();
        self::$currentUser = null;
    }

    private static function setCookie(string $id): void {
        $secure   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $options  = [
            'expires'  => time() + SC_SESSION_TTL,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ];
        setcookie(SC_SESSION_NAME, $id, $options);
    }

    private static function clearCookie(): void {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(SC_SESSION_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    // ── CSRF ──────────────────────────────────────────────────

    public static function csrfToken(): string {
        if (self::$csrfToken !== null) return json_encode(self::$csrfToken);

        // Session-tied CSRF token
        $sessionId = $_COOKIE[SC_SESSION_NAME] ?? 'guest';
        $token     = hash_hmac('sha256', $sessionId . date('Ymd'), SC_SECRET);
        self::$csrfToken = $token;
        return json_encode($token);
    }

    public static function verifyCsrf(Request $req): bool {
        $token     = $req->header('X-CSRF-Token') ?? $req->post('_csrf') ?? '';
        $sessionId = $_COOKIE[SC_SESSION_NAME] ?? 'guest';
        $expected  = hash_hmac('sha256', $sessionId . date('Ymd'), SC_SECRET);
        return hash_equals($expected, $token);
    }

    // ── Authorization ─────────────────────────────────────────

    public static function user(): ?array {
        return self::$currentUser;
    }

    public static function userId(): ?int {
        return self::$currentUser ? (int) self::$currentUser['uid'] : null;
    }

    public static function check(): bool {
        return self::$currentUser !== null;
    }

    public static function role(): ?string {
        return self::$currentUser['role'] ?? null;
    }

    /**
     * Require authentication. Aborts with 401 if not logged in.
     */
    public static function requireAuth(): void {
        if (!self::check()) {
            Response::unauthorized();
        }
    }

    /**
     * Require a minimum role. Aborts with 401/403 if not met.
     *
     * @param string $minRole  One of: editor, developer, admin, super_admin
     */
    public static function requireRole(string $minRole): void {
        self::requireAuth();
        $userLevel = self::ROLES[self::role()] ?? -1;
        $minLevel  = self::ROLES[$minRole]     ?? 99;
        if ($userLevel < $minLevel) {
            Response::forbidden("Requires {$minRole} role or higher.");
        }
    }

    /**
     * Check role without aborting.
     */
    public static function hasRole(string $minRole): bool {
        if (!self::check()) return false;
        $userLevel = self::ROLES[self::role()] ?? -1;
        $minLevel  = self::ROLES[$minRole]     ?? 99;
        return $userLevel >= $minLevel;
    }

    /**
     * Check whether the current user is an admin-level user
     * (admin or super_admin) for admin-only CMS operations.
     */
    public static function isAdmin(): bool {
        return self::hasRole('admin');
    }

    /**
     * Check whether user can edit content (editor or above).
     */
    public static function isEditor(): bool {
        return self::hasRole('editor');
    }

    /**
     * Check whether current user is a front-end member (not an admin user).
     */
    public static function isMember(): bool {
        $role = self::role();
        return in_array($role, ['free_member', 'paid_member'], true);
    }

    // ── API key generation ────────────────────────────────────

    /**
     * Generate a new sc_ API key. Returns [plaintext, prefix, hash].
     */
    public static function generateApiKey(): array {
        $random    = bin2hex(random_bytes(24));
        $plaintext = 'sc_' . $random;
        $prefix    = substr($plaintext, 0, 12);
        $hash      = password_hash($plaintext, PASSWORD_BCRYPT, ['cost' => 12]);
        return [$plaintext, $prefix, $hash];
    }
}

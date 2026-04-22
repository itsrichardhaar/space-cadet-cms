<?php
/**
 * Space Cadet CMS — SQLite Sliding Window Rate Limiter
 */

class RateLimit {

    /**
     * Check a rate limit bucket. Returns true if under limit (allowed),
     * false if limit exceeded. Records the attempt on success.
     *
     * @param string $bucket      e.g. "ip:1.2.3.4" or "key:sc_xxx"
     * @param string $action      e.g. "content_api", "admin_mutation", "login"
     * @param int    $max         Max allowed hits in the window
     * @param int    $windowSecs  Rolling window in seconds
     */
    public static function check(string $bucket, string $action, int $max, int $windowSecs): bool {
        $windowStart = time() - $windowSecs;

        // Count hits in window
        $count = (int) Database::queryOne(
            "SELECT COUNT(*) as c FROM rate_limit_log WHERE bucket = ? AND action = ? AND hit_at > ?",
            [$bucket, $action, $windowStart]
        )['c'];

        if ($count >= $max) {
            return false;
        }

        // Record this hit
        Database::execute(
            "INSERT INTO rate_limit_log (bucket, action, hit_at) VALUES (?, ?, ?)",
            [$bucket, $action, time()]
        );

        // Occasionally purge expired rows (avoid dedicated cron)
        if (mt_rand(1, 100) === 1) {
            self::purge();
        }

        return true;
    }

    /**
     * Convenience: check content API rate limit for an IP.
     */
    public static function checkContentApi(string $ip): bool {
        return self::check(
            "ip:{$ip}",
            'content_api',
            SC_RATE_CONTENT,
            SC_RATE_WINDOW_CONTENT
        );
    }

    /**
     * Convenience: check admin mutation rate limit.
     */
    public static function checkAdminMutation(string $ip): bool {
        return self::check(
            "ip:{$ip}",
            'admin_mutation',
            SC_RATE_ADMIN,
            SC_RATE_WINDOW_ADMIN
        );
    }

    /**
     * Convenience: check login attempt rate limit.
     */
    public static function checkLogin(string $ip): bool {
        return self::check(
            "ip:{$ip}",
            'login',
            SC_RATE_LOGIN,
            SC_RATE_WINDOW_LOGIN
        );
    }

    /**
     * Delete all expired rate limit rows.
     */
    public static function purge(): void {
        $oldest = time() - max(SC_RATE_WINDOW_CONTENT, SC_RATE_WINDOW_ADMIN, SC_RATE_WINDOW_LOGIN);
        Database::execute("DELETE FROM rate_limit_log WHERE hit_at < ?", [$oldest]);
    }
}

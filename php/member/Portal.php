<?php
/**
 * Space Cadet CMS — Member Portal
 * Front-end authentication gate for free/paid members.
 */

class Portal {

    /**
     * Authenticate a member (separate from admin auth).
     * Sets a member session and returns the user row.
     */
    public static function login(string $email, string $password, Request $req): ?array {
        if (!RateLimit::checkLogin($req->ip())) {
            Response::tooManyRequests();
        }

        $user = User::findByEmail($email);
        if (!$user || !in_array($user['role'], ['free_member','paid_member'], true)) {
            return null;
        }
        if (!User::verifyPassword($user, $password)) {
            return null;
        }
        if ($user['status'] !== 'active') {
            return null;
        }

        Auth::createSession($user, $req);
        return $user;
    }

    /**
     * Check whether the current session is a member (not admin).
     */
    public static function check(): bool {
        return Auth::check() && Auth::isMember();
    }

    /**
     * Require an active member session. Aborts with 401 if not.
     */
    public static function requireMember(): void {
        if (!self::check()) {
            Response::unauthorized('Member authentication required.');
        }
    }

    /**
     * Require a paid member. Aborts with 403 for free members.
     */
    public static function requirePaid(): void {
        self::requireMember();
        if (Auth::role() !== 'paid_member') {
            Response::forbidden('This content requires a paid membership.');
        }
    }
}

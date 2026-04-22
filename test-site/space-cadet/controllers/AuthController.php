<?php
/**
 * Space Cadet CMS — Auth Controller
 */

class AuthController {

    public function login(Request $req): void {
        $email    = $req->post('email', '');
        $password = $req->postRaw('password', '');

        if (!$email || !$password) {
            Response::error('Email and password are required.', 400);
        }

        // Rate limit login attempts
        if (!RateLimit::checkLogin($req->ip())) {
            Response::tooManyRequests();
        }

        $user = User::findByEmail($email);

        if (!$user || !User::verifyPassword($user, $password)) {
            Response::error('Invalid email or password.', 401, 'INVALID_CREDENTIALS');
        }

        if ($user['status'] !== 'active') {
            Response::error('Your account has been suspended.', 403, 'SUSPENDED');
        }

        // Members cannot log into admin
        if (in_array($user['role'], ['free_member', 'paid_member'], true)) {
            Response::error('Member accounts cannot access the admin panel.', 403, 'MEMBER_ACCOUNT');
        }

        Auth::createSession($user, $req);

        Response::success(User::sanitize($user));
    }

    public function logout(Request $req): void {
        Auth::destroySession();
        Response::success(['message' => 'Logged out.']);
    }

    public function me(Request $req): void {
        Auth::requireAuth();
        Response::success(User::sanitize(Auth::user()));
    }

    public function changePassword(Request $req): void {
        Auth::requireAuth();

        $current = $req->postRaw('current_password', '');
        $new     = $req->postRaw('new_password', '');
        $confirm = $req->postRaw('confirm_password', '');

        if (!$current || !$new || !$confirm) {
            Response::error('All password fields are required.', 400);
        }

        if (strlen($new) < 8) {
            Response::error('New password must be at least 8 characters.', 422);
        }

        if ($new !== $confirm) {
            Response::error('Passwords do not match.', 422);
        }

        $user = Auth::user();
        if (!User::verifyPassword($user, $current)) {
            Response::error('Current password is incorrect.', 401);
        }

        User::update((int) $user['id'], ['password' => $new]);
        Response::success(['message' => 'Password updated.']);
    }

    public function refresh(Request $req): void {
        Auth::requireAuth();
        // Regenerate session by destroying and recreating
        Auth::destroySession();
        $user = User::findById((int) Auth::user()['id']);
        Auth::createSession($user, $req);
        Response::success(User::sanitize($user));
    }
}

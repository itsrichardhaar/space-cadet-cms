<?php
/**
 * Space Cadet CMS — User Model
 */

class User {

    public static function findByEmail(string $email): ?array {
        return Database::queryOne(
            "SELECT * FROM users WHERE email = ? AND status != 'deleted'",
            [$email]
        );
    }

    public static function findById(int $id): ?array {
        return Database::queryOne("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public static function all(array $filters = []): array {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['role'])) {
            $where[]  = 'role = ?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[]  = '(display_name LIKE ? OR email LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }

        $sql = "SELECT id, email, display_name, role, status, last_login_at, created_at, updated_at
                FROM users WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";

        return Database::query($sql, $params);
    }

    public static function create(array $data): int {
        $now = time();
        Database::execute(
            "INSERT INTO users (email, password_hash, display_name, role, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['email'],
                password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                $data['display_name'],
                $data['role']   ?? 'editor',
                $data['status'] ?? 'active',
                $now,
                $now,
            ]
        );
        return Database::lastInsertId();
    }

    public static function update(int $id, array $data): void {
        $sets   = [];
        $params = [];

        if (isset($data['email']))        { $sets[] = 'email = ?';        $params[] = $data['email']; }
        if (isset($data['display_name'])) { $sets[] = 'display_name = ?'; $params[] = $data['display_name']; }
        if (isset($data['role']))         { $sets[] = 'role = ?';         $params[] = $data['role']; }
        if (isset($data['status']))       { $sets[] = 'status = ?';       $params[] = $data['status']; }
        if (isset($data['password'])) {
            $sets[]   = 'password_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (empty($sets)) return;

        $sets[]   = 'updated_at = ?';
        $params[] = time();
        $params[] = $id;

        Database::execute("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?", $params);
    }

    public static function delete(int $id): void {
        Database::execute("DELETE FROM users WHERE id = ?", [$id]);
    }

    /**
     * Return a safe public representation (no password_hash).
     */
    public static function sanitize(array $user): array {
        unset($user['password_hash']);
        return $user;
    }

    public static function verifyPassword(array $user, string $password): bool {
        return password_verify($password, $user['password_hash']);
    }
}

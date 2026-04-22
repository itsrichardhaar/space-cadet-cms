<?php
/**
 * Space Cadet CMS — PDO Wrapper
 */

class Database {
    private static ?PDO $pdo = null;

    public static function get(): PDO {
        if (self::$pdo === null) {
            self::$pdo = sc_db();
        }
        return self::$pdo;
    }

    /**
     * Execute a SELECT and return all rows.
     */
    public static function query(string $sql, array $params = []): array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a SELECT and return one row or null.
     */
    public static function queryOne(string $sql, array $params = []): ?array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Execute a write statement (INSERT/UPDATE/DELETE) and return affected row count.
     */
    public static function execute(string $sql, array $params = []): int {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Return the last inserted row ID.
     */
    public static function lastInsertId(): int {
        return (int) self::get()->lastInsertId();
    }

    /**
     * Wrap a callable in a transaction. Rolls back on exception.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function transaction(callable $fn): mixed {
        $pdo = self::get();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Check whether a value exists in a table.
     */
    public static function exists(string $table, string $column, mixed $value, ?int $excludeId = null): bool {
        $sql    = "SELECT 1 FROM {$table} WHERE {$column} = ?";
        $params = [$value];
        if ($excludeId !== null) {
            $sql    .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Quick paginated fetch.
     *
     * @return array{rows: array, total: int}
     */
    public static function paginate(string $sql, array $params, int $page, int $perPage): array {
        // Count total
        $countSql  = "SELECT COUNT(*) FROM ({$sql}) AS _count";
        $countStmt = self::get()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch page
        $offset   = max(0, ($page - 1)) * $perPage;
        $pageSql  = "{$sql} LIMIT ? OFFSET ?";
        $pageParams = array_merge($params, [$perPage, $offset]);
        $stmt = self::get()->prepare($pageSql);
        $stmt->execute($pageParams);
        $rows = $stmt->fetchAll();

        return ['rows' => $rows, 'total' => $total];
    }
}

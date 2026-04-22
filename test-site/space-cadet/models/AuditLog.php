<?php
/**
 * Space Cadet CMS — Audit Log Model
 *
 * Writes immutable audit entries for create/update/delete operations.
 * Table: audit_log (id, user_id, action, entity_type, entity_id, diff, ip_address, created_at)
 */
class AuditLog {

    /**
     * Write an audit log entry.
     *
     * @param int|null  $userId     Authenticated user (null = system/API key)
     * @param string    $action     One of: created, updated, deleted
     * @param string    $entityType e.g. collection_item, page, media, form, webhook
     * @param int       $entityId   Primary key of the affected entity
     * @param array     $diff       Optional before/after or change summary
     */
    public static function write(
        ?int   $userId,
        string $action,
        string $entityType,
        int    $entityId,
        array  $diff = []
    ): void {
        try {
            Database::execute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, diff, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $action,
                    $entityType,
                    $entityId,
                    $diff ? json_encode($diff) : null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    time(),
                ]
            );
        } catch (\Throwable) {
            // Audit log failure must never block the main operation
        }
    }

    /**
     * Return paginated audit log entries, newest first.
     *
     * @param array $opts  Optional: entity_type, entity_id, user_id, limit, offset
     */
    public static function list(array $opts = []): array {
        $where  = [];
        $params = [];

        if (!empty($opts['entity_type'])) {
            $where[]  = 'a.entity_type = ?';
            $params[] = $opts['entity_type'];
        }
        if (!empty($opts['entity_id'])) {
            $where[]  = 'a.entity_id = ?';
            $params[] = (int)$opts['entity_id'];
        }
        if (!empty($opts['user_id'])) {
            $where[]  = 'a.user_id = ?';
            $params[] = (int)$opts['user_id'];
        }

        $sql = "SELECT a.id, a.user_id, u.display_name AS user_name, u.email AS user_email,
                       a.action, a.entity_type, a.entity_id, a.diff, a.ip_address, a.created_at
                FROM audit_log a
                LEFT JOIN users u ON u.id = a.user_id"
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY a.created_at DESC';

        $limit  = (int)($opts['limit']  ?? 50);
        $offset = (int)($opts['offset'] ?? 0);

        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $rows = Database::query($sql, $params);

        foreach ($rows as &$row) {
            if ($row['diff']) {
                $row['diff'] = json_decode($row['diff'], true);
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Total count of log entries (for pagination).
     */
    public static function count(array $opts = []): int {
        $where  = [];
        $params = [];

        if (!empty($opts['entity_type'])) { $where[] = 'entity_type = ?'; $params[] = $opts['entity_type']; }
        if (!empty($opts['entity_id']))   { $where[] = 'entity_id = ?';   $params[] = (int)$opts['entity_id']; }
        if (!empty($opts['user_id']))     { $where[] = 'user_id = ?';     $params[] = (int)$opts['user_id']; }

        $sql = 'SELECT COUNT(*) AS n FROM audit_log'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

        return (int)(Database::queryOne($sql, $params)['n'] ?? 0);
    }
}

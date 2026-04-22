<?php
/**
 * Space Cadet CMS — SQLite PDO Singleton
 */

require_once __DIR__ . '/app.php';

function sc_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (!file_exists(SC_DB_PATH)) {
        throw new RuntimeException('Database not found. Please run the installer at /install.php');
    }

    $pdo = new PDO('sqlite:' . SC_DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Performance + safety pragmas
    $pdo->exec("PRAGMA journal_mode = WAL");
    $pdo->exec("PRAGMA foreign_keys = ON");
    $pdo->exec("PRAGMA synchronous = NORMAL");
    $pdo->exec("PRAGMA cache_size = -8000"); // 8 MB page cache
    $pdo->exec("PRAGMA temp_store = MEMORY");

    return $pdo;
}

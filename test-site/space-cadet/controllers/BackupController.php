<?php
class BackupController {

    public function download(Request $req): void {
        Auth::requireRole('admin');

        $db = SC_DB_PATH;
        if (!file_exists($db)) Response::error('Database file not found', 404);

        // Flush WAL to main database file so backup is complete
        try {
            Database::execute("PRAGMA wal_checkpoint(TRUNCATE)");
        } catch (Throwable) {
            // Non-fatal — file copy still works even with a WAL
        }

        $filename = 'space-cadet-backup-' . date('Y-m-d-His') . '.sqlite';

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($db));
        header('Cache-Control: no-store');
        header('Pragma: no-cache');

        readfile($db);
        exit;
    }

    public function restore(Request $req): void {
        Auth::requireRole('admin');

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('No file uploaded or upload error', 400);
        }

        $tmp  = $_FILES['file']['tmp_name'];
        $size = $_FILES['file']['size'];

        // Validate: SQLite magic bytes ("SQLite format 3\000")
        $fh = fopen($tmp, 'rb');
        $magic = fread($fh, 16);
        fclose($fh);
        if (strncmp($magic, "SQLite format 3", 15) !== 0) {
            Response::error('Uploaded file is not a valid SQLite database', 422);
        }

        // Store as pending restore — admin must confirm
        $pendingPath = dirname(SC_DB_PATH) . '/restore-pending.sqlite';
        if (!move_uploaded_file($tmp, $pendingPath)) {
            Response::error('Failed to store uploaded file', 500);
        }

        Response::success([
            'pending' => true,
            'size'    => $size,
            'message' => 'Backup staged. Call POST backup/confirm to apply.',
        ]);
    }

    public function confirm(Request $req): void {
        Auth::requireRole('admin');

        $pendingPath = dirname(SC_DB_PATH) . '/restore-pending.sqlite';
        if (!file_exists($pendingPath)) {
            Response::error('No pending restore found. Upload a backup first.', 404);
        }

        $backupOfCurrent = SC_DB_PATH . '.bak-' . time();

        // Backup current DB before overwriting
        if (!copy(SC_DB_PATH, $backupOfCurrent)) {
            Response::error('Could not back up current database before restore', 500);
        }

        // Close connections — SQLite WAL files may linger, rename is atomic enough
        if (!rename($pendingPath, SC_DB_PATH)) {
            Response::error('Failed to replace database file', 500);
        }

        // Also remove WAL/SHM files from the old DB
        @unlink(SC_DB_PATH . '-wal');
        @unlink(SC_DB_PATH . '-shm');

        AuditLog::write(Auth::userId(), 'restored_backup', 'database', 0, []);

        Response::success(['restored' => true, 'backup_saved_as' => basename($backupOfCurrent)]);
    }
}

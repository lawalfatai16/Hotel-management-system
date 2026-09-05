<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('backup', true);
$db = Database::connect();
$user = Auth::user();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'export') {
    exportBackup($db, $user);
    exit;
}

if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    restoreBackup($db, $user);
    exit;
}

if ($action === 'history') {
    safeExecute(function () use ($db) {
        $stmt = $db->prepare(
            "SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
             WHERE al.module = 'backup' ORDER BY al.created_at DESC LIMIT 20"
        );
        $stmt->execute();
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
    });
    exit;
}

jsonResponse(['success' => false, 'message' => 'Unknown or unsupported request.'], 422);

function exportBackup(PDO $db, array $user): void
{
    $hotelName = setting('hotel_name', 'GMT Hotel and Events Centre');
    $filename = 'GMT_Hotel_Backup_' . date('Y-m-d_His') . '.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "-- {$hotelName} — Database Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $createStmt = $db->query("SHOW CREATE TABLE `{$table}`")->fetch();
        echo "DROP TABLE IF EXISTS `{$table}`;\n";
        echo $createStmt['Create Table'] . ";\n\n";

        $rows = $db->query("SELECT * FROM `{$table}`");
        $rowCount = 0;
        foreach ($rows as $row) {
            $columns = array_map(fn($c) => "`{$c}`", array_keys($row));
            $values = array_map(function ($v) use ($db) {
                return $v === null ? 'NULL' : $db->quote((string) $v);
            }, array_values($row));
            echo "INSERT INTO `{$table}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ");\n";
            $rowCount++;
        }
        if ($rowCount > 0) echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";

    Auth::logAudit($user['id'], "{$user['username']} downloaded a full database backup", 'backup');
}

function restoreBackup(PDO $db, array $user): void
{
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
    }
    if (empty($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'No valid backup file was uploaded.'], 422);
    }
    $sql = file_get_contents($_FILES['backup_file']['tmp_name']);
    if ($sql === false || trim($sql) === '') {
        jsonResponse(['success' => false, 'message' => 'The uploaded file is empty or unreadable.'], 422);
    }

    $statements = splitSqlStatements($sql);
    if (count($statements) === 0) {
        jsonResponse(['success' => false, 'message' => 'No valid SQL statements were found in that file.'], 422);
    }

    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    $db->beginTransaction();
    $executed = 0;
    try {
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with($statement, '--')) continue;
            $db->exec($statement);
            $executed++;
        }
        $db->commit();
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $e) {
        $db->rollBack();
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
        error_log('Restore failed: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Restore failed and was rolled back — no changes were made. Check that the file is a valid backup from this system.'], 500);
    }

    Auth::logAudit($user['id'], "{$user['username']} restored the database from a backup file ({$executed} statements)", 'backup');
    jsonResponse(['success' => true, 'message' => "Restore completed successfully ({$executed} statements executed)."]);
}

/** Splits a SQL dump into individual statements, respecting single/double-quoted strings so a ';' inside a value never causes a false split. */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $current .= $char;

        if ($inString) {
            if ($char === '\\') { // escaped char — consume the next one too
                if ($i + 1 < $length) { $current .= $sql[++$i]; }
                continue;
            }
            if ($char === $stringChar) $inString = false;
            continue;
        }

        if ($char === "'" || $char === '"') {
            $inString = true;
            $stringChar = $char;
            continue;
        }

        if ($char === ';') {
            $statements[] = $current;
            $current = '';
        }
    }
    if (trim($current) !== '') $statements[] = $current;

    return $statements;
}

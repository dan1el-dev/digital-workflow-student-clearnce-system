<?php
/**
 * Shared Utility Functions
 */
require_once __DIR__ . '/db.php';

/**
 * Generate a UUID v4 string
 */
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Log an action to the audit logs
 */
function log_audit($user_id, $action, $affected_table = null, $affected_record_id = null) {
    global $pdo;
    try {
        $log_id = generate_uuid();
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (log_id, user_id, action, affected_table, affected_record_id, timestamp)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$log_id, $user_id, $action, $affected_table, $affected_record_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Audit Log Failure: " . $e->getMessage());
        return false;
    }
}

/**
 * Sanitize string output
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

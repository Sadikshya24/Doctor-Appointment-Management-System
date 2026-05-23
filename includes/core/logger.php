<?php
/**
 * Log System Activity with detailed audit trail
 * 
 * @param PDO $pdo The database connection
 * @param string $action The action performed (e.g., 'Hospital Added')
 * @param string|null $details JSON string of modified data
 * @param string|null $performer Name/Email of the person who performed the action (defaults to session name)
 */
function logSystemActivity($pdo, $action, $details = null, $performer = null) {
    if (!$performer) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $performer = $_SESSION['name'] ?? 'System';
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO logs (action, user, details) VALUES (?, ?, ?)");
        $stmt->execute([$action, $performer, $details]);
    } catch (Exception $e) {
        // Fallback for systems that might not have updated the schema yet
        try {
            $stmt = $pdo->prepare("INSERT INTO logs (action, user) VALUES (?, ?)");
            $stmt->execute([$action . ($details ? " (Details: $details)" : ""), $performer]);
        } catch (Exception $e2) {
            error_log("Failed to log activity: " . $e2->getMessage());
        }
    }
}
?>

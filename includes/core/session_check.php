<?php
/**
 * Global Session Inactivity Guard
 * Enforces 30-minute auto-logout and activity tracking.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, we don't need to check timeout (dashboards already check login)
    return;
}

// 2. Define timeout (1800 seconds = 30 minutes)
$timeout_duration = 1800; 

// 3. Check for inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    // Session expired
    session_unset();
    session_destroy();
    
    // Check if it's an AJAX request
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || (isset($_GET['ajax']) || isset($_POST['ajax']));

    if ($isAjax) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Session expired. Please log in again."]);
        exit;
    }

    // Redirect with message for normal page loads
    if (isset($isSubFolder) && $isSubFolder) {
        header("Location: ../login.php?error=session_expired");
    } else {
        header("Location: login.php?error=session_expired");
    }
    exit;
}

// 4. Update last activity timestamp
$_SESSION['last_activity'] = time();
?>

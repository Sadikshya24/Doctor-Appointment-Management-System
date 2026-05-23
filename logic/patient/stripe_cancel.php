<?php
// logic/patient/stripe_cancel.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/core/session_check.php';
require_once '../../includes/core/db.php';

$booking_id = $_GET['booking_id'] ?? '';

if ($booking_id) {
    // Keep the appointment so the user can retry payment from the dashboard
    // $stmt = $pdo->prepare("DELETE FROM appointments WHERE booking_id = ? AND patient_id = ? AND status = 'pending_payment'");
    // $stmt->execute([$booking_id, $_SESSION['user_id']]);
}

header('Location: ../../patient/dashboard.php?payment=cancelled');
exit;
?>
<?php
// logic/patient/stripe_success.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/core/session_check.php';
require_once '../../includes/core/db.php';
require_once 'stripe_config.php';

$session_id = $_GET['session_id'] ?? '';

if (!$session_id) {
    header('Location: ../../patient/dashboard.php?error=Missing Session ID');
    exit;
}

try {
    // Verify session with Stripe
    $stripe_session = stripe_api_request('GET', 'checkout/sessions/' . $session_id);

    if ($stripe_session['payment_status'] === 'paid') {
        $payment_intent_id = $stripe_session['payment_intent'];
        $booking_id = $stripe_session['metadata']['booking_id'] ?? null;

        if ($booking_id) {
            // Update appointment to paid and scheduled
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'scheduled', payment_status = 'paid', payment_intent_id = ? WHERE booking_id = ?");
            $stmt->execute([$payment_intent_id, $booking_id]);

            // Redirect to dashboard with success and booking ID to show receipt
            header('Location: ../../patient/dashboard.php?payment=success&booking_id=' . urlencode($booking_id));
            exit;
        }
    }

    header('Location: ../../patient/dashboard.php?error=Payment verification failed');
    exit;
} catch (Exception $e) {
    error_log("Stripe Verification Error: " . $e->getMessage());
    header('Location: ../../patient/dashboard.php?error=Error verifying payment');
    exit;
}
?>
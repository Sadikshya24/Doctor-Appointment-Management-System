<?php
// logic/patient/stripe_success.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/core/session_check.php';
require_once '../../includes/core/db.php';
require_once '../../includes/core/email_helper.php';
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
        $amount_total = $stripe_session['amount_total'];

        // Fetch payment intent to obtain live exchange rate if available
        $pi = stripe_api_request('GET', "payment_intents/{$payment_intent_id}?expand[]=latest_charge");
        $usd_amount = $amount_total / 100;
        // Default exchange rate fallback
        $exchange_rate = defined('EXCHANGE_RATE_USD_TO_NPR') ? EXCHANGE_RATE_USD_TO_NPR : 159.8027;
        // Use Stripe's exchange_rate from the latest charge if present
        if (isset($pi['latest_charge']['exchange_rate'])) {
            $exchange_rate = (float) $pi['latest_charge']['exchange_rate'];
        }
        // Calculate NPR amount using the determined exchange rate
        $npr_amount = round($usd_amount * $exchange_rate, 2);
        // Ensure display amount is formatted as NPR
        $display_amount = 'NPR ' . number_format($npr_amount, 2);

        if ($booking_id) {
            // Check existing values for logging purposes
            $logStmt = $pdo->prepare("SELECT final_amount_npr FROM appointments WHERE booking_id = ?");
            $logStmt->execute([$booking_id]);
            $existing_npr = $logStmt->fetchColumn();

            if ($existing_npr !== false && $existing_npr !== null && (float) $existing_npr !== (float) $npr_amount) {
                error_log("Currency Mismatch Detected: Booking $booking_id expected NPR $existing_npr but calculated NPR $npr_amount");
            }

            // Update appointment to paid and scheduled, storing precise numerical values
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'scheduled', payment_status = 'paid', payment_intent_id = ?, amount_paid = ?, stripe_payment_id = ?, amount_usd_charged = ?, exchange_rate_used = ?, final_amount_npr = ? WHERE booking_id = ?");
            $stmt->execute([$payment_intent_id, $display_amount, $payment_intent_id, $usd_amount, $exchange_rate, $npr_amount, $booking_id]);

            // Send Confirmation Email
            $email = $_SESSION['email'];
            $name = $_SESSION['name'];
            $subject = "Payment Confirmed - Booking #$booking_id";
            $body = "Hello $name,<br><br>"
                . "Your payment of <b>$display_amount</b> for appointment booking <b>#$booking_id</b> has been successfully processed.<br>"
                . "Your appointment is now confirmed. You can view your receipt in the dashboard.<br><br>"
                . "Thank you for using MedScape.";
            sendEmail($email, $subject, $body);

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
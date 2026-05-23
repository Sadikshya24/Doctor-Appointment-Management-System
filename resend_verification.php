<?php
session_start();
require_once 'includes/core/db.php';

// Check if email is provided via POST or session
$email = $_POST['email'] ?? $_SESSION['email'] ?? '';

if (empty($email)) {
    $_SESSION['toast_msg'] = "Unable to find your email address. Please try logging in again.";
    $_SESSION['toast_type'] = 'error';
    header("Location: login.php");
    exit;
}

// Fetch user
$stmt = $pdo->prepare("SELECT id, name, is_verified FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['toast_msg'] = "No account found with that email address.";
    $_SESSION['toast_type'] = 'error';
    header("Location: login.php");
    exit;
}

if ($user['is_verified'] == 1) {
    $_SESSION['toast_msg'] = "Your email is already verified.";
    $_SESSION['toast_type'] = 'success';
    header("Location: index.php");
    exit;
}

// Generate new token
$verification_token = bin2hex(random_bytes(32));
$verification_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Update user with new token
$updateStmt = $pdo->prepare("UPDATE users SET verification_token = ?, verification_token_expires_at = ? WHERE id = ?");
if ($updateStmt->execute([$verification_token, $verification_expires_at, $user['id']])) {
    
    // Send Verification Email
    require 'includes/lib/PHPMailer/src/Exception.php';
    require 'includes/lib/PHPMailer/src/PHPMailer.php';
    require 'includes/lib/PHPMailer/src/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SYSTEM_EMAIL, SYSTEM_NAME);
        $mail->addAddress($email);

        $base_url = "http://" . $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['PHP_SELF']);
        if ($path === '\\' || $path === '/') $path = '';
        $verifyLink = $base_url . $path . "/verify.php?token=" . $verification_token;

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email Address - MedScape';
        $mail->Body = "Hello " . htmlspecialchars($user['name']) . ",<br><br>"
            . "Please click the link below to verify your email address:<br><br>"
            . "<a href='" . htmlspecialchars($verifyLink) . "'>" . htmlspecialchars($verifyLink) . "</a><br><br>"
            . "This link will expire in 24 hours.";
        $mail->AltBody = "Hello " . $user['name'] . ",\n\n"
            . "Please click the following link to verify your email address:\n"
            . $verifyLink . "\n\n"
            . "This link will expire in 24 hours.";

        $mail->send();
        
        $_SESSION['toast_msg'] = "A new verification link has been sent to your email.";
        $_SESSION['toast_type'] = 'success';
    } catch (\Exception $e) {
        $_SESSION['toast_msg'] = "Failed to send verification email. Please try again later.";
        $_SESSION['toast_type'] = 'error';
    }
} else {
    $_SESSION['toast_msg'] = "Failed to generate a new verification link.";
    $_SESSION['toast_type'] = 'error';
}

// Redirect back to referring page or dashboard
$redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: " . $redirect);
exit;

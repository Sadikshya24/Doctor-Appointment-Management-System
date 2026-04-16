<?php
session_start();
require_once 'includes/core/db.php';

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['toast_msg'] = "Please enter a valid email address.";
        $_SESSION['toast_type'] = 'error';
    } else {
        // Check if the email exists in the system
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate a unique token
            $token = bin2hex(random_bytes(32));

            // Clear any old tokens for this email
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            // Store the token in the password_resets table
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
            if ($stmt->execute([$email, $token])) {

                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

                require 'includes/lib/PHPMailer/src/Exception.php';
                require 'includes/lib/PHPMailer/src/PHPMailer.php';
                require 'includes/lib/PHPMailer/src/SMTP.php';

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USER;
                    $mail->Password = SMTP_PASS;
                    $mail->SMTPSecure = SMTP_SECURE; // Using constant from config.php
                    $mail->Port = SMTP_PORT;

                    // Recipients
                    $mail->setFrom(SYSTEM_EMAIL, SYSTEM_NAME);
                    $mail->addAddress($email);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - MedScape';
                    $mail->Body = "Hello " . htmlspecialchars($user['name']) . ",<br><br>"
                        . "Please click the following link to reset your password:<br><br>"
                        . "<a href='" . htmlspecialchars($resetLink) . "'>" . htmlspecialchars($resetLink) . "</a><br><br>"
                        . "If you did not request a password reset, please ignore this email.";
                    $mail->AltBody = "Hello " . $user['name'] . ",\n\n"
                        . "Please click the following link to reset your password:\n"
                        . $resetLink . "\n\n"
                        . "If you did not request a password reset, please ignore this email.";

                    $mail->send();
                    $_SESSION['toast_msg'] = "A password reset link has been sent to your email address.";
                    $_SESSION['toast_type'] = 'success';
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    $_SESSION['toast_msg'] = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                    $_SESSION['toast_type'] = 'error';
                } catch (\Exception $e) {
                    $_SESSION['toast_msg'] = "System Error: {$e->getMessage()}";
                    $_SESSION['toast_type'] = 'error';
                }
            } else {
                $_SESSION['toast_msg'] = "There was an error generating your reset link. Please try again later.";
                $_SESSION['toast_type'] = 'error';
            }
        } else {
            $_SESSION['toast_msg'] = "No account found with that email address.";
            $_SESSION['toast_type'] = 'error';
        }
    }
}
?>
<?php
$pageTitle = 'Forgot Password - MedScape';
require_once 'includes/layout/header.php';
?>
<link rel="stylesheet" href="assets/css/auth/auth.css">

<div class="forgot-container">
    <div class="forgot-box">
        <h2>Forgot Password</h2>
        <p>Enter your email address and we'll send you a link to reset your password.</p>

        <form action="forgot_password.php" method="POST">
            <div class="input-field">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email Address"
                    pattern="[A-Za-z0-9]+@(gmail\.com|outlook\.com|yahoo\.com|hotmail\.com|yopmail\.com)" required />
            </div>
            <input type="submit" value="Send Reset Link" class="btn" />
        </form>
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</div>
<?php require_once 'includes/layout/footer.php'; ?>
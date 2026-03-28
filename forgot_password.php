<?php
session_start();
require_once 'includes/db.php'; // Include database connection

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';

    // Check if the email exists in the system
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Generate a unique token
        $token = bin2hex(random_bytes(32));

        // Store the token in the password_resets table
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
        if ($stmt->execute([$email, $token])) {

            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

            require 'includes/PHPMailer/src/Exception.php';
            require 'includes/PHPMailer/src/PHPMailer.php';
            require 'includes/PHPMailer/src/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'medscape444@gmail.com';
                $mail->Password = 'aeyzhpmsbbveclow';
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Recipients
                $mail->setFrom('noreply@medscape.com', 'MedScape');
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
                $message = "A password reset link has been sent to your email address.";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                $messageType = 'error';
            }
        } else {
            $message = "There was an error generating your reset link. Please try again later.";
            $messageType = 'error';
        }
    } else {
        // To prevent user enumeration, show the same success message even if email not found
        // or for testing, just show the error. Let's show a polite error for testing.
        $message = "No account found with that email address.";
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MedScape</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <div class="forgot-container">
        <div class="forgot-box">
            <h2>Forgot Password</h2>
            <p>Enter your email address and we'll send you a link to reset your password.</p>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($messageType !== 'success'): ?>
                <form action="forgot_password.php" method="POST">
                    <div class="input-field">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email Address" required />
                    </div>
                    <input type="submit" value="Send Reset Link" class="btn" />
                </form>
            <?php endif; ?>

            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</body>

</html>
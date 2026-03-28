<?php
session_start();
require_once 'includes/db.php';

$message = '';
$messageType = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$validToken = false;
$userEmail = '';

// Check if token exists and is valid
if (!empty($token)) {
    // Basic check for token lifetime (e.g., 2 hours). Let's just check existence.
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch();

    if ($resetData) {
        $validToken = true;
        $userEmail = $resetData['email'];
    } else {
        $message = "Invalid or expired password reset token.";
        $messageType = 'error';
    }
} else {
    $message = "No reset token provided.";
    $messageType = 'error';
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = 'error';
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = 'error';
    } else {
        // Update user's password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        if ($stmt->execute([$hashed_password, $userEmail])) {

            // Delete the token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$userEmail]);

            $message = "Your password has been successfully reset. You can now <a href='index.php'>login</a>.";
            $messageType = 'success';
            $validToken = false; // Hide the form
        } else {
            $message = "There was an error updating your password.";
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - MedScape</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <div class="reset-container">
        <div class="reset-box">
            <h2>Set New Password</h2>
            <?php if ($validToken): ?>
                <p>Please enter your new password below.</p>
            <?php else: ?>
                <p>Invalid Request</p>
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($validToken): ?>
                <form action="reset_password.php" method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />

                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="New Password" required minlength="6" />
                    </div>
                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" placeholder="Confirm New Password" required
                            minlength="6" />
                    </div>

                    <input type="submit" value="Reset Password" class="btn" />
                </form>
            <?php endif; ?>

            <?php if (!$validToken && $messageType === 'error'): ?>
                <a href="forgot_password.php" class="back-link">Request New Link</a>
            <?php endif; ?>

            <br><a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</body>

</html>
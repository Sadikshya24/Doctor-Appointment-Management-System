<?php
session_start();
require_once 'includes/core/db.php';

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
        $_SESSION['toast_msg'] = "Invalid or expired password reset token.";
        $_SESSION['toast_type'] = 'error';
    }
} else {
    $_SESSION['toast_msg'] = "No reset token provided.";
    $_SESSION['toast_type'] = 'error';
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $_SESSION['toast_msg'] = "Password must be at least 8 characters long and include uppercase, lowercase, numbers, and special characters.";
        $_SESSION['toast_type'] = 'error';
    } elseif ($password !== $confirm_password) {
        $_SESSION['toast_msg'] = "Passwords do not match.";
        $_SESSION['toast_type'] = 'error';
    } else {
        // Update user's password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        if ($stmt->execute([$hashed_password, $userEmail])) {
            // Delete the token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$userEmail]);

            $_SESSION['toast_msg'] = "Your password has been successfully reset. You can now login.";
            $_SESSION['toast_type'] = 'success';
            header("Location: login.php");
            exit;
        } else {
            $_SESSION['toast_msg'] = "There was an error updating your password.";
            $_SESSION['toast_type'] = 'error';
        }
    }
}
?>
<?php
$pageTitle = 'Set New Password - MedScape';
require_once 'includes/layout/header.php';
?>
<link rel="stylesheet" href="assets/css/auth/auth.css">

<div class="reset-container">
    <div class="reset-box">
        <h2>Set New Password</h2>
        <?php if ($validToken): ?>
            <p>Please enter your new password below.</p>
        <?php else: ?>
            <p>Invalid Request</p>
        <?php endif; ?>

        <?php if ($validToken): ?>
            <form action="reset_password.php" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />

                <div class="input-field password-field">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="New Password" 
                        required 
                        pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$"
                        title="At least 8 characters, with uppercase, lowercase, number and special char" />
                    <i class="fas fa-eye toggle-password" title="Toggle password visibility"></i>
                </div>
                <div class="input-field password-field">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" 
                        required 
                        pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$" />
                    <i class="fas fa-eye toggle-password" title="Toggle password visibility"></i>
                </div>
                <p style="font-size: 0.72rem; color: var(--text-muted); margin: -10px 0 15px 5px; line-height: 1.2;">
                    <i class="fas fa-info-circle"></i> 8+ chars: A-Z, a-z, 0-9 & symbols.
                </p>

                <input type="submit" value="Reset Password" class="btn" />
            </form>
        <?php endif; ?>

        <?php if (!$validToken && isset($_SESSION['toast_type']) && $_SESSION['toast_type'] === 'error'): ?>
            <a href="forgot_password.php" class="back-link">Request New Link</a>
        <?php endif; ?>

        <br><a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</div>
<?php require_once 'includes/layout/footer.php'; ?>
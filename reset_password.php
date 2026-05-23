<?php
session_start();
require_once 'includes/core/db.php';

$message = '';
$messageType = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$validToken = false;
$userEmail = '';

// Check if token exists and is valid OR user is logged in
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
} elseif (isset($_SESSION['user_id'])) {
    // User is logged in, they can change their own password
    $validToken = true;
    $userEmail = $_SESSION['email'];
} else {
    $_SESSION['toast_msg'] = "No reset token provided and you are not logged in.";
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

            $_SESSION['toast_msg'] = isset($_SESSION['user_id']) ? "Your password has been updated successfully." : "Your password has been successfully reset. You can now login.";
            $_SESSION['toast_type'] = 'success';
            
            if (isset($_SESSION['user_id'])) {
                $role = strtolower($_SESSION['role']);
                if ($role === 'superadmin') {
                    header("Location: dashboards/superadmin.php");
                } else {
                    header("Location: $role/dashboard.php");
                }
            } else {
                header("Location: login.php");
            }
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
        <h2><?php echo isset($_SESSION['user_id']) ? 'Change Password' : 'Set New Password'; ?></h2>
        <?php if ($validToken): ?>
            <p><?php echo isset($_SESSION['user_id']) ? 'Update your account password below.' : 'Please enter your new password below.'; ?></p>
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

                <input type="submit" value="<?php echo isset($_SESSION['user_id']) ? 'Update Password' : 'Reset Password'; ?>" class="btn" />
            </form>
        <?php endif; ?>

        <?php if (!$validToken && !isset($_SESSION['user_id']) && isset($_SESSION['toast_type']) && $_SESSION['toast_type'] === 'error'): ?>
            <a href="forgot_password.php" class="back-link">Request New Link</a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): 
            $role = strtolower($_SESSION['role']);
            $backUrl = ($role === 'superadmin') ? 'dashboards/superadmin.php' : "$role/dashboard.php";
        ?>
            <br><a href="<?php echo $backUrl; ?>" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <?php else: ?>
            <br><a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/layout/footer.php'; ?>
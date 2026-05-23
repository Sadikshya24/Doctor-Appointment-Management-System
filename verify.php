<?php
session_start();
require_once 'includes/core/db.php';

$message = '';
$messageType = '';
$showResend = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Fetch user with this token
    $stmt = $pdo->prepare("SELECT id, name, email, verification_token_expires_at, is_verified FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['is_verified'] == 1) {
            $message = "Your email is already verified. You can now log in.";
            $messageType = 'success';
        } else {
            $expiry = strtotime($user['verification_token_expires_at']);
            if (time() < $expiry) {
                // Token is valid and not expired
                $updateStmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, verification_token_expires_at = NULL WHERE id = ?");
                if ($updateStmt->execute([$user['id']])) {
                    $message = "Congratulations " . htmlspecialchars($user['name']) . "! Your email has been successfully verified.";
                    $messageType = 'success';
                    
                    // Update session if user is logged in
                    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']) {
                        $_SESSION['is_verified'] = 1;
                    }
                } else {
                    $message = "Something went wrong during verification. Please try again later.";
                    $messageType = 'error';
                }
            } else {
                // Token expired
                $message = "The verification link has expired. Verification links are valid for 24 hours.";
                $messageType = 'error';
                $showResend = true;
                $_SESSION['pending_verification_email'] = $user['email'];
            }
        }
    } else {
        $message = "Invalid verification token. Please check your email for the correct link.";
        $messageType = 'error';
    }
} else {
    header("Location: index.php");
    exit;
}

$pageTitle = 'Email Verification - MedScape';
require_once 'includes/layout/header.php';
?>

<link rel="stylesheet" href="assets/css/auth/auth.css">
<style>
    .verify-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
        padding: 20px;
    }
    .verify-box {
        background: white;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        text-align: center;
        max-width: 500px;
        width: 100%;
    }
    .verify-icon {
        font-size: 60px;
        margin-bottom: 20px;
    }
    .verify-icon.success { color: #2ecc71; }
    .verify-icon.error { color: #e74c3c; }
    
    .verify-box h2 {
        margin-bottom: 15px;
        color: #333;
    }
    .verify-box p {
        color: #666;
        line-height: 1.6;
        margin-bottom: 30px;
    }
    .btn-verify {
        display: inline-block;
        padding: 12px 30px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .btn-success {
        background: #2ecc71;
        color: white;
    }
    .btn-success:hover {
        background: #27ae60;
        transform: translateY(-2px);
    }
    .btn-resend {
        background: #3498db;
        color: white;
        border: none;
        cursor: pointer;
    }
    .btn-resend:hover {
        background: #2980b9;
        transform: translateY(-2px);
    }
</style>

<div class="verify-container">
    <div class="verify-box">
        <div class="verify-icon <?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
        </div>
        <h2><?php echo $messageType === 'success' ? 'Verification Successful' : 'Verification Failed'; ?></h2>
        <p><?php echo $message; ?></p>
        
        <?php if ($messageType === 'success'): ?>
            <a href="login.php" class="btn-verify btn-success">Go to Login</a>
        <?php elseif ($showResend): ?>
            <form action="resend_verification.php" method="POST">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                <button type="submit" class="btn-verify btn-resend">Resend Verification Email</button>
            </form>
        <?php else: ?>
            <a href="index.php" class="btn-verify btn-resend">Back to Home</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/layout/footer.php'; ?>

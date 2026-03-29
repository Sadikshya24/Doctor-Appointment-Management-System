<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'doctor') {
    header('Location: ../index.php');
    exit;
}

$userName = htmlspecialchars($_SESSION['name']);
$userEmail = htmlspecialchars($_SESSION['email']);
$userRole = 'Doctor';

$stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userPhoto = $stmt->fetchColumn() ?: 'assets/img/default.jpeg';

$isDashboard = true;
$pageTitle = 'Doctor Dashboard - MedScape';
require_once '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard_wrapper.css">
<div class="dashboard-container">
    <div class="header">
        <h1>MedScape</h1>
        <div class="header-actions">
            <img src="../<?php echo htmlspecialchars($userPhoto); ?>" alt="Profile" class="header-profile-img"
                onclick="goToProfile()" onerror="this.src='../assets/img/default.jpeg'">
            <a href="../actions/logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    <div id="toast-container" class="toast-container"></div>
    <script>
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            let icon = 'info-circle';
            if (type === 'success') icon = 'check-circle';
            if (type === 'error') icon = 'exclamation-circle';
            if (type === 'warning') icon = 'exclamation-triangle';

            toast.innerHTML = `<i class="fas fa-${icon} toast-icon"></i> <span>${message}</span>`;
            container.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => toast.classList.add('active'), 10);
            
            // Remove after 3s
            setTimeout(() => {
                toast.classList.remove('active');
                setTimeout(() => toast.remove(), 400);
            }, 3000);
        }
    </script>
    <div class="welcome-message">Welcome back, <strong>
            <?php echo $userName; ?>
        </strong>!</div>
    <div class="user-info">
        <p><i class="fas fa-envelope"></i> <strong>Email:</strong>
            <?php echo $userEmail; ?>
        </p>
        <p><i class="fas fa-user-tag"></i> <strong>Account Type:</strong> <span class="role-badge">
                <?php echo $userRole; ?>
            </span></p>
    </div>
    <?php include 'dashboard_content.php'; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
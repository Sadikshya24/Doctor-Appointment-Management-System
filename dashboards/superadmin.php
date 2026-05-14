<?php
session_start();
require_once '../includes/core/session_check.php';
$isSubFolder = true;
$pageTitle = "Admin Dashboard | MedScape";
require_once '../includes/core/db.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../login.php");
    exit;
}

include '../includes/layout/header.php';
?>

<link rel="stylesheet" href="../assets/css/superadmin/superadmin.css">
<link rel="stylesheet" href="../assets/css/superadmin/superadmin_enhanced.css">
<link rel="stylesheet" href="../assets/css/common/dashboard_wrapper.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="admin-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-hand-holding-medical logo-icon"></i>
            <h2>MedScape <span>Admin</span></h2>
        </div>
        <ul class="sidebar-menu">
            <li data-page="dashboard" class="active">
                <i class="fas fa-chart-line"></i> <span>Dashboard</span>
            </li>
            <li data-page="hospitals">
                <i class="fas fa-hospital"></i> <span>Hospitals</span>
            </li>
            <li data-page="doctors">
                <i class="fas fa-user-md"></i> <span>Doctors</span>
            </li>
            <li data-page="patients">
                <i class="fas fa-user-injured"></i> <span>Patients</span>
            </li>
            <li data-page="appointments">
                <i class="fas fa-calendar-check"></i> <span>Appointments</span>
            </li>
            <li data-page="analytics">
                <i class="fas fa-analytics"></i> <span>Analytics</span>
            </li>
            <li data-page="logs">
                <i class="fas fa-history"></i> <span>Audit Logs</span>
            </li>
        </ul>
        <div class="sidebar-footer">
            <a href="../logic/auth/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </div>
    </aside>


    <!-- Main Content -->
    <main class="main-content">
        <?php
        $stmtSA = $pdo->prepare("SELECT is_verified FROM users WHERE id = ?");
        $stmtSA->execute([$_SESSION['user_id']]);
        $_SESSION['is_verified'] = $stmtSA->fetchColumn();
        ?>
        <header class="top-bar">
            <div class="user-profile-wrapper">
                <div class="user-profile" id="profileToggle">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <img src="../<?php echo $_SESSION['profile_photo'] ?? 'assets/img/default.jpeg'; ?>" alt="Admin"
                        class="avatar" onerror="this.src='../assets/img/default.jpeg'">
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header" style="padding: 10px 14px; border-bottom: 1px solid var(--border-color); margin-bottom: 5px;">
                        <div style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                        <div style="color: var(--text-muted); font-size: 0.8rem;">Superadmin</div>
                    </div>
                    <a href="#" onclick="showToast('Profile editing coming soon for admin', 'info'); return false;">
                        <i class="fas fa-user-circle"></i> Edit Profile
                    </a>
                    <a href="../reset_password.php">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="../logic/auth/logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>
        
        <?php if (isset($_SESSION['is_verified']) && $_SESSION['is_verified'] == 0): ?>
            <div class="verification-banner" style="background: #fff3cd; color: #856404; padding: 15px 25px; margin: 20px; border-radius: 10px; border-left: 5px solid #ffeeba; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
                    <span><strong>Verify your email!</strong> Please check your inbox to confirm your email address. Some features may be restricted until verified.</span>
                </div>
                <form action="../resend_verification.php" method="POST" style="margin: 0;">
                    <button type="submit" style="background: #856404; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">Resend Email</button>
                </form>
            </div>
        <?php endif; ?>

        <?php include 'superadmin_content.php'; ?>

    </main>
</div>

<script src="../assets/js/superadmin/admin_dashboard.js"></script>

<?php include '../includes/layout/footer.php'; ?>
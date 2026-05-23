<?php
session_start();
require_once '../includes/core/session_check.php';
$isSubFolder = true;
$pageTitle = "Admin Dashboard | MedScape";
require_once '../includes/core/db.php';
require_once '../includes/layout/dashboard_layout.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../login.php");
    exit;
}

$userName = $_SESSION['name'];
$userRole = 'superadmin';

$stmt = $pdo->prepare("SELECT profile_photo, is_verified FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch();
$userPhoto = $userData['profile_photo'] ?: 'assets/img/default.jpeg';
$_SESSION['is_verified'] = $userData['is_verified'];

$menuItems = [
    ['id' => 'dashboard', 'label' => 'Dashboard Overview', 'icon' => 'fa-th-large', 'active' => true],
    ['id' => 'hospitals', 'label' => 'Hospital Management', 'icon' => 'fa-hospital', 'active' => false],
    ['id' => 'doctors', 'label' => 'Doctor Management', 'icon' => 'fa-user-md', 'active' => false],
    ['id' => 'patients', 'label' => 'Patient Management', 'icon' => 'fa-user-injured', 'active' => false],
    ['id' => 'appointments', 'label' => 'Appointments', 'icon' => 'fa-calendar-check', 'active' => false],
    ['id' => 'analytics', 'label' => 'System Analytics', 'icon' => 'fa-chart-line', 'active' => false],
    ['id' => 'logs', 'label' => 'System Audit Logs', 'icon' => 'fa-clipboard-list', 'active' => false],
];

include '../includes/layout/header.php';
?>
<link rel="stylesheet" href="../assets/css/superadmin/superadmin.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
renderDashboardLayout($userRole, $userName, $userPhoto, $menuItems, 'superadmin_content.php');
?>

<script src="../assets/js/superadmin/admin_dashboard.js"></script>

<?php include '../includes/layout/footer.php'; ?>
<?php
session_start();
require_once '../includes/core/session_check.php';
require_once '../includes/core/db.php';
require_once '../includes/layout/dashboard_layout.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'doctor') {
    header('Location: ../index.php');
    exit;
}

$userName = $_SESSION['name'];
$userEmail = $_SESSION['email'];
$role = 'doctor';

$stmt = $pdo->prepare("SELECT profile_photo, is_verified FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch();
$userPhoto = $userData['profile_photo'] ?: 'assets/img/default.jpeg';
$_SESSION['is_verified'] = $userData['is_verified'];

$isDashboard = true;
$isSubFolder = true;
$pageTitle = 'Doctor Dashboard - MedScape';

include '../includes/layout/header.php';

$menuItems = [
    ['id' => 'overview', 'label' => 'Dashboard Overview', 'icon' => 'fas fa-chart-line', 'active' => true],
    ['id' => 'ai_insights', 'label' => 'AI Analytics', 'icon' => 'fas fa-brain', 'active' => false],
    ['id' => 'bookings', 'label' => 'Patient Bookings', 'icon' => 'fas fa-calendar-alt', 'active' => false],
    ['id' => 'consulted', 'label' => 'Consulted Patients', 'icon' => 'fas fa-clipboard-check', 'active' => false],
    ['id' => 'search', 'label' => 'Patient Search', 'icon' => 'fas fa-search', 'active' => false],
    ['id' => 'settings', 'label' => 'Profile & Availability', 'icon' => 'fas fa-user-cog', 'active' => false],
];

renderDashboardLayout($role, $userName, $userPhoto, $menuItems, 'dashboard_content.php');

include '../includes/layout/footer.php';
?>
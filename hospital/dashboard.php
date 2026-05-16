<?php
session_start();
require_once '../includes/core/session_check.php';
require_once '../includes/core/db.php';
require_once '../includes/layout/dashboard_layout.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../index.php');
    exit;
}

$userName = $_SESSION['name'];
$userEmail = $_SESSION['email'];
$role = 'hospital';

$stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userPhoto = $stmt->fetchColumn() ?: 'assets/img/default.jpeg';

$isDashboard = true;
$isSubFolder = true;
$pageTitle = 'Hospital Dashboard - MedScape';

include '../includes/layout/header.php';

$menuItems = [
    ['id' => 'overview', 'label' => 'Overview', 'icon' => 'fas fa-chart-line', 'active' => true],
    ['id' => 'approved', 'label' => 'Our Doctors', 'icon' => 'fas fa-user-md', 'active' => false],
    ['id' => 'appointments', 'label' => 'Manage Appointments', 'icon' => 'fas fa-calendar-check', 'active' => false],
    ['id' => 'patients', 'label' => 'Hospital Patients', 'icon' => 'fas fa-users', 'active' => false],
    ['id' => 'logs', 'label' => 'Daily Activity', 'icon' => 'fas fa-clipboard-list', 'active' => false],
    ['id' => 'profile', 'label' => 'Hospital Profile', 'icon' => 'fas fa-hospital', 'active' => false]
];

renderDashboardLayout($role, $userName, $userPhoto, $menuItems, 'dashboard_content.php');

include '../includes/layout/footer.php';
?>
<?php
session_start();
require_once '../includes/core/session_check.php';
require_once '../includes/core/db.php';
require_once '../includes/layout/dashboard_layout.php';
require_once '../logic/patient/stripe_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../index.php');
    exit;
}

$userName = $_SESSION['name'];
$userEmail = $_SESSION['email'];
$role = 'patient';

$stmt = $pdo->prepare("SELECT profile_photo, is_verified FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch();
$userPhoto = $userData['profile_photo'] ?: 'assets/img/default.jpeg';
$_SESSION['is_verified'] = $userData['is_verified'];

$isDashboard = true;
$isSubFolder = true;
$pageTitle = 'Patient Dashboard - MedScape';

// Header is included by layout, but title/meta are handled here or by layout
include '../includes/layout/header.php';

$menuItems = [
    ['id' => 'dashboard', 'label' => 'Overview', 'icon' => 'fas fa-home', 'active' => true],
    ['id' => 'book', 'label' => 'Book Appointment', 'icon' => 'fas fa-calendar-plus', 'active' => false],
    ['id' => 'appointments', 'label' => 'My Appointments', 'icon' => 'fas fa-notes-medical', 'active' => false],
    ['id' => 'reports', 'label' => 'Medical Reports', 'icon' => 'fas fa-file-medical', 'active' => false],
    ['id' => 'profile', 'label' => 'My Profile', 'icon' => 'fas fa-user-circle', 'active' => false],
];

renderDashboardLayout($role, $userName, $userPhoto, $menuItems, 'dashboard_content.php');

include '../includes/layout/footer.php';
?>
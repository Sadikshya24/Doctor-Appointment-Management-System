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
$userData = $stmt->fetch();

$userPhoto = !empty($userData['profile_photo'])
    ? $userData['profile_photo']
    : 'assets/img/default.jpeg';

$isDashboard = true;
$isSubFolder = true;
$pageTitle = 'Hospital Dashboard - MedScape';

include '../includes/layout/header.php';

$menuItems = [
    ['id' => 'overview', 'label' => 'Overview', 'icon' => '', 'active' => true],
    ['id' => 'approved', 'label' => 'Our Doctors', 'icon' => '', 'active' => false],
    ['id' => 'appointments', 'label' => 'Manage Appointments', 'icon' => '', 'active' => false],
    ['id' => 'patients', 'label' => 'Hospital Patients', 'icon' => '', 'active' => false],
    ['id' => 'logs', 'label' => 'Daily Activity', 'icon' => '', 'active' => false],
    ['id' => 'profile', 'label' => 'Hospital Profile', 'icon' => '', 'active' => false]
];

renderDashboardLayout($role, $userName, $userPhoto, $menuItems, 'dashboard_content.php');

include '../includes/layout/footer.php';
?>
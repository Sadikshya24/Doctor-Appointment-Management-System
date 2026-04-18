<?php
session_start();
require_once '../../includes/core/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Strict Email Validation
    if (!preg_match('/^[a-zA-Z0-9]+@(gmail\.com|outlook\.com|yahoo\.com|hotmail\.com|yopmail\.com)$/', $email)) {
        $_SESSION['toast_msg'] = "Invalid email. Use letters and numbers followed by recognized domains.";
        $_SESSION['toast_type'] = "error";
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../../index.php'));
        exit;
    }

    // Strict Phone Validation
    if (!empty($phone) && !preg_match('/^9[0-9]{9}$/', $phone)) {
        $_SESSION['toast_msg'] = "Invalid phone number. Must be exactly 10 digits and start with 9.";
        $_SESSION['toast_type'] = "error";
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../../index.php'));
        exit;
    }

    // Check if email already exists for another user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $_SESSION['toast_msg'] = "This email is already taken by another account.";
        $_SESSION['toast_type'] = "error";
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../../index.php'));
        exit;
    }
    
    // Update user details
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
    if ($stmt->execute([$name, $email, $phone, $_SESSION['user_id']])) {
        // Update session variables so changes reflect immediately
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;

        if ($_SESSION['role'] === 'hospital') {
            $province = trim($_POST['province'] ?? '');
            $city = trim($_POST['city'] ?? '');
            
            if ($province !== '' && $city !== '') {
                $stmtHosp = $pdo->prepare("UPDATE hospitals SET province = ?, city = ? WHERE user_id = ?");
                $stmtHosp->execute([$province, $city, $_SESSION['user_id']]);
            }
        }

        // Redirect back to the referrer dashboard
        $_SESSION['toast_msg'] = "Profile updated successfully!";
        $_SESSION['toast_type'] = "success";
        $referer = $_SERVER['HTTP_REFERER'] ?? '../../index.php';
        header('Location: ' . $referer);
        exit;
    } else {
        $_SESSION['toast_msg'] = "Failed to update profile.";
        $_SESSION['toast_type'] = "error";
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../../index.php'));
        exit;
    }
}

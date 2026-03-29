<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name) || empty($email)) {
        echo "<script>alert('Name and Email are required.'); window.history.back();</script>";
        exit;
    }

    if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
        echo "<script>alert('Please enter a valid exactly 10-digit phone number.'); window.history.back();</script>";
        exit;
    }

    // Check if email already exists for another user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        echo "<script>alert('This email is already taken by another account.'); window.history.back();</script>";
        exit;
    }
    
    // Update user details
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
    if ($stmt->execute([$name, $email, $phone, $_SESSION['user_id']])) {
        // Update session variables so changes reflect immediately
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;

        if ($_SESSION['role'] === 'hospital' && isset($_POST['location'])) {
            $location = trim($_POST['location']);
            if ($location !== '') {
                $stmtHosp = $pdo->prepare("UPDATE hospitals SET location = ? WHERE user_id = ?");
                $stmtHosp->execute([$location, $_SESSION['user_id']]);
            }
        }

        // Redirect back to the referrer dashboard
        $referer = $_SERVER['HTTP_REFERER'] ?? '../index.php';
        header('Location: ' . $referer);
        exit;
    } else {
        echo "<script>alert('Failed to update profile.'); window.history.back();</script>";
        exit;
    }
}

<?php
session_start();
require_once '../../includes/core/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowedTypes)) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
        $upload_dir = '../../uploads/profiles/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $destination = $upload_dir . $filename;
        $dbPath = 'uploads/profiles/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
            if ($stmt->execute([$dbPath, $_SESSION['user_id']])) {
                $_SESSION['toast_msg'] = "Profile picture updated successfully!";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_msg'] = "Failed to update database record.";
                $_SESSION['toast_type'] = "error";
            }
        } else {
            $_SESSION['toast_msg'] = "Failed to move uploaded file.";
            $_SESSION['toast_type'] = "error";
        }
    } else {
        $_SESSION['toast_msg'] = "Invalid file type or upload error.";
        $_SESSION['toast_type'] = "error";
    }
}

// Redirect back to the referrer dashboard
$referer = $_SERVER['HTTP_REFERER'] ?? '../../index.php';
header('Location: ' . $referer);
exit;

<?php
session_start();
require_once '../includes/db.php';

// Security: Ensure only superadmin can access these actions
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add_hospital') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $location = $_POST['location'] ?? 'Location Pending';

    try {
        $pdo->beginTransaction();

        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("An account with this email already exists.");
        }

        // Create User
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'hospital')");
        $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
        $user_id = $pdo->lastInsertId();

        // Create Hospital Entry
        $stmt = $pdo->prepare("INSERT INTO hospitals (user_id, location, description) VALUES (?, ?, 'Added by Admin')");
        $stmt->execute([$user_id, $location]);

        $pdo->commit();
        echo json_encode(['success' => 'Hospital added successfully!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_hospital') {
    $id = $_POST['id'] ?? '';
    try {
        $pdo->beginTransaction();

        // Get user_id first
        $stmt = $pdo->prepare("SELECT user_id FROM hospitals WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();

        if ($user_id) {
            // Delete hospital record
            $stmt = $pdo->prepare("DELETE FROM hospitals WHERE id = ?");
            $stmt->execute([$id]);

            // Delete associated user record
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
        }

        $pdo->commit();
        echo json_encode(['success' => 'Hospital deleted successfully!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Failed to delete hospital.']);
    }
    exit;
}

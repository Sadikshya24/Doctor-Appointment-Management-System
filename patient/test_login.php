<?php
// patient/test_login.php
session_start();
// Since we are now inside the patient folder, we go UP one level to reach includes/db.php
require_once '../includes/db.php';

try {
    // Check if the dummy patient exists
    $stmt = $pdo->query("SELECT id FROM users WHERE email = 'patient@test.com'");
    $patient_id = $stmt->fetchColumn();

    // If no patient exists, let's create one for you to test with!
    if (!$patient_id) {
        $insert = $pdo->prepare("INSERT INTO users (name, email, role, phone) VALUES (?, ?, ?, ?)");
        $insert->execute(['Test Patient', 'patient@test.com', 'patient', '9800000000']);
        $patient_id = $pdo->lastInsertId();
    }

    // Set the session variables as if you logged in successfully
    $_SESSION['user_id'] = $patient_id;
    $_SESSION['role'] = 'patient';
    $_SESSION['name'] = 'Test Patient';
    $_SESSION['email'] = 'patient@test.com';

    // Since we are already in the patient folder, we just redirect to dashboard.php
    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    echo "<h1>Error setting up test user</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
    exit;
}
?>

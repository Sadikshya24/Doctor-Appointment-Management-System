<?php
session_start();
header('Content-Type: application/json');
require_once '../../includes/core/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = strtolower($_SESSION['role']);
$action = $_GET['action'] ?? 'get';

if ($action === 'get') {
    // 1. Auto-generate "1 hour before" notifications for Doctors and Patients
    if ($role === 'doctor' || $role === 'patient') {
        $check_sql = "";
        if ($role === 'doctor') {
            $check_sql = "SELECT a.id, a.appointment_date, a.appointment_time, u.name as other_name 
                          FROM appointments a 
                          JOIN users u ON a.patient_id = u.id 
                          JOIN doctors d ON a.doctor_id = d.id 
                          WHERE d.user_id = ? AND a.status = 'scheduled' AND a.payment_status = 'paid'";
        } else {
            $check_sql = "SELECT a.id, a.appointment_date, a.appointment_time, du.name as other_name 
                          FROM appointments a 
                          JOIN doctors d ON a.doctor_id = d.id 
                          JOIN users du ON d.user_id = du.id
                          WHERE a.patient_id = ? AND a.status = 'scheduled' AND a.payment_status = 'paid'";
        }

        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([$user_id]);
        $appointments = $stmt->fetchAll();

        foreach ($appointments as $app) {
            $appt_time = strtotime($app['appointment_date'] . ' ' . $app['appointment_time']);
            $now = time();
            $diff = $appt_time - $now;

            // If appointment is within the next 1 hour (3600 seconds) and not in the past
            if ($diff > 0 && $diff <= 3600) {
                $title = "Upcoming Appointment";
                $message = "You have an appointment with " . $app['other_name'] . " in about " . round($diff / 60) . " minutes.";
                
                // Check if notification already exists for this appointment
                $check_notif = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND title = ? AND message LIKE ?");
                $check_notif->execute([$user_id, $title, "%#" . $app['id'] . "%"]);
                
                if (!$check_notif->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'warning')");
                    $ins->execute([$user_id, $title, $message . " (Ref: #" . $app['id'] . ")"]);
                }
            }
        }
    }

    // 2. Fetch all unread notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $notifications]);
    exit;
}

if ($action === 'mark_read') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
    }
    echo json_encode(['status' => 'success']);
    exit;
}

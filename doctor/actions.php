<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'doctor') {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Unauthorized access."]);
    exit;
}

$action = $_GET['action'] ?? '';

// Fetch the doctor's specific ID from doctors table
$stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();
$doctor_id = $doctor ? $doctor['id'] : null;

if (!$doctor_id) {
    echo json_encode(["status" => "error", "message" => "Doctor profile not found."]);
    exit;
}

try {
    if ($action === 'get_appointments') {
        header('Content-Type: application/json');
        $stmt = $pdo->prepare("
            SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time, a.status, a.reason, 
                   u.name AS patient_name, u.email AS patient_email, hu.name AS hospital_name,
                   r.id AS report_id
            FROM appointments a 
            JOIN users u ON a.patient_id = u.id 
            LEFT JOIN hospitals h ON a.hospital_id = h.id
            LEFT JOIN users hu ON h.user_id = hu.id
            LEFT JOIN reports r ON a.id = r.appointment_id
            WHERE a.doctor_id = ? 
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $stmt->execute([$doctor_id]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'cancel_appointment') {
        header('Content-Type: application/json');
        $appointment_id = $_POST['appointment_id'] ?? '';
        if (!$appointment_id) {
            echo json_encode(["status" => "error", "message" => "Missing appointment ID."]);
            exit;
        }

        $app_stmt = $pdo->prepare("SELECT appointment_date, appointment_time FROM appointments WHERE id = ? AND doctor_id = ?");
        $app_stmt->execute([$appointment_id, $doctor_id]);
        $app = $app_stmt->fetch();

        if (!$app) {
            echo json_encode(["status" => "error", "message" => "Appointment not found."]);
            exit;
        }

        $appointment_datetime = strtotime($app['appointment_date'] . ' ' . $app['appointment_time']);
        if ($appointment_datetime - time() < 7200) {
            echo json_encode(["status" => "error", "message" => "Cannot cancel appointment less than 2 hours before the scheduled time."]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$appointment_id, $doctor_id]);
        echo json_encode(["status" => "success", "message" => "Appointment cancelled."]);
        exit;
    }

    if ($action === 'mark_completed') {
        header('Content-Type: application/json');
        $appointment_id = $_POST['appointment_id'] ?? '';
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$appointment_id, $doctor_id]);
        echo json_encode(["status" => "success", "message" => "Appointment marked as completed."]);
        exit;
    }

    if ($action === 'add_report') {
        header('Content-Type: application/json');
        $appointment_id = $_POST['appointment_id'] ?? '';
        $patient_id = $_POST['patient_id'] ?? '';
        $diagnosis = $_POST['diagnosis'] ?? '';
        $report_details = $_POST['report_details'] ?? '';
        $prescription = $_POST['prescription'] ?? '';

        if (!$patient_id || !$diagnosis || !$report_details || !$prescription) {
            echo json_encode(["status" => "error", "message" => "All report fields are required."]);
            exit;
        }

        // 1. Insert Report
        $stmt = $pdo->prepare("INSERT INTO reports (appointment_id, patient_id, doctor_id, diagnosis, report_details, prescription) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$appointment_id ?: null, $patient_id, $doctor_id, $diagnosis, $report_details, $prescription]);
        
        // 2. Mark Appointment as Completed if ID exists
        if ($appointment_id) {
            $upd = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ? AND doctor_id = ?");
            $upd->execute([$appointment_id, $doctor_id]);
        }
        
        echo json_encode(["status" => "success", "message" => "Medical report saved and appointment completed!"]);
        exit;
    }

    if ($action === 'get_patient_info') {
        $patient_id = $_GET['patient_id'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM patient_info WHERE user_id = ?");
        $stmt->execute([$patient_id]);
        echo json_encode($stmt->fetch() ?: new stdClass());
        exit;
    }

    if ($action === 'get_patient_files') {
        $patient_id = $_GET['patient_id'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM patient_files WHERE user_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$patient_id]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'update_profile') {
        header('Content-Type: application/json');
        $speciality = $_POST['speciality'] ?? '';
        $description = $_POST['description'] ?? '';
        $available_days = $_POST['available_days'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';

        $stmt = $pdo->prepare("UPDATE doctors SET speciality = ?, description = ?, available_days = ?, start_time = ?, end_time = ?, qualification = ?, experience_years = ? WHERE user_id = ?");
        $stmt->execute([$speciality, $description, $available_days, $start_time, $end_time, $_POST['qualification'] ?? 'MBBS', $_POST['experience_years'] ?? 0, $_SESSION['user_id']]);
        echo json_encode(["status" => "success", "message" => "Your profile and availability have been updated!"]);
        exit;
    }

    if ($action === 'reapply') {
        $hospital_id = $_POST['hospital_id'] ?? null;
        if (!$hospital_id) {
            echo "<script>alert('Hospital is required.'); window.location.href='dashboard.php';</script>";
            exit;
        }

        if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/cvs/';
            $file_ext = strtolower(pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['pdf', 'doc', 'docx'];

            if (in_array($file_ext, $allowed_types)) {
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $new_filename = uniqid('cv_') . '.' . $file_ext;
                if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_dir . $new_filename)) {
                    $cv_path = 'uploads/cvs/' . $new_filename;
                    
                    $stmt = $pdo->prepare("UPDATE doctors SET hospital_id = ?, cv_path = ?, status = 'pending' WHERE id = ?");
                    $stmt->execute([$hospital_id, $cv_path, $doctor_id]);
                    echo "<script>alert('Re-application submitted successfully!'); window.location.href='dashboard.php';</script>";
                    exit;
                }
            } else {
                echo "<script>alert('Invalid file format. Please upload PDF or DOC.'); window.location.href='dashboard.php';</script>";
                exit;
            }
        }
        echo "<script>alert('Please upload a valid CV file.'); window.location.href='dashboard.php';</script>";
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(["error" => "Invalid action specified."]);
} catch (Exception $e) {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(["error" => "An error occurred: " . $e->getMessage()]);
}
?>
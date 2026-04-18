<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/core/session_check.php';
require_once '../../includes/core/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'patient') {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Unauthorized access. Patient role required."]);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_hospitals') {
        $stmt = $pdo->query("SELECT h.id, h.province, h.city, h.location, h.description, u.name 
                             FROM hospitals h 
                             JOIN users u ON h.user_id = u.id
                             WHERE u.status = 'active'");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'get_doctors') {
        $sql = "SELECT d.id, d.speciality, d.description, d.available_days, d.start_time, d.end_time, d.hospital_id,
                       d.qualification, d.experience_years, d.nmc_number,
                       u.name, u.profile_photo, h.province, h.city, h.location, hu.name AS hospital_name 
                FROM doctors d 
                JOIN users u ON d.user_id = u.id 
                LEFT JOIN hospitals h ON d.hospital_id = h.id
                LEFT JOIN users hu ON h.user_id = hu.id
                WHERE d.status = 'approved' AND d.hospital_id IS NOT NULL 
                AND u.status = 'active' AND (hu.id IS NULL OR hu.status = 'active')";

        $params = [];

        if (!empty($_GET['speciality'])) {
            $sql .= " AND d.speciality LIKE ?";
            $params[] = "%" . trim($_GET['speciality']) . "%";
        }
        if (!empty($_GET['province'])) {
            $sql .= " AND h.province = ?";
            $params[] = $_GET['province'];
        }
        if (!empty($_GET['city'])) {
            $sql .= " AND h.city = ?";
            $params[] = $_GET['city'];
        }
        if (!empty($_GET['doctor_name'])) {
            $sql .= " AND u.name LIKE ?";
            $params[] = "%" . trim($_GET['doctor_name']) . "%";
        }
        if (!empty($_GET['hospital_id'])) {
            $sql .= " AND d.hospital_id = ?";
            $params[] = $_GET['hospital_id'];
        }
        if (!empty($_GET['hospital_name'])) {
            $sql .= " AND hu.name LIKE ?";
            $params[] = "%" . trim($_GET['hospital_name']) . "%";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'book_appointment') {
        $doctor_id = $_POST['doctor_id'] ?? '';
        $hospital_id = $_POST['hospital_id'] ?? null;
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $reason = $_POST['reason'] ?? '';

        if (!$doctor_id || !$appointment_date || !$appointment_time) {
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit;
        }

        // Check if appointment is in the future
        $appointment_full_datetime = strtotime($appointment_date . ' ' . $appointment_time);
        if ($appointment_full_datetime <= time()) {
            echo json_encode(["status" => "error", "message" => "Appointment must be scheduled for a future date and time."]);
            exit;
        }

        // Check doctor availability
        $doc_stmt = $pdo->prepare("SELECT available_days, start_time, end_time FROM doctors WHERE id = ?");
        $doc_stmt->execute([$doctor_id]);
        $doctor = $doc_stmt->fetch();

        if (!$doctor) {
            echo json_encode(["status" => "error", "message" => "Doctor not found."]);
            exit;
        }

        $dayOfWeek = date('D', strtotime($appointment_date));
        $available_days = array_map('trim', explode(',', $doctor['available_days']));

        if (!in_array($dayOfWeek, $available_days)) {
            echo json_encode(["status" => "error", "message" => "Doctor is not available on this day."]);
            exit;
        }

        // Standardize time formats for comparison
        $input_time = date('H:i:s', strtotime($appointment_time));

        if ($input_time < $doctor['start_time'] || $input_time > $doctor['end_time']) {
            echo json_encode(["status" => "error", "message" => "Appointment time is outside of doctor's working hours."]);
            exit;
        }

        // Check for double booking
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'");
        $check_stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
        if ($check_stmt->fetchColumn() > 0) {
            echo json_encode(["status" => "error", "message" => "This time slot is already booked."]);
            exit;
        }

        // Fetch doctor name for receipt
        $stmtDoc = $pdo->prepare("SELECT u.name FROM users u JOIN doctors d ON u.id = d.user_id WHERE d.id = ?");
        $stmtDoc->execute([$doctor_id]);
        $doctorName = $stmtDoc->fetchColumn() ?: 'Doctor';

        $booking_id = 'BK-' . strtoupper(substr(uniqid(), -6)) . '-' . rand(100, 999);

        $stmt = $pdo->prepare("INSERT INTO appointments (booking_id, patient_id, doctor_id, hospital_id, appointment_date, appointment_time, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([
                $booking_id, 
                (int)$_SESSION['user_id'], 
                (int)$doctor_id, 
                $hospital_id ? (int)$hospital_id : null, 
                $appointment_date, 
                $appointment_time, 
                $reason
            ]);
        } catch (Exception $e) {
            error_log("Booking failed: User ID " . $_SESSION['user_id'] . " attempting to book Doctor ID " . $doctor_id . ". SQL Error: " . $e->getMessage());
            throw $e;
        }

        echo json_encode([
            "status" => "success",
            "message" => "Appointment booked successfully!",
            "booking_id" => $booking_id,
            "doctor_name" => $doctorName,
            "appointment_date" => $appointment_date,
            "appointment_time" => $appointment_time,
            "fee" => "Rs. 1000"
        ]);
        exit;
    }

    if ($action === 'get_appointments') {
        $current_datetime = date('Y-m-d H:i:s');
        $pdo->exec("UPDATE appointments SET status = 'missed' WHERE status IN ('scheduled', 'reschedule_requested') AND CONCAT(appointment_date, ' ', appointment_time) < '$current_datetime'");

        $stmt = $pdo->prepare("SELECT a.id, a.booking_id, a.doctor_id, a.appointment_date, a.appointment_time, a.status, a.reason, 
                                      a.requested_date, a.requested_time,
                                      d.speciality, du.name AS doctor_name, du.status AS doctor_status, 
                                      hu.name AS hospital_name, hu.status AS hospital_status
                               FROM appointments a 
                               JOIN doctors d ON a.doctor_id = d.id 
                               JOIN users du ON d.user_id = du.id
                               LEFT JOIN hospitals h ON a.hospital_id = h.id
                               LEFT JOIN users hu ON h.user_id = hu.id
                               WHERE a.patient_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC");
        $stmt->execute([$_SESSION['user_id']]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'cancel_appointment') {
        $appointment_id = $_POST['appointment_id'] ?? '';
        if (!$appointment_id) {
            echo json_encode(["status" => "error", "message" => "Missing appointment ID."]);
            exit;
        }

        $app_stmt = $pdo->prepare("SELECT appointment_date, appointment_time FROM appointments WHERE id = ? AND patient_id = ?");
        $app_stmt->execute([$appointment_id, $_SESSION['user_id']]);
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

        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND patient_id = ?");
        $stmt->execute([$appointment_id, $_SESSION['user_id']]);
        echo json_encode(["status" => "success", "message" => "Appointment cancelled."]);
        exit;
    }

    if ($action === 'reschedule_appointment') {
        $appointment_id = $_POST['appointment_id'] ?? '';
        $new_date = $_POST['appointment_date'] ?? '';
        $new_time = $_POST['appointment_time'] ?? '';

        if (!$appointment_id || !$new_date || !$new_time) {
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit;
        }

        // Check if new appointment is in the future
        $new_appointment_full_datetime = strtotime($new_date . ' ' . $new_time);
        if ($new_appointment_full_datetime <= time()) {
            echo json_encode(["status" => "error", "message" => "Rescheduled appointment must be for a future date and time."]);
            exit;
        }

        // 1. Ownership & 2-Hour Rule Check
        $app_stmt = $pdo->prepare("SELECT doctor_id, appointment_date, appointment_time FROM appointments WHERE id = ? AND patient_id = ?");
        $app_stmt->execute([$appointment_id, $_SESSION['user_id']]);
        $app = $app_stmt->fetch();

        if (!$app) {
            echo json_encode(["status" => "error", "message" => "Appointment not found."]);
            exit;
        }

        $appointment_datetime = strtotime($app['appointment_date'] . ' ' . $app['appointment_time']);
        if ($appointment_datetime - time() < 7200) {
            echo json_encode(["status" => "error", "message" => "Rescheduling is only allowed at least 2 hours before the appointment."]);
            exit;
        }

        // 2. Doctor Availability Check
        $doctor_id = $app['doctor_id'];
        $doc_stmt = $pdo->prepare("SELECT available_days, start_time, end_time FROM doctors WHERE id = ?");
        $doc_stmt->execute([$doctor_id]);
        $doctor = $doc_stmt->fetch();

        $dayOfWeek = date('D', strtotime($new_date));
        $available_days = array_map('trim', explode(',', $doctor['available_days']));
        if (!in_array($dayOfWeek, $available_days)) {
            echo json_encode(["status" => "error", "message" => "Doctor is not available on this day."]);
            exit;
        }

        $input_time = date('H:i:s', strtotime($new_time));
        if ($input_time < $doctor['start_time'] || $input_time > $doctor['end_time']) {
            echo json_encode(["status" => "error", "message" => "New time is outside of doctor's working hours."]);
            exit;
        }

        // 3. Double Booking Check (Optional for Patient side: it's a request, but good practice to avoid knowingly bad requests)
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'");
        $check_stmt->execute([$doctor_id, $new_date, $new_time]);
        if ($check_stmt->fetchColumn() > 0) {
            echo json_encode(["status" => "error", "message" => "This time slot is already booked."]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE appointments SET requested_date = ?, requested_time = ?, status = 'reschedule_requested' WHERE id = ? AND patient_id = ?");
        $stmt->execute([$new_date, $new_time, (int)$appointment_id, (int)$_SESSION['user_id']]);

        echo json_encode(["status" => "success", "message" => "Reschedule request submitted successfully! Waiting for doctor approval."]);
        exit;
    }

    if ($action === 'update_health_info') {
        $age = $_POST['age'] ?? null;
        $height = $_POST['height'] ?? '';
        $weight = $_POST['weight'] ?? null;
        $meds = $_POST['medications'] ?? '';
        $history = $_POST['medical_history'] ?? '';

        $stmt = $pdo->prepare("SELECT id FROM patient_info WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $update = $pdo->prepare("UPDATE patient_info SET age = ?, height = ?, weight = ?, medications = ?, medical_history = ? WHERE user_id = ?");
            $update->execute([$age, $height, $weight, $meds, $history, $_SESSION['user_id']]);
        } else {
            $insert = $pdo->prepare("INSERT INTO patient_info (user_id, age, height, weight, medications, medical_history) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->execute([$_SESSION['user_id'], $age, $height, $weight, $meds, $history]);
        }
        echo json_encode(["status" => "success", "message" => "Health profile updated successfully!"]);
        exit;
    }

    if ($action === 'upload_health_file') {
        if (isset($_FILES['medical_file']) && $_FILES['medical_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/patient_records/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            } else {
                chmod($upload_dir, 0777); // Self-healing: Ensure it's writable if already exists
            }

            $file_name = $_FILES['medical_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if ($file_ext !== 'pdf') {
                echo json_encode(["status" => "error", "message" => "Only PDF files are allowed."]);
                exit;
            }

            $new_filename = uniqid('rec_') . '.pdf';
            if (move_uploaded_file($_FILES['medical_file']['tmp_name'], $upload_dir . $new_filename)) {
                $file_path = 'uploads/patient_records/' . $new_filename;
                $file_path = 'uploads/patient_records/' . $new_filename;
                $stmt = $pdo->prepare("INSERT INTO patient_files (user_id, file_name, file_path) VALUES (?, ?, ?)");
                $stmt->execute([(int)$_SESSION['user_id'], $file_name, $file_path]);
                echo json_encode(["status" => "success", "message" => "File uploaded successfully!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to save file."]);
            }
        }
        exit;
    }

    if ($action === 'get_health_files') {
        $stmt = $pdo->prepare("SELECT * FROM patient_files WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([(int)$_SESSION['user_id']]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'get_reports') {
        $stmt = $pdo->prepare("SELECT r.id, r.diagnosis, r.report_details, r.prescription, r.created_at, a.appointment_date, 
                                      du.name AS doctor_name
                               FROM reports r 
                               JOIN doctors d ON r.doctor_id = d.id 
                               JOIN users du ON d.user_id = du.id
                               LEFT JOIN appointments a ON r.appointment_id = a.id
                               WHERE r.patient_id = ? ORDER BY r.created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'get_report_history') {
        $report_id = $_GET['report_id'] ?? '';

        $query = "SELECT h.*, r.patient_id 
                  FROM report_history h 
                  JOIN reports r ON h.report_id = r.id 
                  WHERE h.report_id = ? 
                  ORDER BY h.version_number DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$report_id]);
        $history = $stmt->fetchAll();

        // Security check: Only patient involved can see history
        if (!empty($history)) {
            if ($history[0]['patient_id'] != $_SESSION['user_id']) {
                echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
                exit;
            }
        }

        echo json_encode($history);
        exit;
    }

    if ($action === 'delete_health_file') {
        $file_id = $_POST['file_id'] ?? '';
        if (!$file_id) {
            echo json_encode(["status" => "error", "message" => "Missing file ID."]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT file_path FROM patient_files WHERE id = ? AND user_id = ?");
        $stmt->execute([$file_id, $_SESSION['user_id']]);
        if ($file = $stmt->fetch()) {
            $full_path = '../' . $file['file_path'];
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            $pdo->prepare("DELETE FROM patient_files WHERE id = ? AND user_id = ?")->execute([$file_id, $_SESSION['user_id']]);
            echo json_encode(["status" => "success", "message" => "Record deleted successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "File not found."]);
        }
        exit;
    }

    if ($action === 'get_doctor_availability') {
        $doctor_id = $_GET['doctor_id'] ?? '';
        if (!$doctor_id) {
            echo json_encode(["status" => "error", "message" => "Missing doctor ID"]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT start_time, end_time FROM doctors WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch();

        if ($doctor) {
            echo json_encode([
                "status" => "success",
                "start_time" => substr($doctor['start_time'], 0, 5),
                "end_time" => substr($doctor['end_time'], 0, 5)
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Doctor not found"]);
        }
        exit;
    }

    if ($action === 'delete_account') {
        $user_id = $_SESSION['user_id'];
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM reports WHERE patient_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM appointments WHERE patient_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM patient_info WHERE user_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM patient_files WHERE user_id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            $pdo->commit();
            session_unset();
            session_destroy();
            echo json_encode(['status' => 'success', 'message' => 'Account deleted successfully.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete account.']);
        }
        exit;
    }

    echo json_encode(["error" => "Invalid action specified."]);
} catch (Exception $e) {
    echo json_encode(["error" => "An error occurred: " . $e->getMessage()]);
}
?>
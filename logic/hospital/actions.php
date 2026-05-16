<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/core/session_check.php';
require_once '../../includes/core/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'hospital') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit;
}

$action = $_GET['action'] ?? '';

// Get hospital_id
$stmt = $pdo->prepare("SELECT id FROM hospitals WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$hospital = $stmt->fetch();
$hospital_id = $hospital ? $hospital['id'] : null;

if (!$hospital_id) {
    echo json_encode(["status" => "error", "message" => "Hospital profile missing. Contact admin."]);
    exit;
}

try {
    if ($action === 'get_doctor_availability') {
        $doctor_id = $_GET['doctor_id'] ?? '';
        if (!$doctor_id) {
            echo json_encode(["status" => "error", "message" => "Missing doctor ID"]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT start_time, end_time FROM doctors WHERE id = ? AND hospital_id = ?");
        $stmt->execute([$doctor_id, $hospital_id]);
        $doctor = $stmt->fetch();

        if ($doctor) {
            echo json_encode([
                "status" => "success",
                "start_time" => substr($doctor['start_time'], 0, 5),
                "end_time" => substr($doctor['end_time'], 0, 5)
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Doctor not found or does not belong to your hospital."]);
        }
        exit;
    }

    if ($action === 'get_stats') {
        // Total Active Doctors (Approved and Account Status Active)
        $stmtDoc = $pdo->prepare("SELECT COUNT(*) FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.hospital_id = ? AND d.status = 'approved' AND u.status = 'active'");
        $stmtDoc->execute([$hospital_id]);
        $totalDocs = $stmtDoc->fetchColumn();

        // Total Appointments (all types)
        $stmtAppt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            WHERE d.hospital_id = ?
        ");
        $stmtAppt->execute([$hospital_id]);
        $totalAppts = $stmtAppt->fetchColumn();

        // Completed (Consulted) Appointments
        $stmtCompleted = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            WHERE d.hospital_id = ? AND a.status = 'completed'
        ");
        $stmtCompleted->execute([$hospital_id]);
        $completedAppts = $stmtCompleted->fetchColumn();

        // Revenue (Completed Appointments * 1000)
        $revenue = $completedAppts * 1000;

        echo json_encode([
            "total_doctors" => $totalDocs,
            "total_appointments" => $totalAppts,
            "completed_appointments" => $completedAppts,
            "revenue_generated" => $revenue,
            "pending_appointments" => $totalAppts - $completedAppts, // Simplistic, but let's be more precise
            "scheduled_appointments" => $pdo->query("SELECT COUNT(*) FROM appointments a JOIN doctors d ON a.doctor_id = d.id WHERE d.hospital_id = $hospital_id AND a.status = 'scheduled'")->fetchColumn(),
            "total_patients" => $pdo->query("SELECT COUNT(DISTINCT patient_id) FROM appointments a JOIN doctors d ON a.doctor_id = d.id WHERE d.hospital_id = $hospital_id")->fetchColumn()
        ]);
        exit;
    }

    if ($_GET['action'] === 'get_admissions') {
    $stmt = $pdo->prepare("
        SELECT DATE(appointment_date) as day, COUNT(*) as total
        FROM appointments
        WHERE hospital_id = ?
        AND appointment_date >= CURDATE() - INTERVAL 30 DAY
        GROUP BY day
    ");
$stmt->execute([$hospital_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
    }

    if ($_GET['action'] === 'get_payments') {
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as total
        FROM payments p
        JOIN doctors d ON p.doctor_id = d.id
        WHERE d.hospital_id = ?
    ");
    $stmt->execute([$hospital_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
    if ($action === 'get_doctor_activity') {
        $stmt = $pdo->prepare("
            SELECT u.name, d.speciality, d.nmc_number,
                (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id AND status = 'completed' AND appointment_date = CURDATE()) as consulted_today
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            WHERE d.hospital_id = ? AND d.status = 'approved'
            ORDER BY consulted_today DESC
        ");
        $stmt->execute([$hospital_id]);
        echo json_encode($stmt->fetchAll());
        exit;
    }
    if ($action === 'get_doctors') {
        $statusFilter = $_GET['status'] ?? 'pending';
        $stmt = $pdo->prepare("
            SELECT d.id as doctor_id, d.nmc_number, d.cv_path, d.status, d.speciality, d.description, u.name, u.email, u.profile_photo, u.status as account_status
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            WHERE d.hospital_id = ? AND d.status = ?
            ORDER BY u.created_at DESC
        ");
        $stmt->execute([$hospital_id, $statusFilter]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'update_status') {
        $doc_id = $_POST['doctor_id'] ?? '';
        $new_status = $_POST['status'] ?? '';

        if (!in_array($new_status, ['approved', 'rejected'])) {
            echo json_encode(["status" => "error", "message" => "Invalid status provided"]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE doctors SET status = ? WHERE id = ? AND hospital_id = ?");
        $stmt->execute([$new_status, $doc_id, $hospital_id]);
        
        $msg = $new_status === 'approved' ? "Doctor Approved" : "Doctor Rejected";
        logHospitalActivity($pdo, "$msg (ID: $doc_id)", $_SESSION['name'], $hospital_id);

        echo json_encode(["status" => "success", "message" => "Doctor application has been $new_status."]);
        exit;
    }

    if ($action === 'toggle_doctor_status') {
        $doc_id = $_POST['doctor_id'] ?? '';
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT user_id, u.status FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ? AND d.hospital_id = ?");
        $stmt->execute([$doc_id, $hospital_id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            $newStatus = $doc['status'] === 'active' ? 'inactive' : 'active';
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $doc['user_id']]);
            logHospitalActivity($pdo, "Doctor status changed to $newStatus (ID: $doc_id)", $_SESSION['name'], $hospital_id);
            $pdo->commit();
            echo json_encode(["status" => "success", "message" => "Doctor account $newStatus" . "d successfully.", "new_status" => $newStatus]);
        } else {
            $pdo->rollBack();
            echo json_encode(["status" => "error", "message" => "Doctor not found or unauthorized."]);
        }
        exit;
    }

    if ($action === 'get_appointments') {
        $current_datetime = date('Y-m-d H:i:s');
        $pdo->exec("UPDATE appointments SET status = 'missed' WHERE status IN ('scheduled', 'reschedule_requested') AND CONCAT(appointment_date, ' ', appointment_time) < '$current_datetime'");

        $stmt = $pdo->prepare("
            SELECT a.id, a.doctor_id, up.name as patient, ud.name as doctor, a.appointment_date as date, a.appointment_time as time, a.requested_date, a.requested_time, a.status
            FROM appointments a
            JOIN users up ON a.patient_id = up.id
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users ud ON d.user_id = ud.id
            WHERE d.hospital_id = ?
            ORDER BY a.appointment_date DESC
        ");
        $stmt->execute([$hospital_id]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'cancel_appointment') {
        $appt_id = $_POST['appointment_id'] ?? '';
        // 1. Ownership & 2nd-hour Rule Check
        $app_stmt = $pdo->prepare("SELECT appointment_date, appointment_time FROM appointments a JOIN doctors d ON a.doctor_id = d.id WHERE a.id = ? AND d.hospital_id = ?");
        $app_stmt->execute([$appt_id, $hospital_id]);
        $app = $app_stmt->fetch();

        if (!$app) {
            echo json_encode(["status" => "error", "message" => "Appointment not found or unauthorized."]);
            exit;
        }

        $appointment_datetime = strtotime($app['appointment_date'] . ' ' . $app['appointment_time']);
        if ($appointment_datetime - time() < 7200) {
            echo json_encode(["status" => "error", "message" => "Cannot cancel appointment less than 2 hours before the scheduled time."]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
        if ($stmt->execute([$appt_id])) {
            logHospitalActivity($pdo, "Appointment Cancelled (ID: $appt_id)", $_SESSION['name'], $hospital_id);
            echo json_encode(["status" => "success", "message" => "Appointment cancelled."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to cancel."]);
        }
        exit;
    }

    if ($action === 'reschedule_appointment') {
        $appt_id = $_POST['appointment_id'] ?? '';
        $new_date = $_POST['appointment_date'] ?? '';
        $new_time = $_POST['appointment_time'] ?? '';

        if (!$appt_id || !$new_date || !$new_time) {
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit;
        }

        // Check if new appointment is in the future
        $appointment_full_datetime = strtotime($new_date . ' ' . $new_time);
        if ($appointment_full_datetime <= time()) {
            echo json_encode(["status" => "error", "message" => "Rescheduled appointment must be for a future date and time."]);
            exit;
        }

        // 1. Ownership & 2nd-hour Rule Check
        $app_stmt = $pdo->prepare("
            SELECT a.doctor_id, a.appointment_date, a.appointment_time 
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ? AND d.hospital_id = ?
        ");
        $app_stmt->execute([$appt_id, $hospital_id]);
        $app = $app_stmt->fetch();

        if (!$app) {
            echo json_encode(["status" => "error", "message" => "Appointment not found or unauthorized."]);
            exit;
        }

        $appointment_datetime = strtotime($app['appointment_date'] . ' ' . $app['appointment_time']);
        if ($appointment_datetime - time() < 7200) {
            echo json_encode(["status" => "error", "message" => "Rescheduling is only allowed at least 2 hours before the appointment."]);
            exit;
        }

        $doctor_id = $app['doctor_id'];

        // 2. Doctor Availability Check
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
            echo json_encode(["status" => "error", "message" => "New time is outside doctor's working hours."]);
            exit;
        }

        // 3. Double Booking Check
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled' AND id != ?");
        $check_stmt->execute([$doctor_id, $new_date, $new_time, $appt_id]);
        if ($check_stmt->fetchColumn() > 0) {
            echo json_encode(["status" => "error", "message" => "This time slot is already booked."]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'scheduled', requested_date = NULL, requested_time = NULL WHERE id = ?");
        if ($stmt->execute([$new_date, $new_time, $appt_id])) {
            logHospitalActivity($pdo, "Appointment Rescheduled (ID: $appt_id)", $_SESSION['name'], $hospital_id);
            echo json_encode(["status" => "success", "message" => "Appointment rescheduled successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to reschedule."]);
        }
        exit;
    }

    if ($action === 'delete_appointment') {
    $appt_id = $_POST['appointment_id'] ?? '';

    // Check ownership
    $stmt = $pdo->prepare("
        SELECT a.id 
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.id = ? AND d.hospital_id = ?
    ");
    $stmt->execute([$appt_id, $hospital_id]);

    if (!$stmt->fetch()) {
        echo json_encode(["status" => "error", "message" => "Unauthorized or not found"]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->execute([$appt_id]);

    logHospitalActivity($pdo, "Appointment Deleted (ID: $appt_id)", $_SESSION['name'], $hospital_id);

    echo json_encode(["status" => "success", "message" => "Appointment deleted"]);
    exit;
    }

    if ($action === 'update_appointment_status') {
    $appt_id = $_POST['appointment_id'] ?? '';
    $status = $_POST['status'] ?? '';

    $allowed = ['scheduled', 'completed', 'missed', 'cancelled'];

    if (!in_array($status, $allowed)) {
        echo json_encode(["status" => "error", "message" => "Invalid status"]);
        exit;
    }

    // Check ownership
    $stmt = $pdo->prepare("
        SELECT a.id 
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.id = ? AND d.hospital_id = ?
    ");
    $stmt->execute([$appt_id, $hospital_id]);

    if (!$stmt->fetch()) {
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->execute([$status, $appt_id]);

    logHospitalActivity($pdo, "Appointment status updated to $status (ID: $appt_id)", $_SESSION['name'], $hospital_id);

    echo json_encode(["status" => "success"]);
    exit;
 }

    if ($action === 'approve_reschedule') {
        $appt_id = $_POST['appointment_id'] ?? '';
        if (!$appt_id) {
            echo json_encode(["status" => "error", "message" => "Missing appointment ID"]);
            exit;
        }

        // Check if the requested time is still in the future
        $check_stmt = $pdo->prepare("SELECT requested_date, requested_time FROM appointments WHERE id = ?");
        $check_stmt->execute([$appt_id]);
        $appt = $check_stmt->fetch();
        if ($appt && $appt['requested_date']) {
            $req_full_datetime = strtotime($appt['requested_date'] . ' ' . $appt['requested_time']);
            if ($req_full_datetime <= time()) {
                echo json_encode(["status" => "error", "message" => "The requested reschedule time has already passed. Please coordinate a new time."]);
                exit;
            }
        }

        $stmt = $pdo->prepare("
            UPDATE appointments a
            JOIN doctors d ON a.doctor_id = d.id
            SET a.appointment_date = a.requested_date, a.appointment_time = a.requested_time, a.status = 'scheduled', a.requested_date = NULL, a.requested_time = NULL
            WHERE a.id = ? AND d.hospital_id = ? AND a.status = 'reschedule_requested'
        ");
        if ($stmt->execute([$appt_id, $hospital_id])) {
            logHospitalActivity($pdo, "Patient Reschedule Request Approved (ID: $appt_id)", $_SESSION['name'], $hospital_id);
            echo json_encode(["status" => "success", "message" => "Request approved and time updated."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Approval failed."]);
        }
        exit;
    }

    if ($action === 'decline_reschedule') {
        $appt_id = $_POST['appointment_id'] ?? '';
        if (!$appt_id) {
            echo json_encode(["status" => "error", "message" => "Missing appointment ID"]);
            exit;
        }
        $stmt = $pdo->prepare("
            UPDATE appointments a
            JOIN doctors d ON a.doctor_id = d.id
            SET a.status = 'scheduled', a.requested_date = NULL, a.requested_time = NULL
            WHERE a.id = ? AND d.hospital_id = ? AND a.status = 'reschedule_requested'
        ");
        if ($stmt->execute([$appt_id, $hospital_id])) {
            logHospitalActivity($pdo, "Patient Reschedule Request Declined (ID: $appt_id)", $_SESSION['name'], $hospital_id);
            echo json_encode(["status" => "success", "message" => "Request declined."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Decline failed."]);
        }
        exit;
    }

    if ($action === 'get_patients') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.email, u.phone
            FROM users u
            JOIN appointments a ON u.id = a.patient_id
            JOIN doctors d ON a.doctor_id = d.id
            WHERE d.hospital_id = ?
        ");
        $stmt->execute([$hospital_id]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'get_logs') {
        $stmt = $pdo->prepare("SELECT action, user, created_at FROM logs WHERE hospital_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$hospital_id]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    echo json_encode(["status" => "error", "message" => "Invalid action"]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "An error occurred: " . $e->getMessage()]);
}


function logHospitalActivity($pdo, $message, $user, $hospital_id) {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (action, user, hospital_id) VALUES (?, ?, ?)");
        $stmt->execute([$message, $user, $hospital_id]);
    } catch (Exception $e) {
        $stmt = $pdo->prepare("INSERT INTO logs (action, user) VALUES (?, ?)");
        $stmt->execute(["[Hosp ID: $hospital_id] " . $message, $user]);
    }
}
echo json_encode($_SESSION);
exit;
?>
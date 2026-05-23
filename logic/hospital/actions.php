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

        $stmtAppt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            WHERE d.hospital_id = ?
        ");
        $stmtAppt->execute([$hospital_id]);
        $totalAppts = $stmtAppt->fetchColumn();

      
        $stmtCompleted = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            WHERE d.hospital_id = ? AND a.status = 'completed'
        ");
        $stmtCompleted->execute([$hospital_id]);
        $completedAppts = $stmtCompleted->fetchColumn();

        $stmtRevenue = $pdo->prepare("
            SELECT SUM(COALESCE(a.final_amount_npr, 1000)) 
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            WHERE d.hospital_id = ? AND a.status = 'completed' AND a.payment_status = 'paid'
        ");
        $stmtRevenue->execute([$hospital_id]);
        $revenue = $stmtRevenue->fetchColumn() ?: 0;

        $stmtSched = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments a 
            JOIN doctors d ON a.doctor_id = d.id 
            WHERE d.hospital_id = ? AND a.status = 'scheduled'
        ");
        $stmtSched->execute([$hospital_id]);
        $scheduledAppts = $stmtSched->fetchColumn();

        $stmtPatients = $pdo->prepare("
            SELECT COUNT(DISTINCT a.patient_id) 
            FROM appointments a 
            JOIN doctors d ON a.doctor_id = d.id 
            WHERE d.hospital_id = ?
        ");
        $stmtPatients->execute([$hospital_id]);
        $totalPatients = $stmtPatients->fetchColumn();

        
        echo json_encode([
            "total_doctors" => (int)$totalDocs,
            "total_appointments" => (int)$totalAppts,
            "completed_appointments" => (int)$completedAppts,
            "revenue_generated" => (float)$revenue,
            "pending_appointments" => (int)($totalAppts - $completedAppts),
            "scheduled_appointments" => (int)$scheduledAppts,
            "total_patients" => (int)$totalPatients
        ]);
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
        
        $stmt = $pdo->prepare("SELECT user_id, u.status, u.email, u.name FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ? AND d.hospital_id = ?");
        $stmt->execute([$doc_id, $hospital_id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            $newStatus = $doc['status'] === 'active' ? 'inactive' : 'active';
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $doc['user_id']]);
            logHospitalActivity($pdo, "Doctor status changed to $newStatus (ID: $doc_id)", $_SESSION['name'], $hospital_id);
            
            // Send Email Notification
            require_once '../../includes/core/email_helper.php';
            $subject = "Account Status Update - MedScape";
            $statusText = ($newStatus === 'active') ? "ACTIVATED" : "DEACTIVATED";
            $message = "Hello " . htmlspecialchars($doc['name']) . ",<br><br>";
            $message .= "Your MedScape account status has been updated to: <strong>" . $statusText . "</strong>.<br>";
            if ($newStatus === 'active') {
                $message .= "You can now log in and manage your appointments.<br>";
            } else {
                $message .= "Your access has been temporarily suspended. Please contact your hospital administration for details.<br>";
            }
            $message .= "<br>Regards,<br>MedScape Team";
            
            sendEmail($doc['email'], $subject, $message);
            
            $pdo->commit();
            echo json_encode(["status" => "success", "message" => "Doctor account $newStatus" . "d successfully.", "new_status" => $newStatus]);
        } else {
            $pdo->rollBack();
            echo json_encode(["status" => "error", "message" => "Doctor not found or unauthorized."]);
        }
        exit;
    }

    if ($action === 'get_appointments') {
        $q = $_GET['q'] ?? '';
        $current_datetime = date('Y-m-d H:i:s');
        $pdo->exec("UPDATE appointments SET status = 'missed' WHERE status IN ('scheduled', 'reschedule_requested') AND CONCAT(appointment_date, ' ', appointment_time) < '$current_datetime'");

        $sql = "
            SELECT a.id, a.doctor_id, up.name as patient, up.id as patient_id, ud.name as doctor, d.id as doctor_id, a.appointment_date as date, a.appointment_time as time, a.requested_date, a.requested_time, a.status
            FROM appointments a
            JOIN users up ON a.patient_id = up.id
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users ud ON d.user_id = ud.id
            WHERE d.hospital_id = ?
        ";
        
        $params = [$hospital_id];
        if ($q) {
            $sql .= " AND (up.name LIKE ? OR up.id LIKE ? OR ud.name LIKE ? OR d.id LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        
        $sql .= " ORDER BY a.appointment_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        exit;
    }
    // --- PASTE THIS CODE HERE ---
    if ($action === 'update_appointment_status') {
        $appt_id = $_POST['appointment_id'] ?? '';
        $new_status = $_POST['status'] ?? '';

        $allowed_statuses = ['scheduled', 'completed', 'missed', 'cancelled'];
        if (!in_array($new_status, $allowed_statuses)) {
            echo json_encode(["status" => "error", "message" => "Invalid status value."]);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE appointments a
            JOIN doctors d ON a.doctor_id = d.id
            SET a.status = ?
            WHERE a.id = ? AND d.hospital_id = ?
        ");
        $stmt->execute([$new_status, $appt_id, $hospital_id]);

        logHospitalActivity($pdo, "Changed Appointment ID: $appt_id status to $new_status", $_SESSION['name'], $hospital_id);
        echo json_encode(["status" => "success", "message" => "Status updated successfully!"]);
        exit;
    }

    if ($action === 'delete_appointment') {
        $appt_id = $_POST['appointment_id'] ?? '';

        $check = $pdo->prepare("SELECT a.id FROM appointments a JOIN doctors d ON a.doctor_id = d.id WHERE a.id = ? AND d.hospital_id = ?");
        $check->execute([$appt_id, $hospital_id]);
        
        if (!$check->fetch()) {
            echo json_encode(["status" => "error", "message" => "Appointment not found or unauthorized."]);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
        if ($stmt->execute([$appt_id])) {
            logHospitalActivity($pdo, "Permanently Deleted Appointment (ID: $appt_id)", $_SESSION['name'], $hospital_id);
            echo json_encode(["status" => "success", "message" => "Appointment record deleted successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to delete record."]);
        }
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

            // Notify Patient
            $stmt_pat = $pdo->prepare("SELECT patient_id, booking_id FROM appointments WHERE id = ?");
            $stmt_pat->execute([$appt_id]);
            $appt_data = $stmt_pat->fetch();
            if ($appt_data) {
                $stmt_n = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Appointment Rescheduled', ?, 'warning')");
                $stmt_n->execute([$appt_data['patient_id'], "Your appointment #" . $appt_data['booking_id'] . " was rescheduled to " . $new_date . " at " . $new_time . " by the hospital."]);
            }

            echo json_encode(["status" => "success", "message" => "Appointment rescheduled successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to reschedule."]);
        }
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
        $q = $_GET['q'] ?? '';
        $sql = "
            SELECT DISTINCT u.id, u.name, u.email, u.phone
            FROM users u
            JOIN appointments a ON u.id = a.patient_id
            JOIN doctors d ON a.doctor_id = d.id
            WHERE d.hospital_id = ?
        ";
        $params = [$hospital_id];
        if ($q) {
            $sql .= " AND (u.name LIKE ? OR u.id LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
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

    if ($action === 'get_all_patient_reports') {
        $patient_id = $_GET['patient_id'] ?? '';

        if (!$patient_id) {
            echo json_encode(["status" => "error", "message" => "Patient ID is required."]);
            exit;
        }

        // Fetch finalized reports for this patient ONLY from doctors belonging to this hospital
        $query = "
            SELECT r.*, u_doc.name as doctor_name, d.speciality, a.appointment_date
            FROM reports r
            JOIN doctors d ON r.doctor_id = d.id
            JOIN users u_doc ON d.user_id = u_doc.id
            LEFT JOIN appointments a ON r.appointment_id = a.id
            WHERE r.patient_id = ? AND d.hospital_id = ?
            ORDER BY r.created_at DESC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$patient_id, $hospital_id]);
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

?>
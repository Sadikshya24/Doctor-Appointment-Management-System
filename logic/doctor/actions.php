<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/core/session_check.php';
require_once '../../includes/core/db.php';

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
    if ($action === 'get_stats') {
        header('Content-Type: application/json');

        $total = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ?");
        $total->execute([$doctor_id]);

        $today = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()");
        $today->execute([$doctor_id]);

        $completed = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'completed'");
        $completed->execute([$doctor_id]);

        $left = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'scheduled'");
        $left->execute([$doctor_id]);

        echo json_encode([
            "total_appointments" => $total->fetchColumn(),
            "today_appointments" => $today->fetchColumn(),
            "completed_consultations" => $completed->fetchColumn(),
            "appointments_left" => $left->fetchColumn()
        ]);
        exit;
    }
    if ($action === 'get_appointments') {
        $current_datetime = date('Y-m-d H:i:s');
        $pdo->exec("UPDATE appointments SET status = 'missed' WHERE status IN ('scheduled', 'reschedule_requested') AND CONCAT(appointment_date, ' ', appointment_time) < '$current_datetime'");

        header('Content-Type: application/json');
        $status = $_GET['status'] ?? '';

        $query = "
            SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time, a.status, a.reason,
                   a.requested_date, a.requested_time, 
                   u.name AS patient_name, u.email AS patient_email, hu.name AS hospital_name,
                   r.id AS report_id, r.created_at AS report_created_at
            FROM appointments a 
            JOIN users u ON a.patient_id = u.id 
            LEFT JOIN hospitals h ON a.hospital_id = h.id
            LEFT JOIN users hu ON h.user_id = hu.id
            LEFT JOIN reports r ON a.id = r.appointment_id
            WHERE a.doctor_id = ? 
        ";
        $params = [$doctor_id];

        if ($status) {
            if ($status === 'scheduled') {
                $query .= " AND a.status IN ('scheduled', 'reschedule_requested') ";
            } else {
                $query .= " AND a.status = ? ";
                $params[] = $status;
            }
        }

        $dateFilter = $_GET['date'] ?? '';
        if ($dateFilter === 'today') {
            $query .= " AND a.appointment_date = CURDATE() ";
        } elseif ($dateFilter) {
            $query .= " AND a.appointment_date = ? ";
            $params[] = $dateFilter;
        }

        $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC ";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
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

    if ($action === 'reschedule_appointment') {
        header('Content-Type: application/json');
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
        $app_stmt = $pdo->prepare("SELECT doctor_id, appointment_date, appointment_time FROM appointments WHERE id = ? AND doctor_id = ?");
        $app_stmt->execute([$appointment_id, $doctor_id]);
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

        // 2. Doctor Availability Check (Self-check for the doctor)
        $doc_stmt = $pdo->prepare("SELECT available_days, start_time, end_time FROM doctors WHERE id = ?");
        $doc_stmt->execute([$doctor_id]);
        $doctor_info = $doc_stmt->fetch();

        $dayOfWeek = date('D', strtotime($new_date));
        $available_days = array_map('trim', explode(',', $doctor_info['available_days']));
        if (!in_array($dayOfWeek, $available_days)) {
            echo json_encode(["status" => "error", "message" => "You are not scheduled to work on this day."]);
            exit;
        }

        $input_time = date('H:i:s', strtotime($new_time));
        if ($input_time < $doctor_info['start_time'] || $input_time > $doctor_info['end_time']) {
            echo json_encode(["status" => "error", "message" => "New time is outside your working hours."]);
            exit;
        }

        // 3. Double Booking Check
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled' AND id != ?");
        $check_stmt->execute([$doctor_id, $new_date, $new_time, $appointment_id]);
        if ($check_stmt->fetchColumn() > 0) {
            echo json_encode(["status" => "error", "message" => "This time slot is already booked."]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'scheduled', requested_date = NULL, requested_time = NULL WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$new_date, $new_time, $appointment_id, $doctor_id]);

        echo json_encode(["status" => "success", "message" => "Appointment rescheduled naturally!"]);
        exit;
    }

    if ($action === 'approve_reschedule') {
        header('Content-Type: application/json');
        $appointment_id = $_POST['appointment_id'] ?? '';
        if (!$appointment_id) {
            echo json_encode(["status" => "error", "message" => "Missing appointment ID."]);
            exit;
        }

        // Check if the requested time is still in the future
        $check_stmt = $pdo->prepare("SELECT requested_date, requested_time FROM appointments WHERE id = ? AND doctor_id = ?");
        $check_stmt->execute([$appointment_id, $doctor_id]);
        $appt = $check_stmt->fetch();
        if ($appt && $appt['requested_date']) {
            $req_full_datetime = strtotime($appt['requested_date'] . ' ' . $appt['requested_time']);
            if ($req_full_datetime <= time()) {
                echo json_encode(["status" => "error", "message" => "The requested reschedule time has already passed. Please coordinate a new time."]);
                exit;
            }
        }

        $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = requested_date, appointment_time = requested_time, status = 'scheduled', requested_date = NULL, requested_time = NULL WHERE id = ? AND doctor_id = ? AND status = 'reschedule_requested'");
        $stmt->execute([$appointment_id, $doctor_id]);
        echo json_encode(["status" => "success", "message" => "Patient's reschedule request approved!"]);
        exit;
    }

    if ($action === 'decline_reschedule') {
        header('Content-Type: application/json');
        $appointment_id = $_POST['appointment_id'] ?? '';
        if (!$appointment_id) {
            echo json_encode(["status" => "error", "message" => "Missing appointment ID."]);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'scheduled', requested_date = NULL, requested_time = NULL WHERE id = ? AND doctor_id = ? AND status = 'reschedule_requested'");
        $stmt->execute([$appointment_id, $doctor_id]);
        echo json_encode(["status" => "success", "message" => "Patient's reschedule request declined."]);
        exit;
    }

    if ($action === 'mark_completed') {
        header('Content-Type: application/json');
        $appointment_id = $_POST['appointment_id'] ?? '';

        // Time restriction check: only 1 hour before scheduled time
        $check_time = $pdo->prepare("SELECT appointment_date, appointment_time FROM appointments WHERE id = ? AND doctor_id = ?");
        $check_time->execute([$appointment_id, $doctor_id]);
        $appt_info = $check_time->fetch();
        if ($appt_info) {
            $appt_datetime = strtotime($appt_info['appointment_date'] . ' ' . $appt_info['appointment_time']);
            if ($appt_datetime - time() > 3600) {
                echo json_encode(["status" => "error", "message" => "Consultation can only start 1 hour before the scheduled time."]);
                exit;
            }
        }

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

        // 1. Insert Report (Removed redundant 'details' column)
        $stmt = $pdo->prepare("INSERT INTO reports (appointment_id, patient_id, doctor_id, diagnosis, report_details, prescription) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$appointment_id ?: null, $patient_id, $doctor_id, $diagnosis, $report_details, $prescription]);
        $report_id = $pdo->lastInsertId();

        // 2. Insert Initial Version into History
        $hist = $pdo->prepare("INSERT INTO report_history (report_id, diagnosis, report_details, prescription, version_number) VALUES (?, ?, ?, ?, 1)");
        $hist->execute([$report_id, $diagnosis, $report_details, $prescription]);

        // 3. Mark Appointment as Completed if ID exists
        if ($appointment_id) {
            $upd = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ? AND doctor_id = ?");
            $upd->execute([$appointment_id, $doctor_id]);
        }

        echo json_encode(["status" => "success", "message" => "Medical report saved and appointment completed!"]);
        exit;
    }

    if ($action === 'edit_report') {
        header('Content-Type: application/json');
        $report_id = $_POST['report_id'] ?? '';
        $diagnosis = $_POST['diagnosis'] ?? '';
        $report_details = $_POST['report_details'] ?? '';
        $prescription = $_POST['prescription'] ?? '';

        if (!$report_id || !$diagnosis || !$report_details || !$prescription) {
            echo json_encode(["status" => "error", "message" => "All fields are required."]);
            exit;
        }

        // 1. Ownership and Time Validation (24 hours)
        $chk = $pdo->prepare("SELECT created_at FROM reports WHERE id = ? AND doctor_id = ?");
        $chk->execute([$report_id, $doctor_id]);
        $report = $chk->fetch();

        if (!$report) {
            echo json_encode(["status" => "error", "message" => "Report not found or unauthorized."]);
            exit;
        }

        $created_time = strtotime($report['created_at']);
        if (time() - $created_time > 86400) { // 24 hours in seconds
            echo json_encode(["status" => "error", "message" => "Edit window closed. Reports can only be edited within 24 hours."]);
            exit;
        }

        // 2. Update Primary Report
        $upd = $pdo->prepare("UPDATE reports SET diagnosis = ?, report_details = ?, prescription = ?, details = ? WHERE id = ?");
        $upd->execute([$diagnosis, $report_details, $prescription, "Diagnosis: $diagnosis. Notes: $report_details", $report_id]);

        // 3. Get next version number
        $v_stmt = $pdo->prepare("SELECT MAX(version_number) FROM report_history WHERE report_id = ?");
        $v_stmt->execute([$report_id]);
        $next_v = ($v_stmt->fetchColumn() ?: 1) + 1;

        // 4. Insert new version into history
        $hist = $pdo->prepare("INSERT INTO report_history (report_id, diagnosis, report_details, prescription, version_number) VALUES (?, ?, ?, ?, ?)");
        $hist->execute([$report_id, $diagnosis, $report_details, $prescription, $next_v]);

        echo json_encode(["status" => "success", "message" => "Report updated successfully with version V$next_v"]);
        exit;
    }

    if ($action === 'get_report_history') {
        header('Content-Type: application/json');
        $report_id = $_GET['report_id'] ?? '';

        $query = "SELECT h.*, r.doctor_id, r.patient_id 
                  FROM report_history h 
                  JOIN reports r ON h.report_id = r.id 
                  WHERE h.report_id = ? 
                  ORDER BY h.version_number DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$report_id]);
        $history = $stmt->fetchAll();

        // Security check: Only patient or doctor involved can see history
        if (!empty($history)) {
            $is_authorized = ($_SESSION['role'] === 'doctor' && $history[0]['doctor_id'] == $doctor_id) ||
                ($_SESSION['role'] === 'patient' && $history[0]['patient_id'] == $_SESSION['user_id']);

            if (!$is_authorized) {
                echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
                exit;
            }
        }

        echo json_encode($history);
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
            $_SESSION['toast_msg'] = "Hospital is required.";
            $_SESSION['toast_type'] = "error";
            header("Location: ../../doctor/dashboard.php");
            exit;
        }

        if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/cvs/';
            $file_ext = strtolower(pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['pdf', 'doc', 'docx'];

            if (in_array($file_ext, $allowed_types)) {
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);

                $new_filename = uniqid('cv_') . '.' . $file_ext;
                if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_dir . $new_filename)) {
                    $cv_path = 'uploads/cvs/' . $new_filename;

                    $stmt = $pdo->prepare("UPDATE doctors SET hospital_id = ?, cv_path = ?, status = 'pending' WHERE id = ?");
                    $stmt->execute([$hospital_id, $cv_path, $doctor_id]);
                    $_SESSION['toast_msg'] = "Re-application submitted successfully!";
                    $_SESSION['toast_type'] = "success";
                    header("Location: ../../doctor/dashboard.php");
                    exit;
                }
            } else {
                $_SESSION['toast_msg'] = "Invalid file format. Please upload PDF or DOC.";
                $_SESSION['toast_type'] = "error";
                header("Location: ../../doctor/dashboard.php");
                exit;
            }
        }
        $_SESSION['toast_msg'] = "Please upload a valid CV file.";
        $_SESSION['toast_type'] = "error";
        header("Location: ../../doctor/dashboard.php");
        exit;
    }

    if ($action === 'search_patient') {
        header('Content-Type: application/json');
        $query_str = $_GET['q'] ?? '';

        if (strlen($query_str) < 1) {
            echo json_encode([]);
            exit;
        }

        // 1. Get current doctor's hospital_id
        $h_stmt = $pdo->prepare("SELECT hospital_id FROM doctors WHERE id = ?");
        $h_stmt->execute([$doctor_id]);
        $hosp_id = $h_stmt->fetchColumn();

        if (!$hosp_id) {
            echo json_encode([]);
            exit;
        }

        // 2. Search patients who have at least one appointment at this hospital
        $search = "%$query_str%";
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.email, u.phone
            FROM users u
            JOIN appointments a ON u.id = a.patient_id
            WHERE u.role = 'patient' 
              AND a.hospital_id = ?
              AND (u.id = ? OR u.name LIKE ?)
            LIMIT 10
        ");
        $stmt->execute([$hosp_id, $query_str, $search]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'get_all_patient_reports') {
        header('Content-Type: application/json');
        $patient_id = $_GET['patient_id'] ?? '';

        if (!$patient_id) {
            echo json_encode(["status" => "error", "message" => "Patient ID is required."]);
            exit;
        }

        // Fetch all finalized reports for this patient across ALL doctors
        $query = "
            SELECT r.*, u_doc.name as doctor_name, d.speciality, a.appointment_date
            FROM reports r
            JOIN doctors d ON r.doctor_id = d.id
            JOIN users u_doc ON d.user_id = u_doc.id
            LEFT JOIN appointments a ON r.appointment_id = a.id
            WHERE r.patient_id = ?
            ORDER BY r.created_at DESC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$patient_id]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'get_ai_insights') {
        header('Content-Type: application/json');

        // 1. Forecast Patient Volume (Next 7 Days)
        // Basic AI Logic: Average load per day of week over last 60 days
        $forecast = [];
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        $stats_query = "
            SELECT DAYNAME(appointment_date) as day_name, COUNT(*) as count
            FROM appointments
            WHERE doctor_id = ? AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            GROUP BY day_name
        ";
        $stmt_stats = $pdo->prepare($stats_query);
        $stmt_stats->execute([$doctor_id]);
        $history = $stmt_stats->fetchAll(PDO::FETCH_KEY_PAIR);

        // Fetch doctor's working days
        $doc_stmt = $pdo->prepare("SELECT available_days FROM doctors WHERE id = ?");
        $doc_stmt->execute([$doctor_id]);
        $avail_str = $doc_stmt->fetchColumn() ?: 'Mon,Tue,Wed,Thu,Fri';
        $working_days = array_map('trim', explode(',', $avail_str));

        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $dayName = date('l', strtotime($date));
            $dayAbbr = date('D', strtotime($date)); // 'Mon', 'Tue', etc.
            
            $isWorking = in_array($dayAbbr, $working_days);
            
            if (!$isWorking) {
                $predicted = 0;
            } else {
                // If history exists, use average; else default to 1 for working day
                $predicted = isset($history[$dayName]) ? ceil($history[$dayName] / 8) : 1;
            }

            $forecast[] = [
                "date" => $date,
                "day" => $dayName,
                "predicted_load" => $predicted
            ];
        }

        // 2. Patient Follow-up Suggestions
        // AI Logic: Patients with 'completed' status who mentioned 'follow' in reports but have no future appointments
        $suggestions_query = "
            SELECT r.patient_id, u.name as patient_name, r.diagnosis, r.created_at as last_visit
            FROM reports r
            JOIN users u ON r.patient_id = u.id
            WHERE r.doctor_id = ? 
              AND (r.prescription LIKE '%follow%' OR r.report_details LIKE '%follow%' OR r.diagnosis LIKE '%follow%')
              AND NOT EXISTS (
                  SELECT 1 FROM appointments a 
                  WHERE a.patient_id = r.patient_id 
                    AND a.doctor_id = ? 
                    AND a.appointment_date > DATE(r.created_at)
                    AND a.status = 'scheduled'
              )
            GROUP BY r.patient_id
            ORDER BY r.created_at DESC
            LIMIT 5
        ";
        $stmt_sug = $pdo->prepare($suggestions_query);
        $stmt_sug->execute([$doctor_id, $doctor_id]);
        $follow_ups = $stmt_sug->fetchAll();

        // 3. Sync with ai_recommendations table
        foreach ($follow_ups as $fu) {
            $check = $pdo->prepare("SELECT id FROM ai_recommendations WHERE doctor_id = ? AND patient_id = ? AND type = 'follow_up' AND status = 'pending'");
            $check->execute([$doctor_id, $fu['patient_id']]);
            if (!$check->fetch()) {
                $ins = $pdo->prepare("INSERT INTO ai_recommendations (doctor_id, patient_id, type, title, description) VALUES (?, ?, 'follow_up', ?, ?)");
                $ins->execute([
                    $doctor_id, 
                    $fu['patient_id'], 
                    "Follow-up Suggestion: " . $fu['patient_name'],
                    "Patient " . $fu['patient_name'] . " was diagnosed with '" . $fu['diagnosis'] . "' on " . date('M d', strtotime($fu['last_visit'])) . ". The report mentions a follow-up, but no future appointment is scheduled."
                ]);
            }
        }

        // 4. Fetch current pending recommendations
        $rec_stmt = $pdo->prepare("SELECT * FROM ai_recommendations WHERE doctor_id = ? AND status = 'pending' ORDER BY created_at DESC");
        $rec_stmt->execute([$doctor_id]);
        $recommendations = $rec_stmt->fetchAll();

        echo json_encode([
            "forecast" => $forecast,
            "recommendations" => $recommendations
        ]);
        exit;
    }

    if ($action === 'update_ai_recommendation') {
        header('Content-Type: application/json');
        $id = $_POST['id'] ?? '';
        $status = $_POST['status'] ?? ''; // 'accepted', 'ignored', 'dismissed'

        if (!$id || !$status) {
            echo json_encode(["status" => "error", "message" => "Missing data."]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE ai_recommendations SET status = ? WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$status, $id, $doctor_id]);
        echo json_encode(["status" => "success", "message" => "Recommendation updated."]);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(["error" => "Invalid action specified."]);
} catch (Exception $e) {
    if (!headers_sent())
        header('Content-Type: application/json');
    echo json_encode(["error" => "An error occurred: " . $e->getMessage()]);
}
?>
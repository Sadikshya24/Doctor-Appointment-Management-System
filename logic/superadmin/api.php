<?php
session_start();
header("Content-Type: application/json");
require_once '../../includes/core/db.php';
require_once '../../includes/core/logger.php';


// Security: Ensure only superadmin can access these actions
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    // 1. STATISTICS (ENHANCED)
    if ($path === "stats") {
        echo json_encode([
            "hospitals" => (int)$pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn(),
            "doctors" => (int)$pdo->query("SELECT COUNT(*) FROM doctors WHERE status='approved'")->fetchColumn(),
            "patients" => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn(),
            "appointments" => (int)$pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn()
        ]);
        exit;
    }

    // 2. DOCTORS
    if ($method === "GET" && $path === "doctors") {
        $stmt = $pdo->query("
            SELECT d.id, u.name, d.speciality, h.user_id as hospital_user_id, 
            (SELECT name FROM users WHERE id = h.user_id) as hospital_name,
            u.status, d.status as doctor_approval_status
            FROM doctors d 
            JOIN users u ON d.user_id = u.id 
            LEFT JOIN hospitals h ON d.hospital_id = h.id
        ");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($method === "POST" && preg_match('/doctors\/(\d+)\/toggle-status/', $path, $m)) {
        $id = intval($m[1]);
        $stmt = $pdo->prepare("SELECT user_id, u.status, u.name, u.email FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id=?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            $newStatus = $doc['status'] === 'active' ? 'inactive' : 'active';
            $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$newStatus, $doc['user_id']]);
            logSystemActivity($pdo, "Doctor status toggled", json_encode([
                "doctor_id" => $id,
                "doctor_name" => $doc['name'],
                "new_status" => $newStatus
            ]));

            // Email Notification
            require_once '../../includes/core/email_helper.php';
            $subject = "Account Status Update - MedScape";
            $statusText = ($newStatus === 'active') ? "ACTIVATED" : "DEACTIVATED";
            $message = "Hello " . htmlspecialchars($doc['name']) . ",<br><br>";
            $message .= "Your MedScape account status has been updated to: <strong>" . $statusText . "</strong>.<br>";
            if ($newStatus === 'active') {
                $message .= "You can now log in to the portal.<br>";
            } else {
                $message .= "Your access has been suspended by the administrator. Please contact support if you believe this is an error.<br>";
            }
            $message .= "<br>Regards,<br>MedScape Administration";
            sendEmail($doc['email'], $subject, $message);

            echo json_encode(["success" => true, "new_status" => $newStatus]);
        } else {
            echo json_encode(["status" => "error", "message" => "Doctor not found."]);
        }
        exit;
    }

    // 3. PATIENTS
    if ($method === "GET" && $path === "patients") {
        $stmt = $pdo->query("SELECT id, name, email, phone, status, created_at FROM users WHERE role='patient'");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // 4. APPOINTMENTS
    if ($method === "GET" && $path === "appointments") {
        $stmt = $pdo->query("
            SELECT a.id, a.booking_id, p.name as patient_name, d_u.name as doctor_name, 
            a.appointment_date, a.appointment_time, a.status 
            FROM appointments a 
            JOIN users p ON a.patient_id = p.id 
            JOIN doctors d ON a.doctor_id = d.id 
            JOIN users d_u ON d.user_id = d_u.id
            ORDER BY a.created_at DESC
        ");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // 5. HOSPITALS
    if ($method === "GET" && $path === "hospitals") {
        $stmt = $pdo->query("SELECT h.id, u.name, h.location, h.province, h.city, u.status, u.is_verified FROM hospitals h JOIN users u ON h.user_id = u.id");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($method === "POST" && $path === "hospitals/add") {
        $data = json_decode(file_get_contents("php://input"), true);
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $province = $data['province'] ?? '';
        $city = $data['city'] ?? '';
        $location = $data['location'] ?? 'Location Pending';
        $phone = $data['phone'] ?? '';

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'hospital')");
        $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
        $user_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO hospitals (user_id, province, city, location, description) VALUES (?, ?, ?, ?, 'Added by Admin')");
        $stmt->execute([$user_id, $province, $city, $location]);
        
        logSystemActivity($pdo, "Hospital added", json_encode(["hospital_name" => $name, "email" => $email]));
        $pdo->commit();
        echo json_encode(["success" => true]);
        exit;
    }

    if ($method === "POST" && preg_match('/hospitals\/(\d+)\/toggle-status/', $path, $m)) {
        $id = intval($m[1]);
        $stmt = $pdo->prepare("SELECT user_id, u.status, u.name, u.email FROM hospitals h JOIN users u ON h.user_id = u.id WHERE h.id=?");
        $stmt->execute([$id]);
        $hosp = $stmt->fetch();
        
        if ($hosp) {
            $newStatus = $hosp['status'] === 'active' ? 'inactive' : 'active';
            $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$newStatus, $hosp['user_id']]);
            logSystemActivity($pdo, "Hospital status toggled", json_encode([
                "hospital_id" => $id,
                "hospital_name" => $hosp['name'],
                "new_status" => $newStatus
            ]));

            // Email Notification
            require_once '../../includes/core/email_helper.php';
            $subject = "Hospital Access Update - MedScape";
            $statusText = ($newStatus === 'active') ? "ACTIVATED" : "DEACTIVATED";
            $message = "Hello " . htmlspecialchars($hosp['name']) . ",<br><br>";
            $message .= "Your MedScape Hospital account status has been updated to: <strong>" . $statusText . "</strong>.<br>";
            if ($newStatus === 'active') {
                $message .= "Your hospital portal is now fully functional.<br>";
            } else {
                $message .= "Your hospital portal access has been suspended by the system administrator.<br>";
            }
            $message .= "<br>Regards,<br>MedScape Administration";
            sendEmail($hosp['email'], $subject, $message);

            // Notification
            $notifTitle = "Account Status Updated";
            $notifMsg = "Your hospital account has been " . ($newStatus === 'active' ? 'activated' : 'deactivated') . " by the system administrator.";
            $stmt_n = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt_n->execute([$hosp['user_id'], $notifTitle, $notifMsg, $newStatus === 'active' ? 'success' : 'warning']);

            echo json_encode(["success" => true, "new_status" => $newStatus]);
        } else {
            echo json_encode(["status" => "error", "message" => "Hospital not found."]);
        }
        exit;
    }

    if ($method === "POST" && preg_match('/patients\/(\d+)\/toggle-status/', $path, $m)) {
        $id = intval($m[1]);
        $stmt = $pdo->prepare("SELECT id, status, name, email FROM users WHERE id=? AND role='patient'");
        $stmt->execute([$id]);
        $pat = $stmt->fetch();
        
        if ($pat) {
            $newStatus = $pat['status'] === 'active' ? 'inactive' : 'active';
            $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$newStatus, $id]);
            logSystemActivity($pdo, "Patient status toggled", json_encode([
                "patient_id" => $id,
                "patient_name" => $pat['name'],
                "new_status" => $newStatus
            ]));

            // Email Notification
            require_once '../../includes/core/email_helper.php';
            $subject = "Account Status Update - MedScape";
            $statusText = ($newStatus === 'active') ? "ACTIVATED" : "DEACTIVATED";
            $message = "Hello " . htmlspecialchars($pat['name']) . ",<br><br>";
            $message .= "Your MedScape account status has been updated to: <strong>" . $statusText . "</strong>.<br>";
            if ($newStatus === 'active') {
                $message .= "You can now log in and access your medical history.<br>";
            } else {
                $message .= "Your account has been deactivated. Please contact support for assistance.<br>";
            }
            $message .= "<br>Regards,<br>MedScape Administration";
            sendEmail($pat['email'], $subject, $message);

            echo json_encode(["success" => true, "new_status" => $newStatus]);
        } else {
            echo json_encode(["status" => "error", "message" => "Patient not found."]);
        }
        exit;
    }

    // 6. ANALYTICS
    if ($path === "analytics") {
        // Appointments by day (Last 7 days)
        $stmt1 = $pdo->query("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM appointments 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        
        // Specialty distribution
        $stmt2 = $pdo->query("
            SELECT speciality, COUNT(*) as count 
            FROM doctors 
            GROUP BY speciality
        ");

        // User registration trend
        $stmt3 = $pdo->query("
            SELECT DATE(created_at) as date, role, COUNT(*) as count 
            FROM users 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at), role
            ORDER BY date ASC
        ");

        echo json_encode([
            "appointments_trend" => $stmt1->fetchAll(),
            "specialty_distribution" => $stmt2->fetchAll(),
            "registration_trend" => $stmt3->fetchAll()
        ]);
        exit;
    }

    // 7. LOGS
    if ($path === "logs") {
        $stmt = $pdo->query("SELECT * FROM logs ORDER BY created_at DESC LIMIT 100");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    echo json_encode(["error" => "Invalid API route"]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}





<?php
session_start();
header("Content-Type: application/json");
require_once '../../includes/core/db.php';

// Security: Ensure only superadmin can access these actions
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    // 1. STATISTICS
    if ($path === "stats") {
        echo json_encode([
            "hospitals" => (int)$pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn(),
            "doctors" => (int)$pdo->query("SELECT COUNT(*) FROM doctors WHERE status='approved'")->fetchColumn()
        ]);
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
        
        // 1. Create User
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'hospital')");
        $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
        $user_id = $pdo->lastInsertId();
        
        // 2. Create Hospital Entry
        $stmt = $pdo->prepare("INSERT INTO hospitals (user_id, province, city, location, description) VALUES (?, ?, ?, ?, 'Added by Admin')");
        $stmt->execute([$user_id, $province, $city, $location]);
        
        $pdo->commit();
        logActivity($pdo, "Hospital added: $name", $_SESSION['name'] ?? 'Admin');
        echo json_encode(["success" => true]);
        exit;
    }

    if ($method === "POST" && preg_match('/hospitals\/(\d+)\/toggle-status/', $path, $m)) {
        $id = intval($m[1]);
        $pdo->beginTransaction();
        
        // Get user_id and current status
        $stmt = $pdo->prepare("SELECT user_id, u.status FROM hospitals h JOIN users u ON h.user_id = u.id WHERE h.id=?");
        $stmt->execute([$id]);
        $hosp = $stmt->fetch();
        
        if ($hosp) {
            $newStatus = $hosp['status'] === 'active' ? 'inactive' : 'active';
            $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$newStatus, $hosp['user_id']]);
            logActivity($pdo, "Hospital status changed to $newStatus (ID $id)", $_SESSION['name'] ?? 'Admin');
            $pdo->commit();
            echo json_encode(["success" => true, "new_status" => $newStatus]);
        } else {
            $pdo->rollBack();
            echo json_encode(["status" => "error", "message" => "Hospital not found."]);
        }
        exit;
    }



    echo json_encode(["error" => "Invalid API route"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

function logActivity($pdo, $message, $user) {
    $stmt = $pdo->prepare("INSERT INTO logs (action, user) VALUES (?, ?)");
    $stmt->execute([$message, $user]);
}
?>

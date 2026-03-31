<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$conn = new mysqli("localhost", "root", "", "doctors_app");

if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === "GET" && $path === "doctors/pending") {
    $res = $conn->query("SELECT id,name,nmcNumber,cv_file FROM doctors WHERE status='pending'");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($method === "POST" && preg_match('/doctors\/(\d+)\/verify/', $path, $m)) {
    $id = intval($m[1]);

    if ($conn->query("UPDATE doctors SET status='approved' WHERE id=$id")) {
        $conn->query("INSERT INTO logs (action,user) VALUES ('Doctor approved ID $id','Admin')");
        echo json_encode(["success"=>true]);
    } else {
        echo json_encode(["error"=>"Failed to approve"]);
    }
    exit;
}

if ($method === "POST" && preg_match('/doctors\/(\d+)\/reject/', $path, $m)) {
    $id = intval($m[1]);

    if ($conn->query("UPDATE doctors SET status='rejected' WHERE id=$id")) {
        $conn->query("INSERT INTO logs (action,user) VALUES ('Doctor rejected ID $id','Admin')");
        echo json_encode(["success"=>true]);
    } else {
        echo json_encode(["error"=>"Failed to reject"]);
    }
    exit;
}

if ($method === "GET" && $path === "doctors") {
    $name = $_GET['name'] ?? '';

    $res = $conn->query("
        SELECT id,name,nmcNumber,status 
        FROM doctors 
        WHERE name LIKE '%$name%'
    ");

    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($method === "POST" && preg_match('/doctors\/(\d+)\/update/', $path, $m)) {
    $id = intval($m[1]);

    $data = json_decode(file_get_contents("php://input"), true);
    $name = $conn->real_escape_string($data['name']);
    $nmc = $conn->real_escape_string($data['nmcNumber']);

    if ($conn->query("UPDATE doctors SET name='$name', nmcNumber='$nmc' WHERE id=$id")) {
        $conn->query("INSERT INTO logs (action,user) VALUES ('Doctor updated ID $id','Admin')");
        echo json_encode(["success"=>true]);
    } else {
        echo json_encode(["error"=>"Update failed"]);
    }
    exit;
}

if ($method === "POST" && preg_match('/doctors\/(\d+)\/delete/', $path, $m)) {
    $id = intval($m[1]);

    if ($conn->query("DELETE FROM doctors WHERE id=$id")) {
        $conn->query("INSERT INTO logs (action,user) VALUES ('Doctor deleted ID $id','Admin')");
        echo json_encode(["success"=>true]);
    } else {
        echo json_encode(["error"=>"Delete failed"]);
    }
    exit;
}

if ($path === "patients") {
    $res = $conn->query("SELECT id,name,status FROM patients");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($path === "appointments") {
    $res = $conn->query("
        SELECT 
        id,
        patientName AS patient,
        doctorName AS doctor,
        date,
        time,
        status
        FROM appointments
    ");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($method === "POST" && preg_match('/appointments\/(\d+)\/cancel/', $path, $m)) {
    $id = intval($m[1]);

    if ($conn->query("UPDATE appointments SET status='cancelled' WHERE id=$id")) {
        $conn->query("INSERT INTO logs (action,user) VALUES ('Appointment cancelled ID $id','Admin')");
        echo json_encode(["success"=>true]);
    } else {
        echo json_encode(["error"=>"Cancel failed"]);
    }
    exit;
}

if ($method === "POST" && preg_match('/appointments\/(\d+)\/reschedule/', $path, $m)) {
    $id = intval($m[1]);

    $data = json_decode(file_get_contents("php://input"), true);
    $newDate = $conn->real_escape_string($data['date']);
    $newTime = $conn->real_escape_string($data['time']);

    if ($conn->query("
        UPDATE appointments 
        SET date='$newDate', time='$newTime', status='rescheduled'
        WHERE id=$id
    ")) {
        $conn->query("INSERT INTO logs (action,user) VALUES ('Appointment rescheduled ID $id','Admin')");
        echo json_encode(["success"=>true]);
    } else {
        echo json_encode(["error"=>"Reschedule failed"]);
    }
    exit;
}

if ($path === "logs") {
    $res = $conn->query("
        SELECT action AS message, user, created_at
        FROM logs
        ORDER BY created_at DESC
    ");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($path === "stats") {
    $p = $conn->query("SELECT COUNT(*) as c FROM patients")->fetch_assoc();
    $d = $conn->query("SELECT COUNT(*) as c FROM doctors WHERE status='approved'")->fetch_assoc();
    $a = $conn->query("SELECT COUNT(*) as c FROM appointments")->fetch_assoc();

    echo json_encode([
        "patients"=>$p['c'],
        "doctors"=>$d['c'],
        "appointments"=>$a['c']
    ]);
    exit;
}

echo json_encode(["error"=>"Invalid API route"]);

$conn->close();
?>
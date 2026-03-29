<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$conn = new mysqli("localhost", "root", "", "doctors_app");

if ($conn->connect_error) {
    die(json_encode(["error" => "DB failed"]));
}

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method == "GET" && $path == "doctors/pending") {
    $res = $conn->query("SELECT id,name,nmcNumber,cv_file FROM doctors WHERE status='pending'");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($method == "POST" && preg_match('/doctors\/(\d+)\/verify/', $path, $m)) {
    $id = $m[1];
    $conn->query("UPDATE doctors SET status='approved' WHERE id=$id");
    $conn->query("INSERT INTO logs (action,user) VALUES ('Doctor approved ID $id','Admin')");
    echo json_encode(["success"=>true]);
    exit;
}

if ($method == "POST" && preg_match('/doctors\/(\d+)\/reject/', $path, $m)) {
    $id = $m[1];
    $conn->query("UPDATE doctors SET status='rejected' WHERE id=$id");
    $conn->query("INSERT INTO logs (action,user) VALUES ('Doctor rejected ID $id','Admin')");
    echo json_encode(["success"=>true]);
    exit;
}

if ($path == "patients") {
    $res = $conn->query("SELECT id,name,status FROM patients");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($path == "appointments") {
    $res = $conn->query("
        SELECT 
        patientName AS patient,
        doctorName AS doctor,
        status
        FROM appointments
    ");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($path == "logs") {
    $res = $conn->query("
        SELECT 
        action AS message,
        user,
        created_at
        FROM logs
        ORDER BY created_at DESC
    ");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

if ($path == "stats") {
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

echo json_encode(["error"=>"invalid"]);
$conn->close();
?>
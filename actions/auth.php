<?php
session_start();
require_once '../includes/db.php'; // Include database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Security check: validate the role to ensure it's one of the allowed types
    $allowed_roles = ['patient', 'doctor', 'hospital', 'superadmin'];
    $role = $_POST['role'] ?? 'patient';

    if (!in_array($role, $allowed_roles)) {
        $role = 'patient';
    }

    if ($action === 'login') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Fetch user from database based on email and role
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = ?');
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch();

        // Verify password
        if ($user && password_verify($password, $user['password_hash'])) {
            // Authentication successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            if ($role === 'superadmin') {
                header("Location: ../dashboards/dashboard.php");
            } else {
                header("Location: ../" . $role . "/dashboard.php");
            }
            exit;
        } else {
            // Authentication failed
            // In a real app, you would handle this more gracefully (e.g., passing an error back)
            echo "<script>alert('Invalid email, password, or role combination.'); window.location.href='../login.php';</script>";
            exit;
        }

    } elseif ($action === 'register') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!preg_match('/^[0-9]{10}$/', $phone)) {
            echo "<script>alert('Please enter a valid exactly 10-digit phone number.'); window.location.href='../login.php';</script>";
            exit;
        }

        // Ensure superadmin/hospital cannot be registered directly from the outward signup form 
        if ($role === 'superadmin' || $role === 'hospital') {
            die("Unauthorized role selection for registration.");
        }

        // Check if email already exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo "<script>alert('An account with this email already exists.'); window.location.href='../login.php';</script>";
            exit;
        }

        // Hash password securely
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user into database
        $stmt = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)');

        if ($stmt->execute([$name, $email, $phone, $hashed_password, $role])) {
            $user_id = $pdo->lastInsertId();

            if ($role === 'doctor') {
                $nmc_number = $_POST['nmc_number'] ?? '';
                $cv_path = '';

                // Handle File Upload securely
                if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/cvs/';
                    $file_ext = strtolower(pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION));
                    $allowed_types = ['pdf', 'doc', 'docx'];

                    if (in_array($file_ext, $allowed_types)) {
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $new_filename = uniqid('cv_') . '.' . $file_ext;
                        if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_dir . $new_filename)) {
                            $cv_path = 'uploads/cvs/' . $new_filename;
                        }
                    } else {
                        die("Invalid file type uploaded for CV.");
                    }
                }

                $doc_stmt = $pdo->prepare('INSERT INTO doctors (user_id, hospital_id, nmc_number, cv_path, status, speciality, description) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $doc_stmt->execute([
                    $user_id,
                    $_POST['hospital_id'] ?? null,
                    $nmc_number,
                    $cv_path,
                    'pending',
                    'General',
                    'Awaiting full profile setup'
                ]);
            }

            if ($role === 'hospital') {
                $hosp_stmt = $pdo->prepare('INSERT INTO hospitals (user_id, location, description) VALUES (?, ?, ?)');
                $hosp_stmt->execute([$user_id, 'Location Pending', 'Profile awaiting setup']);
            }

            // Log the user in immediately after successful registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
            $_SESSION['name'] = $name;

            if ($role === 'superadmin') {
                header("Location: ../dashboards/dashboard.php");
            } else {
                header("Location: ../" . $role . "/dashboard.php");
            }
            exit;
        } else {
            echo "<script>alert('Registration failed. Please try again.'); window.location.href='../login.php';</script>";
            exit;
        }
    }
}
?>
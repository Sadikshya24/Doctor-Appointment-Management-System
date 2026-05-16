<?php
session_start();
require_once '../../includes/core/db.php'; // Include database connection

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

        if ($user && $user['status'] === 'inactive') {
            $_SESSION['auth_error'] = 'This account has been deactivated. Please contact administration.';
            header('Location: ../../login.php');
            exit;
        }

        // Deactivated Hospital Check for Doctors
        if ($user && $user['role'] === 'doctor') {
            $stmtHosp = $pdo->prepare('
                SELECT hu.status 
                FROM doctors d 
                JOIN hospitals h ON d.hospital_id = h.id 
                JOIN users hu ON h.user_id = hu.id 
                WHERE d.user_id = ?
            ');
            $stmtHosp->execute([$user['id']]);
            $hospital_status = $stmtHosp->fetchColumn();

            if ($hospital_status === 'inactive') {
                $_SESSION['auth_error'] = 'Your affiliated hospital account is currently deactivated. Please contact your hospital administration.';
                header('Location: ../../login.php');
                exit;
            }
        }

        // Verify password
        if ($user && password_verify($password, $user['password_hash'])) {
            // Authentication successful
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['is_verified'] = $user['is_verified'] ?? 0;
            $_SESSION['last_activity'] = time(); // Fixed initial timestamp

            if ($role === 'superadmin') {
                header("Location: ../../dashboards/superadmin.php");
            } else {
                header("Location: ../../" . $role . "/dashboard.php");
            }
            exit;
        } else {
            // Authentication failed
            $_SESSION['auth_error'] = 'Invalid email, password, or role combination.';
            header('Location: ../../login.php');
            exit;
        }

    } elseif ($action === 'register') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $password = $_POST['password'] ?? '';

        // Strict Email Validation
        if (!preg_match('/^[a-zA-Z0-9]+@(gmail\.com|outlook\.com|yahoo\.com|hotmail\.com|yopmail\.com)$/', $email)) {
            $_SESSION['auth_error'] = 'Invalid email. Use letters and numbers followed by @gmail.com, @outlook.com, @yahoo.com, @hotmail.com, or @yopmail.com.';
            header('Location: ../../login.php');
            exit;
        }

        // Strict Phone Validation
        if (!preg_match('/^9[0-9]{9}$/', $phone)) {
            $_SESSION['auth_error'] = 'Invalid phone number. Must be exactly 10 digits and start with 9.';
            header('Location: ../../login.php');
            exit;
        }

        // Password Complexity Validation
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
            $_SESSION['auth_error'] = 'Password must be at least 8 characters long, include an uppercase letter, a lowercase letter, a number, and a special character.';
            header('Location: ../../login.php');
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
            $_SESSION['auth_error'] = 'An account with this email already exists.';
            header('Location: ../../login.php');
            exit;
        }

        // Hash password securely
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Generate verification token
        $verification_token = bin2hex(random_bytes(32));
        $verification_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Insert new user into database
        $stmt = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash, role, verification_token, verification_token_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)');

        if ($stmt->execute([$name, $email, $phone, $hashed_password, $role, $verification_token, $verification_expires_at])) {
            $user_id = $pdo->lastInsertId();

            // Send Verification Email
            require '../../includes/lib/PHPMailer/src/Exception.php';
            require '../../includes/lib/PHPMailer/src/PHPMailer.php';
            require '../../includes/lib/PHPMailer/src/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->Port = SMTP_PORT;

                $mail->setFrom(SYSTEM_EMAIL, SYSTEM_NAME);
                $mail->addAddress($email);

                // Check path construction to ensure correct verify link
                $base_url = "http://" . $_SERVER['HTTP_HOST'];
                $path = dirname(dirname(dirname($_SERVER['PHP_SELF'])));
                if ($path === '\\' || $path === '/') $path = '';
                $verifyLink = $base_url . $path . "/verify.php?token=" . $verification_token;

                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Email Address - MedScape';
                $mail->Body = "Hello " . htmlspecialchars($name) . ",<br><br>"
                    . "Thank you for registering at MedScape. Please click the link below to verify your email address:<br><br>"
                    . "<a href='" . htmlspecialchars($verifyLink) . "'>" . htmlspecialchars($verifyLink) . "</a><br><br>"
                    . "This link will expire in 24 hours.";
                $mail->AltBody = "Hello " . $name . ",\n\n"
                    . "Please click the following link to verify your email address:\n"
                    . $verifyLink . "\n\n"
                    . "This link will expire in 24 hours.";

                $mail->send();
            } catch (\Exception $e) {
                // Email sending failed, but user is registered.
                // We can optionally log this error.
            }
            $user_id = $pdo->lastInsertId();

            if ($role === 'doctor') {
                $nmc_number = $_POST['nmc_number'] ?? '';
                $hospital_id = $_POST['hospital_id'] ?? '';
                $cv_path = '';

                if (!preg_match('/^[0-9]{3,6}$/', $nmc_number)) {
                    $_SESSION['auth_error'] = 'Invalid NMC Number. It must be numeric and 3-6 digits only.';
                    header('Location: ../../login.php');
                    exit;
                }

                // Check if NMC number clashes with an already approved doctor
                $stmt_nmc = $pdo->prepare("SELECT id FROM doctors WHERE nmc_number = ? AND status = 'approved'");
                $stmt_nmc->execute([$nmc_number]);
                if ($stmt_nmc->fetch()) {
                    $_SESSION['auth_error'] = 'This NMC number is already registered to an approved doctor.';
                    header('Location: ../../login.php');
                    exit;
                }

                if (empty($hospital_id)) {
                    $_SESSION['auth_error'] = 'Doctors must strictly select an affiliated hospital during signup.';
                    header('Location: ../../login.php');
                    exit;
                }

                // Handle File Upload securely
                if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../../uploads/cvs/';
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
            session_regenerate_id(true); // New ID for new session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
            $_SESSION['name'] = $name;
            $_SESSION['is_verified'] = 0;
            $_SESSION['last_activity'] = time(); // New timestamp

            if ($role === 'superadmin') {
                header("Location: ../../dashboards/superadmin.php");
            } else {
                header("Location: ../../" . $role . "/dashboard.php");
            }
            exit;
        } else {
            $_SESSION['auth_error'] = 'Registration failed. Please try again.';
            header('Location: ../../login.php');
            exit;
        }
    }
}
?>
<?php
session_start();
require_once '../includes/db.php';

// Auth check
$allowed_roles = ['patient', 'doctor'];
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role']), $allowed_roles)) {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];
$role = strtolower($_SESSION['role']);
$report_id = $_GET['id'] ?? 0;

// Determine ownership condition based on role
if ($role === 'patient') {
    $auth_condition = "r.patient_id = ?";
    $auth_id = $user_id;
} else if ($role === 'doctor') {
    $stmtDoc = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmtDoc->execute([$user_id]);
    $auth_id = $stmtDoc->fetchColumn();
    $auth_condition = "r.doctor_id = ?";
    
    if (!$auth_id) die("Doctor profile not found.");
}

// Fetch detailed report with doctor, hospital, and appointment info
$stmt = $pdo->prepare("
    SELECT r.*, u_doc.name as doctor_name, d.speciality, d.qualification, d.nmc_number,
           hu.name as hospital_name, h.location as hospital_location,
           u_pat.name as patient_name, u_pat.email as patient_email, u_pat.phone as patient_phone,
           a.booking_id, a.appointment_date
    FROM reports r
    JOIN doctors d ON r.doctor_id = d.id
    JOIN users u_doc ON d.user_id = u_doc.id
    LEFT JOIN hospitals h ON d.hospital_id = h.id
    LEFT JOIN users hu ON h.user_id = hu.id
    JOIN users u_pat ON r.patient_id = u_pat.id
    LEFT JOIN appointments a ON r.appointment_id = a.id
    WHERE r.id = ? AND {$auth_condition}
");
$stmt->execute([$report_id, $auth_id]);
$report = $stmt->fetch();

if (!$report) {
    die("Report not found or access denied.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Medical Report - <?php echo htmlspecialchars($report['booking_id'] ?? 'N/A'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e40af;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
        }
        body { font-family: 'Inter', sans-serif; color: var(--text-main); line-height: 1.6; margin: 0; padding: 40px; background: #fff; }
        .report-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--primary); padding-bottom: 20px; margin-bottom: 30px; }
        .brand h1 { margin: 0; color: var(--primary); font-family: 'Outfit', sans-serif; font-size: 2rem; }
        .brand p { margin: 5px 0 0 0; color: var(--text-muted); font-size: 0.9rem; }
        .hospital-info { text-align: right; }
        .hospital-info h2 { margin: 0; font-size: 1.2rem; }
        .hospital-info p { margin: 2px 0; color: var(--text-muted); font-size: 0.85rem; }

        .report-meta { display: flex; justify-content: space-between; margin-bottom: 40px; background: #f9fafb; padding: 20px; border-radius: 8px; border: 1px solid var(--border); }
        .meta-col h4 { margin: 0 0 10px 0; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
        .meta-col p { margin: 3px 0; font-weight: 600; }

        .section { margin-bottom: 30px; }
        .section-title { font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; }
        
        .diagnosis-box { padding: 15px; background: #eff6ff; border-radius: 6px; border-left: 5px solid var(--primary); font-style: italic; }
        .prescription-box { padding: 20px; border: 1px dashed var(--primary); border-radius: 8px; background: #fff; white-space: pre-wrap; font-family: 'Courier New', Courier, monospace; font-size: 1.05rem; }

        .report-footer { margin-top: 60px; padding-top: 20px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-end; }
        .signature-line { width: 250px; border-top: 1px solid #333; text-align: center; padding-top: 5px; font-size: 0.85rem; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            button { display: none; }
        }
    </style>
</head>
<body>
    <div style="max-width: 800px; margin: 0 auto;">
        <div class="no-print" style="text-align: right; margin-bottom: 20px;">
            <button onclick="window.print()" style="background:var(--primary); color:white; border:none; padding:10px 20px; border-radius:5px; cursor:pointer; font-weight:600;">Print / Save as PDF</button>
        </div>

        <header class="report-header">
            <div class="brand">
                <h1>MedScape</h1>
                <p>Electronic Health Record</p>
            </div>
            <div class="hospital-info">
                <h2><?php echo htmlspecialchars($report['hospital_name'] ?? 'General Clinic'); ?></h2>
                <p><?php echo htmlspecialchars($report['hospital_location'] ?? 'Location N/A'); ?></p>
                <p>Date: <?php echo date('F d, Y', strtotime($report['created_at'])); ?></p>
            </div>
        </header>

        <div class="report-meta">
            <div class="meta-col">
                <h4>Patient Information</h4>
                <p><?php echo htmlspecialchars($report['patient_name']); ?></p>
                <p><?php echo htmlspecialchars($report['patient_phone']); ?></p>
                <p><?php echo htmlspecialchars($report['patient_email']); ?></p>
            </div>
            <div class="meta-col">
                <h4>Medical Professional</h4>
                <p>Dr. <?php echo htmlspecialchars($report['doctor_name']); ?></p>
                <p><?php echo htmlspecialchars($report['speciality']); ?></p>
                <p>Reg: <?php echo htmlspecialchars($report['nmc_number'] ?? 'N/A'); ?></p>
            </div>
            <div class="meta-col" style="text-align: right;">
                <h4>Booking reference</h4>
                <p style="color: var(--primary);"><?php echo htmlspecialchars($report['booking_id'] ?? 'N/A'); ?></p>
                <p style="font-size: 0.8rem; color: var(--text-muted);">Status: Finalized</p>
            </div>
        </div>

        <div class="section">
            <div class="section-title">DIAGNOSIS</div>
            <div class="diagnosis-box">
                <?php echo nl2br(htmlspecialchars($report['diagnosis'])); ?>
            </div>
        </div>

        <div class="section">
            <div class="section-title">CLINICAL FINDINGS & NOTES</div>
            <div style="padding-left: 5px;">
                <?php echo nl2br(htmlspecialchars($report['report_details'])); ?>
            </div>
        </div>

        <div class="section">
            <div class="section-title">PRESCRIPTION / TREATMENT PLAN</div>
            <div class="prescription-box"><?php echo htmlspecialchars($report['prescription']); ?></div>
        </div>

        <footer class="report-footer">
            <div style="font-size: 0.8rem; color: var(--text-muted);">
                Digitally generated by MedScape portal.<br>
                For verification, scan booking ID.
            </div>
            <div class="signature">
                <div class="signature-line">
                    <p style="margin: 0;">Authorized Signature / Seal</p>
                    <p style="margin: 0; font-size: 0.75rem; color: var(--text-muted);">Dr. <?php echo htmlspecialchars($report['doctor_name']); ?></p>
                </div>
            </div>
        </footer>
    </div>

    <script>
        // Auto trigger print after a small delay for styling to settle
        window.onload = () => {
            setTimeout(() => {
                // window.print();
            }, 500);
        };
    </script>
</body>
</html>

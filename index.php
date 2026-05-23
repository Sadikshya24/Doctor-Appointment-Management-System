<?php
require_once 'includes/core/db.php';

// Fetch stats
$stats = [
    'doctors' => 0,
    'hospitals' => 0,
    'patients' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM doctors WHERE status = 'approved'");
    $stats['doctors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hospitals");
    $stats['hospitals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'patient'");
    $stats['patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    // Suppress errors for landing page
}

$pageTitle = 'MedScape - Home';
require_once 'includes/layout/header.php';
?>

<link rel="stylesheet" href="assets/css/common/landing.css">


<div class="landing-hero">
    <div class="top-nav">
        <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login / Register</a>
    </div>


    <h1>Welcome to MedScape</h1>
    <p>Your premier platform for booking doctor appointments. Connect with top specialists, check availability,
        and easily schedule your visits with our modern, secure system.</p>
    <a href="login.php" class="btn-hero btn-with-arrow">Get Started Today <i class="fas fa-chevron-right nav-arrow"></i></a>
</div>

<div class="stats-container">
    <div class="stat-card-landing">
        <i class="fas fa-user-md"></i>
        <h3><?php echo htmlspecialchars($stats['doctors']); ?>+</h3>
        <p>Verified Doctors</p>
    </div>
    <div class="stat-card-landing">
        <i class="fas fa-hospital"></i>
        <h3><?php echo htmlspecialchars($stats['hospitals']); ?>+</h3>
        <p>Partner Hospitals</p>
    </div>
    <div class="stat-card-landing">
        <i class="fas fa-users"></i>
        <h3><?php echo htmlspecialchars($stats['patients']); ?>+</h3>
        <p>Registered Patients</p>
    </div>
</div>

<div class="features-section">
    <h2>Our Features</h2>
    <div class="features-grid">
        <div class="feature-item">
            <div class="feature-icon-box">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h3>Easy Appointments</h3>
            <p>Book, reschedule, or cancel appointments with top specialists seamlessly from your dashboard.</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon-box">
                <i class="fas fa-file-medical"></i>
            </div>
            <h3>Secure Records</h3>
            <p>Your medical history and consultation reports are stored securely and accessible anytime, anywhere.</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon-box">
                <i class="fas fa-user-md"></i>
            </div>
            <h3>Verified Specialists</h3>
            <p>Connect with highly qualified, NMC-verified doctors and partnered hospitals you can trust.</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon-box">
                <i class="fas fa-laptop-medical"></i>
            </div>
            <h3>Role-Based Portals</h3>
            <p>Tailored dashboards for patients, doctors, and hospitals to manage their unique workflows efficiently.
            </p>
        </div>
    </div>
</div>


<?php require_once 'includes/layout/footer.php'; ?>
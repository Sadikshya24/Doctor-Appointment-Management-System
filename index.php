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

<link rel="stylesheet" href="assets/css/dashboard_wrapper.css">
<style>
    /* Landing Page Harmonization */
    body {
        background-color: var(--bg-body);
        margin: 0;
        font-family: 'Outfit', sans-serif;
        color: var(--text-main);
    }

    .landing-hero {
        position: relative;
        text-align: center;
        padding: 120px 20px 100px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
        color: white;
        border-radius: 0 0 40px 40px;
        margin-bottom: 60px;
        box-shadow: var(--shadow-lg);
        overflow: hidden;
    }

    .landing-hero::after {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 60%);
        opacity: 0.6;
        pointer-events: none;
    }

    .landing-hero h1 {
        font-size: 3.8rem;
        margin-bottom: 24px;
        font-weight: 800;
        letter-spacing: -1.5px;
        position: relative;
        z-index: 2;
    }

    .landing-hero p {
        font-size: 1.3rem;
        max-width: 700px;
        margin: 0 auto 48px;
        opacity: 0.9;
        line-height: 1.7;
        position: relative;
        z-index: 2;
    }

    .btn-hero {
        display: inline-block;
        background-color: var(--text-white);
        color: var(--primary-color);
        padding: 18px 50px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 1.2rem;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        position: relative;
        z-index: 2;
    }

    .btn-hero:hover {
        transform: translateY(-4px) scale(1.02);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        color: var(--primary-hover);
    }

    .top-nav {
        position: absolute;
        top: 30px;
        right: 40px;
        z-index: 10;
    }

    .btn-login {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        color: white;
        padding: 12px 28px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        border: 1.5px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-login:hover {
        background: white;
        color: var(--primary-color);
        transform: translateY(-2px);
    }

    .stats-container {
        display: flex;
        justify-content: center;
        gap: 32px;
        padding: 0 20px 80px;
        flex-wrap: wrap;
        max-width: 1240px;
        margin: 0 auto;
    }

    .stat-card {
        background: var(--bg-card);
        border-radius: 24px;
        padding: 48px 32px;
        text-align: center;
        flex: 1;
        min-width: 280px;
        box-shadow: var(--shadow-md);
        transition: all 0.4s ease;
        border: 1px solid var(--border-color);
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-12px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }

    .stat-card i {
        font-size: 3.5rem;
        background: -webkit-linear-gradient(45deg, var(--primary-color), #818cf8);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 24px;
        display: inline-block;
    }

    .stat-card h3 {
        font-size: 3.2rem;
        color: var(--text-main);
        margin: 0 0 8px;
        font-weight: 800;
        letter-spacing: -1px;
    }

    .stat-card p {
        color: var(--text-muted);
        font-weight: 600;
        font-size: 1.1rem;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin: 0;
    }

    .features-section {
        padding: 100px 24px;
        background-color: var(--bg-body);
        text-align: center;
    }

    .features-section h2 {
        font-size: 2.8rem;
        color: var(--text-main);
        margin-bottom: 64px;
        font-weight: 800;
        position: relative;
        display: inline-block;
    }

    .features-section h2::after {
        content: '';
        position: absolute;
        bottom: -15px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 6px;
        background: linear-gradient(90deg, var(--primary-color), #818cf8);
        border-radius: 3px;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 32px;
        max-width: 1240px;
        margin: 0 auto;
    }

    .feature-item {
        padding: 40px;
        border-radius: 24px;
        background-color: var(--bg-card);
        transition: all 0.3s ease;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
        text-align: left;
    }

    .feature-item:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary-color);
    }

    .feature-icon {
        width: 64px;
        height: 64px;
        background-color: var(--primary-light);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 24px;
        color: var(--primary-color);
        font-size: 1.8rem;
    }

    .feature-item h3 {
        font-size: 1.5rem;
        color: var(--text-main);
        margin-bottom: 16px;
        font-weight: 700;
    }

    .feature-item p {
        color: var(--text-muted);
        line-height: 1.7;
        font-size: 1.1rem;
        margin: 0;
    }

    @media (max-width: 768px) {
        .landing-hero h1 {
            font-size: 2.8rem;
        }

        .landing-hero p {
            font-size: 1.1rem;
        }

        .top-nav {
            right: 20px;
            top: 20px;
        }

        .stat-card {
            padding: 40px 24px;
        }

        .stat-card h3 {
            font-size: 2.8rem;
        }
    }
</style>

<div class="landing-hero">
    <div class="top-nav">
        <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login / Register</a>
    </div>


    <h1>Welcome to MedScape</h1>
    <p>Your premier platform for booking doctor appointments. Connect with top specialists, check availability,
        and easily schedule your visits with our modern, secure system.</p>
    <a href="login.php" class="btn-hero">Get Started Today</a>
</div>

<div class="stats-container">
    <div class="stat-card">
        <i class="fas fa-user-md"></i>
        <h3><?php echo htmlspecialchars($stats['doctors']); ?>+</h3>
        <p>Verified Doctors</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-hospital"></i>
        <h3><?php echo htmlspecialchars($stats['hospitals']); ?>+</h3>
        <p>Partner Hospitals</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-users"></i>
        <h3><?php echo htmlspecialchars($stats['patients']); ?>+</h3>
        <p>Registered Patients</p>
    </div>
</div>

<div class="features-section">
    <h2>Our Features</h2>
    <div class="features-grid">
        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h3>Easy Appointments</h3>
            <p>Book, reschedule, or cancel appointments with top specialists seamlessly from your dashboard.</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-file-medical"></i>
            </div>
            <h3>Secure Records</h3>
            <p>Your medical history and consultation reports are stored securely and accessible anytime, anywhere.</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-user-md"></i>
            </div>
            <h3>Verified Specialists</h3>
            <p>Connect with highly qualified, NMC-verified doctors and partnered hospitals you can trust.</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon">
                <i class="fas fa-laptop-medical"></i>
            </div>
            <h3>Role-Based Portals</h3>
            <p>Tailored dashboards for patients, doctors, and hospitals to manage their unique workflows efficiently.
            </p>
        </div>
    </div>
</div>

<?php require_once 'includes/layout/footer.php'; ?>
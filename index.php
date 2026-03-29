<?php
require_once 'includes/db.php';

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
require_once 'includes/header.php';
?>

<style>
    /* Premium Landing Page Styles */
    body {
        background-color: var(--bg-body);
        margin: 0;
        font-family: 'Outfit', sans-serif;
        color: var(--text-main);
    }

    .landing-hero {
        position: relative;
        text-align: center;
        padding: 100px 20px 80px;
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        color: white;
        border-radius: 0 0 40px 40px;
        margin-bottom: 50px;
        box-shadow: 0 15px 40px rgba(59, 130, 246, 0.15);
        overflow: hidden;
    }

    .landing-hero::after {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 60%);
        opacity: 0.5;
        pointer-events: none;
    }

    .landing-hero h1 {
        font-size: 3.5rem;
        margin-bottom: 20px;
        font-weight: 700;
        letter-spacing: -1px;
        position: relative;
        z-index: 2;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .landing-hero p {
        font-size: 1.25rem;
        max-width: 650px;
        margin: 0 auto 40px;
        opacity: 0.95;
        line-height: 1.6;
        position: relative;
        z-index: 2;
    }

    .btn-hero {
        display: inline-block;
        background-color: white;
        color: #1e3a8a;
        padding: 16px 45px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 1.15rem;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        position: relative;
        z-index: 2;
    }

    .btn-hero:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        background-color: #f8fafc;
    }

    .top-nav {
        position: absolute;
        top: 25px;
        right: 40px;
        z-index: 10;
    }

    .btn-login {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        color: white;
        padding: 10px 25px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        border: 1px solid rgba(255, 255, 255, 0.3);
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-login:hover {
        background: white;
        color: #1e3a8a;
    }

    .stats-container {
        display: flex;
        justify-content: center;
        gap: 30px;
        padding: 0 20px 80px;
        flex-wrap: wrap;
        max-width: 1200px;
        margin: 0 auto;
    }

    .stat-card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 50px 30px;
        text-align: center;
        flex: 1;
        min-width: 250px;
        box-shadow: var(--shadow);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid var(--border-color);
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, #3b82f6, #60a5fa);
    }

    .stat-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
    }

    .stat-card i {
        font-size: 3.5rem;
        background: -webkit-linear-gradient(45deg, #1e3a8a, #3b82f6);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 25px;
        display: inline-block;
    }

    .stat-card h3 {
        font-size: 3.5rem;
        color: var(--text-main);
        margin-bottom: 5px;
        font-weight: 800;
        letter-spacing: -1px;
    }

    .stat-card p {
        color: var(--text-muted);
        font-weight: 600;
        font-size: 1.1rem;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }

    .features-section {
        padding: 80px 20px;
        background-color: var(--bg-body);
        text-align: center;
    }

    .features-section h2 {
        font-size: 2.5rem;
        color: var(--text-main);
        margin-bottom: 50px;
        font-weight: 700;
        position: relative;
        display: inline-block;
    }

    .features-section h2::after {
        content: '';
        position: absolute;
        bottom: -15px;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 4px;
        background-color: #3b82f6;
        border-radius: 2px;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 40px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .feature-item {
        padding: 30px;
        border-radius: 16px;
        background-color: var(--bg-card);
        transition: transform 0.3s, box-shadow 0.3s;
        border: 1px solid var(--border-color);
    }

    .feature-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.05);
        border-color: #bfdbfe;
    }

    .feature-icon {
        width: 70px;
        height: 70px;
        background-color: var(--input-bg);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        color: var(--primary-color);
        font-size: 2rem;
    }

    .feature-item h3 {
        font-size: 1.5rem;
        color: var(--text-main);
        margin-bottom: 15px;
        font-weight: 700;
    }

    .feature-item p {
        color: var(--text-muted);
        line-height: 1.6;
        font-size: 1.05rem;
    }

    @media (max-width: 768px) {
        .landing-hero h1 {
            font-size: 2.5rem;
        }

        .top-nav {
            right: 20px;
            top: 20px;
        }

        .stat-card {
            padding: 40px 20px;
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
        <p>Happy Patients</p>
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

<?php require_once 'includes/footer.php'; ?>
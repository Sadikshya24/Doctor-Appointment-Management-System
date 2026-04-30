<?php
session_start();
require_once '../includes/core/session_check.php';
$isSubFolder = true;
$pageTitle = "Admin Dashboard | MedScape";
require_once '../includes/core/db.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../login.php");
    exit;
}

include '../includes/layout/header.php';
?>

<link rel="stylesheet" href="../assets/css/superadmin/superadmin.css">
<link rel="stylesheet" href="../assets/css/common/dashboard_wrapper.css">

<div class="admin-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-hand-holding-medical logo-icon"></i>
            <h2>MedScape <span>Admin</span></h2>
        </div>
        <ul class="sidebar-menu">
            <li data-page="dashboard" class="active">
                <i class="fas fa-chart-line"></i> <span>Dashboard</span>
            </li>
            <li data-page="hospitals">
                <i class="fas fa-hospital"></i> <span>Hospitals</span>
            </li>
        </ul>
        <div class="sidebar-footer">
            <a href="../logic/auth/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search anything...">
            </div>
            <div class="user-profile">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <img src="../<?php echo $_SESSION['profile_photo'] ?? 'assets/img/default.jpeg'; ?>" alt="Admin"
                    class="avatar">
            </div>
        </header>

        <!-- DASHBOARD PAGE -->
        <section id="dashboard" class="page-section active">
            <div class="welcome-banner">
                <h1>Welcome back, Admin!</h1>
                <p>Here's what's happening with MedScape today.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon p-bg"><i class="fas fa-hospital"></i></div>
                    <div class="stat-info">
                        <h3>Total Hospitals</h3>
                        <h2 id="hospitalCount">0</h2>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon d-bg"><i class="fas fa-user-md"></i></div>
                    <div class="stat-info">
                        <h3>Total Doctors</h3>
                        <h2 id="doctorCount">0</h2>
                    </div>
                </div>
            </div>
        </section>


        <!-- HOSPITALS PAGE -->
        <section id="hospitals" class="page-section">
            <div class="section-header">
                <h2>Hospital Management</h2>
            </div>
            <div class="content-grid" style="grid-template-columns: 1fr 1.5fr;">
                <div class="grid-box">
                    <h3>Add New Hospital</h3>
                    <form id="addHospitalForm">
                        <div class="input-field-modern">
                            <label>Hospital Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="input-field-modern">
                            <label>Email Address</label>
                            <input type="email" name="email"
                                pattern="[a-zA-Z0-9]+@(gmail\.com|outlook\.com|yahoo\.com|hotmail\.com|yopmail\.com)"
                                title="Only Alphanumeric @gmail/outlook/yahoo/hotmail/yopmail.com allowed" required>
                        </div>
                        <div class="input-field-modern">
                            <label>Password</label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="password" name="password" required style="width: 100%; padding-right: 40px;">
                                <i class="fas fa-eye toggle-password" style="position: absolute; right: 15px; cursor: pointer; color: var(--admin-text-light);" title="Toggle password visibility"></i>
                            </div>
                        </div>
                        <div class="input-field-modern">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" pattern="9[0-9]{9}" title="10 digits starting with 9"
                                required>
                        </div>
                        <div style="display:flex; gap:10px; margin-bottom:15px;">
                            <div class="input-field-modern" style="flex:1; margin-bottom:0;">
                                <label>Province</label>
                                <select name="province" id="sa-province" required onchange="updateSACities()" style="width:100%;">
                                    <option value="" disabled selected>Select Province</option>
                                    <option value="Koshi">Koshi</option>
                                    <option value="Madhesh">Madhesh</option>
                                    <option value="Bagmati">Bagmati</option>
                                    <option value="Gandaki">Gandaki</option>
                                    <option value="Lumbini">Lumbini</option>
                                    <option value="Karnali">Karnali</option>
                                    <option value="Sudurpaschim">Sudurpaschim</option>
                                </select>
                            </div>
                            <div class="input-field-modern" style="flex:1; margin-bottom:0;">
                                <label>City</label>
                                <select name="city" id="sa-city" required style="width:100%;">
                                    <option value="" disabled selected>Select City</option>
                                </select>
                            </div>
                        </div>
                        <div class="input-field-modern">
                            <label>Location / Street</label>
                            <input type="text" name="location" placeholder="e.g. Near Bus Stand" required>
                        </div>
                        <button type="submit" class="btn-primary-modern">Register Hospital</button>
                    </form>
                </div>
                <div class="grid-box">
                    <h3>Existing Hospitals</h3>
                    <div class="table-container shadow-none">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="hospitalTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
        </section>
    </main>
</div>

<script src="../assets/js/superadmin/admin_dashboard.js"></script>

<?php include '../includes/layout/footer.php'; ?>
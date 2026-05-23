<?php
// Ensure this is only accessed via full dashboard include or has PDO
if (!isset($pdo)) {
    require_once 'db.php';
}

// Check doctor status
$stmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctorInfo = $stmt->fetch();

if (!$doctorInfo || $doctorInfo['status'] === 'pending') {
    echo "
    <div class='pd-container' style='text-align: center; padding: 60px 20px;'>
        <h2 style='font-size: 2rem; margin-bottom: 16px;'>Account Pending Approval</h2>
        <p style='color: #64748b; font-size: 1.1rem; line-height: 1.6; max-width: 600px; margin: 0 auto;'>
            Your registration is currently under review. Our administration team is verifying your credentials (<b>NMC: " . htmlspecialchars($doctorInfo['nmc_number'] ?? 'N/A') . "</b>). <br><br>
            Once approved, your dashboard will automatically become available.
        </p>
    </div>
    ";
    return;
}

if ($doctorInfo['status'] === 'rejected') {
    $stmtHosp = $pdo->query("SELECT h.id, u.name, h.location FROM hospitals h JOIN users u ON h.user_id = u.id");
    $hospitalsList = $stmtHosp->fetchAll(PDO::FETCH_ASSOC);

    echo "
    <div class='dashboard-container' style='text-align: center; margin-top: 50px;'>
        <h2 style='color:var(--text-main);'>Application Rejected</h2>
        <p style='color:#666; font-size:1.1em; line-height:1.6;'>
            Unfortunately, your application was not approved by the previously selected hospital. <br>
            Please select a hospital and upload an updated CV to re-apply.
        </p>
        
        <form action='../logic/doctor/actions.php?action=reapply' method='POST' enctype='multipart/form-data' style='max-width:400px; margin: 30px auto; text-align:left; background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #ddd;'>
            <div style='margin-bottom:15px;'>
                <label style='display:block; margin-bottom:5px; font-weight:bold; color:#444;'>Select Hospital</label>
                <select name='hospital_id' required style='width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;'>
                    <option value='' disabled selected>Select Hospital</option>";
    foreach ($hospitalsList as $h) {
        echo "<option value='{$h['id']}'>" . htmlspecialchars($h['name'] . ' - ' . $h['location']) . "</option>";
    }
    echo "      </select>
            </div>
            <div style='margin-bottom:20px;'>
                <label style='display:block; margin-bottom:5px; font-weight:bold; color:#444;'>Upload Updated CV (PDF/DOC)</label>
                <input type='file' name='cv_file' accept='.pdf,.doc,.docx' required style='width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; background:#fff;'>
            </div>
            <button type='submit' style='width:100%; padding:12px; background:transparent; color:#28a745; border:1px solid #28a745; border-radius:4px; font-size:1.1em; cursor:pointer;'>Submit Re-Application</button>
        </form>
    </div>
    ";
    return;
}

$docId = $doctorInfo['id'];

// Update appointment list query to include report creation time for edit window check
$check_reports = $pdo->prepare("
    SELECT a.id, r.created_at as report_created_at 
    FROM appointments a 
    JOIN reports r ON a.id = r.appointment_id 
    WHERE a.doctor_id = ?
");
$check_reports->execute([$docId]);
$report_times = $check_reports->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<script>
    window.reportCreatedTimes = <?php echo json_encode($report_times); ?>;
</script>

<link rel="stylesheet" href="../assets/css/doctor/doctor.css">

<div class="dd-container">

    <!-- Overview Tab -->
    <div id="tab-overview" class="dd-content active">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Appointments</h3>
                    <h2 id="stat-total">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Appointments Today</h3>
                    <h2 id="stat-today">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Completed Visits</h3>
                    <h2 id="stat-completed">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Appointments Left</h3>
                    <h2 id="stat-left">0</h2>
                </div>
            </div>
        </div>

        <div style="margin-top: 35px;">
            <div
                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
                <h3 style="margin:0; color: #1e293b;">Today's Schedule</h3>
                <span id="today-date-display" style="font-size: 0.9em; color: #64748b; font-weight: 500;"></span>
            </div>
            <div id="todays-appointments-list" class="dd-grid">
                <!-- Loaded via JS -->
            </div>
        </div>
    </div>

    <!-- AI Insights Tab -->
    <div id="tab-ai_insights" class="dd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'overview')">Back to Overview</a>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
            <div>
                <h2 style="margin:0;">AI Clinical Analytics</h2>
                <p style="color: #64748b; margin-top: 5px;">Predictive forecasting and patient follow-up
                    recommendations.</p>
            </div>
            <!-- Refresh Data Button Removed -->
        </div>

        <div class="dd-grid" style="grid-template-columns: 1.5fr 1fr; gap: 25px;">
            <!-- Forecasting Section -->
            <div class="dd-card" style="padding: 25px;">
                <h4 style="margin-bottom: 20px;">7-Day Patient Load Forecast</h4>
                <div id="forecast-container"
                    style="height: 250px; display: flex; align-items: flex-end; justify-content: space-between; padding-top: 20px; border-bottom: 2px solid #e2e8f0; margin-bottom: 15px;">
                    <!-- Loaded via JS -->
                </div>
                <div id="forecast-labels"
                    style="display: flex; justify-content: space-between; color: #64748b; font-size: 0.8em; font-weight: 600;">
                    <!-- Loaded via JS -->
                </div>
                <div
                    style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-radius: 8px; border-left: 4px solid #0ea5e9;">
                    <p style="font-size: 0.9em; color: #0c4a6e; margin: 0;">
                        <b>AI Insight:</b> Forecast is based on your historical
                        booking trends over the last 60 days.
                    </p>
                </div>
            </div>

            <!-- Recommendations Section -->
            <div class="dd-card" style="padding: 25px;">
                <h4 style="margin-bottom: 20px;">Clinical Suggestions</h4>
                <div id="ai-recommendations-list" style="display: flex; flex-direction: column; gap: 15px;">
                    <!-- Loaded via JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Active Bookings Tab -->
    <div id="tab-bookings" class="dd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'overview')">Back to Overview</a>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0;">Active Patient Bookings</h2>
        </div>
        <div id="bookings-list" class="dd-grid">
            <!-- Loaded via JS -->
        </div>
    </div>

    <!-- Consulted Tab -->
    <div id="tab-consulted" class="dd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'overview')">Back to Overview</a>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0;">Consulted Patients History</h2>
        </div>
        <div id="consulted-list" class="dd-grid">
            <!-- Loaded via JS -->
        </div>
    </div>

    <!-- Settings Tab -->
    <div id="tab-settings" class="dd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'overview')">Back to Overview</a>
        <h2>Profile & Availability</h2>

        <div class="dd-card" style="margin-bottom: 25px;">
            <h4>Personal Details</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">Keep your contact information updated.</p>
            <?php
            $stmtPhone = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
            $stmtPhone->execute([$_SESSION['user_id']]);
            $currentPhone = $stmtPhone->fetchColumn() ?: '';
            ?>
            <form action="../logic/common/update_profile.php" method="POST">
                <div class="dd-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="dd-input"
                        value="<?php echo htmlspecialchars($_SESSION['name']); ?>" required>
                </div>
                <div class="dd-group">
                    <label>Email Address
                        <?php if (isset($_SESSION['is_verified']) && $_SESSION['is_verified'] == 1): ?>
                            <span class="badge" style="background: #2ecc71; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; margin-left: 5px; vertical-align: middle;">Verified</span>
                        <?php else: ?>
                            <span class="badge" style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; margin-left: 5px; vertical-align: middle;">Unverified</span>
                        <?php endif; ?>
                    </label>
                    <input type="email" name="email" class="dd-input"
                        value="<?php echo htmlspecialchars($_SESSION['email']); ?>"
                        pattern="[a-zA-Z0-9]+@(gmail\.com|outlook\.com|yahoo\.com|hotmail\.com|yopmail\.com)"
                        title="Alphanumeric username + @gmail/outlook/yahoo/hotmail/yopmail.com" required>
                </div>
                <div class="dd-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="dd-input"
<<<<<<< HEAD
                        value="<?php echo htmlspecialchars($currentPhone); ?>" pattern="9[0-9]{9}"
                        title="10 digits starting with 9">
=======
                        value="<?php echo htmlspecialchars($currentPhone); ?>" pattern="(97|98)[0-9]{8}"
                        title="Please enter a valid Nepali phone number.">
>>>>>>> mallika
                </div>
                <button type="submit" class="dd-btn" style="width:100%;">Save Personal Details</button>
            </form>
        </div>
        <div class="dd-card" style="margin-bottom: 25px;">
            <h4>Profile Picture</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">Upload a new image to personalize your account.</p>
            <form action="../logic/common/update_photo.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="profile_photo" accept="image/*" class="dd-input" required
                    style="margin-bottom:15px; padding: 10px; background: var(--input-bg);">
                <button type="submit" class="dd-btn" style="width:100%;">Upload Photo</button>
            </form>
        </div>

        <form class="dd-form" onsubmit="updateProfile(event)">
            <div class="dd-grid" style="grid-template-columns: 1fr 1fr; gap:15px; margin-bottom: 15px;">
                <div class="dd-group">
                    <label>Qualification (e.g. MBBS, MD)</label>
                    <input type="text" id="prof-qual" class="dd-input"
                        value="<?php echo htmlspecialchars($doctorInfo['qualification']); ?>" required>
                </div>
                <div class="dd-group">
                    <label>Years of Experience</label>
                    <input type="number" id="prof-exp" class="dd-input"
                        value="<?php echo (int) $doctorInfo['experience_years']; ?>" required min="0">
                </div>
            </div>
            <div class="dd-group">
                <label>Speciality</label>
                <input type="text" id="prof-speciality" class="dd-input"
                    value="<?php echo htmlspecialchars($doctorInfo['speciality']); ?>" required>
            </div>
            <div class="dd-group">
                <label>Introduction / Description</label>
                <textarea id="prof-desc" class="dd-input"
                    rows="3"><?php echo htmlspecialchars($doctorInfo['description']); ?></textarea>
            </div>

            <h4 style="margin-top:30px; margin-bottom:15px; border-bottom:1px solid #ddd; padding-bottom:10px;">
                Availability Settings</h4>

            <div class="dd-group">
                <label>Available Days (e.g. Mon,Tue,Wed)</label>
                <input type="text" id="prof-days" class="dd-input"
                    value="<?php echo htmlspecialchars($doctorInfo['available_days']); ?>" required>
            </div>
            <div class="dd-grid" style="grid-template-columns: 1fr 1fr; gap:10px;">
                <div class="dd-group">
                    <label>Start Time</label>
                    <input type="time" id="prof-start" class="dd-input"
                        value="<?php echo htmlspecialchars($doctorInfo['start_time']); ?>" required>
                </div>
                <div class="dd-group">
                    <label>End Time</label>
                    <input type="time" id="prof-end" class="dd-input"
                        value="<?php echo htmlspecialchars($doctorInfo['end_time']); ?>" required>
                </div>
            </div>
            <button type="submit" class="dd-btn" style="width:100%; margin-top: 15px;">Save Changes</button>
        </form>
    </div>

    <!-- Patient Search Tab -->
    <div id="tab-search" class="dd-content">
        <div
            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
            <h2 style="margin:0;">Patient Search</h2>
            <p style="color: #64748b; font-size: 0.9em; margin: 0;">Lookup MedScape patient history by ID or Name. <span
                    style="color:var(--primary); font-weight:600;">(Filtered to your hospital)</span></p>
        </div>

        <div class="dd-card" style="margin-bottom: 25px; padding: 25px; background: #fff;">
            <div style="display: flex; gap: 12px; margin-bottom: 0;">
                <div style="flex: 1; position: relative;">
                    <input type="text" id="patient-search-input" class="dd-input" 
                           placeholder="Search Patient by Name or ID..." 
                           oninput="performPatientSearch()"
                           style="padding-left: 15px;">
                </div>
                <button class="dd-btn" onclick="performPatientSearch()" style="padding: 0 30px;">Search</button>
            </div>
        </div>

        <div id="patient-search-results" class="dd-grid">
            <div
                style="text-align:center; padding: 50px; color:#94a3b8; grid-column: 1/-1; background:#f8fafc; border-radius:12px; border: 2px dashed #e2e8f0;">
                <i class="fas fa-user-md" style="font-size:3em; margin-bottom:15px; opacity: 0.5;"></i><br>
                Enter a Patient ID or Name to begin clinical review.
            </div>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div id="report-modal" class="dd-modal-overlay">
    <div class="dd-modal" style="max-height: 90vh; overflow-y: auto;">
        <button class="dd-close"
            onclick="document.getElementById('report-modal').classList.remove('active')">&times;</button>
        <h3 id="report-modal-title">Add Note / Prescription</h3>
        <form onsubmit="submitReport(event)">
            <input type="hidden" id="report-appt-id">
            <input type="hidden" id="report-pat-id">
            <input type="hidden" id="report-id"> <!-- Added for editing -->

            <div class="dd-group">
                <label>Patient Details</label>
                <input type="text" id="report-pat-name" class="dd-input" disabled>
            </div>

            <div id="patient-health-summary"
                style="margin-bottom: 20px; padding: 15px; background: #fff5f5; border-radius: 8px; border: 1px solid #fed7d7; display:none;">
                <h5 style="margin:0 0 10px 0; color: #c53030;">Patient Health
                    Summary</h5>
                <div id="ph-details"
                    style="font-size: 0.9em; display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-bottom: 10px;">
                    <!-- Loaded via JS -->
                </div>
            </div>
            <div class="dd-group">
                <label>Diagnosis</label>
                <input type="text" id="report-diagnosis" class="dd-input" required placeholder="Main diagnosis...">
            </div>
            <div class="dd-group">
                <label>Medical Report / Notes</label>
                <textarea id="report-details" class="dd-input" rows="4" required
                    placeholder="Physical findings, observations, etc..."></textarea>
            </div>
            <div class="dd-group">
                <label>Prescription</label>
                <textarea id="report-prescription" class="dd-input" rows="4" required
                    placeholder="Medications, dosage, and frequency..."></textarea>
            </div>

            <div style="margin-top: 15px; border-top: 1px solid #ddd; padding-top: 15px;">
                <button type="button" class="dd-btn" style="width:100%;"
                    onclick="openPatientHistory(document.getElementById('report-pat-id').value, document.getElementById('report-pat-name').value)">
                    Check Full Medical History
                </button>
            </div>

            <button type="submit" id="report-submit-btn" class="dd-btn dd-btn-success"
                style="width:100%; margin-top: 15px;">Save Report & Mark Completed</button>
        </form>
    </div>
</div>

<!-- Patient History Modal (Cross-Doctor Clinical Records) -->
<div id="patient-history-modal" class="dd-modal-overlay">
    <div class="dd-modal" style="max-width: 700px; max-height: 85vh; overflow-y: auto;">
        <div
            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:10px;">
            <h3 style="margin:0;">Complete Clinical History</h3>
            <button class="dd-close"
                onclick="document.getElementById('patient-history-modal').classList.remove('active')"
                style="background:none; border:none; font-size:1.5em; cursor:pointer;">&times;</button>
        </div>
        <h4 id="ph-modal-patient-name" style="margin-top:0; color:var(--primary);"></h4>
        <div id="patient-history-content">
            <!-- Loaded via JS -->
        </div>
    </div>
</div>

<!-- History Modal -->
<div id="history-modal" class="dd-modal-overlay">
    <div class="dd-modal" style="max-width: 600px; max-height: 85vh; overflow-y: auto;">
        <div
            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:10px;">
            <h3 style="margin:0;">Report History</h3>
            <button class="dd-close" onclick="document.getElementById('history-modal').classList.remove('active')"
                style="background:none; border:none; font-size:1.5em; cursor:pointer;">&times;</button>
        </div>
        <div id="history-list">
            <!-- Loaded via JS -->
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="reschedule-modal" class="dd-modal-overlay">
    <div class="dd-modal" style="max-width: 450px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Reschedule Appointment</h3>
            <button class="dd-close" onclick="closeRescheduleModal()"
                style="background: none; border: none; font-size: 1.5em; cursor: pointer; color: #666;">&times;</button>
        </div>
        <form id="reschedule-form" onsubmit="submitReschedule(event)">
            <input type="hidden" id="reschedule-appt-id">

            <div class="dd-group" style="margin-bottom: 20px;">
                <label>New Appointment Date</label>
                <input type="date" id="reschedule-date" class="dd-input" required min="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="dd-group" style="margin-bottom: 25px;">
                <label>Available Time Slots</label>
                <select id="reschedule-time" class="dd-input" required>
                    <?php
                    // Pre-generate standard slots for the doctor (default 9-5 if not specified)
                    $start = $doctorInfo['start_time'] ?: '09:00';
                    $end = $doctorInfo['end_time'] ?: '17:00';
                    $s = new DateTime($start);
                    $e = new DateTime($end);
                    while ($s < $e) {
                        $ts = $s->format('H:i');
                        echo "<option value='$ts'>$ts</option>";
                        $s->modify('+30 minutes');
                    }
                    ?>
                </select>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="button" class="dd-btn"
                    style="flex:1; background: transparent; border: 1px solid var(--primary); color: var(--primary);"
                    onclick="closeRescheduleModal()">Cancel</button>
                <button type="submit" class="dd-btn" style="flex:2;">Confirm New Time</button>
            </div>
        </form>
    </div>
</div>

<script>
    const parentSwitchTab = window.switchTab;
    window.switchTab = function(e, tabId) {
        // Use global switchTab if available
        if (typeof parentSwitchTab === 'function') {
            parentSwitchTab(e, tabId);
        } else {
            // UI Updates for Sidebar (fallback)
            document.querySelectorAll('.sidebar-menu li').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.dd-content').forEach(c => c.classList.remove('active'));
            if (e && e.currentTarget && e.currentTarget.tagName === 'LI') {
                e.currentTarget.classList.add('active');
            } else {
                const sideItem = document.querySelector(`.sidebar-menu li[data-page="${tabId}"]`);
                if (sideItem) sideItem.classList.add('active');
            }
            const targetTab = document.getElementById(`tab-${tabId}`);
            if (targetTab) targetTab.classList.add('active');
        }

        if (tabId === 'overview') loadDocStats();
        if (tabId === 'ai_insights') loadAiInsights();
        if (tabId === 'bookings') loadDocAppointments('bookings-list', 'scheduled');
        if (tabId === 'consulted') loadConsultedPatients('consulted-list');
    }
    // window.switchTab is now configured and will handle onclick events directly
    async function loadDocStats() {
        try {
            const res = await fetch('../logic/doctor/actions.php?action=get_stats');
            const data = await res.json();
            document.getElementById('stat-total').textContent = data.total_appointments || 0;
            document.getElementById('stat-today').textContent = data.today_appointments || 0;
            document.getElementById('stat-completed').textContent = data.completed_consultations || 0;
            document.getElementById('stat-left').textContent = data.appointments_left || 0;

            // Also load today's specific appointments
            loadTodaysAppointments();

            // Set today's date in the display
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('today-date-display').textContent = new Date().toLocaleDateString(undefined, options);
        } catch (e) { console.error(e); }
    }

    async function loadTodaysAppointments() {
        const container = document.getElementById('todays-appointments-list');
        if (!container) return;

        container.innerHTML = '<div style="text-align:center; padding: 20px; color:#aaa; grid-column: 1/-1;">Loading today\'s schedule...</div>';

        try {
            const res = await fetch('../logic/doctor/actions.php?action=get_appointments&date=today&status=scheduled');
            const appts = await res.json();

            if (!appts || appts.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center; padding: 40px; color:#94a3b8; font-size:1.1em; grid-column: 1/-1; background:#f8fafc; border-radius:12px; border: 2px dashed #e2e8f0;">
                        No appointments scheduled for today.
                    </div>`;
                return;
            }

            const now = new Date().getTime();
            const oneHour = 3600000;

            container.innerHTML = appts.map(a => {
                const apptTime = new Date(`${a.appointment_date}T${a.appointment_time}`).getTime();
                const isAvailable = (apptTime - now) <= oneHour;

                return `
                <div class="dd-card" style="border-left: 4px solid var(--primary);">
                    <div class="dd-card-header">
                        <div class="dd-card-title-group">
                            <h4 style="margin:0;">${a.patient_name}</h4>
                        </div>
                        <div class="dd-card-badge-group">
                            <span style="font-size: 0.75em; color: ${a.payment_status === 'paid' ? '#2ecc71' : '#e74c3c'}; font-weight: 600;">
                                ${a.payment_status === 'paid' ? 'PAID' : 'UNPAID'}
                            </span>
                            <span style="background:var(--primary-light); color:var(--primary); padding:4px 10px; border-radius:6px; font-size:0.85em; font-weight:600;">
                                ${a.appointment_time.slice(0, 5)}
                            </span>
                        </div>
                    </div>
                    <p style="font-size:0.9em; margin-bottom:15px; color:#64748b;">
                        ${a.reason || 'General Checkup'}
                    </p>
                    <div style="display:flex; gap:10px;">
                        ${isAvailable ? `
                            <button class="dd-btn btn-with-arrow" style="flex:1; font-size:0.85em; padding:8px;" onclick="openReportModal(${a.id}, '${a.patient_id || ''}', '${a.patient_name}')">
                                Consult
                            </button>
                        ` : `
                            <button class="dd-btn" style="flex:1; font-size:0.85em; padding:8px; background:#e2e8f0; color:#64748b; cursor:not-allowed;" disabled title="Available 1 hour before scheduled time">
                                Locked
                            </button>
                        `}
                        <button class="dd-btn" style="background:#f1f5f9; color:#475569; flex:0.4; font-size:0.85em; padding:8px;" onclick="rescheduleDocAppt(${a.id})">
                            Reschedule
                        </button>
                    </div>
                </div>
            `;
            }).join('');
        } catch (e) {
            console.error(e);
            container.innerHTML = '<div style="color:red; grid-column: 1/-1;">Error loading today\'s schedule</div>';
        }
    }

    async function loadConsultedPatients(containerId) {
        try {
            const res = await fetch(`../logic/doctor/actions.php?action=get_appointments&status=completed`);
            const appts = await res.json();
            const container = document.getElementById(containerId);
            if (!container) return;

            if (!appts || appts.length === 0) {
                container.innerHTML = `<div style="text-align:center; padding: 40px; color:#aaa; font-size:1.2em; grid-column: 1/-1;">No consulted patients found.</div>`;
                return;
            }

            // Group by patient_id
            const groups = {};
            appts.forEach(a => {
                if (!groups[a.patient_id]) {
                    groups[a.patient_id] = {
                        patient_name: a.patient_name,
                        patient_id: a.patient_id,
                        visits: []
                    };
                }
                groups[a.patient_id].visits.push(a);
            });

            // Convert to array and sort by last visit date
            const groupedArray = Object.values(groups).map(g => {
                g.visits.sort((a, b) => {
                    const dateA = new Date(`${a.appointment_date}T${a.appointment_time}`);
                    const dateB = new Date(`${b.appointment_date}T${b.appointment_time}`);
                    return dateB - dateA;
                });
                g.lastVisit = g.visits[0];
                g.visitCount = g.visits.length;
                return g;
            });

            container.innerHTML = groupedArray.map(g => {
                const a = g.lastVisit;
                return `
                <div class="dd-card consulted-group-card" onclick="openPatientHistory(${g.patient_id}, '${escapeQuotes(g.patient_name)}')" style="cursor:pointer; border-left: 4px solid var(--primary);">
                    <div class="dd-card-header">
                        <div class="dd-card-title-group">
                            <h4 style="margin:0 0 5px 0;">${g.patient_name}</h4>
                            <span style="font-size:0.8em; color:var(--text-muted);">Patient ID: ${g.patient_id}</span>
                        </div>
                        <div class="dd-card-badge-group">
                            <span class="dd-badge" style="background:var(--primary); color:white; border-radius:12px; padding:2px 10px;">${g.visitCount} Visit${g.visitCount > 1 ? 's' : ''}</span>
                        </div>
                    </div>
                    <div style="margin-top:10px; padding-top:10px; border-top:1px solid #f1f5f9;">
                        <p style="margin:5px 0; font-size:0.9em; color:#475569;"><strong>Last Visit:</strong> ${a.appointment_date}</p>
                        <p style="margin:5px 0; font-size:0.9em; color:#475569;"><strong>Reason:</strong> ${a.reason || 'General Checkup'}</p>
                    </div>
                    <div style="margin-top:15px; text-align:center; font-size:0.8em; font-weight:600; color:var(--primary);">
                        VIEW FULL VISIT HISTORY
                    </div>
                </div>
                `;
            }).join('');
        } catch (e) {
            console.error(e);
            container.innerHTML = '<div style="color:red; grid-column: 1/-1;">Error loading consulted patients</div>';
        }
    }

    async function loadDocAppointments(containerId, status = '') {
        try {
            const res = await fetch(`../logic/doctor/actions.php?action=get_appointments&status=${status}`);
            const appts = await res.json();
            const container = document.getElementById(containerId);

            if (!container) return;

            if (!appts || appts.length === 0) {
                container.innerHTML = `<div style="text-align:center; padding: 40px; color:#aaa; font-size:1.2em; grid-column: 1/-1;">No ${status} appointments found.</div>`;
                return;
            }

            const now = new Date().getTime();
            const oneHour = 3600000;

            container.innerHTML = appts.map(a => {
                const apptTime = new Date(`${a.appointment_date}T${a.appointment_time}`).getTime();
                const isAvailable = (apptTime - now) <= oneHour;

                const apptTimestamp = new Date(`${a.appointment_date} ${a.appointment_time}`).getTime();
                const diffSeconds = (apptTimestamp - now) / 1000;
                const isLocked = diffSeconds < 7200; // 2 hours

                return `
            <div class="dd-card">
                <div class="dd-card-header">
                    <div class="dd-card-title-group">
                        <h4 style="margin:0 0 10px 0;">Patient: ${a.patient_name} <span style="font-size:0.7em; color:#64748b; margin-left:5px;">(ID: ${a.patient_id})</span></h4>
                    </div>
                    <div class="dd-card-badge-group">
                        ${(!['completed', 'scheduled', 'missed', 'cancelled'].includes(a.status)) ? `
                            <span class="dd-badge b-${a.status === 'reschedule_requested' ? 'warning' : a.status}" style="${a.status === 'reschedule_requested' ? 'background:#ff9800; color:#fff;' : ''}">${a.status === 'reschedule_requested' ? 'RESCHEDULE PENDING' : a.status.toUpperCase()}</span>
                        ` : ''}
                        
                        ${a.status !== 'completed' ? `
                            <span class="dd-badge" style="background: ${a.payment_status === 'paid' ? '#2ecc71' : '#e74c3c'}; color: white;">
                                ${a.payment_status === 'paid' ? 'PAID' : 'UNPAID'}
                            </span>
                        ` : ''}

                        ${isLocked && a.status === 'scheduled' ? `<span class="dd-badge" style="background:#6c757d; color:#fff;">LOCKED</span>` : ''}
                    </div>
                </div>
                <p style="margin:5px 0;"><strong>Date:</strong> ${a.appointment_date}</p>
                <p style="margin:5px 0;"><strong>Time:</strong> ${a.appointment_time.slice(0, 5)}</p>
                
                ${a.status === 'completed' && a.report_id ? `
                    <div style="margin-top:20px; display:flex; flex-direction:column; gap:10px;">
                        <div style="display:flex; gap:8px;">
                            <a href="../logic/doctor/download_report.php?id=${a.report_id}" target="_blank" class="dd-btn btn-with-arrow" style="flex:2; text-decoration:none; display:block; text-align:center;">
                                View Report
                            </a>
                            <button class="dd-btn" style="flex:0.5;" onclick="openHistoryModal(${a.report_id})" title="Version History">
                            </button>
                        </div>
                        
                        ${(new Date().getTime() - new Date(a.appointment_date + 'T' + a.appointment_time).getTime()) <= 3600000 ? `
                            <button class="dd-btn dd-btn-outline" style="width:100%;" onclick="loadReportForEdit(${a.report_id}, '${escapeQuotes(a.patient_name)}')">
                                Edit Report (1h Window)
                            </button>
                        ` : ''}
                    </div>
                ` : a.status === 'completed' ? `
                    <p style="color:#ef4444; font-size:0.9em; margin-top:10px;">Missing Report</p>
                ` : a.status === 'reschedule_requested' ? `
                    <div style="margin-top:20px; padding:15px; background:#fff3e0; border-left:4px solid #ff9800; border-radius:4px;">
                        <h5 style="margin:0 0 10px 0; color:#e65100;">Patient Reschedule Request</h5>
                        <p style="margin:0 0 5px 0; font-size:0.95em;">Requested Date: <strong>${a.requested_date}</strong></p>
                        <p style="margin:0 0 15px 0; font-size:0.95em;">Requested Time: <strong>${a.requested_time ? a.requested_time.slice(0, 5) : ''}</strong></p>
                        <div style="display:flex; gap:10px;">
                            <button class="dd-btn dd-btn-success" style="flex:1;" onclick="approveReschedule(${a.id})">Approve</button>
                            <button class="dd-btn dd-btn-danger" style="flex:1;" onclick="declineReschedule(${a.id})">Decline</button>
                        </div>
                    </div>
                ` : `
                    <div style="margin-top:20px; display:flex; flex-direction:column; gap:8px;">
                        ${isAvailable ? `
                            <button class="dd-btn btn-with-arrow" style="width:100%;" onclick="openReportModal(${a.id}, '${a.patient_id || ''}', '${a.patient_name}')">
                                Consult
                            </button>
                        ` : `
                            <button class="dd-btn" style="width:100%; background:transparent; border-color:var(--gray-300); color:var(--gray-400); cursor:not-allowed;" disabled title="Available 1 hour before scheduled time">Locked (Available 1 hour before)</button>
                        `}
                        ${!isLocked ? `
                            <div style="display:flex; gap:10px;">
                                <button class="dd-btn" style="flex:1;" onclick="rescheduleDocAppt(${a.id})">Reschedule</button>
                                <button class="dd-btn dd-btn-danger" style="flex:1;" onclick="cancelDocAppt(${a.id})">Cancel</button>
                            </div>
                        ` : `
                            <div style="padding:10px; background: #f8fafc; border-radius: 6px; text-align:center; font-size: 0.85em; color: #64748b; border: 1px dashed #cbd5e1; display:flex; align-items:center; justify-content:center;">
                                Scheduling Locked
                            </div>
                        `}
                    </div>
                `}
            </div>
        `;
            }).join('');
        } catch (err) { console.error('Error fetching appointments:', err); }
    }

    function rescheduleDocAppt(id) {
        document.getElementById('reschedule-appt-id').value = id;
        document.getElementById('reschedule-modal').classList.add('active');
    }

    function closeRescheduleModal() {
        document.getElementById('reschedule-modal').classList.remove('active');
    }

    async function submitReschedule(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('appointment_id', document.getElementById('reschedule-appt-id').value);
        fd.append('appointment_date', document.getElementById('reschedule-date').value);
        fd.append('appointment_time', document.getElementById('reschedule-time').value);

        try {
            const res = await fetch('../logic/doctor/actions.php?action=reschedule_appointment', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
                closeRescheduleModal();
                loadDocAppointments('bookings-list', 'scheduled');
            } else {
                showToast(result.message || 'Reschedule failed', 'error');
            }
        } catch (e) { showToast("Connection error", 'error'); }
    }

    async function cancelDocAppt(id) {
        const confirmed = await showConfirm({
            title: 'Cancel Appointment?',
            message: "Cancel this patient's appointment?",
            confirmText: 'Yes, Cancel',
            type: 'danger'
        });
        if (!confirmed) return;
        const formData = new FormData(); formData.append('appointment_id', id);
        try {
            await fetch('../logic/doctor/actions.php?action=cancel_appointment', { method: 'POST', body: formData });
            loadDocAppointments('bookings-list', 'scheduled');
        } catch (e) { console.error(e); }
    }

    async function approveReschedule(id) {
        const confirmed = await showConfirm({
            title: 'Approve Reschedule?',
            message: "Approve the patient's requested new time?",
            confirmText: 'Approve',
            type: 'info'
        });
        if (!confirmed) return;
        const formData = new FormData(); formData.append('appointment_id', id);
        try {
            const res = await fetch('../logic/doctor/actions.php?action=approve_reschedule', { method: 'POST', body: formData });
            const result = await res.json();
            showToast(result.message || 'Approved', result.status === 'success' ? 'success' : 'error');
            loadDocAppointments('bookings-list', 'scheduled');
        } catch (e) { showToast('Connection error', 'error'); }
    }

    async function declineReschedule(id) {
        const confirmed = await showConfirm({
            title: 'Decline Reschedule?',
            message: "Decline the patient's request? The appointment will remain at the original time.",
            confirmText: 'Decline',
            type: 'danger'
        });
        if (!confirmed) return;
        const formData = new FormData(); formData.append('appointment_id', id);
        try {
            const res = await fetch('../logic/doctor/actions.php?action=decline_reschedule', { method: 'POST', body: formData });
            const result = await res.json();
            showToast(result.message || 'Declined', result.status === 'success' ? 'success' : 'error');
            loadDocAppointments('bookings-list', 'scheduled');
        } catch (e) { showToast('Connection error', 'error'); }
    }

    async function openReportModal(apptId, patId, patName) {
        document.getElementById('report-appt-id').value = apptId;
        document.getElementById('report-pat-id').value = patId;
        document.getElementById('report-pat-name').value = patName;
        document.getElementById('report-id').value = '';
        document.getElementById('report-diagnosis').value = '';
        document.getElementById('report-details').value = '';
        document.getElementById('report-prescription').value = '';

        document.getElementById('report-modal-title').textContent = 'Add Note / Prescription';
        document.getElementById('report-submit-btn').textContent = 'Save Report & Mark Completed';
        document.getElementById('report-modal').classList.add('active');

        // Fetch Health Summary
        const summaryBox = document.getElementById('patient-health-summary');
        const detailsContainer = document.getElementById('ph-details');
        summaryBox.style.display = 'none';

        if (!patId) return;

        try {
            const infoRes = await fetch(`../logic/doctor/actions.php?action=get_patient_info&patient_id=${patId}`);
            const info = await infoRes.json();

            if (Object.keys(info).length > 0) {
                summaryBox.style.display = 'block';
                detailsContainer.innerHTML = `
                    <span><b>Age:</b> ${info.age || 'N/A'}</span>
                    <span><b>Weight:</b> ${info.weight || 'N/A'} kg</span>
                    <span><b>Height:</b> ${info.height || 'N/A'}</span>
                    <span><b>History:</b> ${info.medical_history || 'None reported'}</span>
                `;
            }
        } catch (e) { console.error("Error fetching health summary:", e); }
    }

    async function loadReportForEdit(reportId, patName) {
        try {
            const res = await fetch(`../logic/doctor/actions.php?action=get_report_history&report_id=${reportId}`);
            const history = await res.json();
            if (!history || !history[0]) return;

            const latest = history[0];
            document.getElementById('report-appt-id').value = '';
            document.getElementById('report-pat-id').value = latest.patient_id;
            document.getElementById('report-pat-name').value = patName;
            document.getElementById('report-id').value = reportId;
            document.getElementById('report-diagnosis').value = latest.diagnosis;
            document.getElementById('report-details').value = latest.report_details;
            document.getElementById('report-prescription').value = latest.prescription;

            document.getElementById('report-modal-title').textContent = 'Edit Consultation Report';
            document.getElementById('report-submit-btn').textContent = 'Update Report (Saves New Version)';
            document.getElementById('report-modal').classList.add('active');
            document.getElementById('patient-health-summary').style.display = 'none';
        } catch (e) { showToast("Error loading report", 'error'); }
    }

    async function openHistoryModal(reportId) {
        const container = document.getElementById('history-list');
        container.innerHTML = '<p style="text-align:center; padding:20px;">Loading history...</p>';
        document.getElementById('history-modal').classList.add('active');

        try {
            const res = await fetch(`../logic/doctor/actions.php?action=get_report_history&report_id=${reportId}`);
            const history = await res.json();

            if (!history || history.length === 0) {
                container.innerHTML = '<p style="text-align:center; padding:20px;">No version history found.</p>';
                return;
            }

            container.innerHTML = history.map((h, i) => `
                <div style="margin-bottom:20px; padding:15px; background:${i === 0 ? '#f0f9ff' : '#f8fafc'}; border:1px solid ${i === 0 ? '#bae6fd' : '#e2e8f0'}; border-radius:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-weight:700; color:var(--primary); font-size:0.9em;">VERSION ${h.version_number} ${i === 0 ? '<span style="background:var(--primary); color:white; padding:2px 6px; border-radius:4px; font-size:0.8em; margin-left:5px;">LATEST</span>' : ''}</span>
                        <span style="font-size:0.85em; color:var(--text-muted);"><i class="far fa-clock"></i> ${new Date(h.created_at).toLocaleString()}</span>
                    </div>
                    <p style="margin:5px 0; font-size:0.9em;"><strong>Diagnosis:</strong> ${h.diagnosis}</p>
                    <p style="margin:5px 0; font-size:0.9em;"><strong>Notes:</strong> ${h.report_details}</p>
                    <div style="margin-top:10px; padding:10px; background:#fff; border:1px dashed #cbd5e1; border-radius:4px; font-size:0.85em;">
                        <strong>Prescription:</strong><br>
                        ${h.prescription.replace(/\n/g, '<br>')}
                    </div>
                </div>
            `).join('');
        } catch (e) { container.innerHTML = '<p style="color:red; text-align:center;">Error loading history</p>'; }
    }

    async function submitReport(e) {
        e.preventDefault();
        const reportId = document.getElementById('report-id').value;
        const action = reportId ? 'edit_report' : 'add_report';

        const formData = new FormData();
        if (reportId) formData.append('report_id', reportId);
        formData.append('appointment_id', document.getElementById('report-appt-id').value);
        formData.append('patient_id', document.getElementById('report-pat-id').value);
        formData.append('diagnosis', document.getElementById('report-diagnosis').value);
        formData.append('report_details', document.getElementById('report-details').value);
        formData.append('prescription', document.getElementById('report-prescription').value);

        try {
            const res = await fetch(`../logic/doctor/actions.php?action=${action}`, {
                method: 'POST',
                body: formData
            });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
                document.getElementById('report-modal').classList.remove('active');
                if (window.activePage === 'consulted') {
                    loadDocAppointments('consulted-list', 'completed');
                } else {
                    loadDocAppointments('bookings-list', 'scheduled');
                    loadDocStats();
                }
            } else {
                showToast(result.message || result.error, 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Error submitting report.', 'error');
        }
    }

    async function updateProfile(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('qualification', document.getElementById('prof-qual').value);
        formData.append('experience_years', document.getElementById('prof-exp').value);
        formData.append('speciality', document.getElementById('prof-speciality').value);
        formData.append('description', document.getElementById('prof-desc').value);
        formData.append('available_days', document.getElementById('prof-days').value);
        formData.append('start_time', document.getElementById('prof-start').value);
        formData.append('end_time', document.getElementById('prof-end').value);

        try {
            const res = await fetch('../logic/doctor/actions.php?action=update_profile', { method: 'POST', body: formData });
            const result = await res.json();
            showToast(result.message, result.status === 'success' ? 'success' : 'error');
        } catch (e) { showToast("Error updating profile", 'error'); }
    }

    async function loadAiInsights() {
        const forecastContainer = document.getElementById('forecast-container');
        const forecastLabels = document.getElementById('forecast-labels');
        const recList = document.getElementById('ai-recommendations-list');

        if (!forecastContainer || !recList) return;

        forecastContainer.innerHTML = '<p style="margin: auto; color: #94a3b8;">Analyzing trends...</p>';
        recList.innerHTML = '<p style="text-align: center; color: #94a3b8; padding: 20px;">Scanning records...</p>';

        try {
            const res = await fetch('../logic/doctor/actions.php?action=get_ai_insights');
            const data = await res.json();

            // Render Forecast
            if (data.forecast && data.forecast.length > 0) {
                const maxLoad = Math.max(...data.forecast.map(f => f.predicted_load), 5);

                forecastContainer.innerHTML = data.forecast.map(f => {
                    const height = (f.predicted_load / maxLoad) * 100;
                    const isToday = new Date(f.date).toDateString() === new Date().toDateString();
                    return `
                        <div style="flex: 1; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: flex-end;">
                            <div style="width: 35px; height: ${height}%; background: linear-gradient(180deg, #6366f1 0%, #3b82f6 100%); border-radius: 6px 6px 0 0; transition: all 0.5s ease; position: relative; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: ${isToday ? '2px solid #1e293b' : 'none'};" title="${f.predicted_load} patients predicted">
                                <span style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); font-size: 0.75em; font-weight: 700; color: #475569;">${f.predicted_load}</span>
                            </div>
                        </div>
                    `;
                }).join('');

                forecastLabels.innerHTML = data.forecast.map(f => {
                    const isToday = new Date(f.date).toDateString() === new Date().toDateString();
                    return `<div style="flex: 1; text-align: center; ${isToday ? 'color: #6366f1; font-weight: 700;' : ''}">${f.day.slice(0, 3).toUpperCase()}</div>`;
                }).join('');
            }

            // Render Recommendations
            if (data.recommendations && data.recommendations.length > 0) {
                const count = data.recommendations.length;
                const r = data.recommendations[0]; // Show only the first one for "switching" feel
                
                recList.innerHTML = `
                    <div class="dd-card ai-insight-card" style="padding: 15px; border-left: 4px solid #6366f1; background: #f8fafc; position: relative; animation: fadeIn 0.5s ease;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <h5 style="margin: 0; color: #1e293b; padding-right: 20px;">${r.title}</h5>
                            <span style="font-size: 0.7em; color: #94a3b8; font-weight: 600; white-space: nowrap;">Insight 1 of ${count}</span>
                        </div>
                        <p style="font-size: 0.85em; color: #64748b; line-height: 1.4; margin: 0 0 12px 0;">${r.description}</p>
                        <div style="display: flex; gap: 8px;">
                            <button class="dd-btn" style="padding: 6px 12px; font-size: 0.8em; flex: 1;" onclick="handleRecommendation(${r.id}, 'accepted', ${r.patient_id}, '${escapeQuotes(r.title)}')">
                                <i class="fas fa-check"></i> Action
                            </button>
                            <button class="dd-btn dd-btn-outline" style="padding: 6px 12px; font-size: 0.8em; color: #64748b;" onclick="handleRecommendation(${r.id}, 'ignored')">
                                Ignore
                            </button>
                        </div>
                        <span style="position: absolute; top: 12px; right: 12px; color: #94a3b8; cursor: pointer; font-size: 0.9em;" onclick="handleRecommendation(${r.id}, 'dismissed')" title="Dismiss">&times;</span>
                    </div>
                `;
            } else {
                recList.innerHTML = `
                    <div style="text-align: center; padding: 40px 20px; color: #94a3b8; animation: fadeIn 0.5s ease;">
                        <i class="fas fa-check-circle" style="font-size: 2em; color: #22c55e; opacity: 0.5; margin-bottom: 10px;"></i>
                        <p style="font-size: 0.9em; margin: 0;">AI engine reports no urgent recommendations at this time.</p>
                    </div>
                `;
            }

        } catch (e) {
            console.error(e);
            forecastContainer.innerHTML = '<p style="margin: auto; color: #ef4444;">Failed to load analytics</p>';
        }
    }

    async function handleRecommendation(id, status, patientId = null, title = '') {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('status', status);

        try {
            const res = await fetch('../logic/doctor/actions.php?action=update_ai_recommendation', { method: 'POST', body: fd });
            const result = await res.json();

            if (result.status === 'success') {
                if (status === 'accepted' && patientId) {
                    // If accepted, maybe open patient search or something?
                    // For now, let's just show a toast and refresh
                    showToast("Insight accepted. Opening patient profile...", 'info');
                    performPatientSearchWithId(patientId);
                } else {
                    showToast(result.message, 'success');
                }
                loadAiInsights();
            }
        } catch (e) { console.error(e); }
    }

    function performPatientSearchWithId(id) {
        switchTab(null, 'search');
        const input = document.getElementById('patient-search-input');
        if (input) {
            input.value = id;
            performPatientSearch();
        }
    }
    // Initial Load
    if (document.getElementById('tab-overview')) {
        loadDocStats();
    }

    // --- Patient Search & History Logic ---
    function handlePatientSearch(e) {
        if (e.key === 'Enter') performPatientSearch();
    }

    async function performPatientSearch() {
        const query = document.getElementById('patient-search-input').value;
        const container = document.getElementById('patient-search-results');

        if (!query.trim()) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = '<div style="text-align:center; padding:30px; grid-column: 1/-1;">Searching MedScape database...</div>';

        try {
            const res = await fetch(`../logic/doctor/actions.php?action=search_patient&q=${encodeURIComponent(query)}`);
            const patients = await res.json();

            if (!patients || patients.length === 0) {
                container.innerHTML = `<div style="text-align:center; padding:40px; color:#94a3b8; font-size:1.1em; grid-column: 1/-1;">No patient found matching "${query}".</div>`;
                return;
            }

            container.innerHTML = patients.map(p => `
                <div class="dd-card" style="border-top: 4px solid var(--primary);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <h4 style="margin:0;">${p.name}</h4>
                        <span style="font-size:0.75em; background:#f1f5f9; color:#64748b; padding:2px 8px; border-radius:4px; font-weight:600;">ID: ${p.id}</span>
                    </div>
                    <p style="font-size:0.85em; margin-bottom:5px;">${p.email}</p>
                    <p style="font-size:0.85em; margin-bottom:15px;">${p.phone || 'No phone listed'}</p>
                    
                    <button class="dd-btn" style="width:100%;" onclick="openPatientHistory(${p.id}, '${escapeQuotes(p.name)}')">
                        Review Full History
                    </button>
                </div>
            `).join('');
        } catch (e) { container.innerHTML = '<div style="color:red; grid-column: 1/-1;">Error performing search</div>'; }
    }

    async function openPatientHistory(patientId, patientName) {
        if (!patientId) return;

        const container = document.getElementById('patient-history-content');
        document.getElementById('ph-modal-patient-name').textContent = "Patient: " + patientName;
        container.innerHTML = '<p style="text-align:center; padding:30px;">Accessing medical records portal...</p>';
        document.getElementById('patient-history-modal').classList.add('active');

        try {
            const res = await fetch(`../logic/doctor/actions.php?action=get_all_patient_reports&patient_id=${patientId}`);
            const records = await res.json();

            if (!records || records.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding:40px; color:#64748b;">No consultation records found for this patient.</div>';
                return;
            }

            container.innerHTML = records.map((r, i) => `
                <div style="margin-bottom:20px; padding:20px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; position:relative;">
                    <div style="position:absolute; top:20px; right:20px; font-size:0.85em; color:var(--text-muted); font-weight:500;">
                        ${new Date(r.created_at).toLocaleDateString()}
                    </div>
                    <div style="margin-bottom:15px;">
                        <h4 style="margin:0; color:var(--primary); font-size:1.1em;">Dr. ${r.doctor_name}</h4>
                        <span style="font-size:0.8em; color:#64748b;">Speciality: ${r.speciality}</span>
                    </div>
                    
                    <div style="margin-bottom:12px;">
                        <span style="font-size:0.75em; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; font-weight:700;">Final Diagnosis</span>
                        <p style="margin:4px 0 0 0; font-size:0.95em; color:var(--text-main); font-weight:600;">${r.diagnosis}</p>
                    </div>

                    <div style="margin-bottom:15px; padding-left:10px; border-left:3px solid #cbd5e1;">
                        <p style="margin:0; font-size:0.9em; color:#475569;">${r.report_details}</p>
                    </div>

                    <div style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px dashed #cbd5e1;">
                        <div style="font-size:0.75em; color:var(--primary); font-weight:700; margin-bottom:5px; text-transform:uppercase;">Prescription / Meds</div>
                        <p style="margin:0; font-size:0.9em; color:#1e293b; white-space: pre-wrap;">${r.prescription}</p>
                    </div>

                    <div style="margin-top:15px; display:flex; gap:10px;">
                        <a href="../logic/doctor/download_report.php?id=${r.id}" target="_blank" class="dd-btn btn-with-arrow" style="flex:1; text-decoration:none; display:block; text-align:center; padding: 6px; font-size: 0.85em;">
                            Download PDF
                        </a>
                        <button class="dd-btn" style="flex:1; padding: 6px; font-size: 0.85em;" onclick="openHistoryModal(${r.id})" title="Version History">
                            Version History
                        </button>
                    </div>
                    
                    ${(r.is_owner == 1 && (new Date().getTime() - new Date(r.appointment_date + 'T' + r.appointment_time).getTime()) <= 3600000) ? `
                        <button class="dd-btn dd-btn-outline" style="width:100%; margin-top: 10px; padding: 6px; font-size: 0.85em;" onclick="document.getElementById('patient-history-modal').classList.remove('active'); loadReportForEdit(${r.id}, '${escapeQuotes(patientName)}')">
                            Edit Report (1h Window)
                        </button>
                    ` : ''}
                </div>
            `).join('');
        } catch (e) { container.innerHTML = '<p style="color:red; text-align:center;">Error accessing patient history portal</p>'; }
    }
</script>
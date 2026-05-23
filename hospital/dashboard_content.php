<?php
// Ensure this is accessed via include
if (!isset($pdo)) {
    require_once 'db.php';
}

$stmt = $pdo->prepare("SELECT * FROM hospitals WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$hosInfo = $stmt->fetch();
?>

<link rel="stylesheet" href="../assets/css/hospital/hospital.css">

<div class="hd-container">

    <!-- Overview Tab -->
    <div id="tab-overview" class="hd-content active">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Active Doctors</h3>
                    <h2 id="h-stat-doctors">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Consulted Bookings</h3>
                    <h2 id="h-stat-consulted">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>To be Consulted</h3>
                    <h2 id="h-stat-pending">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>No. of Patients</h3>
                    <h2 id="h-stat-patients">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Revenue Generated</h3>
                    <h2 id="h-stat-revenue">Rs. 0</h2>
                </div>
            </div>
        </div>

        <h2>Doctor Applications</h2>
        <p style="color:#666; margin-bottom:20px;">Review CVs and approve doctors to work at your hospital.</p>
        <div id="pending-doctors-list" class="hd-grid">
            <!-- Loaded via JS -->
        </div>
    </div>

    <!-- Active Roster Tab -->
    <div id="tab-approved" class="hd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'overview')">Back to Overview</a>
        <h2>Active Roster</h2>
        <p style="color:#666; margin-bottom:20px;">Doctors currently approved and active under your hospital.</p>
        <div id="approved-doctors-list" class="hd-grid">
            <!-- Loaded via JS -->
        </div>
    </div>

    <!-- Manage Appointments Tab -->
    <div id="tab-appointments" class="hd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'overview')">Back to Overview</a>
        <h2>Manage Appointments</h2>
        <p style="color:#666; margin-bottom:20px;">View and manage all bookings scheduled at your hospital.</p>
        
        <div class="hd-card" style="margin-bottom:20px; padding:15px; background: #fff; border: 1px solid #e2e8f0;">
            <div style="display:flex; gap:10px;">
                <input type="text" id="appt-search-input" class="hd-input" placeholder="Search by Doctor Name, ID or Patient Name, ID..." style="flex:1;" oninput="loadHospAppointments()">
                <button class="hd-btn" onclick="loadHospAppointments()" style="padding: 0 25px;">Search</button>
            </div>
        </div>
        <div class="hd-card" style="padding:0; overflow:hidden;">
            <div class="table-container shadow-none">
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="background:#f8fafc;">
                        <tr>
                            <th style="padding:15px; text-align:left;">Patient</th>
                            <th style="padding:15px; text-align:left;">Doctor</th>
                            <th style="padding:15px; text-align:left;">Schedule</th>
                            <th style="padding:15px; text-align:left;">Status</th>
                            <th style="padding:15px; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="hospital-appointments-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Hospital Patients Tab -->
    <div id="tab-patients" class="hd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'overview')">Back to Overview</a>
        <h2>Hospital Patients</h2>
        <p style="color:#666; margin-bottom:20px;">List of unique patients who have visited your hospital.</p>
        
        <div class="hd-card" style="margin-bottom:20px; padding:15px; background: #fff; border: 1px solid #e2e8f0;">
            <div style="display:flex; gap:10px;">
                <input type="text" id="patient-search-input" class="hd-input" placeholder="Search by Patient Name or ID..." style="flex:1;" oninput="loadHospPatients()">
                <button class="hd-btn" onclick="loadHospPatients()" style="padding: 0 25px;">Search</button>
            </div>
        </div>
        <div id="hospital-patients-list" class="hd-grid">
            <!-- Loaded via JS -->
        </div>
    </div>

    <!-- Profile Tab -->
    <div id="tab-profile" class="hd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'overview')">Back to Overview</a>
        <h2>Hospital Profile</h2>

        <div class="hd-card" style="margin-bottom: 25px;">
            <h4>Hospital Details</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">Keep your hospital's contact information updated.
            </p>
            <?php
            $stmtPhone = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
            $stmtPhone->execute([$_SESSION['user_id']]);
            $currentPhone = $stmtPhone->fetchColumn() ?: '';

            $stmtLoc = $pdo->prepare("SELECT province, city FROM hospitals WHERE user_id = ?");
            $stmtLoc->execute([$_SESSION['user_id']]);
            $hospLoc = $stmtLoc->fetch() ?: ['province' => '', 'city' => ''];
            $currentProvince = $hospLoc['province'] ?: '';
            $currentCity = $hospLoc['city'] ?: '';
            ?>
            <form action="../logic/common/update_profile.php" method="POST">
                <div class="hd-group" style="margin-bottom: 15px;">
                    <label>Hospital Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($_SESSION['name']); ?>" required
                        class="hd-input" style="width:100%;">
                </div>
                <div class="hd-group" style="margin-bottom: 15px;">
                    <label>Email Address 
                        <?php if (isset($_SESSION['is_verified']) && $_SESSION['is_verified'] == 1): ?>
                            <span class="badge" style="background: #2ecc71; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; margin-left: 5px; vertical-align: middle;">Verified</span>
                        <?php else: ?>
                            <span class="badge" style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; margin-left: 5px; vertical-align: middle;">Unverified</span>
                        <?php endif; ?>
                    </label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_SESSION['email']); ?>"
                        required class="hd-input" style="width:100%;">
                </div>
                <div class="hd-group" style="margin-bottom: 15px;">
                    <label>Phone Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($currentPhone); ?>"
                        pattern="[0-9]{10}" title="Please enter exactly 10 digits" class="hd-input" style="width:100%;">
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom: 20px;">
                    <div class="hd-group">
                        <label>Province</label>
                        <select name="province" id="profile-province" class="hd-input" style="width:100%;" required onchange="updateProfileCities()">
                            <option value="">Select Province</option>
                            <option value="Koshi" <?php echo $currentProvince === 'Koshi' ? 'selected' : ''; ?>>Koshi</option>
                            <option value="Madhesh" <?php echo $currentProvince === 'Madhesh' ? 'selected' : ''; ?>>Madhesh</option>
                            <option value="Bagmati" <?php echo $currentProvince === 'Bagmati' ? 'selected' : ''; ?>>Bagmati</option>
                            <option value="Gandaki" <?php echo $currentProvince === 'Gandaki' ? 'selected' : ''; ?>>Gandaki</option>
                            <option value="Lumbini" <?php echo $currentProvince === 'Lumbini' ? 'selected' : ''; ?>>Lumbini</option>
                            <option value="Karnali" <?php echo $currentProvince === 'Karnali' ? 'selected' : ''; ?>>Karnali</option>
                            <option value="Sudurpaschim" <?php echo $currentProvince === 'Sudurpaschim' ? 'selected' : ''; ?>>Sudurpaschim</option>
                        </select>
                    </div>
                    <div class="hd-group">
                        <label>City</label>
                        <select name="city" id="profile-city" class="hd-input" style="width:100%;" required>
                            <option value="">Select City</option>
                            <?php if ($currentCity): ?>
                                <option value="<?php echo htmlspecialchars($currentCity); ?>" selected><?php echo htmlspecialchars($currentCity); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary-modern btn-with-arrow" style="width:100%; margin-top:16px;">
                    Save Hospital Details
                </button>
            </form>
        </div>

        <div class="hd-card">
            <h4>Profile Picture</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">Upload a new image for your hospital.</p>
            <form action="../logic/common/update_photo.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="profile_photo" accept="image/*" required class="hd-input"
                    style="margin-bottom:15px; display:block; padding:8px; width:100%; background: var(--input-bg);">
                <button type="submit" class="hd-btn" style="width:100%;">Upload Logo</button>
            </form>
        </div>

    </div>

    <!-- Daily Activity Tab -->
    <div id="tab-logs" class="hd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'overview')">Back to Overview</a>
        <h2>Doctor Activity Log</h2>
        <p style="color:#666; margin-bottom:20px;">Performance of doctors for the current day (Consultations completed today).</p>
        <div class="hd-card" style="padding:0; overflow:hidden;">
            <div class="table-container shadow-none">
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="background:#f8fafc;">
                        <tr>
                            <th style="padding:15px; text-align:left;">Doctor Name</th>
                            <th style="padding:15px; text-align:left;">NMC Number</th>
                            <th style="padding:15px; text-align:left;">Speciality</th>
                            <th style="padding:15px; text-align:center;">Consultations Today</th>
                        </tr>
                    </thead>
                    <tbody id="doctor-activity-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div id="reschedule-modal" class="hd-modal-overlay">
        <div class="hd-modal" style="max-width: 450px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;">Reschedule Appointment</h3>
                <button class="hd-close" onclick="closeRescheduleModal()"
                    style="background: none; border: none; font-size: 1.5em; cursor: pointer; color: #666;">&times;</button>
            </div>
            <form id="reschedule-form" onsubmit="submitReschedule(event)">
                <input type="hidden" id="reschedule-appt-id">
                <input type="hidden" id="reschedule-doctor-id">

                <div class="hd-group" style="margin-bottom: 20px;">
                    <label>New Appointment Date</label>
                    <input type="date" id="reschedule-date" class="hd-input" required min="<?php echo date('Y-m-d'); ?>"
                        onchange="updateRescheduleSlots()">
                </div>

                <div class="hd-group" style="margin-bottom: 25px;">
                    <label>Available Time Slots</label>
                    <select id="reschedule-time" class="hd-input" required>
                        <option value="">Select a new time</option>
                    </select>
                </div>

                <div style="display:flex; gap:12px;">
                    <button type="button" class="hd-btn"
                        style="flex:1;"
                        onclick="closeRescheduleModal()">Cancel</button>
                    <button type="submit" class="hd-btn" style="flex:2;">Confirm New Time</button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Patient Records Modal -->
<div class="hd-modal-overlay" id="patient-records-modal">
    <div class="hd-modal" style="max-width: 650px; max-height: 85vh; display: flex; flex-direction: column; padding: 25px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 1.4em; color: #0f172a;">Medical Records: <span id="records-modal-name" style="font-weight: 400; color: #3b82f6;"></span></h3>
            <button onclick="closePatientRecordsModal()" style="background: none; border: none; font-size: 1.5em; cursor: pointer; color: #64748b;">&times;</button>
        </div>
        
        <div style="overflow-y: auto; flex-grow: 1; padding-right: 10px;">
            <!-- Biodata Section -->
            <div style="margin-bottom: 30px;">
                <h4 style="border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; margin-bottom: 15px; color: #0f172a;">Health Profile (Biodata)</h4>
                <div id="pr-biodata-content" style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">Loading...</div>
            </div>

            <!-- Uploaded Files Section -->
            <div style="margin-bottom: 30px;">
                <h4 style="border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; margin-bottom: 15px; color: #0f172a;">Patient Uploaded Files</h4>
                <div id="pr-files-content">Loading...</div>
            </div>

            <!-- Consultation Reports Section -->
            <div style="margin-bottom: 20px;">
                <h4 style="border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; margin-bottom: 15px; color: #0f172a;">Clinical Reports (This Hospital)</h4>
                <div id="pr-reports-content">Loading...</div>
            </div>
        </div>
        
        <div style="border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 20px; text-align: right;">
            <button onclick="closePatientRecordsModal()" class="hd-btn hd-btn-outline" style="padding: 10px 20px;">Close</button>
        </div>
    </div>
</div>

<script>
    const NEPAL_LOCATIONS = {
        "Koshi": ["Biratnagar", "Itahari", "Dharan", "Birtamod", "Damak"],
        "Madhesh": ["Janakpur", "Birgunj", "Kalaiya", "Gausala", "Lahan"],
        "Bagmati": ["Kathmandu", "Lalitpur", "Bhaktapur", "Hetauda", "Bharatpur"],
        "Gandaki": ["Pokhara", "Gorkha", "Bandipur", "Baglung", "Waling"],
        "Lumbini": ["Butwal", "Bhairahawa", "Nepalgunj", "Ghorahi", "Tulsipur"],
        "Karnali": ["Birendranagar", "Jumla", "Khalanga"],
        "Sudurpaschim": ["Dhangadhi", "Mahendranagar", "Tikapur", "Attariya"]
    };

    function updateProfileCities() {
        const province = document.getElementById('profile-province').value;
        const citySelect = document.getElementById('profile-city');
        const previousCity = citySelect.value; // remember current selection
        
        citySelect.innerHTML = '<option value="">Select City</option>';
        if (NEPAL_LOCATIONS[province]) {
            NEPAL_LOCATIONS[province].forEach(city => {
                const opt = document.createElement('option');
                opt.value = city;
                opt.textContent = city;
                if (city === previousCity) opt.selected = true;
                citySelect.appendChild(opt);
            });
        }
    }

    // Initialize cities on load if province exists
    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('profile-province')) {
            updateProfileCities();
        }
    });

    const parentSwitchTab = window.switchTab;
    window.switchTab = function(e, tabId) {
        // Use global switchTab if available
        if (typeof parentSwitchTab === 'function') {
            parentSwitchTab(e, tabId);
        } else {
            // UI Updates for Sidebar (fallback)
            document.querySelectorAll('.sidebar-menu li').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.hd-content').forEach(c => c.classList.remove('active'));
            if (e && e.currentTarget && e.currentTarget.tagName === 'LI') {
                e.currentTarget.classList.add('active');
            } else {
                const sideItem = document.querySelector(`.sidebar-menu li[data-page="${tabId}"]`);
                if (sideItem) sideItem.classList.add('active');
            }
            const targetTab = document.getElementById(`tab-${tabId}`);
            if (targetTab) targetTab.classList.add('active');
        }

        if (tabId === 'pending' || tabId === 'overview') {
            loadHospStats();
            loadHospDoctors('pending');
        }
        if (tabId === 'approved') loadHospDoctors('approved');
        if (tabId === 'appointments') loadHospAppointments();
        if (tabId === 'patients') loadHospPatients();
        if (tabId === 'logs') loadDoctorActivity();
    }
    // window.switchTab is now configured and will handle onclick events directly
    async function loadHospStats() {
        try {
            const res = await fetch('../logic/hospital/actions.php?action=get_stats');
            const data = await res.json();
            document.getElementById('h-stat-doctors').textContent = data.total_doctors || 0;
            document.getElementById('h-stat-consulted').textContent = data.completed_appointments || 0;
            document.getElementById('h-stat-pending').textContent = data.scheduled_appointments || 0;
            document.getElementById('h-stat-patients').textContent = data.total_patients || 0;
            document.getElementById('h-stat-revenue').textContent = 'Rs. ' + (data.revenue_generated || 0).toLocaleString();
        } catch (e) { console.error(e); }
    }

    async function loadHospDoctors(status) {
        try {
            const res = await fetch(`../logic/hospital/actions.php?action=get_doctors&status=${status}`);
            const doctors = await res.json();
            const container = document.getElementById(`${status}-doctors-list`);

            if (!doctors || doctors.length === 0) {
                container.innerHTML = `<div style="text-align:center; padding: 40px; color:#aaa; font-size:1.1em; width: 100%; grid-column: 1 / -1;">
                ${status === 'pending' ? 'You have no pending doctor applications.' : 'No active doctors on roster yet.'}
            </div>`;
                return;
            }

            container.innerHTML = doctors.map(d => `
            <div class="hd-card">
                <div style="display:flex; align-items:center; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:15px;">
                    <img src="../${d.profile_photo || 'assets/img/default.jpeg'}" alt="Profile" style="width:50px; height:50px; border-radius:50%; object-fit:cover; margin-right:15px; border:2px solid #ddd;">
                    <div>
                        <h4 style="margin:0;">Dr. ${d.name}</h4>
                        <span style="font-size:0.85em; color:#666;">NMC: <span style="font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px;">${d.nmc_number || 'N/A'}</span></span>
                    </div>
                </div>
                <p><strong>Speciality:</strong> ${d.speciality}</p>
                <p><strong>Email:</strong> ${d.email}</p>
                
                ${d.cv_path ? `<a href="../${d.cv_path}" target="_blank" class="hd-btn hd-btn-outline btn-with-arrow" style="display:inline-block; margin-top:10px; margin-bottom:15px; text-decoration:none;">View Submitted CV</a>` : '<p style="color:#d9534f; font-size:0.8em; margin-top:10px;">Warning: No CV uploaded</p>'}
                
                ${status === 'pending' ? `
                <div style="display:flex; gap:10px; margin-top:10px; border-top:1px solid #eee; padding-top:15px;">
                    <button class="hd-btn hd-btn-success" style="flex:1;" onclick="updateDocStatus(${d.doctor_id}, 'approved')">Approve</button>
                    <button class="hd-btn hd-btn-danger" style="flex:1;" onclick="updateDocStatus(${d.doctor_id}, 'rejected')">Reject</button>
                </div>
                ` : `
                <div style="margin-top:10px; border-top:1px solid #eee; padding-top:15px; display:flex; flex-direction:column; gap:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                        <span style="font-size:0.85em; color:#666;">Account Status:</span>
                        <span class="${d.account_status === 'active' ? 'badge-scheduled' : 'badge-cancelled'}">${d.account_status.toUpperCase()}</span>
                    </div>
                    <button class="hd-btn btn-with-arrow" style="width:100%;" onclick="toggleDoctorStatus(${d.doctor_id}, '${d.account_status}')">
                        ${d.account_status === 'active' ? 'Deactivate Account' : 'Reactivate Account'}
                    </button>
                </div>
                `}
            </div>
        `).join('');
        } catch (err) { console.error('Error fetching doctors:', err); }
    }

    async function loadHospAppointments() {
    const query = document.getElementById('appt-search-input') ? document.getElementById('appt-search-input').value : '';
    try {
        const res = await fetch(`../logic/hospital/actions.php?action=get_appointments&q=${encodeURIComponent(query)}`);
        const appts = await res.json();
        const body = document.getElementById(`hospital-appointments-body`);
        body.innerHTML = appts.map(a => {
            const apptTimestamp = new Date(`${a.date} ${a.time}`).getTime();
            const now = new Date().getTime();
            const diffSeconds = (apptTimestamp - now) / 1000;
            const isLocked = diffSeconds < 7200; // 2 hours

            // --- REPLACEMENT ACTIONS BLOCK ---
            let actionButtonsHTML = '';

            // 1. Handle Active Patient Reschedule Requests
            if (a.status === 'reschedule_requested') {
                actionButtonsHTML = `
                    <button onclick="hospApproveReschedule(${a.id})" class="hd-btn hd-btn-success" style="padding:5px 10px; font-size:0.8em; margin-right:4px;">Approve</button>
                    <button onclick="hospDeclineReschedule(${a.id})" class="hd-btn hd-btn-danger" style="padding:5px 10px; font-size:0.8em;">Decline</button>
                `;
            } 
            // 2. Handle standard Scheduled slots that are NOT locked yet
            else if (a.status === 'scheduled' && !isLocked) {
                actionButtonsHTML = `
                    <button onclick="rescheduleHospAppt(${a.id}, ${a.doctor_id})" class="hd-btn" style="padding:5px 10px; font-size:0.8em; margin-right:4px;">Reschedule</button>
                    <button onclick="cancelAppt(${a.id})" class="hd-btn hd-btn-danger" style="padding:5px 10px; font-size:0.8em;">Cancel</button>
                `;
            } 
            // 3. Fallback for Completed, Cancelled, Missed, or 2-Hour Locked appointments
            else {
                actionButtonsHTML = `
                    <select onchange="changeAppointmentStatus(${a.id}, this.value)" style="padding: 4px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 0.85em; background:#fff; color:#334155; margin-right:4px; cursor:pointer;">
                        <option value="scheduled" ${a.status === 'scheduled' ? 'selected' : ''}>Scheduled</option>
                        <option value="completed" ${a.status === 'completed' ? 'selected' : ''}>Completed</option>
                        <option value="missed" ${a.status === 'missed' ? 'selected' : ''}>Missed</option>
                        <option value="cancelled" ${a.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                    </select>
                `;
            }

            // 4. Always append a global Delete Button component for full administrative control
            actionButtonsHTML += `
                <button onclick="deleteAppointment(${a.id})" class="hd-btn hd-btn-danger" style="padding:5px 10px; font-size:0.8em; background:#ef4444; border-color:#ef4444; color:#fff;" title="Delete Record permanently">
                    Delete
                </button>
            `;
            // ---------------------------------

            return `
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:15px;"><b>${a.patient}</b></td>
                <td style="padding:15px;">Dr. ${a.doctor}</td>
                <td style="padding:15px; font-size:0.9em; color:#64748b;">${a.date} at ${a.time.slice(0, 5)}</td>
                <td style="padding:15px;">
                    <span class="badge-${a.status === 'reschedule_requested' ? 'warning' : a.status}">${a.status === 'reschedule_requested' ? 'RESCHEDULE PENDING' : a.status.toUpperCase()}</span>
                    ${isLocked && a.status === 'scheduled' ? `<div style="font-size:0.75em; color:#ef4444; font-weight:600; margin-top:4px;">⚠️ LOCK ACTIVE</div>` : ''}
                    ${a.status === 'reschedule_requested' ? `<div style="font-size:0.85em; color:#e65100; margin-top:5px;">Req: <b>${a.requested_date} at ${a.requested_time ? a.requested_time.slice(0, 5) : ''}</b></div>` : ''}
                </td>
                <td style="padding:15px; text-align:right;">
                    <div style="display:flex; gap:5px; justify-content:flex-end; align-items:center;">
                        ${actionButtonsHTML}
                    </div>
                </td>
            </tr>
            `;
        }).join('') || '<tr><td colspan="5" style="padding:30px; text-align:center; color:#94a3b8;">No appointments found.</td></tr>';
    } catch (e) { console.error(e); }
}
async function changeAppointmentStatus(appointmentId, newStatus) {
    const fd = new FormData();
    fd.append('appointment_id', appointmentId);
    fd.append('status', newStatus);
    try {
        const res = await fetch('../logic/hospital/actions.php?action=update_appointment_status', { method: 'POST', body: fd });
        const data = await res.json();
        alert(data.message);
        loadHospAppointments(); // Refresh layout views
    } catch (e) { console.error(e); }
}

async function deleteAppointment(appointmentId) {
    if (!confirm("Are you sure you want to permanently delete this appointment from the system?")) return;
    const fd = new FormData();
    fd.append('appointment_id', appointmentId);
    try {
        const res = await fetch('../logic/hospital/actions.php?action=delete_appointment', { method: 'POST', body: fd });
        const data = await res.json();
        alert(data.message);
        loadHospAppointments(); // Refresh layout views
    } catch (e) { console.error(e); }
}
    async function loadHospPatients() {
        const query = document.getElementById('patient-search-input') ? document.getElementById('patient-search-input').value : '';
        try {
            const res = await fetch(`../logic/hospital/actions.php?action=get_patients&q=${encodeURIComponent(query)}`);
            const patients = await res.json();
            const container = document.getElementById(`hospital-patients-list`);
            container.innerHTML = patients.map(p => `
                <div class="hd-card" style="padding:15px; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <h4 style="margin:0 0 10px 0;">${p.name}</h4>
                        <p style="font-size:0.9em; color:#64748b; margin:4px 0;">${p.email}</p>
                        <p style="font-size:0.9em; color:#64748b; margin:4px 0;">${p.phone || 'N/A'}</p>
                    </div>
                    <button class="hd-btn btn-with-arrow" style="margin-top: 15px; width: 100%;" onclick="viewPatientRecords(${p.id}, '${p.name}')">View Medical Records</button>
                </div>
            `).join('') || '<div style="grid-column: 1/-1; text-align:center; padding:40px; color:#94a3b8;">No registered patients currently.</div>';
        } catch (e) { console.error(e); }
    }

    async function loadDoctorActivity() {
        try {
            const res = await fetch(`../logic/hospital/actions.php?action=get_doctor_activity`);
            const activity = await res.json();
            const body = document.getElementById(`doctor-activity-body`);
            body.innerHTML = activity.map(d => `
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:15px;"><b>Dr. ${d.name}</b></td>
                    <td style="padding:15px;"><span style="font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px;">${d.nmc_number}</span></td>
                    <td style="padding:15px;">${d.speciality}</td>
                    <td style="padding:15px; text-align:center;">
                        <span style="font-size:1.1em; font-weight:700; color:${d.consulted_today > 0 ? '#10b981' : '#94a3b8'};">
                            ${d.consulted_today}
                        </span>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="4" style="padding:30px; text-align:center; color:#94a3b8;">No doctor activity data available.</td></tr>';
        } catch (e) { console.error(e); }
    }

    async function toggleDoctorStatus(id, currentStatus) {
        const action = currentStatus === 'active' ? 'deactivate' : 'activate';
        const confirmed = await showConfirm({
            title: `${action.charAt(0).toUpperCase() + action.slice(1)} Doctor?`,
            message: `Are you sure you want to ${action} this doctor's account? Access will be revoked immediately.`,
            confirmText: action.charAt(0).toUpperCase() + action.slice(1),
            type: action === 'active' ? 'danger' : 'info'
        });
        if (!confirmed) return;
        
        const fd = new FormData();
        fd.append('doctor_id', id);
        
        try {
            const res = await fetch('../logic/hospital/actions.php?action=toggle_doctor_status', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
                loadHospDoctors('approved');
            } else {
                showToast(result.message, 'error');
            }
        } catch (e) {
            showToast("Connection error", 'error');
        }
    }

    function rescheduleHospAppt(id, docId) {
        document.getElementById('reschedule-appt-id').value = id;
        document.getElementById('reschedule-doctor-id').value = docId;
        document.getElementById('reschedule-modal').classList.add('active');
        updateRescheduleSlots();
    }

    function closeRescheduleModal() {
        document.getElementById('reschedule-modal').classList.remove('active');
    }

    async function updateRescheduleSlots() {
        const docId = document.getElementById('reschedule-doctor-id').value;
        const timeSelect = document.getElementById('reschedule-time');
        timeSelect.innerHTML = '<option value="">Loading slots...</option>';

        try {
            // Reusing general action to get doctor availability
            const res = await fetch(`../logic/hospital/actions.php?action=get_doctor_availability&doctor_id=${docId}`);
            const data = await res.json();

            if (data.status === 'success') {
                const slots = generateTimeSlots(data.start_time || '09:00', data.end_time || '17:00');
                timeSelect.innerHTML = '<option value="">Select Time Slot</option>';
                slots.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s;
                    timeSelect.appendChild(opt);
                });
            } else {
                timeSelect.innerHTML = '<option value="09:00">09:00 (Default)</option><option value="10:00">10:00</option>';
            }
        } catch (e) {
            console.error(e);
            timeSelect.innerHTML = '<option value="09:00">09:00 (Error fallback)</option>';
        }
    }

    async function submitReschedule(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('appointment_id', document.getElementById('reschedule-appt-id').value);
        fd.append('appointment_date', document.getElementById('reschedule-date').value);
        fd.append('appointment_time', document.getElementById('reschedule-time').value);

        try {
            const res = await fetch('../logic/hospital/actions.php?action=reschedule_appointment', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
                closeRescheduleModal();
                loadHospAppointments();
            } else {
                showToast(result.message || 'Reschedule failed', 'error');
            }
        } catch (e) { showToast("Connection error", 'error'); }
    }

    async function cancelAppt(id) {
        const confirmed = await showConfirm({
            title: 'Cancel Appointment?',
            message: 'Are you sure you want to cancel this appointment?',
            confirmText: 'Yes, Cancel',
            type: 'danger'
        });
        if (!confirmed) return;
        const fd = new FormData(); fd.append('appointment_id', id);
        try {
            const res = await fetch('../logic/hospital/actions.php?action=cancel_appointment', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
                loadHospAppointments();
            } else { showToast(result.message, 'error'); }
        } catch (e) { showToast("Error", 'error'); }
    }

    async function hospApproveReschedule(id) {
        const confirmed = await showConfirm({
            title: 'Approve Reschedule?',
            message: "Approve the patient's requested new time?",
            confirmText: 'Approve',
            type: 'info'
        });
        if (!confirmed) return;
        const formData = new FormData(); formData.append('appointment_id', id);
        try {
            const res = await fetch('../logic/hospital/actions.php?action=approve_reschedule', { method: 'POST', body: formData });
            const result = await res.json();
            showToast(result.message || 'Approved', result.status === 'success' ? 'success' : 'error');
            loadHospAppointments();
        } catch (e) { showToast('Connection error', 'error'); }
    }

    async function hospDeclineReschedule(id) {
        const confirmed = await showConfirm({
            title: 'Decline Reschedule?',
            message: "Decline the patient's request? The appointment will remain at the original time.",
            confirmText: 'Decline',
            type: 'danger'
        });
        if (!confirmed) return;
        const formData = new FormData(); formData.append('appointment_id', id);
        try {
            const res = await fetch('../logic/hospital/actions.php?action=decline_reschedule', { method: 'POST', body: formData });
            const result = await res.json();
            showToast(result.message || 'Declined', result.status === 'success' ? 'success' : 'error');
            loadHospAppointments();
        } catch (e) { showToast('Connection error', 'error'); }
    }

    async function updateDocStatus(id, newStatus) {
        const confirmed = await showConfirm({
            title: `${newStatus.charAt(0).toUpperCase() + newStatus.slice(1)} Doctor?`,
            message: `Are you sure you want to ${newStatus} this doctor?`,
            confirmText: newStatus.charAt(0).toUpperCase() + newStatus.slice(1),
            type: newStatus === 'rejected' ? 'danger' : 'info'
        });
        if (!confirmed) return;
        const formData = new FormData();
        formData.append('doctor_id', id);
        formData.append('status', newStatus);
        try {
            const res = await fetch('../logic/hospital/actions.php?action=update_status', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
                loadHospDoctors('pending');
            } else { showToast(result.message || 'Action failed.', 'error'); }
        } catch (e) { console.error(e); showToast('Connection error.', 'error'); }
    }
    async function viewPatientRecords(patientId, patientName) {
        document.getElementById('records-modal-name').textContent = patientName;
        document.getElementById('patient-records-modal').classList.add('active');
        
        document.getElementById('pr-biodata-content').innerHTML = 'Loading...';
        document.getElementById('pr-files-content').innerHTML = 'Loading...';
        document.getElementById('pr-reports-content').innerHTML = 'Loading...';

        try {
            // Fetch Biodata
            const resBio = await fetch(`../logic/hospital/actions.php?action=get_patient_info&patient_id=${patientId}`);
            const bio = await resBio.json();
            if (bio && bio.age) {
                document.getElementById('pr-biodata-content').innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div><strong style="color:#64748b;">Age:</strong> ${bio.age}</div>
                        <div><strong style="color:#64748b;">Blood Group:</strong> ${bio.blood_group || 'N/A'}</div>
                        <div><strong style="color:#64748b;">Height:</strong> ${bio.height ? bio.height + ' cm' : 'N/A'}</div>
                        <div><strong style="color:#64748b;">Weight:</strong> ${bio.weight ? bio.weight + ' kg' : 'N/A'}</div>
                    </div>
                    <div style="margin-bottom: 10px;"><strong style="color:#64748b;">Medical History:</strong><br> ${bio.medical_history || 'None reported'}</div>
                    <div><strong style="color:#64748b;">Current Medications:</strong><br> ${bio.medications || 'None reported'}</div>
                `;
            } else {
                document.getElementById('pr-biodata-content').innerHTML = '<span style="color:#94a3b8;">No health profile submitted.</span>';
            }

            // Fetch Files
            const resFiles = await fetch(`../logic/hospital/actions.php?action=get_patient_files&patient_id=${patientId}`);
            const files = await resFiles.json();
            if (files && files.length > 0) {
                document.getElementById('pr-files-content').innerHTML = files.map(f => `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:#f8fafc; margin-bottom:5px; border-radius:6px; border:1px solid #e2e8f0;">
                        <span style="font-weight:500;">${f.file_name}</span>
                        <a href="../${f.file_path}" target="_blank" class="hd-btn" style="padding:4px 10px; text-decoration:none;">View</a>
                    </div>
                `).join('');
            } else {
                document.getElementById('pr-files-content').innerHTML = '<span style="color:#94a3b8;">No files uploaded.</span>';
            }

            // Fetch Reports
            const resReports = await fetch(`../logic/hospital/actions.php?action=get_all_patient_reports&patient_id=${patientId}`);
            const reports = await resReports.json();
            if (reports && reports.length > 0) {
                document.getElementById('pr-reports-content').innerHTML = reports.map(r => `
                    <div style="border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:12px; background:#fff;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                            <strong style="color:#0f172a; font-size:1.05em;">Dr. ${r.doctor_name} <span style="font-size:0.8em; color:#64748b; font-weight:normal;">(${r.speciality})</span></strong>
                            <span style="color:#64748b; font-size:0.85em;">${r.appointment_date || r.created_at.split(' ')[0]}</span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <strong style="color:#64748b; font-size:0.85em; text-transform:uppercase;">Diagnosis</strong>
                            <p style="margin:2px 0 0 0; font-size:0.95em;">${r.diagnosis || 'N/A'}</p>
                        </div>
                        <div style="margin-bottom:8px;">
                            <strong style="color:#64748b; font-size:0.85em; text-transform:uppercase;">Clinical Notes</strong>
                            <p style="margin:2px 0 0 0; font-size:0.95em;">${r.report_details || 'N/A'}</p>
                        </div>
                        <div>
                            <strong style="color:#64748b; font-size:0.85em; text-transform:uppercase;">Prescription</strong>
                            <p style="margin:2px 0 0 0; font-size:0.95em;">${r.prescription || 'None'}</p>
                        </div>
                    </div>
                `).join('');
            } else {
                document.getElementById('pr-reports-content').innerHTML = '<span style="color:#94a3b8;">No medical reports available from this hospital.</span>';
            }

        } catch (e) {
            console.error(e);
            document.getElementById('pr-reports-content').innerHTML = '<span style="color:#ef4444;">Failed to load records.</span>';
        }
    }

    function closePatientRecordsModal() {
        document.getElementById('patient-records-modal').classList.remove('active');
    }


    // Initial Load
    if (document.getElementById('tab-overview')) {
        loadHospStats();
        loadHospDoctors('pending');
    }
</script>

<style>
    .badge-scheduled {
        background: #dcfce7;
        color: #166534;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.85em;
        font-weight: 500;
    }

    .badge-cancelled {
        background: #fee2e2;
        color: #991b1b;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.85em;
        font-weight: 500;
    }

    .badge-completed {
        background: #eff6ff;
        color: #1e40af;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.85em;
        font-weight: 500;
    }

    .badge-warning {
        background: #ff9800;
        color: #fff;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.85em;
        font-weight: 500;
    }

    .badge-missed {
        background: #6c757d;
        color: #fff;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.85em;
        font-weight: 500;
    }
</style>
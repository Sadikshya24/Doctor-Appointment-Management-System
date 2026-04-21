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
                <div class="stat-icon d-bg"><i class="fas fa-user-md"></i></div>
                <div class="stat-info">
                    <h3>Active Doctors</h3>
                    <h2 id="h-stat-doctors">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon p-bg"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <h3>Consulted Bookings</h3>
                    <h2 id="h-stat-consulted">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon a-bg"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3>To be Consulted</h3>
                    <h2 id="h-stat-pending">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon r-bg"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3>No. of Patients</h3>
                    <h2 id="h-stat-patients">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon r-bg" style="background:#10b981;"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info">
                    <h3>Revenue Generated</h3>
                    <h2 id="h-stat-revenue">Rs. 0</h2>
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:25px;">
    
            <!-- Admission Graph -->
            <div class="hd-card">
                <h4>Admissions (Last 30 Days)</h4>
                <canvas id="admissionChart" height="120"></canvas>
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
        <h2>Active Roster</h2>
        <p style="color:#666; margin-bottom:20px;">Doctors currently approved and active under your hospital.</p>
        <div id="approved-doctors-list" class="hd-grid">
            <!-- Loaded via JS -->
        </div>
    </div>

    <!-- Manage Appointments Tab -->
    <div id="tab-appointments" class="hd-content">
        <h2>Manage Appointments</h2>
        <p style="color:#666; margin-bottom:20px;">View and manage all bookings scheduled at your hospital.</p>
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
        <h2>Hospital Patients</h2>
        <p style="color:#666; margin-bottom:20px;">List of unique patients who have visited your hospital.</p>
        <div id="hospital-patients-list" class="hd-grid">
            <!-- Loaded via JS -->
        </div>
    </div>

    <!-- Profile Tab -->
    <div id="tab-profile" class="hd-content">
        <h2>Hospital Profile</h2>

        <div class="hd-card" style="margin-bottom: 25px;">
            <h4><i class="fas fa-university"></i> Hospital Details</h4>
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
                    <label>Email Address</label>
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
                <button type="submit" class="hd-btn hd-btn-success" style="width:100%;"><i class="fas fa-save"></i> Save
                    Hospital Details</button>
            </form>
        </div>

        <div class="hd-card">
            <h4><i class="fas fa-image"></i> Hospital Logo</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">Upload a new image for your hospital.</p>
            <form action="../logic/common/update_photo.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="profile_photo" accept="image/*" required class="hd-input"
                    style="margin-bottom:15px; display:block; padding:8px; width:100%; background: var(--input-bg);">
                <button type="submit" class="hd-btn hd-btn-success" style="width:100%;"><i class="fas fa-upload"></i>
                    Upload Logo</button>
            </form>
        </div>

    </div>

    <!-- Daily Activity Tab -->
    <div id="tab-logs" class="hd-content">
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
                        style="flex:1; background: transparent; border: 1px solid var(--primary); color: var(--primary);"
                        onclick="closeRescheduleModal()">Cancel</button>
                    <button type="submit" class="hd-btn" style="flex:2; background: var(--primary); color: white;">Confirm New Time</button>
                </div>
            </form>
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

    function switchTab(e, tabId) {
        // UI Updates for Sidebar
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

        if (tabId === 'pending') {
            loadHospStats();
            loadHospDoctors('pending');
        }
        if (tabId === 'approved') loadHospDoctors('approved');
        if (tabId === 'appointments') loadHospAppointments();
        if (tabId === 'patients') loadHospPatients();
        if (tabId === 'logs') loadDoctorActivity();
    }

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

    async function loadAdmissionChart() {
    const res = await fetch('../logic/hospital/actions.php?action=get_admissions', {
    credentials: 'include'
    });
    const data = await res.json();

    const labels = data.map(d => d.day);
    const values = data.map(d => d.total);

    new Chart(document.getElementById('admissionChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Admissions',
                data: values,
                tension: 0.3
            }]
        }
    });
    }

    async function loadPaymentChart() {
    const res = await fetch('../logic/hospital/actions.php?action=get_payments', {
    credentials: 'include'
    });
    const data = await res.json();

    const labels = data.map(d => d.status);
    const values = data.map(d => d.total);

    new Chart(document.getElementById('paymentChart'), {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: values
            }]
        }
    });
}

    async function loadHospDoctors(status) {
        try {
            const res = await fetch(`../logic/hospital/actions.php?action=get_doctors&status=${status}`);
            const doctors = await res.json();
            const container = document.getElementById(`${status}-doctors-list`);

            if (!doctors || doctors.length === 0) {
                container.innerHTML = `<div style="text-align:center; padding: 40px; color:#aaa; font-size:1.1em; width: 100%; grid-column: 1 / -1;">
                <i class="fas ${status === 'pending' ? 'fa-check-circle' : 'fa-users-slash'}" style="font-size:3em; margin-bottom:15px; color:#ddd;"></i><br>
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
                        <span style="font-size:0.85em; color:#666;"><i class="fas fa-id-card-alt"></i> NMC: <span style="font-family:monospace; background:#eee; padding:2px 6px; border-radius:4px;">${d.nmc_number || 'N/A'}</span></span>
                    </div>
                </div>
                <p><strong><i class="fas fa-stethoscope"></i> Speciality:</strong> ${d.speciality}</p>
                <p><strong><i class="fas fa-envelope"></i> Email:</strong> ${d.email}</p>
                
                ${d.cv_path ? `<a href="../${d.cv_path}" target="_blank" class="hd-btn hd-btn-outline" style="display:inline-block; margin-top:10px; margin-bottom:15px; text-decoration:none;"><i class="fas fa-file-pdf"></i> View Submitted CV</a>` : '<p style="color:#d9534f; font-size:0.8em; margin-top:10px;">Warning: No CV uploaded</p>'}
                
                ${status === 'pending' ? `
                <div style="display:flex; gap:10px; margin-top:10px; border-top:1px solid #eee; padding-top:15px;">
                    <button class="hd-btn hd-btn-success" style="flex:1;" onclick="updateDocStatus(${d.doctor_id}, 'approved')"><i class="fas fa-check"></i> Approve</button>
                    <button class="hd-btn hd-btn-danger" style="flex:1;" onclick="updateDocStatus(${d.doctor_id}, 'rejected')"><i class="fas fa-times"></i> Reject</button>
                </div>
                ` : `
                <div style="margin-top:10px; border-top:1px solid #eee; padding-top:15px; display:flex; flex-direction:column; gap:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                        <span style="font-size:0.85em; color:#666;">Account Status:</span>
                        <span class="${d.account_status === 'active' ? 'badge-scheduled' : 'badge-cancelled'}">${d.account_status.toUpperCase()}</span>
                    </div>
                    <button class="hd-btn ${d.account_status === 'active' ? 'hd-btn-danger' : 'hd-btn-success'}" style="width:100%;" onclick="toggleDoctorStatus(${d.doctor_id}, '${d.account_status}')">
                        <i class="fas ${d.account_status === 'active' ? 'fa-user-slash' : 'fa-user-check'}"></i> 
                        ${d.account_status === 'active' ? 'Deactivate Account' : 'Reactivate Account'}
                    </button>
                </div>
                `}
            </div>
        `).join('');
        } catch (err) { console.error('Error fetching doctors:', err); }
    }

    async function loadHospAppointments() {
    try {
        const res = await fetch(`../logic/hospital/actions.php?action=get_appointments`);
        const appts = await res.json();
        const body = document.getElementById(`hospital-appointments-body`);

        body.innerHTML = appts.map(a => {
            const apptTimestamp = new Date(`${a.date} ${a.time}`).getTime();
            const now = new Date().getTime();
            const diffSeconds = (apptTimestamp - now) / 1000;
            const isLocked = diffSeconds < 7200; // 2 hours

            return `
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:15px;"><b>${a.patient}</b></td>
                    <td style="padding:15px;">Dr. ${a.doctor}</td>
                    <td style="padding:15px; font-size:0.9em; color:#64748b;">
                        ${a.date} at ${a.time.slice(0, 5)}
                    </td>

                    <td style="padding:15px;">
                        <span class="badge-${a.status === 'reschedule_requested' ? 'warning' : a.status}">
                            ${a.status === 'reschedule_requested' ? 'RESCHEDULE PENDING' : a.status.toUpperCase()}
                        </span>

                        ${isLocked && a.status === 'scheduled' ? `
                            <div style="font-size:0.75em; color:#64748b; margin-top:4px;">
                                <i class="fas fa-lock"></i> LOCK ACTIVE
                            </div>
                        ` : ''}

                        ${a.status === 'reschedule_requested' ? `
                            <div style="font-size:0.85em; color:#e65100; margin-top:5px;">
                                <i class="fas fa-exclamation-circle"></i>
                                Req: <b>${a.requested_date} at ${a.requested_time ? a.requested_time.slice(0, 5) : ''}</b>
                            </div>
                        ` : ''}
                    </td>

                    <td style="padding:15px; text-align:right;">
                        ${a.status === 'reschedule_requested' ? `
                            <div style="display:flex; gap:5px; justify-content:flex-end;">
                                <button onclick="hospApproveReschedule(${a.id})" class="hd-btn" style="padding:5px 10px; font-size:0.8em; background:#2e7d32; color:white;">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button onclick="hospDeclineReschedule(${a.id})" class="hd-btn hd-btn-danger" style="padding:5px 10px; font-size:0.8em;">
                                    <i class="fas fa-times"></i> Decline
                                </button>
                            </div>
                        ` : (a.status === 'scheduled' && !isLocked) ? `
                            <div style="display:flex; gap:5px; justify-content:flex-end;">
                                <button onclick="rescheduleHospAppt(${a.id}, ${a.doctor_id})" class="hd-btn" style="padding:5px 10px; font-size:0.8em; background:var(--primary); color:white;">
                                    <i class="fas fa-clock"></i> Reschedule
                                </button>
                                <button onclick="cancelAppt(${a.id})" class="hd-btn hd-btn-danger" style="padding:5px 10px; font-size:0.8em;">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        ` : (a.status === 'scheduled' && isLocked) ? `
                            <span style="font-size:0.8em; color:#94a3b8;">
                                <i class="fas fa-history"></i> Changes Locked
                            </span>
                        ` : `
                            <div style="display:flex; gap:5px; justify-content:flex-end;">
                                <button onclick="updateStatus(${a.id})" class="hd-btn" style="padding:5px 10px; font-size:0.8em; background:#3b82f6; color:white;">
                                    ✏️
                                </button>
                                <button onclick="deleteAppointment(${a.id})" class="hd-btn hd-btn-danger" style="padding:5px 10px; font-size:0.8em;">
                                    🗑️
                                </button>
                            </div>
                        `}
                    </td>
                </tr>
            `;
        }).join('') || `
            <tr>
                <td colspan="5" style="padding:30px; text-align:center; color:#94a3b8;">
                    No appointments found.
                </td>
            </tr>
        `;

    } catch (e) {
        console.error(e);
    }
}
async function deleteAppointment(id) {
    if (!confirm("Delete this appointment?")) return;

    const fd = new FormData();
    fd.append('appointment_id', id);

    const res = await fetch('../logic/hospital/actions.php?action=delete_appointment', {
        method: 'POST',
        body: fd
    });

    const result = await res.json();
    alert(result.message || result.status || "Done");
    loadHospAppointments();
}

async function updateStatus(id) {
    const status = prompt("Enter: scheduled / completed / missed / cancelled");
    if (!status) return;

    const fd = new FormData();
    fd.append('appointment_id', id);
    fd.append('status', status);

    const res = await fetch('../logic/hospital/actions.php?action=update_appointment_status', {
        method: 'POST',
        body: fd
    });

    const result = await res.json();
    alert(result.message || result.status || "Done");
    loadHospAppointments();
}

    async function loadHospPatients() {
        try {
            const res = await fetch(`../logic/hospital/actions.php?action=get_patients`);
            const patients = await res.json();
            const container = document.getElementById(`hospital-patients-list`);
            container.innerHTML = patients.map(p => `
                <div class="hd-card" style="padding:15px;">
                    <h4 style="margin:0 0 10px 0;">${p.name}</h4>
                    <p style="font-size:0.9em; color:#64748b; margin:4px 0;"><i class="fas fa-envelope"></i> ${p.email}</p>
                    <p style="font-size:0.9em; color:#64748b; margin:4px 0;"><i class="fas fa-phone"></i> ${p.phone || 'N/A'}</p>
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
    document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('tab-overview')) {
        loadHospStats();
        loadHospDoctors('pending');
        loadAdmissionChart();
        loadPaymentChart();
    }
}); 

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
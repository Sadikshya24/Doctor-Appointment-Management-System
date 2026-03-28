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
    <div class='dashboard-container' style='text-align: center; margin-top: 50px;'>
        <i class='fas fa-user-clock' style='font-size: 4em; color: #5995fd; margin-bottom: 20px;'></i>
        <h2 style='color:var(--text-main);'>Account Pending Approval</h2>
        <p style='color:#666; font-size:1.1em; line-height:1.6;'>
            Your registration is currently under review. Our hospital administration team is verifying your CV and NMC Number (<b>" . htmlspecialchars($doctorInfo['nmc_number'] ?? 'N/A') . "</b>). <br><br>
            Once approved, your dashboard will automatically become available here.
        </p>
    </div>
    ";
    return; // Stop interpreting the rest of the dashboard
}

if ($doctorInfo['status'] === 'rejected') {
    $stmtHosp = $pdo->query("SELECT h.id, u.name, h.location FROM hospitals h JOIN users u ON h.user_id = u.id");
    $hospitalsList = $stmtHosp->fetchAll(PDO::FETCH_ASSOC);

    echo "
    <div class='dashboard-container' style='text-align: center; margin-top: 50px;'>
        <i class='fas fa-times-circle' style='font-size: 4em; color: #dc3545; margin-bottom: 20px;'></i>
        <h2 style='color:var(--text-main);'>Application Rejected</h2>
        <p style='color:#666; font-size:1.1em; line-height:1.6;'>
            Unfortunately, your application was not approved by the previously selected hospital. <br>
            Please select a hospital and upload an updated CV to re-apply.
        </p>
        
        <form action='../doctor/actions.php?action=reapply' method='POST' enctype='multipart/form-data' style='max-width:400px; margin: 30px auto; text-align:left; background:#f9f9f9; padding:20px; border-radius:8px; border:1px solid #ddd;'>
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
            <button type='submit' style='width:100%; padding:12px; background:#28a745; color:#fff; border:none; border-radius:4px; font-size:1.1em; cursor:pointer;'><i class='fas fa-paper-plane'></i> Submit Re-Application</button>
        </form>
    </div>
    ";
    return;
}

$docId = $doctorInfo['id'];
?>

<link rel="stylesheet" href="../assets/css/doctor.css">

<div class="dd-container">

    <div class="dd-tabs">
        <div class="dd-tab active" onclick="switchDocTab('overview')"><i class="fas fa-stethoscope"></i> Overview &
            Bookings</div>
        <div class="dd-tab" onclick="switchDocTab('settings')"><i class="fas fa-user-cog"></i> My Profile & Availability
        </div>
    </div>

    <!-- Bookings Tab -->
    <div id="dtab-overview" class="dd-content active">
        <h2>Manage Appointments</h2>
        <div id="doctor-appointments" class="dd-grid">
            <!-- Loaded via JS -->
        </div>
    </div>

    <!-- Settings Tab -->
    <div id="dtab-settings" class="dd-content">
        <h2>Profile & Availability</h2>
        
        <div class="dd-card" style="margin-bottom: 25px;">
            <h4><i class="fas fa-user-edit"></i> Personal Details</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">Keep your contact information updated.</p>
            <?php
            $stmtPhone = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
            $stmtPhone->execute([$_SESSION['user_id']]);
            $currentPhone = $stmtPhone->fetchColumn() ?: '';
            ?>
            <form action="../actions/update_profile.php" method="POST">
                <div class="dd-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="dd-input" value="<?php echo htmlspecialchars($_SESSION['name']); ?>" required>
                </div>
                <div class="dd-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="dd-input" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" required>
                </div>
                <div class="dd-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="dd-input" value="<?php echo htmlspecialchars($currentPhone); ?>" pattern="[0-9]{10}" title="Please enter exactly 10 digits">
                </div>
                <button type="submit" class="dd-btn" style="width:100%;"><i class="fas fa-save"></i> Save Personal Details</button>
            </form>
        </div>
        <div class="dd-card" style="margin-bottom: 25px;">
            <h4><i class="fas fa-image"></i> Profile Picture</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">Upload a new image to personalize your account.</p>
            <form action="../actions/update_photo.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="profile_photo" accept="image/*" class="dd-input" required
                    style="margin-bottom:15px; padding: 10px; background: var(--input-bg);">
                <button type="submit" class="dd-btn" style="background:var(--success); width:100%;"><i class="fas fa-upload"></i> Upload Photo</button>
            </form>
        </div>

        <form class="dd-form" onsubmit="updateProfile(event)">
            <div class="dd-grid" style="grid-template-columns: 1fr 1fr; gap:15px; margin-bottom: 15px;">
                <div class="dd-group">
                    <label>Qualification (e.g. MBBS, MD)</label>
                    <input type="text" id="prof-qual" class="dd-input" value="<?php echo htmlspecialchars($doctorInfo['qualification']); ?>" required>
                </div>
                <div class="dd-group">
                    <label>Years of Experience</label>
                    <input type="number" id="prof-exp" class="dd-input" value="<?php echo (int)$doctorInfo['experience_years']; ?>" required min="0">
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
            <button type="submit" class="dd-btn" style="width:100%; margin-top: 15px;"><i class="fas fa-save"></i> Save
                Changes</button>
        </form>
    </div>

</div>

<!-- Report Modal -->
<div id="report-modal" class="dd-modal-overlay">
    <div class="dd-modal" style="max-height: 90vh; overflow-y: auto;">
        <button class="dd-close"
            onclick="document.getElementById('report-modal').classList.remove('active')">&times;</button>
        <h3>Add Note / Prescription</h3>
        <form onsubmit="submitReport(event)">
            <input type="hidden" id="report-appt-id">
            <input type="hidden" id="report-pat-id">
            <div class="dd-group">
                <label>Patient Details</label>
                <input type="text" id="report-pat-name" class="dd-input" disabled>
            </div>
            
            <div id="patient-health-summary" style="margin-bottom: 20px; padding: 15px; background: #fff5f5; border-radius: 8px; border: 1px solid #fed7d7; display:none;">
                <h5 style="margin:0 0 10px 0; color: #c53030;"><i class="fas fa-file-medical-alt"></i> Patient Health Summary</h5>
                <div id="ph-details" style="font-size: 0.9em; display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-bottom: 10px;">
                    <!-- Loaded via JS -->
                </div>
                <div id="ph-meds-box" style="margin-bottom: 10px;">
                    <strong>Medications:</strong> <p id="ph-meds" style="margin:5px 0; font-size:0.9em;"></p>
                </div>
                <div id="ph-files-box">
                    <strong>Previous Records:</strong>
                    <div id="ph-files-list" style="margin-top:5px;"></div>
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
            <button type="submit" class="dd-btn dd-btn-success" style="width:100%;">Save Report & Mark
                Completed</button>
        </form>
    </div>
</div>

<script>
    function goToProfile() {
        const tabs = document.querySelectorAll('.dd-tab');
        if (tabs[1]) tabs[1].click();
    }

    function switchDocTab(tab) {
        document.querySelectorAll('.dd-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.dd-content').forEach(c => c.classList.remove('active'));

        event.currentTarget.classList.add('active');
        document.getElementById('dtab-' + tab).classList.add('active');

        if (tab === 'overview') loadDocAppointments();
    }

    async function loadDocAppointments() {
        try {
            const res = await fetch('actions.php?action=get_appointments');
            const appts = await res.json();
            const container = document.getElementById('doctor-appointments');

            if (!appts || appts.length === 0) {
                container.innerHTML = `<div style="text-align:center; padding: 40px; color:#aaa; font-size:1.2em;"><i class="fas fa-calendar-times"></i><br>No upcoming bookings found.</div>`;
                return;
            }

            container.innerHTML = appts.map(a => `
            <div class="dd-card">
                <span class="dd-badge b-${a.status}">${a.status.toUpperCase()}</span>
                <h4>Patient: ${a.patient_name}</h4>
                <p><strong><i class="far fa-calendar"></i> Time:</strong> ${a.appointment_date} at ${a.appointment_time.slice(0, 5)}</p>
                <p><strong><i class="fas fa-notes-medical"></i> Reason:</strong> ${a.reason}</p>
                
                ${a.status === 'scheduled' ? `
                    <div style="margin-top:20px; display:flex; gap:10px;">
                        <button class="dd-btn" onclick="openReportModal(${a.id}, '${a.patient_id || ''}', '${a.patient_name}')"><i class="fas fa-pencil-alt"></i> Consult</button>
                        <button class="dd-btn dd-btn-danger" onclick="cancelDocAppt(${a.id})"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                ` : `
                    <div style="margin-top:20px;">
                        ${a.report_id ? `
                            <a href="download_report.php?id=${a.report_id}" target="_blank" class="dd-btn" style="text-decoration:none; display:block; text-align:center; background:var(--primary);">
                                <i class="fas fa-file-download"></i> View Report
                            </a>
                        ` : `
                            <span class="dd-badge b-${a.status}" style="position:static;">${a.status.toUpperCase()}</span>
                        `}
                    </div>
                `}
            </div>
        `).join('');
        } catch (err) { console.error('Error fetching appointments:', err); }
    }

    async function cancelDocAppt(id) {
        if (!confirm("Cancel this patient's appointment?")) return;
        const formData = new FormData(); formData.append('appointment_id', id);
        try {
            await fetch('actions.php?action=cancel_appointment', { method: 'POST', body: formData });
            loadDocAppointments();
        } catch (e) { console.error(e); }
    }

    async function openReportModal(apptId, patId, patName) {
        document.getElementById('report-appt-id').value = apptId;
        document.getElementById('report-pat-id').value = patId;
        document.getElementById('report-pat-name').value = patName;
        document.getElementById('report-details').value = '';
        document.getElementById('report-modal').classList.add('active');

        // Fetch Health Summary
        const summaryBox = document.getElementById('patient-health-summary');
        const detailsContainer = document.getElementById('ph-details');
        const medsEl = document.getElementById('ph-meds');
        const filesContainer = document.getElementById('ph-files-list');

        summaryBox.style.display = 'none';

        if (!patId) return;

        try {
            const [infoRes, filesRes] = await Promise.all([
                fetch(`actions.php?action=get_patient_info&patient_id=${patId}`),
                fetch(`actions.php?action=get_patient_files&patient_id=${patId}`)
            ]);
            
            const info = await infoRes.json();
            const files = await filesRes.json();

            if (Object.keys(info).length > 0 || files.length > 0) {
                summaryBox.style.display = 'block';
                
                detailsContainer.innerHTML = `
                    <span><b>Age:</b> ${info.age || 'N/A'}</span>
                    <span><b>Weight:</b> ${info.weight || 'N/A'} kg</span>
                    <span><b>Height:</b> ${info.height || 'N/A'}</span>
                    <span><b>History:</b> ${info.medical_history || 'None reported'}</span>
                `;
                medsEl.innerText = info.medications || 'None reported';
                
                filesContainer.innerHTML = files.length > 0 ? files.map(f => `
                    <div style="font-size:0.85em; margin-bottom:5px;">
                        <a href="../${f.file_path}" target="_blank" style="color:#d32f2f; text-decoration:none;"><i class="fas fa-file-pdf"></i> ${f.file_name}</a>
                    </div>
                `).join('') : '<span style="font-size:0.85em; color:#999;">No PDF records.</span>';
            }
        } catch (e) {
            console.error("Error fetching health summary:", e);
        }
    }

    async function submitReport(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('appointment_id', document.getElementById('report-appt-id').value);
        formData.append('patient_id', document.getElementById('report-pat-id').value);
        formData.append('diagnosis', document.getElementById('report-diagnosis').value);
        formData.append('report_details', document.getElementById('report-details').value);
        formData.append('prescription', document.getElementById('report-prescription').value);

        try {
            const res = await fetch('actions.php?action=add_report', {
                method: 'POST',
                body: formData
            });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
                document.getElementById('report-modal').classList.remove('active');
                loadDocAppointments();
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
            const res = await fetch('actions.php?action=update_profile', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
            } else {
                showToast(result.message || 'Update failed', 'error');
            }
        } catch (e) { showToast("An error occurred", 'error'); }
    }

    // Initial Load
    if (document.getElementById('dtab-overview')) {
        loadDocAppointments();
    }
</script>
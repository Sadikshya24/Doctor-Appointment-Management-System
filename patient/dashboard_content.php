<script>
    // Critical functions forced to global scope early to avoid ReferenceErrors
    function escapeQuotes(str) {
        if (!str) return '';
        return String(str)
            .replace(/`/g, "\\`")
            .replace(/'/g, "\\'")
            .replace(/"/g, '&quot;');
    }
    window.escapeQuotes = escapeQuotes;

    async function loadDoctors() {
        const specialityEl = document.getElementById('filter-speciality');
        const provinceEl = document.getElementById('filter-province');
        const cityEl = document.getElementById('filter-city');
        const doctorEl = document.getElementById('filter-doctor');
        const hospitalEl = document.getElementById('filter-hospital');

        const speciality = specialityEl ? specialityEl.value : '';
        const province = provinceEl ? provinceEl.value : '';
        const city = cityEl ? cityEl.value : '';
        const doctorName = doctorEl ? doctorEl.value : '';
        const hospitalId = hospitalEl ? hospitalEl.value : '';

        const container = document.getElementById('doctors-list');
        if (!container) return;

        try {
            const params = new URLSearchParams({
                speciality: speciality,
                province: province,
                city: city,
                doctor_name: doctorName,
                hospital_id: hospitalId
            });
            const res = await fetch(`../logic/patient/actions.php?action=get_doctors&${params.toString()}`);
            const doctors = await res.json();

            if (!Array.isArray(doctors) || doctors.length === 0) {
                container.innerHTML = `<div class="empty-state"><i class="fas fa-search"></i><br>${(doctors && doctors.error) ? doctors.error : 'No doctors found matching filters.'}</div>`;
                return;
            }

            container.innerHTML = doctors.map(d => `
            <div class="pd-card">
                <div style="display:flex; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:15px;">
                    <img src="../${d.profile_photo || 'assets/img/default.jpeg'}" alt="Profile" class="doctor-img-large">
                    <div style="margin-left:20px;">
                        <h4 style="margin:0; font-size:1.25em; color:var(--text-main);">Dr. ${d.name}</h4>
                        <span style="font-size:0.9em; color:var(--primary-color); font-weight:600;"><i class="fas fa-stethoscope"></i> ${d.speciality}</span>
                        <div style="font-size:0.85em; color:var(--text-muted); margin-top:4px;"><i class="fas fa-graduation-cap"></i> ${d.qualification || 'MBBS'} &bull; ${d.experience_years || 0} Yrs Experience</div>
                    </div>
                </div>
                <p><strong><i class="fas fa-id-card"></i> NMC:</strong> ${d.nmc_number || 'N/A'}</p>
                <p><strong><i class="fas fa-stethoscope"></i> Speciality:</strong> ${d.speciality}</p>
                <p><strong><i class="fas fa-hospital"></i> Hospital:</strong> ${d.hospital_name ? d.hospital_name + ' (' + (d.city || d.location || 'Location Not Set') + ', ' + (d.province || '') + ')' : 'Independent Clinic'}</p>
                <p><strong><i class="fas fa-clock"></i> Timings:</strong> ${d.start_time ? d.start_time.slice(0, 5) : 'N/A'} - ${d.end_time ? d.end_time.slice(0, 5) : 'N/A'} (${d.available_days})</p>
                <button class="pd-btn" style="margin-top: 15px; width: 100%;" 
                    onclick="openBookingModal(${d.id}, '${d.hospital_id || ''}', '${escapeQuotes(d.name)}', '${escapeQuotes(d.qualification || 'MBBS')}', '${d.experience_years || 0}', '${escapeQuotes(d.nmc_number || 'N/A')}', '${escapeQuotes(d.start_time)}', '${escapeQuotes(d.end_time)}')">
                    Book Appointment
                </button>
            </div>
        `).join('');
        } catch (err) { 
            console.error('Error loading doctors:', err);
            container.innerHTML = `<div class="empty-state">Error connection to server.</div>`;
        }
    }
    window.loadDoctors = loadDoctors;

    window.switchTab = function (e, tabId) {
        document.querySelectorAll('.sidebar-menu li').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.pd-content').forEach(c => c.classList.remove('active'));

        if (e && e.currentTarget && e.currentTarget.tagName === 'LI') {
            e.currentTarget.classList.add('active');
        } else {
            const sideItem = document.querySelector(`.sidebar-menu li[data-page="${tabId}"]`);
            if (sideItem) sideItem.classList.add('active');
        }

        const targetTab = document.getElementById(`tab-${tabId}`);
        if (targetTab) targetTab.classList.add('active');

        if (tabId === 'book') loadHospitals().then(() => loadDoctors());
        if (tabId === 'appointments') loadAppointments();
        if (tabId === 'reports') loadReports();
        if (tabId === 'profile') loadHealthFiles();
    };
    function switchTab(e, tabId) { window.switchTab(e, tabId); }

    // --- Fetch Data helpers needed early ---
    async function loadHospitals() {
        try {
            const res = await fetch('../logic/patient/actions.php?action=get_hospitals');
            const hospitals = await res.json();

            const select = document.getElementById('filter-hospital');
            if (!select) return;
            select.innerHTML = '<option value="">All Hospitals</option>';
            hospitals.forEach(h => {
                select.innerHTML += `<option value="${h.id}">${h.name} - ${h.location}</option>`;
            });
        } catch (err) { console.error('Error loading hospitals:', err); }
    }

    const NEPAL_LOCATIONS = {
        "Koshi": ["Biratnagar", "Itahari", "Dharan", "Birtamod", "Damak"],
        "Madhesh": ["Janakpur", "Birgunj", "Kalaiya", "Gausala", "Lahan"],
        "Bagmati": ["Kathmandu", "Lalitpur", "Bhaktapur", "Hetauda", "Bharatpur"],
        "Gandaki": ["Pokhara", "Gorkha", "Bandipur", "Baglung", "Waling"],
        "Lumbini": ["Butwal", "Bhairahawa", "Nepalgunj", "Ghorahi", "Tulsipur"],
        "Karnali": ["Birendranagar", "Jumla", "Khalanga"],
        "Sudurpaschim": ["Dhangadhi", "Mahendranagar", "Tikapur", "Attariya"]
    };

    function updateSearchCities() {
        const province = document.getElementById('filter-province').value;
        const citySelect = document.getElementById('filter-city');

        citySelect.innerHTML = '<option value="">All Cities</option>';
        if (NEPAL_LOCATIONS[province]) {
            NEPAL_LOCATIONS[province].forEach(city => {
                const opt = document.createElement('option');
                opt.value = city;
                opt.textContent = city;
                citySelect.appendChild(opt);
            });
        }
    }

    // --- Booking Modals & Actions (Needed early for dynamic buttons) ---
    function openBookingModal(docId, hospId, docName, qual, exp, nmc, startTime, endTime) {
        document.getElementById('book-doctor-id').value = docId;
        document.getElementById('book-hospital-id').value = hospId;
        document.getElementById('book-doctor-name').value = 'Dr. ' + docName;

        // Set detailed info
        document.getElementById('bd-qual').textContent = qual;
        document.getElementById('bd-exp').textContent = exp;
        document.getElementById('bd-nmc').textContent = nmc;
        document.getElementById('book-doctor-details').style.display = 'block';

        // Clear other fields
        document.getElementById('book-date').value = '';
        document.getElementById('book-time').innerHTML = '<option value="">Select Time Slot</option>';
        document.getElementById('book-reason').value = '';
        document.getElementById('booking-error').style.display = 'none';

        const timeSelect = document.getElementById('book-time');
        if (startTime && endTime) {
            const slots = generateTimeSlots(startTime, endTime);
            slots.forEach(timeStr => {
                timeSelect.innerHTML += `<option value="${timeStr}">${timeStr}</option>`;
            });
        }

        document.getElementById('booking-modal').classList.add('active');
    }
    window.openBookingModal = openBookingModal;

    function closeBookingModal() {
        document.getElementById('booking-modal').classList.remove('active');
    }
    window.closeBookingModal = closeBookingModal;

    async function submitBooking(event) {
        event.preventDefault();
        const formData = new FormData();
        formData.append('doctor_id', document.getElementById('book-doctor-id').value);
        formData.append('hospital_id', document.getElementById('book-hospital-id').value);
        formData.append('appointment_date', document.getElementById('book-date').value);
        formData.append('appointment_time', document.getElementById('book-time').value);
        formData.append('reason', document.getElementById('book-reason').value);

        try {
            const res = await fetch('../logic/patient/actions.php?action=book_appointment', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.status === 'success') {
                closeBookingModal();
                switchTab(null, 'appointments');
                showBill(result.booking_id, 'Dr. ' + result.doctor_name, result.appointment_date + ' at ' + result.appointment_time.slice(0, 5), result.fee || 'Rs. 1000');
            } else {
                const errDiv = document.getElementById('booking-error');
                errDiv.textContent = result.message || result.error;
                errDiv.style.display = 'block';
            }
        } catch (err) { console.error(err); }
    }
    window.submitBooking = submitBooking;

    function showBill(bookingId, doctorName, datetime, fee) {
        document.getElementById('bill-id').textContent = bookingId || 'N/A';
        document.getElementById('bill-patient').textContent = <?php echo json_encode($_SESSION['name']); ?>;
        document.getElementById('bill-doctor').textContent = doctorName;
        document.getElementById('bill-datetime').textContent = datetime;
        document.getElementById('bill-fee').textContent = fee;
        document.getElementById('bill-modal').classList.add('active');
    }
    window.showBill = showBill;

    function closeBillModal() { document.getElementById('bill-modal').classList.remove('active'); }
    window.closeBillModal = closeBillModal;

    function printBill() {
        const content = document.getElementById('bill-content').innerHTML;
        const original = document.body.innerHTML;
        document.body.innerHTML = content;
        const style = document.createElement('style');
        style.type = 'text/css';
        style.media = 'print';
        style.innerHTML = `body { padding:20px !important; margin:0 !important; background:#fff !important; }`;
        document.head.appendChild(style);
        window.print();
        document.body.innerHTML = original;
        window.location.reload();
    }
    window.printBill = printBill;
</script>

<link rel="stylesheet" href="../assets/css/patient/patient.css">

<!-- Booking Modal -->
<div id="booking-modal" class="pd-modal-overlay">
    <div class="pd-modal">
        <div class="pd-modal-header">
            <h3>Confirm Appointment</h3>
            <button class="pd-modal-close" onclick="closeBookingModal()">&times;</button>
        </div>
        <div id="booking-error"
            style="display:none; color:#d32f2f; margin-bottom:15px; background:#ffebee; padding:10px; border-radius:5px; border:1px solid #ef5350;">
        </div>
        <form id="booking-form" onsubmit="submitBooking(event)">
            <input type="hidden" id="book-doctor-id">
            <input type="hidden" id="book-hospital-id">

            <div id="book-doctor-details"
                style="background:#f1f8ff; padding:15px; border-radius:5px; margin-bottom:15px; display:none; border: 1px solid #d0e4f5;">
                <p style="margin:0 0 5px 0; font-size: 0.9em;"><strong><i class="fas fa-graduation-cap"></i>
                        Qualification:</strong> <span id="bd-qual"></span></p>
                <p style="margin:0 0 5px 0; font-size: 0.9em;"><strong><i class="fas fa-briefcase-medical"></i>
                        Experience:</strong> <span id="bd-exp"></span> Years</p>
                <p style="margin:0; font-size: 0.9em;"><strong><i class="fas fa-id-card"></i> NMC Number:</strong> <span
                        id="bd-nmc"></span></p>
            </div>

            <div class="pd-form-group" style="margin-bottom: 15px;">
                <label>Doctor Name</label>
                <input type="text" id="book-doctor-name" class="pd-input" readonly style="background:var(--bg-page);">
            </div>

            <div class="pd-form-group" style="margin-bottom: 15px;">
                <label>Date</label>
                <input type="date" id="book-date" class="pd-input" required min="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="pd-form-group" style="margin-bottom: 15px;">
                <label>Time</label>
                <select id="book-time" class="pd-input" required>
                    <option value="">Select Time Slot</option>
                </select>
            </div>

            <div class="pd-form-group" style="margin-bottom: 25px;">
                <label>Reason for Visit</label>
                <textarea id="book-reason" class="pd-input" rows="3" required
                    placeholder="Briefly describe your symptoms or reason..."></textarea>
            </div>

            <button type="submit" class="pd-btn" style="width: 100%;">Confirm Booking</button>
        </form>
    </div>
</div>

<!-- Account Deletion Confirmation Modal -->
<div id="delete-modal" class="pd-modal-overlay">
    <div class="pd-modal" style="max-width: 450px;">
        <div class="pd-modal-header">
            <h3 style="color: #991b1b;">Confirm Deletion</h3>
            <button class="pd-modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div style="padding: 10px 0 25px 0;">
            <p style="margin: 0 0 15px 0; color: var(--text-main-internal);">
                This action will permanently delete your medical history, reports, and all future appointments.
            </p>
            <p style="margin: 0 0 10px 0; font-size: 0.9em; font-weight: 600;">To confirm, please type <span
                    style="color: #d32f2f;">delete</span> in the box below:</p>
            <input type="text" id="delete-confirm-input" class="pd-input" style="width: 100%;"
                placeholder="type 'delete' here" autocomplete="off">
            <p id="delete-error"
                style="display:none; color:#d32f2f; font-size:0.85em; margin-top:10px; font-weight: 500;">
                <i class="fas fa-info-circle"></i> Input does not match 'delete'
            </p>
        </div>
        <button class="pd-btn pd-btn-danger"
            style="width: 100%; height: 45px; display: flex; align-items: center; justify-content: center; gap: 8px;"
            onclick="submitDeleteAccount()">
            <i class="fas fa-trash-alt"></i> Permanently Delete My Account
        </button>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="reschedule-modal" class="pd-modal-overlay">
    <div class="pd-modal" style="max-width: 450px;">
        <div class="pd-modal-header">
            <h3>Modify Appointment</h3>
            <button class="pd-modal-close" onclick="closeRescheduleModal()">&times;</button>
        </div>
        <form id="reschedule-form" onsubmit="submitReschedule(event)">
            <input type="hidden" id="reschedule-appt-id">
            <input type="hidden" id="reschedule-doctor-id">

            <div class="pd-form-group" style="margin-bottom: 20px;">
                <label>New Appointment Date</label>
                <input type="date" id="reschedule-date" class="pd-input" required min="<?php echo date('Y-m-d'); ?>"
                    onchange="updateRescheduleSlots()">
            </div>

            <div class="pd-form-group" style="margin-bottom: 25px;">
                <label>Available Time Slots</label>
                <select id="reschedule-time" class="pd-input" required>
                    <option value="">Select a new time</option>
                </select>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="button" class="pd-btn pd-btn-outline" style="flex:1;"
                    onclick="closeRescheduleModal()">Cancel</button>
                <button type="submit" class="pd-btn" style="flex:2;">Confirm Reschedule</button>
            </div>
        </form>
    </div>
</div>

<div id="bill-modal" class="pd-modal-overlay">
    <div class="pd-modal" style="max-width: 500px;">
        <div class="pd-modal-header" style="border-bottom:none;">
            <h3 style="margin:0;"><i class="fas fa-file-invoice-dollar"></i> Booking Invoice</h3>
            <button class="pd-modal-close" onclick="closeBillModal()">&times;</button>
        </div>
        <div id="bill-content"
            style="padding:20px; border:1px solid #ddd; border-radius:8px; margin-bottom:20px; background:#fff;">
            <div style="text-align:center; margin-bottom:20px;">
                <h2 style="margin:0; color:var(--primary);">MedScape</h2>
                <p style="margin:5px 0 0 0; color:#666;">Booking Confirmation</p>
            </div>
            <table style="width:100%; border-collapse:collapse; text-align:left;">
                <tr style="border-bottom:1px solid #eee;">
                    <th style="padding:8px 0;">Booking ID:</th>
                    <td id="bill-id"
                        style="text-align:right; font-family:monospace; font-weight:bold; font-size:1.1em; color:var(--primary);">
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #eee;">
                    <th style="padding:8px 0;">Patient Name:</th>
                    <td id="bill-patient" style="text-align:right;"></td>
                </tr>
                <tr style="border-bottom:1px solid #eee;">
                    <th style="padding:8px 0;">Doctor Name:</th>
                    <td id="bill-doctor" style="text-align:right;"></td>
                </tr>
                <tr style="border-bottom:1px solid #eee;">
                    <th style="padding:8px 0;">Date & Time:</th>
                    <td id="bill-datetime" style="text-align:right;"></td>
                </tr>
                <tr>
                    <th style="padding:15px 0 8px 0; font-size:1.1em;">Consultation Fee:</th>
                    <td id="bill-fee"
                        style="text-align:right; font-size:1.1em; font-weight:bold; color:var(--success); padding-top:15px;">
                    </td>
                </tr>
            </table>
            <p style="margin-top:20px; font-size:0.85em; color:#888; text-align:center; line-height:1.4;">Please present
                this booking ID at the hospital counter to pay the fee.<br>Keep this document safe.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="pd-btn" style="flex:1;" onclick="printBill()"><i class="fas fa-print"></i> Print /
                Download</button>
            <button class="pd-btn pd-btn-danger" style="flex:1;" onclick="closeBillModal()">Close</button>
        </div>
    </div>
</div>

<!-- History Modal -->
<div id="history-modal" class="pd-modal-overlay">
    <div class="pd-modal" style="max-width: 600px; max-height: 85vh; overflow-y: auto;">
        <div
            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:10px;">
            <h3 style="margin:0;"><i class="fas fa-history"></i> Medical Report History</h3>
            <button class="pd-modal-close"
                onclick="document.getElementById('history-modal').classList.remove('active')">&times;</button>
        </div>
        <div id="history-list">
            <!-- Loaded via JS -->
        </div>
    </div>
</div>

<div class="pd-container">

    <!-- Overview Tab (now named 'dashboard' for consistency) -->
    <div id="tab-dashboard" class="pd-content active">
        <h2>Dashboard Overview</h2>
        <p>Welcome to your patient portal. From here you can book new appointments, manage existing ones, and view your
            medical reports.</p>
        <div class="pd-grid" style="margin-top: 20px;">
            <div class="pd-card" style="text-align:center; padding: 40px 20px; cursor: pointer;"
                onclick="switchTab(event, 'book')">
                <i class="fas fa-user-md" style="font-size:3em; color:var(--primary); margin-bottom:15px;"></i>
                <h4>Find a Doctor</h4>
                <p>Browse by speciality or hospital</p>
            </div>
            <div class="pd-card" style="text-align:center; padding: 40px 20px; cursor: pointer;"
                onclick="switchTab(event, 'appointments')">
                <i class="fas fa-calendar-check" style="font-size:3em; color:var(--success); margin-bottom:15px;"></i>
                <h4>My Appointments</h4>
                <p>View or cancel upcoming visits</p>
            </div>
            <div class="pd-card" style="text-align:center; padding: 40px 20px; cursor: pointer;"
                onclick="switchTab(event, 'reports')">
                <i class="fas fa-file-alt" style="font-size:3em; color:var(--warning); margin-bottom:15px;"></i>
                <h4>Medical Reports</h4>
                <p>Access your medical history</p>
            </div>
        </div>
    </div>

    <!-- Book Appointment Tab -->
    <div id="tab-book" class="pd-content">
        <h2>Book an Appointment</h2>

        <div class="pd-filter-bar" style="display:flex; flex-wrap:wrap; gap:10px;">
            <div class="pd-form-group" style="flex:1; min-width: 200px;">
                <label>Speciality</label>
                <select id="filter-speciality" class="pd-input" onchange="loadDoctors()">
                    <option value="">All Specialities</option>
                    <option value="Cardiologist">Cardiologist</option>
                    <option value="Dermatologist">Dermatologist</option>
                    <option value="Pediatrician">Pediatrician</option>
                    <option value="Neurologist">Neurologist</option>
                    <option value="General">General</option>
                </select>
            </div>
            <div class="pd-form-group" style="flex:1; min-width: 150px;">
                <label>Province</label>
                <select id="filter-province" class="pd-input" onchange="updateSearchCities(); loadDoctors();">
                    <option value="">All Provinces</option>
                    <option value="Koshi">Koshi</option>
                    <option value="Madhesh">Madhesh</option>
                    <option value="Bagmati">Bagmati</option>
                    <option value="Gandaki">Gandaki</option>
                    <option value="Lumbini">Lumbini</option>
                    <option value="Karnali">Karnali</option>
                    <option value="Sudurpaschim">Sudurpaschim</option>
                </select>
            </div>
            <div class="pd-form-group" style="flex:1; min-width: 150px;">
                <label>City</label>
                <select id="filter-city" class="pd-input" onchange="loadDoctors()">
                    <option value="">All Cities</option>
                </select>
            </div>
            <div class="pd-form-group" style="flex:1; min-width: 200px;">
                <label>Doctor Name</label>
                <input type="text" id="filter-doctor" class="pd-input" placeholder="Search Doctor..."
                    onkeyup="loadDoctors()">
            </div>
            <div class="pd-form-group" style="flex:1; min-width: 200px;">
                <label>Hospital</label>
                <select id="filter-hospital" class="pd-input" onchange="loadDoctors()">
                    <option value="">All Hospitals</option>
                </select>
            </div>
            <button class="pd-btn" onclick="loadDoctors()" style="height: 42px; margin-top:22px;"><i
                    class="fas fa-search"></i> Search</button>
        </div>

        <div id="doctors-list" class="pd-grid">
            <!-- Populated via JS -->
        </div>
    </div>

    <!-- My Appointments Tab -->
    <div id="tab-appointments" class="pd-content">
        <h2>My Appointments</h2>
        <div id="appointments-list" class="pd-grid">
            <!-- Populated via JS -->
        </div>
    </div>

    <!-- Reports Tab -->
    <div id="tab-reports" class="pd-content">
        <h2>Medical Reports</h2>
        <div id="reports-list" class="pd-grid">
            <!-- Populated via JS -->
        </div>
    </div>

    <!-- Profile Tab -->
    <div id="tab-profile" class="pd-content">
        <h2>My Profile</h2>
        <div class="pd-card" style="margin-bottom: 25px;">
            <h4><i class="fas fa-user-edit"></i> Personal Details</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">Keep your contact information updated.</p>
            <?php
            $stmtPhone = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
            $stmtPhone->execute([$_SESSION['user_id']]);
            $currentPhone = $stmtPhone->fetchColumn() ?: '';
            ?>
            <form action="../logic/common/update_profile.php" method="POST" class="auth-form">
                <div class="pd-form-group" style="margin-bottom: 15px;">
                    <label>Full Name</label>
                    <input type="text" name="name" class="pd-input"
                        value="<?php echo htmlspecialchars($_SESSION['name']); ?>" required>
                </div>
                <div class="pd-form-group" style="margin-bottom: 15px;">
                    <label>Email Address</label>
                    <input type="email" name="email" class="pd-input"
                        value="<?php echo htmlspecialchars($_SESSION['email']); ?>"
                        pattern="[a-zA-Z0-9]+@(gmail\.com|outlook\.com|yahoo\.com|hotmail\.com|yopmail\.com)"
                        title="Alphanumeric username + @gmail/outlook/yahoo/hotmail/yopmail.com" required>
                </div>
                <div class="pd-form-group" style="margin-bottom: 20px;">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="pd-input"
                        value="<?php echo htmlspecialchars($currentPhone); ?>" pattern="9[0-9]{9}"
                        title="Exactly 10 digits starting with 9">
                </div>
                <button type="submit" class="pd-btn" style="width:100%;"><i class="fas fa-save"></i> Save
                    Changes</button>
            </form>
        </div>
        <div class="pd-card">
            <h4><i class="fas fa-image"></i> Profile Picture</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">Upload a new image to personalize your account.</p>
            <form action="../logic/common/update_photo.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="profile_photo" accept="image/*" class="pd-input" required
                    style="margin-bottom:15px; padding: 10px; background: var(--input-bg);">
                <button type="submit" class="pd-btn" style="background:var(--success); width:100%;"><i
                        class="fas fa-upload"></i> Upload Photo</button>
            </form>
        </div>

        <div class="pd-card" style="margin-top: 25px;">
            <h4><i class="fas fa-heartbeat"></i> Health Profile</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">Provide your health metrics for better consultation.
            </p>
            <?php
            $stmtHealth = $pdo->prepare("SELECT * FROM patient_info WHERE user_id = ?");
            $stmtHealth->execute([$_SESSION['user_id']]);
            $hInfo = $stmtHealth->fetch() ?: [];
            ?>
            <form onsubmit="updateHealthInfo(event)">
                <div class="pd-grid" style="grid-template-columns: 1fr 1fr 1fr; gap:15px; margin-bottom: 15px;">
                    <div class="pd-form-group">
                        <label>Age</label>
                        <input type="number" id="h-age" class="pd-input"
                            value="<?php echo (int) ($hInfo['age'] ?? 0); ?>" min="0">
                    </div>
                    <div class="pd-form-group">
                        <label>Height (cm/ft)</label>
                        <input type="text" id="h-height" class="pd-input"
                            value="<?php echo htmlspecialchars($hInfo['height'] ?? ''); ?>" placeholder="e.g. 175cm">
                    </div>
                    <div class="pd-form-group">
                        <label>Weight (kg)</label>
                        <input type="number" id="h-weight" class="pd-input"
                            value="<?php echo (int) ($hInfo['weight'] ?? 0); ?>" min="0">
                    </div>
                </div>
                <div class="pd-form-group" style="margin-bottom: 15px;">
                    <label>Currently Taking Medications</label>
                    <textarea id="h-meds" class="pd-input" rows="2"
                        placeholder="List your current medications..."><?php echo htmlspecialchars($hInfo['medications'] ?? ''); ?></textarea>
                </div>
                <div class="pd-form-group" style="margin-bottom: 20px;">
                    <label>Medical History / Allergies</label>
                    <textarea id="h-history" class="pd-input" rows="2"
                        placeholder="e.g. Asthma, Penicillin allergy..."><?php echo htmlspecialchars($hInfo['medical_history'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="pd-btn" style="width:100%;"><i class="fas fa-save"></i> Update Health
                    Info</button>
            </form>
        </div>

        <div class="pd-card" style="margin-top: 25px; margin-bottom: 25px;">
            <h4><i class="fas fa-file-upload"></i> Previous Medical Records (PDF)</h4>
            <p style="color:var(--text-muted); margin-bottom:15px;">Upload consultation papers or lab reports.</p>
            <form onsubmit="uploadHealthFile(event)" style="margin-bottom: 20px;">
                <input type="file" id="h-file" accept=".pdf" class="pd-input" required
                    style="margin-bottom:10px; width:100%; background: var(--input-bg);">
                <button type="submit" class="pd-btn" style="width:100%; background: var(--success);"><i
                        class="fas fa-upload"></i> Upload Record</button>
            </form>
            <div id="health-files-list">
                <!-- Loaded via JS -->
            </div>
        </div>

        <!-- Account Deletion Section -->
        <div class="pd-card" style="margin-top: 50px; border: 1px solid #fee2e2; background: #fffafb;">
            <h4 style="color: #991b1b;"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h4>
            <p style="color: #7f1d1d; font-size: 0.9em; margin-bottom: 20px;">
                Permanently delete your account and all associated data. This action is irreversible.
            </p>
            <button class="pd-btn pd-btn-danger"
                style="width: 100%; height: 45px; display: flex; align-items: center; justify-content: center; gap: 10px;"
                onclick="openDeleteModal()">
                <i class="fas fa-user-slash"></i> Delete My Account
            </button>
        </div>
    </div>
</div>



<script>
    // --- UI Navigation ---
    function goToProfile() {
        const tabs = document.querySelectorAll('.pd-tab');
        if (tabs[4]) tabs[4].click();
    }

    async function updateHealthInfo(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('age', document.getElementById('h-age').value);
        formData.append('height', document.getElementById('h-height').value);
        formData.append('weight', document.getElementById('h-weight').value);
        formData.append('medications', document.getElementById('h-meds').value);
        formData.append('medical_history', document.getElementById('h-history').value);

        try {
            const res = await fetch('../logic/patient/actions.php?action=update_health_info', { method: 'POST', body: formData });
            const result = await res.json();
            showToast(result.message || result.status, 'success');
        } catch (e) { showToast("Error saving health info", 'error'); }
    }

    async function uploadHealthFile(e) {
        e.preventDefault();
        const fileInput = document.getElementById('h-file');
        if (fileInput.files.length === 0) return;

        const formData = new FormData();
        formData.append('medical_file', fileInput.files[0]);

        try {
            const res = await fetch('../logic/patient/actions.php?action=upload_health_file', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
                fileInput.value = '';
                loadHealthFiles();
            } else { showToast(result.message, 'error'); }
        } catch (e) { showToast("Error uploading file", 'error'); }
    }

    async function loadHealthFiles() {
        try {
            const res = await fetch('../logic/patient/actions.php?action=get_health_files');
            const files = await res.json();
            const container = document.getElementById('health-files-list');
            if (!files || files.length === 0) {
                container.innerHTML = '<p style="color:#aaa; font-size:0.9em;">No records uploaded yet.</p>';
                return;
            }
            container.innerHTML = files.map(f => `
                <div style="display:flex; justify-content:space-between; align-items:center; background:var(--input-bg); padding:10px; border-radius:5px; margin-bottom:8px; border: 1px solid var(--border-color);">
                    <span style="font-size:0.9em;"><i class="fas fa-file-pdf" style="color:#ef4444;"></i> ${f.file_name}</span>
                    <div style="display:flex; gap:10px;">
                        <a href="../${f.file_path}" target="_blank" class="pd-btn" style="padding:5px 10px; font-size:0.8em; text-decoration:none;">View</a>
                        <button onclick="deleteHealthFile(${f.id})" class="pd-btn pd-btn-danger" style="padding:5px 10px; font-size:0.8em;"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `).join('');
        } catch (e) { console.error(e); }
    }

    async function deleteHealthFile(id) {
        const confirmed = await showConfirm({
            title: 'Delete Record?',
            message: 'Permanent delete this medical record?',
            confirmText: 'Delete',
            type: 'danger'
        });
        if (!confirmed) return;
        const formData = new FormData();
        formData.append('file_id', id);
        try {
            const res = await fetch('../logic/patient/actions.php?action=delete_health_file', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
                loadHealthFiles();
            } else { showToast(result.message, 'error'); }
        } catch (e) { showToast("Error deleting record", 'error'); }
    }





    async function loadAppointments() {
        try {
            const res = await fetch('../logic/patient/actions.php?action=get_appointments');
            const appts = await res.json();
            const container = document.getElementById('appointments-list');

            if (!appts || appts.length === 0) {
                container.innerHTML = `<div class="empty-state"><i class="fas fa-calendar"></i><br>You have no appointments.</div>`;
                return;
            }

            container.innerHTML = appts.map(a => {
                const isInactive = (a.doctor_status === 'inactive' || (a.hospital_name && a.hospital_status === 'inactive'));
                const showWarning = isInactive && (a.status === 'scheduled' || a.status === 'reschedule_requested' || a.status === 'pending');

                const apptTime = new Date(`${a.appointment_date} ${a.appointment_time}`).getTime();
                const now = new Date().getTime();
                const diff = (apptTime - now) / 1000;
                const isLocked = diff < 7200; // 2 hours

                return `
                <div class="pd-card" style="${showWarning ? 'border: 1px solid #fee2e2; background: #fffcfc;' : ''}">
                    <span class="badge badge-${a.status === 'reschedule_requested' ? 'warning' : a.status}" style="${a.status === 'reschedule_requested' ? 'background:#ff9800; color:#fff;' : ''}">${a.status === 'reschedule_requested' ? 'RESCHEDULE PENDING' : a.status.toUpperCase()}</span>
                    
                    ${isLocked && a.status === 'scheduled' ? `<span class="badge" style="background:#6c757d; color:#fff; top: 45px;"><i class="fas fa-lock"></i> LOCKED</span>` : ''}

                    ${showWarning ? `
                        <div style="margin: 10px 0; padding: 10px; background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 4px;">
                            <p style="margin:0; color:#991b1b; font-size:0.85em; font-weight:600;">
                                <i class="fas fa-exclamation-triangle"></i> Service Provider Currently Inactive
                            </p>
                            <p style="margin:3px 0 0 0; color:#b91c1c; font-size:0.8em;">
                                The ${a.doctor_status === 'inactive' ? 'doctor' : 'hospital'} is currently unavailable. Please contact support or check back later.
                            </p>
                        </div>
                    ` : ''}

                    <h4>Dr. ${a.doctor_name} <span style="font-size:0.8em; color:var(--text-muted);">(${a.speciality})</span></h4>
                    <p><strong><i class="far fa-calendar-alt"></i> Date:</strong> ${a.appointment_date}</p>
                    <p><strong><i class="far fa-clock"></i> Time:</strong> ${a.appointment_time.slice(0, 5)}</p>
                    <p><strong><i class="fas fa-hospital"></i> Location:</strong> ${a.hospital_name || 'Independent Clinic'}</p>
                    <p><strong><i class="fas fa-info-circle"></i> Reason:</strong> ${a.reason}</p>
                    
                    ${a.status === 'reschedule_requested' ? `
                        <div style="margin-top:15px; padding:10px; background:#fff3e0; border-left:4px solid #ff9800; border-radius:4px;">
                            <p style="margin:0; font-size:0.9em; color:#e65100;"><strong><i class="fas fa-hourglass-half"></i> Request Pending:</strong> Waiting for doctor approval to reschedule to ${a.requested_date} at ${a.requested_time ? a.requested_time.slice(0, 5) : ''}</p>
                        </div>
                    ` : ''}

                    <div style="display:flex; flex-direction:column; gap:8px; margin-top: 15px;">
                        <button class="pd-btn" style="width:100%;" onclick="showBill('${a.booking_id}', 'Dr. ${escapeQuotes(a.doctor_name)}', '${a.appointment_date} at ${a.appointment_time.slice(0, 5)}', 'Rs. 1000')">
                            <i class="fas fa-file-invoice"></i> View Bill
                        </button>
                        
                        ${a.status === 'scheduled' && !isInactive && !isLocked ? `
                            <div style="display:flex; gap:10px;">
                                <button class="pd-btn" style="flex:1; background:var(--primary-color); color:white;" onclick="rescheduleAppt(${a.id})"><i class="fas fa-calendar-alt"></i> Reschedule</button>
                                <button class="pd-btn pd-btn-danger" style="flex:1;" onclick="cancelAppt(${a.id})">Cancel</button>
                            </div>
                        ` : (a.status === 'scheduled' && isLocked) ? `
                            <div style="padding:10px; background: #f8fafc; border-radius: 6px; text-align:center; font-size: 0.85em; color: #64748b; border: 1px dashed #cbd5e1;">
                                <i class="fas fa-lock"></i> Scheduling locked (within 2 hours)
                            </div>
                        ` : (a.status === 'scheduled' && isInactive) ? `
                            <button class="pd-btn pd-btn-danger" style="width:100%;" onclick="cancelAppt(${a.id})">Cancel Appointment</button>
                        ` : ''}
                    </div>
                </div>`;
            }).join('');
        } catch (err) { console.error('Error loading appointments:', err); }
    }

    async function loadReports() {
        try {
            const res = await fetch('../logic/patient/actions.php?action=get_reports');
            const reports = await res.json();
            const container = document.getElementById('reports-list');

            if (!reports || reports.length === 0) {
                container.innerHTML = `<div class="empty-state"><i class="fas fa-file-medical"></i><br>No reports available yet.</div>`;
                return;
            }

            container.innerHTML = reports.map(r => `
            <div class="pd-card report-card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                    <h4 style="margin:0; color:var(--primary-color);">Medical Report</h4>
                    <span style="font-size:0.8em; color:var(--text-muted);"><i class="far fa-calendar-alt"></i> ${r.created_at}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Doctor:</span>
                    <span class="detail-value">Dr. ${r.doctor_name}</span>
                </div>
                ${r.appointment_date ? `
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">${r.appointment_date}</span>
                </div>` : ''}
                <hr style="border:0; border-top:1px solid var(--border-color); margin:15px 0;">
                
                <div style="margin-bottom:15px;">
                    <h5 style="margin:0 0 5px 0; color:var(--text-main);"><i class="fas fa-diagnoses"></i> Diagnosis</h5>
                    <p style="margin:0; color:var(--text-muted); font-style:italic;">${r.diagnosis}</p>
                </div>
                
                <div style="margin-bottom:15px;">
                    <h5 style="margin:0 0 5px 0; color:var(--text-main);"><i class="fas fa-notes-medical"></i> Clinical Notes</h5>
                    <p style="margin:0; color:var(--text-muted);">${r.report_details}</p>
                </div>
                
                <div style="padding:15px; background:var(--input-bg); border-radius:8px; border-left:4px solid var(--primary-color); margin-bottom:15px;">
                    <h5 style="margin:0 0 5px 0; color:var(--primary-color);"><i class="fas fa-prescription"></i> Prescription</h5>
                    <p style="margin:0; color:var(--text-main); white-space: pre-wrap;">${r.prescription}</p>
                </div>

                <div style="display:flex; gap:10px; margin-top:15px;">
                    <a href="../logic/doctor/download_report.php?id=${r.id}" target="_blank" class="pd-btn" style="flex:2; text-decoration:none; display:inline-block; text-align:center; background:var(--primary-color);">
                        <i class="fas fa-download"></i> Download / Print
                    </a>
                    <button class="pd-btn" style="flex:0.5; background:#f1f5f9; color:#475569;" onclick="openHistoryModal(${r.id})" title="View Version History">
                        <i class="fas fa-history"></i>
                    </button>
                </div>
            </div>
        `).join('');
        } catch (err) { console.error('Error loading reports:', err); }
    }


    async function rescheduleAppt(id) {
        // Fetch current appointment to get doctor info
        try {
            const res = await fetch('../logic/patient/actions.php?action=get_appointments');
            const appts = await res.json();
            const appt = appts.find(a => a.id == id);
            if (!appt) return;

            document.getElementById('reschedule-appt-id').value = id;
            document.getElementById('reschedule-doctor-id').value = appt.doctor_id;
            document.getElementById('reschedule-date').value = appt.appointment_date;

            document.getElementById('reschedule-modal').classList.add('active');
            updateRescheduleSlots();
        } catch (e) { console.error(e); }
    }

    function closeRescheduleModal() {
        document.getElementById('reschedule-modal').classList.remove('active');
    }

    async function updateRescheduleSlots() {
        const docId = document.getElementById('reschedule-doctor-id').value;
        const timeSelect = document.getElementById('reschedule-time');
        timeSelect.innerHTML = '<option value="">Loading slots...</option>';

        try {
            const res = await fetch(`../logic/patient/actions.php?action=get_doctor_availability&doctor_id=${docId}`);
            const data = await res.json();

            if (data.status === 'success') {
                const start = data.start_time || '09:00';
                const end = data.end_time || '17:00';
                const slots = generateTimeSlots(start, end);

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
        } catch (e) { console.error(e); }
    }

    async function submitReschedule(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('appointment_id', document.getElementById('reschedule-appt-id').value);
        fd.append('appointment_date', document.getElementById('reschedule-date').value);
        fd.append('appointment_time', document.getElementById('reschedule-time').value);

        try {
            const res = await fetch('../logic/patient/actions.php?action=reschedule_appointment', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
                closeRescheduleModal();
                loadAppointments();
            } else {
                showToast(result.message || 'Reschedule failed', 'error');
            }
        } catch (e) { showToast("Connection error", 'error'); }
    }

    async function openHistoryModal(reportId) {
        const container = document.getElementById('history-list');
        container.innerHTML = '<p style="text-align:center; padding:20px; color:#666;">Loading report history...</p>';
        document.getElementById('history-modal').classList.add('active');

        try {
            const res = await fetch(`../logic/patient/actions.php?action=get_report_history&report_id=${reportId}`);
            const history = await res.json();

            if (!history || history.length === 0) {
                container.innerHTML = '<p style="text-align:center; padding:20px; color:#666;">No past versions found.</p>';
                return;
            }

            container.innerHTML = history.map((h, i) => `
                <div style="margin-bottom:20px; padding:15px; background:${i === 0 ? '#f0f9ff' : '#fff'}; border:1px solid ${i === 0 ? '#bae6fd' : '#e2e8f0'}; border-radius:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span style="font-weight:700; color:var(--primary); font-size:0.9em; text-transform:uppercase; letter-spacing:0.5px;">
                            VERSION ${h.version_number} ${i === 0 ? '<span style="background:var(--primary); color:white; padding:2px 8px; border-radius:4px; font-size:0.75em; margin-left:5px; vertical-align:middle;">CURRENT</span>' : ''}
                        </span>
                        <span style="font-size:0.85em; color:var(--text-muted);"><i class="far fa-clock"></i> ${new Date(h.created_at).toLocaleString()}</span>
                    </div>
                    <div style="margin-bottom:10px;">
                        <h5 style="margin:0 0 5px 0; color:var(--text-main); font-size:0.95em;"><i class="fas fa-diagnoses"></i> Diagnosis</h5>
                        <p style="margin:0; font-size:0.9em; color:#444;">${h.diagnosis}</p>
                    </div>
                    <div style="margin-bottom:10px;">
                        <h5 style="margin:0 0 5px 0; color:var(--text-main); font-size:0.95em;"><i class="fas fa-notes-medical"></i> Clinical Notes</h5>
                        <p style="margin:0; font-size:0.9em; color:#444;">${h.report_details}</p>
                    </div>
                    <div style="margin-top:12px; padding:12px; background:#f8fafc; border-left:4px solid var(--primary); border-radius:4px;">
                        <h5 style="margin:0 0 5px 0; color:var(--primary); font-size:0.9em;"><i class="fas fa-prescription"></i> Prescription</h5>
                        <p style="margin:0; font-size:0.9em; color:#1e293b; white-space: pre-wrap;">${h.prescription}</p>
                    </div>
                </div>
            `).join('');
        } catch (e) {
            container.innerHTML = '<p style="color:#ef4444; text-align:center; padding:20px;"><i class="fas fa-exclamation-circle"></i> Error loading history</p>';
        }
    }

    async function cancelAppt(id) {
        const confirmed = await showConfirm({
            title: 'Cancel Appointment?',
            message: 'Are you sure you want to cancel this appointment?',
            confirmText: 'Yes, Cancel',
            type: 'danger'
        });
        if (!confirmed) return;

        const formData = new FormData();
        formData.append('appointment_id', id);

        try {
            const res = await fetch('../logic/patient/actions.php?action=cancel_appointment', {
                method: 'POST',
                body: formData
            });
            const result = await res.json();

            if (result.status === 'success') {
                showToast(result.message, 'success');
                loadAppointments();
            } else {
                showToast(result.message || result.error, 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Error cancelling appointment.', 'error');
        }
    }


    // --- Account Deletion Logic ---
    function openDeleteModal() {
        document.getElementById('delete-confirm-input').value = '';
        document.getElementById('delete-error').style.display = 'none';
        document.getElementById('delete-modal').classList.add('active');
    }

    function closeDeleteModal() {
        document.getElementById('delete-modal').classList.remove('active');
    }

    async function submitDeleteAccount() {
        const input = document.getElementById('delete-confirm-input').value.trim().toLowerCase();
        if (input !== 'delete') {
            document.getElementById('delete-error').style.display = 'block';
            return;
        }

        const confirmed2 = await showConfirm({
            title: 'Are you ABSOLUTELY sure?',
            message: 'This action cannot be undone. Do you really want to permanently delete your account?',
            confirmText: 'Confirm Deletion',
            type: 'danger'
        });
        if (!confirmed2) return;

        try {
            let fd = new FormData();
            fd.append('action', 'delete_account');
            const res = await fetch('../logic/patient/actions.php?action=delete_account', { method: 'POST', body: fd });
            const result = await res.json();

            if (result.status === 'success') {
                window.location.href = '../index.php';
            } else {
                showToast(result.message || 'Deletion failed. Please try again.', 'error');
            }
        } catch (e) {
            showToast('An error occurred during deletion connection.', 'error');
        }
    }
</script>
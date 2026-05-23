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
                container.innerHTML = `<div class="empty-state">${(doctors && doctors.error) ? doctors.error : 'No doctors found matching filters.'}</div>`;
                return;
            }

            container.innerHTML = doctors.map(d => `
            <div class="pd-card">
                <div style="display:flex; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:15px;">
                    <img src="../${d.profile_photo || 'assets/img/default.jpeg'}" alt="Profile" class="doctor-img-large">
                    <div style="margin-left:20px;">
                        <h4 style="margin:0; font-size:1.25em; color:var(--text-main);">Dr. ${d.name}</h4>
                        <span style="font-size:0.9em; color:var(--primary); font-weight:600;">${d.speciality}</span>
                        <div style="font-size:0.85em; color:var(--text-muted); margin-top:4px;">${d.qualification || 'MBBS'} &bull; ${d.experience_years || 0} Yrs Experience</div>
                    </div>
                </div>
                <p><strong>NMC:</strong> ${d.nmc_number || 'N/A'}</p>
                <p><strong>Speciality:</strong> ${d.speciality}</p>
                <p><strong>Hospital:</strong> ${d.hospital_name ? d.hospital_name + ' (' + (d.city || d.location || 'Location Not Set') + ', ' + (d.province || '') + ')' : 'Independent Clinic'}</p>
                <p><strong>Timings:</strong> ${d.start_time ? d.start_time.slice(0, 5) : 'N/A'} - ${d.end_time ? d.end_time.slice(0, 5) : 'N/A'} (${d.available_days})</p>
                <button class="pd-btn pd-btn-outline btn-with-arrow" style="margin-top: 15px; width: 100%;" 
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

    // Capture reference to global switchTab before overriding
    const parentSwitchTab = window.switchTab;

    window.switchTab = function (e, tabId) {
        // Use global switchTab if available
        if (typeof parentSwitchTab === 'function') {
            parentSwitchTab(e, tabId);
        } else {
            // Fallback for standalone mode
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
        }

        if (tabId === 'book' || tabId === 'tab-book') loadHospitals().then(() => loadDoctors());
        if (tabId === 'appointments' || tabId === 'tab-appointments') loadAppointments();
        if (tabId === 'reports' || tabId === 'tab-reports') loadReports();
        if (tabId === 'profile' || tabId === 'tab-profile') loadHealthFiles();
        if (tabId === 'ai_assistant' || tabId === 'tab-ai_assistant') renderChatMessages();
    };
    // window.switchTab is now fully configured above and will be used by onclick handlers

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
            if (result.status === 'stripe_redirect') {
                showToast('Redirecting to secure payment...', 'success');
                setTimeout(() => window.location.href = result.url, 1000);
            } else if (result.status === 'success') {
                closeBookingModal();
                switchTab(null, 'appointments');
                showBill(result.booking_id, 'Dr. ' + result.doctor_name, result.appointment_date + ' at ' + result.appointment_time.slice(0, 5), result.hospital_name || 'Independent Clinic', result.fee || '<?php echo STRIPE_FEE_LABEL; ?>');
            } else {
                const errDiv = document.getElementById('booking-error');
                errDiv.textContent = result.message || result.error;
                errDiv.style.display = 'block';
            }
        } catch (err) { console.error(err); }
    }
    window.submitBooking = submitBooking;

    function showBill(bookingId, doctorName, datetime, hospitalName, fee, isPaid = false) {
        document.getElementById('bill-id').textContent = bookingId || 'N/A';
        document.getElementById('bill-patient').textContent = <?php echo json_encode($_SESSION['name']); ?>;
        document.getElementById('bill-doctor').textContent = doctorName;
        document.getElementById('bill-hospital-name').textContent = hospitalName;
        document.getElementById('bill-datetime').textContent = datetime;
        // Display fee cleanly, assuming it's already a well-formatted string or needs 'NPR' prefix
        let formattedFee = fee;
        if (typeof fee === 'number' || (typeof fee === 'string' && !fee.startsWith('NPR') && !fee.startsWith('Rs.'))) {
            formattedFee = 'NPR ' + (typeof fee === 'number' ? fee.toFixed(2) : parseFloat(fee).toFixed(2));
        }
        if (formattedFee === 'NPR NaN') formattedFee = fee; // Fallback if parsing fails
        document.getElementById('bill-fee').textContent = formattedFee;

        const noteEl = document.getElementById('bill-note');
        if (isPaid) {
            noteEl.innerHTML = 'This is a digital receipt for your payment.<br>Your appointment is confirmed.';
            noteEl.style.color = 'var(--success)';
        } else {
            noteEl.innerHTML = 'Please pay the fee to confirm your booking.<br>Your appointment is currently pending payment.';
            noteEl.style.color = '#888';
        }

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
    async function retryPayment(bookingId) {
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        try {
            const res = await fetch('../logic/patient/actions.php?action=retry_payment', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.status === 'stripe_redirect') {
                showToast('Redirecting to secure payment...', 'success');
                setTimeout(() => window.location.href = result.url, 1000);
            } else { showToast(result.message || 'Error initiating payment.', 'error'); }
        } catch (err) { console.error(err); showToast('Connection error.', 'error'); }
    }

    async function refundAppt(id) {
        const confirmed = await showConfirm({
            title: 'Refund & Cancel?',
            message: 'Are you sure you want to cancel this appointment and request a refund?',
            confirmText: 'Yes, Refund',
            type: 'danger'
        });
        if (!confirmed) return;
        const formData = new FormData();
        formData.append('appointment_id', id);
        try {
            const res = await fetch('../logic/patient/actions.php?action=refund_appointment', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.status === 'success') {
                showToast(result.message, 'success');
                loadAppointments();
            } else { showToast(result.message || 'Refund failed.', 'error'); }
        } catch (err) { console.error(err); showToast('Error processing refund.', 'error'); }
    }

    window.addEventListener('load', async () => {
        const urlParams = new URLSearchParams(window.location.search);
        const paymentStatus = urlParams.get('payment');
        const bookingId = urlParams.get('booking_id');

        if (paymentStatus === 'success') {
            window.history.replaceState({}, document.title, window.location.pathname);
            if (typeof loadAppointments === 'function') loadAppointments();

            if (bookingId) {
                try {
                    const res = await fetch('../logic/patient/actions.php?action=get_appointments');
                    const appts = await res.json();
                    const a = appts.find(item => item.booking_id === bookingId);
                    if (a) {
                        let finalFeeToDisplay = a.final_amount_npr ? 'NPR ' + Number(a.final_amount_npr).toFixed(2) : (a.amount_paid || '<?php echo STRIPE_FEE_LABEL; ?>');
                        showBill(a.booking_id, 'Dr. ' + a.doctor_name, a.appointment_date + ' at ' + a.appointment_time.slice(0, 5), a.hospital_name || 'Independent Clinic', finalFeeToDisplay, true);
                        showToast('Payment successful! Your appointment is confirmed.', 'success');
                    }
                } catch (e) { console.error(e); }
            } else {
                showToast('Payment successful! Your appointment is confirmed.', 'success');
            }
        } else if (paymentStatus === 'cancelled') {
            showToast('Payment was cancelled.', 'warning');
            window.history.replaceState({}, document.title, window.location.pathname);
            if (typeof loadAppointments === 'function') loadAppointments();
        }
    });
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

            <button type="submit" class="pd-btn pd-btn-outline" style="width: 100%;">Confirm Booking</button>
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
        <button class="pd-btn pd-btn-outline-danger"
            style="width: 100%; height: 45px; display: flex; align-items: center; justify-content: center; gap: 8px;"
            onclick="submitDeleteAccount()">
            Permanently Delete My Account
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
                <button type="submit" class="pd-btn pd-btn-outline" style="flex:2;">Confirm Reschedule</button>
            </div>
        </form>
    </div>
</div>

<div id="bill-modal" class="pd-modal-overlay">
    <div class="pd-modal" style="max-width: 500px;">
        <div class="pd-modal-header" style="border-bottom:none;">
            <h3 style="margin:0;">Booking Invoice</h3>
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
                    <th style="padding:8px 0;">Hospital:</th>
                    <td id="bill-hospital-name" style="text-align:right;"></td>
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
            <p id="bill-note"
                style="margin-top:20px; font-size:0.85em; color:#888; text-align:center; line-height:1.4;">Please
                present
                this booking ID at the hospital counter to pay the fee.<br>Keep this document safe.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="pd-btn pd-btn-outline" style="flex:1;" onclick="printBill()">Print / Download</button>
            <button class="pd-btn pd-btn-outline-danger" style="flex:1;" onclick="closeBillModal()">Close</button>
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
            <div class="pd-card card-with-arrow"
                style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px 20px; cursor: pointer;"
                onclick="switchTab(event, 'book')">
                <h4 style="padding-right: 0;">Find a Doctor</h4>
                <p>Browse by speciality or hospital</p>
            </div>
            <div class="pd-card card-with-arrow"
                style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px 20px; cursor: pointer;"
                onclick="switchTab(event, 'appointments')">
                <h4 style="padding-right: 0;">My Appointments</h4>
                <p>View or cancel upcoming visits</p>
            </div>
            <div class="pd-card card-with-arrow"
                style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px 20px; cursor: pointer;"
                onclick="switchTab(event, 'reports')">
                <h4 style="padding-right: 0;">Medical Reports</h4>
                <p>Access your medical history</p>
            </div>
        </div>
    </div>

    <!-- Book Appointment Tab -->
    <div id="tab-book" class="pd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'dashboard')">Back to Overview</a>
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
                    oninput="loadDoctors()">
            </div>
            <div class="pd-form-group" style="flex:1; min-width: 200px;">
                <label>Hospital</label>
                <select id="filter-hospital" class="pd-input" onchange="loadDoctors()">
                    <option value="">All Hospitals</option>
                </select>
            </div>
            <button class="pd-btn pd-btn-outline" onclick="loadDoctors()"
                style="height: 42px; margin-top:22px;">Search</button>
        </div>

        <div id="doctors-list" class="pd-grid">
            <!-- Populated via JS -->
        </div>
    </div>

    <!-- My Appointments Tab -->
    <div id="tab-appointments" class="pd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'dashboard')">Back to Overview</a>
        <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
            <h2 style="margin: 0;">My Appointments</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <select id="filter-status" class="pd-input"
                    style="flex: 1; min-width: 150px; padding: 8px 12px; border-radius: 6px; font-size: 0.9em; border: 1px solid var(--gray-300);"
                    onchange="renderAppointments()">
                    <option value="all">All Statuses</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="missed">Missed</option>
                    <option value="reschedule_requested">Reschedule Pending</option>
                    <option value="pending_payment">Pending Payment</option>
                </select>

                <select id="filter-payment" class="pd-input"
                    style="flex: 1; min-width: 150px; padding: 8px 12px; border-radius: 6px; font-size: 0.9em; border: 1px solid var(--gray-300);"
                    onchange="renderAppointments()">
                    <option value="all">All Payments</option>
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                </select>

                <select id="sort-date" class="pd-input"
                    style="flex: 1; min-width: 150px; padding: 8px 12px; border-radius: 6px; font-size: 0.9em; border: 1px solid var(--gray-300);"
                    onchange="renderAppointments()">
                    <option value="date_desc">Date (Newest First)</option>
                    <option value="date_asc">Date (Oldest First)</option>
                </select>
            </div>
        </div>
        <div id="appointments-list" class="pd-grid">
            <!-- Populated via JS -->
        </div>
    </div>

    <!-- Reports Tab -->
    <div id="tab-reports" class="pd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'dashboard')">Back to Overview</a>
        <h2>Medical Reports</h2>
        <div id="reports-list" class="pd-grid">
            <!-- Populated via JS -->
        </div>
    </div>

    <!-- Profile Tab -->
    <div id="tab-profile" class="pd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'dashboard')"><i class="fas fa-arrow-left"></i> Back to
            Overview</a>
        <h2>My Profile</h2>
        <div class="pd-card" style="margin-bottom: 25px;">
            <h4>Personal Details</h4>
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
                    <label>Email Address
                        <?php if (isset($_SESSION['is_verified']) && $_SESSION['is_verified'] == 1): ?>
                            <span class="badge"
                                style="background: #2ecc71; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; margin-left: 5px; vertical-align: middle;">Verified</span>
                        <?php else: ?>
                            <span class="badge"
                                style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; margin-left: 5px; vertical-align: middle;">Unverified</span>
                        <?php endif; ?>
                    </label>
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
                <button type="submit" class="pd-btn pd-btn-outline" style="width:100%;">Save Changes</button>
            </form>
        </div>
        <div class="pd-card">
            <h4>Profile Picture</h4>
            <p style="color:var(--text-muted); margin-bottom:20px;">Upload a new image to personalize your account.</p>
            <form action="../logic/common/update_photo.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="profile_photo" accept="image/*" class="pd-input" required
                    style="margin-bottom:15px; padding: 10px; background: var(--input-bg);">
                <button type="submit" class="pd-btn pd-btn-outline" style="width:100%;">Upload Photo</button>
            </form>
        </div>

        <div class="pd-card" style="margin-top: 25px;">
            <h4>Health Profile</h4>
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
                <button type="submit" class="pd-btn pd-btn-outline" style="width:100%;">Update Health Info</button>
            </form>
        </div>

        <div class="pd-card" style="margin-top: 25px; margin-bottom: 25px;">
            <h4>Previous Medical Records (PDF)</h4>
            <p style="color:var(--text-muted); margin-bottom:15px;">Upload consultation papers or lab reports.</p>
            <form onsubmit="uploadHealthFile(event)" style="margin-bottom: 20px;">
                <input type="file" id="h-file" accept=".pdf" class="pd-input" required
                    style="margin-bottom:10px; width:100%; background: var(--input-bg);">
                <button type="submit" class="pd-btn pd-btn-outline" style="width:100%;">Upload Record</button>
            </form>
            <div id="health-files-list">
                <!-- Loaded via JS -->
            </div>
        </div>

        <!-- Account Deletion Section -->
        <div class="pd-card" style="margin-top: 50px; border: 1px solid #fee2e2; background: #fffafb;">
            <h4 style="color: #991b1b;">Danger Zone</h4>
            <p style="color: #7f1d1d; font-size: 0.9em; margin-bottom: 20px;">
                Permanently delete your account and all associated data. This action is irreversible.
            </p>
            <button class="pd-btn pd-btn-outline-danger"
                style="width: 100%; height: 45px; display: flex; align-items: center; justify-content: center; gap: 10px;"
                onclick="openDeleteModal()">
                Delete My Account
            </button>
        </div>
    </div>

    <!-- AI Symptom Checker Tab -->
    <div id="tab-ai_assistant" class="pd-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'dashboard')"
            style="display:inline-flex; align-items:center; gap:5px; text-decoration:none; color:var(--primary); font-size:0.9em; margin-bottom:15px;"><i
                class="fas fa-arrow-left"></i> Back to Overview</a>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h2 style="margin:0;">AI Symptom Checker</h2>
        </div>

        <div class="ai-chat-layout">
            <!-- Sidebar with quick info and disclaimer -->
            <div class="ai-chat-sidebar">
                <div class="ai-card ai-info-card">
                    <h4>How it works</h4>
                    <p>1. Type your symptoms in the chat.</p>
                    <p>2. The AI matches your symptoms to the correct medical specialty.</p>
                    <p>3. Browse recommended specialists and direct appointment slots.</p>
                </div>

                <div class="ai-card ai-disclaimer-card">
                    <h4 style="color:var(--warning);"><i class="fas fa-exclamation-triangle"></i> Medical Disclaimer
                    </h4>
                    <p>This automated symptom analysis is for informational purposes only. It is not a substitute for
                        professional medical advice, diagnosis, or treatment. Always consult a qualified physician for
                        any health issues.</p>
                </div>
            </div>

            <!-- Immersive Chat Feed -->
            <div class="ai-chat-feed-container">
                <div id="ai-chat-messages" class="ai-chat-messages">
                    <!-- Loaded dynamically -->
                </div>

                <!-- Quick Symptom Chips -->
                <div class="ai-quick-symptoms">
                    <span class="quick-chip-label">Quick Symptoms:</span>
                    <button class="quick-chip"
                        onclick="sendQuickSymptom('I have chest tightness and a rapid heart rate')">🫀 Chest
                        Pain</button>
                    <button class="quick-chip"
                        onclick="sendQuickSymptom('I am experiencing a severe headache, migraine and dizziness')">🧠
                        Severe Headache</button>
                    <button class="quick-chip"
                        onclick="sendQuickSymptom('I have bone fractures and severe joint pain in my knee')">🦴
                        Joint/Bone Pain</button>
                    <button class="quick-chip"
                        onclick="sendQuickSymptom('I have an itchy red skin rash and skin allergies')">🧴 Skin
                        Rash</button>
                    <button class="quick-chip"
                        onclick="sendQuickSymptom('I have a high fever, dry cough and sore throat')">🤒 Fever &
                        Cold</button>
                </div>

                <!-- Input Area -->
                <form id="ai-chat-input-form" onsubmit="handleAiChatSubmit(event)" class="ai-chat-input-form">
                    <input type="text" id="ai-chat-input" class="pd-input"
                        placeholder="Describe your symptoms in detail (e.g., chest pain, severe headache)..."
                        autocomplete="off" required>
                    <button type="submit" class="pd-btn pd-btn-outline" style="border-radius: var(--radius-sm);"><i
                            class="fas fa-paper-plane"></i></button>
                </form>
            </div>
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
                        <button onclick="deleteHealthFile(${f.id})" class="pd-btn pd-btn-danger" style="padding:5px 10px; font-size:0.8em;">Delete</button>
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





    let allPatientAppointments = [];

    async function loadAppointments() {
        try {
            const res = await fetch('../logic/patient/actions.php?action=get_appointments');
            allPatientAppointments = await res.json();

            renderAppointments();
        } catch (err) { console.error('Error loading appointments:', err); }
    }

    function renderAppointments() {
        const container = document.getElementById('appointments-list');
        if (!allPatientAppointments || allPatientAppointments.length === 0) {
            container.innerHTML = `<div class="empty-state">You have no appointments.</div>`;
            return;
        }

        const filterStatus = document.getElementById('filter-status')?.value || 'all';
        const filterPayment = document.getElementById('filter-payment')?.value || 'all';
        const sortDate = document.getElementById('sort-date')?.value || 'date_desc';

        // 1. Filter
        let filteredAppts = allPatientAppointments.filter(a => {
            let statusMatch = (filterStatus === 'all') || (a.status === filterStatus);
            let paymentMatch = (filterPayment === 'all') || (a.payment_status === filterPayment);
            return statusMatch && paymentMatch;
        });

        // 2. Sort
        if (sortDate === 'date_desc') {
            filteredAppts.sort((a, b) => new Date(b.appointment_date + ' ' + b.appointment_time) - new Date(a.appointment_date + ' ' + a.appointment_time));
        } else if (sortDate === 'date_asc') {
            filteredAppts.sort((a, b) => new Date(a.appointment_date + ' ' + a.appointment_time) - new Date(b.appointment_date + ' ' + b.appointment_time));
        }

        if (filteredAppts.length === 0) {
            container.innerHTML = `<div class="empty-state" style="grid-column: 1/-1;">No appointments match your filters.</div>`;
            return;
        }

        container.innerHTML = filteredAppts.map(a => {
            const isInactive = (a.doctor_status === 'inactive' || (a.hospital_name && a.hospital_status === 'inactive'));
            const showWarning = isInactive && (a.status === 'scheduled' || a.status === 'reschedule_requested' || a.status === 'pending');

            const apptTime = new Date(`${a.appointment_date} ${a.appointment_time}`).getTime();
            const now = new Date().getTime();
            const diff = (apptTime - now) / 1000;
            const isLocked = diff < 7200; // 2 hours

            return `
            <div class="pd-card" style="${showWarning ? 'border: 1px solid #fee2e2; background: #fffcfc;' : ''}">
                <div class="pd-card-header">
                    <div class="pd-card-title-group">
                        <h4>Dr. ${a.doctor_name} <span style="font-size:0.8em; color:var(--text-muted);">(${a.speciality})</span></h4>
                    </div>
                    <div class="pd-card-badge-group">
                        ${(!['refunded'].includes(a.status)) ? `
                            ${a.status !== 'pending_payment' ? `<span class="badge badge-${a.status === 'reschedule_requested' ? 'warning' : a.status}" style="${a.status === 'reschedule_requested' ? 'background:#ff9800; color:#fff;' : ''}">${a.status === 'reschedule_requested' ? 'RESCHEDULE PENDING' : a.status.replace('_', ' ').toUpperCase()}</span>` : ''}
                            
                            ${!['completed', 'missed', 'cancelled'].includes(a.status) ? `
                                <span class="badge badge-${a.payment_status === 'paid' ? 'success' : 'pending'}">
                                    ${a.payment_status === 'paid' ? 'PAID' : 'PAYMENT PENDING'}
                                </span>
                            ` : ''}
                        ` : ''}

                        ${isLocked && a.status === 'scheduled' ? `<span class="badge" style="background:#6c757d; color:#fff;">LOCKED</span>` : ''}
                    </div>
                </div>

                ${showWarning ? `
                    <div style="margin: 10px 0; padding: 10px; background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 4px;">
                        <p style="margin:0; color:#991b1b; font-size:0.85em; font-weight:600;">
                            Service Provider Currently Inactive
                        </p>
                        <p style="margin:3px 0 0 0; color:#b91c1c; font-size:0.8em;">
                            The ${a.doctor_status === 'inactive' ? 'doctor' : 'hospital'} is currently unavailable. Please contact support or check back later.
                        </p>
                    </div>
                ` : ''}

                <p><strong>Date:</strong> ${a.appointment_date}</p>
                <p><strong>Time:</strong> ${a.appointment_time.slice(0, 5)}</p>
                <p><strong>Location:</strong> ${a.hospital_name || 'Independent Clinic'}</p>
                <p><strong>Reason:</strong> ${a.reason}</p>
                
                ${a.status === 'reschedule_requested' ? `
                    <div style="margin-top:15px; padding:10px; background:#fff3e0; border-left:4px solid #ff9800; border-radius:4px;">
                        <p style="margin:0; font-size:0.9em; color:#e65100;"><strong>Request Pending:</strong> Waiting for doctor approval to reschedule to ${a.requested_date} at ${a.requested_time ? a.requested_time.slice(0, 5) : ''}</p>
                    </div>
                ` : ''}

                <div style="display:flex; flex-direction:column; gap:8px; margin-top: 15px;">
                    ${a.payment_status === 'paid' ? `
                         <button class="pd-btn pd-btn-outline" style="width:100%;" onclick="showBill('${a.booking_id}', 'Dr. ${escapeQuotes(a.doctor_name)}', '${a.appointment_date} at ${a.appointment_time.slice(0, 5)}', '${escapeQuotes(a.hospital_name || 'Independent Clinic')}', '${a.final_amount_npr ? "NPR " + Number(a.final_amount_npr).toFixed(2) : (a.amount_paid || "<?php echo STRIPE_FEE_LABEL; ?>")}', true)">
                            View Receipt
                        </button>
                    ` : ''}
                    
                    ${!['cancelled', 'missed', 'completed', 'refunded'].includes(a.status) ? `
                        ${a.payment_status === 'pending' ? `
                            <button class="pd-btn pd-btn-outline-success" style="width:100%;" onclick="retryPayment('${a.booking_id}')">
                                Pay Now
                            </button>
                        ` : ''}
                        
                        ${isLocked && !isInactive ? `
                            <div style="padding:10px; background: #f8fafc; border-radius: 6px; text-align:center; font-size: 0.85em; color: #64748b; border: 1px dashed #cbd5e1; margin-top: 5px;">
                                Scheduling & Refunding locked (within 2 hours)
                            </div>
                        ` : `
                            <div style="display:flex; gap:10px; margin-top:5px;">
                                ${a.status === 'scheduled' && !isInactive ? `
                                    <button class="pd-btn pd-btn-outline" style="flex:1;" onclick="rescheduleAppt(${a.id})">Reschedule</button>
                                ` : ''}
                                <button class="pd-btn pd-btn-outline-danger" style="${(a.status === 'scheduled' && !isInactive) ? 'flex:1;' : 'width:100%;'}" onclick="${a.payment_status === 'paid' ? `refundAppt(${a.id})` : `cancelAppt(${a.id})`}">${a.payment_status === 'paid' ? 'Cancel & Refund' : 'Cancel Appointment'}</button>
                            </div>
                        `}
                    ` : ''}
                </div>
            </div>`;
        }).join('');
    }

    async function loadReports() {
        try {
            const res = await fetch('../logic/patient/actions.php?action=get_reports');
            const reports = await res.json();
            const container = document.getElementById('reports-list');

            if (!reports || reports.length === 0) {
                container.innerHTML = `<div class="empty-state">No reports available yet.</div>`;
                return;
            }

            container.innerHTML = reports.map(r => `
            <div class="pd-card report-card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                    <h4 style="margin:0; color:var(--primary);">Medical Report</h4>
                    <span style="font-size:0.8em; color:var(--text-muted);">${r.created_at}</span>
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
                    <h5 style="margin:0 0 5px 0; color:var(--text-main);">Diagnosis</h5>
                    <p style="margin:0; color:var(--text-muted); font-style:italic;">${r.diagnosis}</p>
                </div>
                
                <div style="margin-bottom:15px;">
                    <h5 style="margin:0 0 5px 0; color:var(--text-main);">Clinical Notes</h5>
                    <p style="margin:0; color:var(--text-muted);">${r.report_details}</p>
                </div>
                
                <div style="padding:15px; background:var(--input-bg); border-radius:8px; border-left:4px solid var(--primary); margin-bottom:15px;">
                    <h5 style="margin:0 0 5px 0; color:var(--primary);">Prescription</h5>
                    <p style="margin:0; color:var(--text-main); white-space: pre-wrap;">${r.prescription}</p>
                </div>

                <div style="display:flex; gap:10px; margin-top:15px;">
                    <a href="../logic/doctor/download_report.php?id=${r.id}" target="_blank" class="pd-btn pd-btn-outline" style="flex:2; text-decoration:none; display:inline-block; text-align:center;">
                        Download / Print
                    </a>
                    <button class="pd-btn pd-btn-outline" style="flex:0.5;" onclick="openHistoryModal(${r.id})" title="View Version History">
                        History
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
            container.innerHTML = '<p style="color:#ef4444; text-align:center; padding:20px;">Error loading history</p>';
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

<!-- Floating AI Chatbot FAB -->
<div class="ai-fab-container">
    <button class="ai-fab" onclick="toggleAiDrawer()" title="Consult AI Assistant">
        <i class="fas fa-robot"></i>
    </button>
</div>

<!-- Slide-out AI Chat Drawer -->
<div id="ai-chat-drawer" class="ai-drawer">
    <div class="ai-drawer-header">
        <div style="display:flex; align-items:center; gap:10px;">
            <div class="ai-drawer-avatar"><i class="fas fa-robot"></i></div>
            <div>
                <h4 style="margin:0; font-size:1.05em; color:var(--white);">MedScape AI</h4>
                <span style="font-size:0.75em; color:rgba(255,255,255,0.85);">Symptom & doctor recommendation</span>
            </div>
        </div>
        <button class="ai-drawer-close" onclick="toggleAiDrawer()">&times;</button>
    </div>

    <div class="ai-drawer-body">
        <div id="ai-drawer-messages" class="ai-chat-messages">
            <!-- Dynamically Loaded -->
        </div>
    </div>

    <div class="ai-drawer-quick">
        <button class="quick-chip" onclick="sendQuickSymptomDrawer('Chest tightness and heart palpitations')">🫀 Chest
            Pain</button>
        <button class="quick-chip" onclick="sendQuickSymptomDrawer('Severe headache and migraine')">🧠 Headache</button>
        <button class="quick-chip" onclick="sendQuickSymptomDrawer('Skin rash and itching')">🧴 Skin Rash</button>
    </div>

    <form onsubmit="handleAiDrawerSubmit(event)" class="ai-drawer-footer">
        <input type="text" id="ai-drawer-input" class="pd-input" placeholder="Type symptoms here..." autocomplete="off"
            required style="flex:1;">
        <button type="submit" class="pd-btn pd-btn-outline" style="padding:10px; border-radius: var(--radius-sm);"><i
                class="fas fa-paper-plane"></i></button>
    </form>
</div>

<style>
    /* AI Chat Layout Styles */
    .ai-chat-layout {
        display: flex;
        gap: var(--space-5);
        margin-top: var(--space-4);
        height: 600px;
        align-items: stretch;
    }

    @media (max-width: 992px) {
        .ai-chat-layout {
            flex-direction: column;
            height: auto;
        }

        .ai-chat-sidebar {
            width: 100% !important;
            margin-bottom: var(--space-4);
        }

        .ai-chat-feed-container {
            height: 500px;
        }
    }

    .ai-chat-sidebar {
        width: 280px;
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
        flex-shrink: 0;
    }

    .ai-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: var(--space-4);
        box-shadow: var(--shadow-sm);
    }

    .ai-info-card h4,
    .ai-disclaimer-card h4 {
        margin: 0 0 10px 0;
        font-size: 1rem;
        color: var(--text-main);
        font-weight: var(--fw-semibold);
    }

    .ai-info-card p,
    .ai-disclaimer-card p {
        font-size: 0.825rem;
        color: var(--text-muted);
        margin: 5px 0;
        line-height: 1.4;
    }

    .ai-disclaimer-card {
        border-left: 4px solid var(--warning);
        background: #fdfaf2;
    }

    .ai-chat-feed-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .ai-chat-messages {
        flex: 1;
        padding: var(--space-4);
        overflow-y: auto;
        background: var(--gray-50);
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    .chat-message-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        width: 100%;
    }

    .chat-message-row.user {
        justify-content: flex-end;
    }

    .chat-message-row.ai {
        justify-content: flex-start;
    }

    .chat-avatar {
        width: 36px;
        height: 36px;
        border-radius: var(--radius-full);
        background: var(--primary-light);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        flex-shrink: 0;
        border: 1px solid rgba(89, 149, 253, 0.2);
    }

    .chat-bubble {
        max-width: 75%;
        padding: 12px 16px;
        border-radius: var(--radius-md);
        font-size: 0.9rem;
        line-height: 1.5;
        word-break: break-word;
    }

    .chat-bubble.user {
        background: var(--primary);
        color: var(--white);
        border-bottom-right-radius: 2px;
        box-shadow: 0 2px 4px rgba(89, 149, 253, 0.1);
    }

    .chat-bubble.ai {
        background: var(--white);
        color: var(--text-main);
        border: 1px solid var(--border-color);
        border-bottom-left-radius: 2px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }

    .chat-disclaimer {
        margin-top: 10px;
        padding: 8px 12px;
        background: var(--warning-light);
        border-left: 3px solid var(--warning);
        border-radius: var(--radius-sm);
        font-size: 0.775rem;
        color: #92400e;
    }

    /* Quick Symptom Chips */
    .ai-quick-symptoms {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-2);
        padding: 10px var(--space-4);
        border-top: 1px solid var(--border-color);
        background: var(--white);
        align-items: center;
    }

    .quick-chip-label {
        font-size: 0.775rem;
        font-weight: 600;
        color: var(--text-muted);
        margin-right: 5px;
    }

    .quick-chip {
        background: var(--gray-50);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-full);
        padding: 5px 12px;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all var(--transition-fast);
        color: var(--text-main);
    }

    .quick-chip:hover {
        background: var(--primary-light);
        color: var(--primary);
        border-color: rgba(89, 149, 253, 0.4);
    }

    .ai-chat-input-form {
        display: flex;
        padding: var(--space-3) var(--space-4);
        border-top: 1px solid var(--border-color);
        background: var(--white);
        gap: 10px;
    }

    .ai-chat-input-form input {
        flex: 1;
        border-radius: var(--radius-sm) !important;
    }

    /* recommended doctor cards inside chat */
    .recommended-doctors-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 12px;
        width: 100%;
    }

    .ai-doctor-card {
        background: var(--gray-50);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 12px;
        width: 100%;
    }

    .smart-slots-container {
        margin-top: 8px;
        border-top: 1px dashed var(--border-color);
        padding-top: 8px;
    }

    .slots-label {
        display: block;
        font-size: 0.775rem;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 5px;
    }

    .slots-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .slot-pill {
        background: var(--white);
        border: 1px solid rgba(89, 149, 253, 0.3);
        color: var(--primary-dark);
        padding: 5px 10px;
        border-radius: var(--radius-sm);
        font-size: 0.775rem;
        cursor: pointer;
        font-weight: 600;
        transition: all var(--transition-fast);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .slot-pill:hover {
        background: var(--primary);
        color: var(--white);
        border-color: var(--primary);
    }

    /* Floating FAB */
    .ai-fab-container {
        position: fixed;
        bottom: 25px;
        right: 25px;
        z-index: 999;
    }

    .ai-fab {
        width: 56px;
        height: 56px;
        border-radius: var(--radius-full);
        background: var(--primary);
        color: var(--white);
        border: none;
        box-shadow: 0 4px 10px rgba(89, 149, 253, 0.4);
        cursor: pointer;
        font-size: 1.4rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all var(--transition-fast);
    }

    .ai-fab:hover {
        background: var(--primary-hover);
        transform: scale(1.05);
    }

    /* Slide-out Drawer */
    .ai-drawer {
        position: fixed;
        top: 0;
        right: -360px;
        width: 350px;
        height: 100vh;
        background: var(--white);
        z-index: 1001;
        box-shadow: -4px 0 15px rgba(15, 23, 42, 0.1);
        display: flex;
        flex-direction: column;
        transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        border-left: 1px solid var(--border-color);
    }

    .ai-drawer.active {
        right: 0;
    }

    .ai-drawer-header {
        background: var(--primary);
        color: var(--white);
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ai-drawer-avatar {
        width: 32px;
        height: 32px;
        border-radius: var(--radius-full);
        background: rgba(255, 255, 255, 0.2);
        color: var(--white);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }

    .ai-drawer-close {
        background: none;
        border: none;
        color: var(--white);
        font-size: 1.6rem;
        cursor: pointer;
        line-height: 1;
    }

    .ai-drawer-body {
        flex: 1;
        overflow-y: auto;
        background: var(--gray-50);
    }

    .ai-drawer-quick {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        padding: 10px;
        border-top: 1px solid var(--border-color);
        background: var(--white);
    }

    .ai-drawer-quick .quick-chip {
        padding: 4px 8px;
        font-size: 0.725rem;
    }

    .ai-drawer-footer {
        padding: 10px 15px;
        display: flex;
        border-top: 1px solid var(--border-color);
        gap: 8px;
        background: var(--white);
    }

    .ai-drawer-footer input {
        border-radius: var(--radius-sm) !important;
        padding: 8px 12px;
        font-size: 0.85rem;
    }
</style>

<script>
    // --- AI Symptom Checker JavaScript ---
    let chatHistory = [
        { sender: 'ai', text: 'Hello! I am MedScape\'s AI Symptom Checker. Please describe how you are feeling (e.g. chest pain, headache, joint pain) so I can help recommend the right specialist and smart booking slots.' }
    ];

    function toggleAiDrawer() {
        const drawer = document.getElementById('ai-chat-drawer');
        if (drawer) {
            drawer.classList.toggle('active');
            if (drawer.classList.contains('active')) {
                renderChatMessages();
                const drawerInput = document.getElementById('ai-drawer-input');
                if (drawerInput) drawerInput.focus();
            }
        }
    }
    window.toggleAiDrawer = toggleAiDrawer;

    function renderChatMessages() {
        const fullContainer = document.getElementById('ai-chat-messages');
        const drawerContainer = document.getElementById('ai-drawer-messages');

        const messagesHtml = chatHistory.map(msg => {
            if (msg.sender === 'user') {
                return `
                <div class="chat-message-row user">
                    <div class="chat-bubble user">
                        ${msg.text}
                    </div>
                </div>
            `;
            } else {
                return `
                <div class="chat-message-row ai">
                    <div class="chat-avatar"><i class="fas fa-robot"></i></div>
                    <div class="chat-bubble ai">
                        ${msg.text}
                        ${msg.doctorsHtml ? `<div class="recommended-doctors-container">${msg.doctorsHtml}</div>` : ''}
                        ${msg.disclaimer ? `<div class="chat-disclaimer"><i class="fas fa-exclamation-triangle"></i> <strong>Medical Disclaimer:</strong> ${msg.disclaimer}</div>` : ''}
                    </div>
                </div>
            `;
            }
        }).join('');

        if (fullContainer) {
            fullContainer.innerHTML = messagesHtml;
            fullContainer.scrollTop = fullContainer.scrollHeight;
        }
        if (drawerContainer) {
            drawerContainer.innerHTML = messagesHtml;
            drawerContainer.scrollTop = drawerContainer.scrollHeight;
        }
    }
    window.renderChatMessages = renderChatMessages;

    function analyzeSymptoms(text) {
        const input = text.toLowerCase().trim();

        const specialities = {
            'Cardiologist': {
                keywords: ['chest pain', 'chest tightness', 'heart', 'palpitation', 'breathlessness', 'angina', 'high blood pressure', 'hypertension', 'arrhythmia', 'shortness of breath'],
                disclaimer: 'Chest pain or pressure and severe shortness of breath can be signs of a life-threatening cardiovascular emergency (like a heart attack). If these symptoms are severe or sudden, seek emergency medical care immediately.',
                reason: 'cardiac-related symptoms'
            },
            'Neurologist': {
                keywords: ['headache', 'migraine', 'dizziness', 'numbness', 'seizure', 'paralysis', 'tremor', 'tingling', 'vertigo', 'loss of balance', 'fainting', 'brain'],
                disclaimer: 'Sudden numbness, weakness on one side of the body, difficulty speaking, or severe confusion can indicate a neurological emergency such as a stroke. Please visit an emergency room immediately if these symptoms are sudden.',
                reason: 'neurological symptoms'
            },
            'Orthopedist': {
                keywords: ['joint pain', 'fracture', 'broken bone', 'back pain', 'arthritis', 'muscle pain', 'sprain', 'knee pain', 'shoulder pain', 'spine', 'bone pain', 'dislocation'],
                disclaimer: 'Severe joint swelling, inability to bear weight, or a visible bone deformity suggests an acute bone fracture or severe ligament damage that requires urgent orthopedic evaluation.',
                reason: 'musculoskeletal symptoms'
            },
            'Dermatologist': {
                keywords: ['rash', 'skin', 'acne', 'itching', 'itchy', 'burn', 'eczema', 'hives', 'mole', 'blister', 'psoriasis', 'pigmentation', 'dermatitis'],
                disclaimer: 'A rapidly spreading rash, especially if accompanied by fever, difficulty breathing, or skin blistering, can represent a severe systemic allergic reaction or critical dermatological condition requiring urgent care.',
                reason: 'dermatological/skin concerns'
            },
            'General': {
                keywords: ['fever', 'cold', 'cough', 'sore throat', 'fatigue', 'stomach ache', 'stomach pain', 'nausea', 'vomiting', 'diarrhea', 'flu', 'indigestion', 'weakness', 'throat'],
                disclaimer: 'Persistent high fever, severe abdominal pain, persistent vomiting, or dehydration are serious symptoms that should be assessed by a General Physician to prevent complications.',
                reason: 'general systemic symptoms'
            }
        };

        let bestMatch = null;
        let maxCount = 0;

        for (const [spec, data] of Object.entries(specialities)) {
            let count = 0;
            data.keywords.forEach(kw => {
                let index = input.indexOf(kw);
                while (index !== -1) {
                    count++;
                    index = input.indexOf(kw, index + kw.length);
                }
            });
            if (count > maxCount) {
                maxCount = count;
                bestMatch = spec;
            }
        }

        if (bestMatch && maxCount > 0) {
            return {
                speciality: bestMatch,
                reason: specialities[bestMatch].reason,
                disclaimer: specialities[bestMatch].disclaimer
            };
        }

        return null;
    }

    function calculateUpcomingSlots(doctor) {
        const today = new Date();
        const recommendedSlots = [];
        const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const doctorDays = doctor.available_days.split(',').map(d => d.trim());

        for (let i = 1; i <= 7; i++) {
            const nextDate = new Date();
            nextDate.setDate(today.getDate() + i);
            const dayName = dayNames[nextDate.getDay()];

            if (doctorDays.includes(dayName)) {
                const dateString = nextDate.toISOString().split('T')[0];
                const options = { weekday: 'short', month: 'short', day: 'numeric' };
                const displayDate = nextDate.toLocaleDateString('en-US', options);
                const slots = generateTimeSlots(doctor.start_time, doctor.end_time);

                if (slots.length > 0) {
                    const morningSlot = slots[Math.floor(slots.length * 0.25)] || slots[0];
                    const afternoonSlot = slots[Math.floor(slots.length * 0.7)] || slots[slots.length - 1];

                    recommendedSlots.push({
                        date: dateString,
                        time: morningSlot,
                        displayDate: displayDate
                    });
                    recommendedSlots.push({
                        date: dateString,
                        time: afternoonSlot,
                        displayDate: displayDate
                    });
                }
            }
            if (recommendedSlots.length >= 3) break;
        }
        return recommendedSlots.slice(0, 3);
    }

    async function processSymptomRecommendation(symptomsText) {
        chatHistory.push({ sender: 'ai', text: '<div style="display:flex; align-items:center; gap:8px;"><i class="fas fa-spinner fa-spin"></i> Analyzing your symptoms...</div>' });
        renderChatMessages();

        await new Promise(resolve => setTimeout(resolve, 800));
        chatHistory.pop();

        const analysis = analyzeSymptoms(symptomsText);

        if (!analysis) {
            chatHistory.push({
                sender: 'ai',
                text: `I couldn't identify a specific specialty based on your description.<br><br>
                   Please describe your symptoms in more detail, or tell me if it relates to one of these common areas:<br>
                   - <b>Heart / Blood Pressure</b> issues<br>
                   - <b>Headaches, Migraine, or Nerves</b><br>
                   - <b>Bone, Joint, or Muscle pain</b><br>
                   - <b>Skin Rashes, Acne, or Itching</b><br>
                   - <b>Fever, Cold, Cough, or Sore Throat</b>`
            });
            renderChatMessages();
            return;
        }

        try {
            const res = await fetch(`../logic/patient/actions.php?action=get_doctors&speciality=${analysis.speciality}`);
            const doctors = await res.json();

            if (!Array.isArray(doctors) || doctors.length === 0) {
                chatHistory.push({
                    sender: 'ai',
                    text: `I matched your symptoms to **${analysis.speciality}**, but we currently do not have any approved, active doctors available in this speciality.<br><br>Please consult our general clinics or check back later.`,
                    disclaimer: analysis.disclaimer
                });
                renderChatMessages();
                return;
            }

            let doctorsHtml = '';
            doctors.forEach(doc => {
                const slots = calculateUpcomingSlots(doc);
                let slotsHtml = '';

                if (slots.length > 0) {
                    slotsHtml = `
                    <div class="smart-slots-container">
                        <span class="slots-label">Smart Appointment Recommendations:</span>
                        <div class="slots-grid">
                            ${slots.map(s => `
                                <button class="slot-pill" onclick="bookRecommendedSlot(${doc.id}, '${doc.hospital_id || ''}', '${escapeQuotes(doc.name)}', '${s.date}', '${s.time}', '${escapeQuotes(doc.qualification || 'MBBS')}', '${doc.experience_years || 0}', '${escapeQuotes(doc.nmc_number || 'N/A')}', '${doc.start_time}', '${doc.end_time}', '${escapeQuotes(symptomsText)}')">
                                    <i class="far fa-clock"></i> ${s.displayDate} @ ${s.time}
                                </button>
                            `).join('')}
                        </div>
                    </div>
                `;
                } else {
                    slotsHtml = `<div style="font-size:0.85em; color:var(--text-muted); margin-top:10px;">No upcoming slots found in the next 7 days.</div>`;
                }

                doctorsHtml += `
                <div class="ai-doctor-card">
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
                        <img src="../${doc.profile_photo || 'assets/img/default.jpeg'}" alt="${doc.name}" style="width:45px; height:45px; border-radius:50%; object-fit:cover; border: 1px solid var(--border-color);">
                        <div>
                            <h4 style="margin:0; font-size:1em; color:var(--text-main);">Dr. ${doc.name}</h4>
                            <span style="font-size:0.75em; color:var(--primary); font-weight:600;">${doc.speciality}</span>
                            <div style="font-size:0.7em; color:var(--text-muted);">${doc.qualification || 'MBBS'} &bull; ${doc.experience_years || 0} Yrs Exp</div>
                        </div>
                    </div>
                    <div style="font-size:0.8em; margin:4px 0; color:var(--text-main);">
                        <strong>Hospital:</strong> ${doc.hospital_name || 'Independent Clinic'}
                    </div>
                    <div style="font-size:0.8em; margin:4px 0; color:var(--text-main);">
                        <strong>Hours:</strong> ${doc.start_time.slice(0, 5)} - ${doc.end_time.slice(0, 5)} (${doc.available_days})
                    </div>
                    ${slotsHtml}
                    <button class="pd-btn pd-btn-outline" style="width:100%; margin-top:8px; font-size:0.8em; padding:5px 10px; height:auto;" onclick="openBookingModal(${doc.id}, '${doc.hospital_id || ''}', '${escapeQuotes(doc.name)}', '${escapeQuotes(doc.qualification || 'MBBS')}', '${doc.experience_years || 0}', '${escapeQuotes(doc.nmc_number || 'N/A')}', '${doc.start_time}', '${doc.end_time}')">
                        Select Custom Slot
                    </button>
                </div>
            `;
            });

            chatHistory.push({
                sender: 'ai',
                text: `Based on your symptoms of <i>"${symptomsText}"</i>, I recommend consulting a **${analysis.speciality}** (Specialist in ${analysis.reason}). Here are our matching specialists:`,
                doctorsHtml: doctorsHtml,
                disclaimer: analysis.disclaimer
            });
            renderChatMessages();

        } catch (e) {
            console.error(e);
            chatHistory.push({ sender: 'ai', text: 'Sorry, I encountered an error connecting to our database. Please try again.' });
            renderChatMessages();
        }
    }
    window.processSymptomRecommendation = processSymptomRecommendation;

    function handleAiChatSubmit(e) {
        e.preventDefault();
        const inputEl = document.getElementById('ai-chat-input');
        const symptoms = inputEl.value.trim();
        if (!symptoms) return;

        chatHistory.push({ sender: 'user', text: symptoms });
        renderChatMessages();
        inputEl.value = '';

        processSymptomRecommendation(symptoms);
    }
    window.handleAiChatSubmit = handleAiChatSubmit;

    function handleAiDrawerSubmit(e) {
        e.preventDefault();
        const inputEl = document.getElementById('ai-drawer-input');
        const symptoms = inputEl.value.trim();
        if (!symptoms) return;

        chatHistory.push({ sender: 'user', text: symptoms });
        renderChatMessages();
        inputEl.value = '';

        processSymptomRecommendation(symptoms);
    }
    window.handleAiDrawerSubmit = handleAiDrawerSubmit;

    function sendQuickSymptom(symptoms) {
        chatHistory.push({ sender: 'user', text: symptoms });
        renderChatMessages();
        processSymptomRecommendation(symptoms);
    }
    window.sendQuickSymptom = sendQuickSymptom;

    function sendQuickSymptomDrawer(symptoms) {
        chatHistory.push({ sender: 'user', text: symptoms });
        renderChatMessages();
        processSymptomRecommendation(symptoms);
    }
    window.sendQuickSymptomDrawer = sendQuickSymptomDrawer;

    function bookRecommendedSlot(doctorId, hospitalId, doctorName, date, time, qualification, experience, nmcNumber, startTime, endTime, symptoms) {
        document.getElementById('book-doctor-id').value = doctorId;
        document.getElementById('book-hospital-id').value = hospitalId;
        document.getElementById('book-doctor-name').value = 'Dr. ' + doctorName;

        document.getElementById('bd-qual').textContent = qualification;
        document.getElementById('bd-exp').textContent = experience;
        document.getElementById('bd-nmc').textContent = nmcNumber;
        document.getElementById('book-doctor-details').style.display = 'block';

        document.getElementById('book-date').value = date;

        const timeSelect = document.getElementById('book-time');
        timeSelect.innerHTML = '<option value="">Select Time Slot</option>';

        if (startTime && endTime) {
            const slots = generateTimeSlots(startTime, endTime);
            slots.forEach(timeStr => {
                const isSelected = (timeStr === time) ? 'selected' : '';
                timeSelect.innerHTML += `<option value="${timeStr}" ${isSelected}>${timeStr}</option>`;
            });
        }

        document.getElementById('book-reason').value = 'Symptom Checker: ' + symptoms;
        document.getElementById('booking-error').style.display = 'none';

        // Close AI drawer if open
        const drawer = document.getElementById('ai-chat-drawer');
        if (drawer) drawer.classList.remove('active');

        document.getElementById('booking-modal').classList.add('active');

        if (typeof showToast === 'function') {
            showToast('Recommended slot and details pre-filled!', 'success');
        }
    }
    window.bookRecommendedSlot = bookRecommendedSlot;

    // Initial render
    document.addEventListener('DOMContentLoaded', () => {
        renderChatMessages();
    });
</script>
<div class="ad-container">

    <!-- DASHBOARD OVERVIEW -->
    <div id="tab-dashboard" class="ad-content active">
        <div style="margin-bottom: 35px;">
            <h2 style="margin:0; color: #1e293b;">Dashboard Overview</h2>
            <p style="color: #64748b; margin-top: 5px;">System-wide overview of MedScape health.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Hospitals</h3>
                    <h2 id="hospitalCount">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Doctors</h3>
                    <h2 id="doctorCount">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Patients</h3>
                    <h2 id="patientCount">0</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Appointments</h3>
                    <h2 id="appointmentCount">0</h2>
                </div>
            </div>
        </div>

        <div class="ad-grid" style="grid-template-columns: 1.5fr 1fr; gap: 25px;">
            <div class="ad-card">
                <h3>Appointment Trend (Last 7 Days)</h3>
                <div class="chart-container">
                    <canvas id="appointmentsChart"></canvas>
                </div>
            </div>
            <div class="ad-card">
                <h3>Recent Activity</h3>
                <div id="miniLogList" class="mini-logs">
                    <!-- Logs injected by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- HOSPITALS MANAGEMENT -->
    <div id="tab-hospitals" class="ad-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'dashboard')">Back to Overview</a>
        <div style="margin-bottom: 25px;">
            <h2 style="margin:0;">Hospital Management</h2>
            <p style="color: #64748b; margin-top: 5px;">Register and manage healthcare facilities.</p>
        </div>

        <div class="ad-grid" style="grid-template-columns: 1fr 2fr; gap: 25px;">
            <div class="ad-card">
                <h4>Add New Hospital</h4>
                <form id="addHospitalForm">
                    <div class="input-field-modern">
                        <label>Hospital Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="input-field-modern">
                        <label>Email Address</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="input-field-modern">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="input-field-modern">
                        <label>Phone</label>
                        <input type="tel" name="phone" pattern="(97|98)[0-9]{8}" title="Please enter a valid Nepali phone number." required>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom: 15px;">
                        <div class="input-field-modern">
                            <label>Province</label>
                            <select name="province" id="sa-province" onchange="updateSACities()" required>
                                <option value="">Select</option>
                                <option value="Koshi">Koshi</option>
                                <option value="Madhesh">Madhesh</option>
                                <option value="Bagmati">Bagmati</option>
                                <option value="Gandaki">Gandaki</option>
                                <option value="Lumbini">Lumbini</option>
                                <option value="Karnali">Karnali</option>
                                <option value="Sudurpaschim">Sudurpaschim</option>
                            </select>
                        </div>
                        <div class="input-field-modern">
                            <label>City</label>
                            <select name="city" id="sa-city" required>
                                <option value="">Select</option>
                            </select>
                        </div>
                    </div>
                    <div class="input-field-modern">
                        <label>Location</label>
                        <input type="text" name="location" required>
                    </div>
                    <button type="submit" class="btn-primary-modern" style="width:100%; margin-top:16px;">
                        Register Hospital
                    </button>
                </form>
            </div>
            <div class="ad-card" style="padding: 0;">
                <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                    <h4 style="margin:0;">Existing Hospitals</h4>
                </div>
                <div class="table-container shadow-none">
                    <table style="width:100%; border-collapse:collapse;">
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
    </div>

    <!-- DOCTORS MANAGEMENT -->
    <div id="tab-doctors" class="ad-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'dashboard')">Back to Overview</a>
        <div style="margin-bottom: 25px;">
            <h2 style="margin:0;">Doctor Management</h2>
            <p style="color: #64748b; margin-top: 5px;">Monitor and approve medical practitioners.</p>
        </div>

        <div class="ad-card" style="padding: 0;">
            <div class="table-container shadow-none">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th>Doctor Name</th>
                            <th>Speciality</th>
                            <th>Hospital</th>
                            <th>Approval</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="doctorTable"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PATIENTS MANAGEMENT -->
    <div id="tab-patients" class="ad-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'dashboard')">Back to Overview</a>
        <div style="margin-bottom: 25px;">
            <h2 style="margin:0;">Patient Management</h2>
            <p style="color: #64748b; margin-top: 5px;">View registered users and account status.</p>
        </div>

        <div class="ad-card" style="padding: 0;">
            <div class="table-container shadow-none">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="patientTable"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- APPOINTMENTS MANAGEMENT -->
    <div id="tab-appointments" class="ad-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'dashboard')">Back to Overview</a>
        <div style="margin-bottom: 25px;">
            <h2 style="margin:0;">Appointment Management</h2>
            <p style="color: #64748b; margin-top: 5px;">Global view of all scheduled consultations.</p>
        </div>

        <div class="ad-card" style="padding: 0;">
            <div class="table-container shadow-none">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="appointmentTable"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ANALYTICS -->
    <div id="tab-analytics" class="ad-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'dashboard')">Back to Overview</a>
        <div style="margin-bottom: 25px;">
            <h2 style="margin:0;">System Analytics</h2>
            <p style="color: #64748b; margin-top: 5px;">Data-driven insights into platform performance.</p>
        </div>

        <div class="ad-grid">
            <div class="ad-card">
                <h3>Speciality Distribution</h3>
                <div class="chart-container" style="height: 350px;">
                    <canvas id="specialtyChart"></canvas>
                </div>
            </div>
            <div class="ad-card">
                <h3>User Registration (Last 30 Days)</h3>
                <div class="chart-container" style="height: 350px;">
                    <canvas id="registrationChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- AUDIT LOGS -->
    <div id="tab-logs" class="ad-content">
        <a href="#" class="back-btn" onclick="switchTab(event, 'dashboard')">Back to Overview</a>
        <div style="margin-bottom: 25px;">
            <h2 style="margin:0;">System Audit Logs</h2>
            <p style="color: #64748b; margin-top: 5px;">Detailed history of administrative actions.</p>
        </div>

        <div class="ad-card" style="padding: 0;">
            <div id="logListContainer" style="max-height: 600px; overflow-y: auto;">
                <!-- Logs injected here -->
            </div>
        </div>
    </div>

</div>

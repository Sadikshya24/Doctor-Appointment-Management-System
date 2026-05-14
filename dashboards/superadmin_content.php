<!-- DASHBOARD OVERVIEW -->
<section id="dashboard" class="page-section active">
    <div class="welcome-banner">
        <h1>Welcome back, Admin!</h1>
        <p>System-wide overview of MedScape health.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card glass-card">
            <div class="stat-icon p-bg"><i class="fas fa-hospital"></i></div>
            <div class="stat-info">
                <h3>Total Hospitals</h3>
                <h2 id="hospitalCount">0</h2>
            </div>
        </div>
        <div class="stat-card glass-card">
            <div class="stat-icon d-bg"><i class="fas fa-user-md"></i></div>
            <div class="stat-info">
                <h3>Total Doctors</h3>
                <h2 id="doctorCount">0</h2>
            </div>
        </div>
        <div class="stat-card glass-card">
            <div class="stat-icon pat-bg"><i class="fas fa-user-injured"></i></div>
            <div class="stat-info">
                <h3>Total Patients</h3>
                <h2 id="patientCount">0</h2>
            </div>
        </div>
        <div class="stat-card glass-card">
            <div class="stat-icon app-bg"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info">
                <h3>Total Appointments</h3>
                <h2 id="appointmentCount">0</h2>
            </div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="glass-card chart-container">
            <h3><i class="fas fa-chart-line"></i> Appointment Trend (Last 7 Days)</h3>
            <canvas id="appointmentsChart"></canvas>
        </div>
        <div class="glass-card chart-container">
            <h3><i class="fas fa-history"></i> Recent Activity</h3>
            <div id="miniLogList" class="mini-logs">
                <!-- Logs injected by JS -->
            </div>
        </div>
    </div>
</section>

<!-- HOSPITALS MANAGEMENT -->
<section id="hospitals" class="page-section">
    <div class="section-header">
        <h2>Hospital Management</h2>
    </div>
    <div class="content-grid" style="grid-template-columns: 1fr 2fr; gap: 24px;">
        <div class="glass-card" style="padding: 24px;">
            <h3>Add New Hospital</h3>
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
                    <input type="tel" name="phone" required>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="input-field-modern" style="flex:1;">
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
                    <div class="input-field-modern" style="flex:1;">
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
                <button type="submit" class="btn-primary-modern" style="width:100%; margin-top:16px;">Register Hospital</button>
            </form>
        </div>
        <div class="glass-card" style="padding: 0;">
            <div style="padding: 24px; border-bottom: 1px solid var(--admin-border);">
                <h3>Existing Hospitals</h3>
            </div>
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

<!-- DOCTORS MANAGEMENT -->
<section id="doctors" class="page-section">
    <div class="section-header">
        <h2>Doctor Management</h2>
    </div>
    <div class="glass-card" style="padding: 0;">
        <div class="table-container shadow-none">
            <table>
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
</section>

<!-- PATIENTS MANAGEMENT -->
<section id="patients" class="page-section">
    <div class="section-header">
        <h2>Patient Management</h2>
    </div>
    <div class="glass-card" style="padding: 0;">
        <div class="table-container shadow-none">
            <table>
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Registered</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="patientTable"></tbody>
            </table>
        </div>
    </div>
</section>

<!-- APPOINTMENTS MANAGEMENT -->
<section id="appointments" class="page-section">
    <div class="section-header">
        <h2>Appointment Management</h2>
    </div>
    <div class="glass-card" style="padding: 0;">
        <div class="table-container shadow-none">
            <table>
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
</section>

<!-- ANALYTICS -->
<section id="analytics" class="page-section">
    <div class="section-header">
        <h2>System Analytics</h2>
    </div>
    <div class="chart-grid">
        <div class="glass-card chart-container">
            <h3>Speciality Distribution</h3>
            <canvas id="specialtyChart"></canvas>
        </div>
        <div class="glass-card chart-container">
            <h3>User Registration (Last 30 Days)</h3>
            <canvas id="registrationChart"></canvas>
        </div>
    </div>
</section>

<!-- AUDIT LOGS -->
<section id="logs" class="page-section">
    <div class="section-header">
        <h2>System Audit Logs</h2>
    </div>
    <div class="glass-card" style="padding: 0;">
        <div id="logListContainer" style="max-height: 600px; overflow-y: auto;">
            <!-- Logs injected here -->
        </div>
    </div>
</section>

        <!-- DASHBOARD PAGE -->
        <section id="dashboard" class="page-section active">
            <div class="welcome-banner">
                <h1>Welcome back, Admin!</h1>
                <p>Here's what's happening with MedScape today.</p>
            </div>

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

            <div class="content-grid">
                <div class="grid-box">
                    <h3><i class="fas fa-star"></i> Featured Doctors</h3>
                    <ul class="doctor-list">
                        <li>
                            <div class="doc-item">
                                <i class="fas fa-user-circle"></i>
                                <div>
                                    <strong>Dr. Ramesh Shrestha</strong>
                                    <span>Cardiology</span>
                                </div>
                            </div>
                        </li>
                        <li>
                            <div class="doc-item">
                                <i class="fas fa-user-circle"></i>
                                <div>
                                    <strong>Dr. Sanjay Kandel</strong>
                                    <span>Surgery</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="grid-box">
                    <h3><i class="fas fa-clock"></i> Recent Activity</h3>
                    <div id="miniLogList" class="mini-logs">
                        <!-- Logs injected by JS -->
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
                            <input type="password" name="password" required>
                        </div>
                        <div class="input-field-modern">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" pattern="9[0-9]{9}" title="10 digits starting with 9" required>
                        </div>
                        <div style="display:flex; gap:10px; margin-bottom:15px;">
                            <div class="input-field-modern" style="flex:1; margin-bottom:0;">
                                <label>Province</label>
                                <select name="province" id="sa-province" required onchange="updateSACities()" style="width:100%; padding:10px; box-sizing:border-box;">
                                    <option value="">Select Province</option>
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
                                <select name="city" id="sa-city" required style="width:100%; padding:10px; box-sizing:border-box;">
                                    <option value="">Select City</option>
                                </select>
                            </div>
                        </div>
                        <div class="input-field-modern">
                            <label>Location / Street</label>
                            <input type="text" name="location" required>
                        </div>
                        <button type="submit" class="btn-primary-modern">Register Hospital</button>
                    </form>
                    <script>
                        const SA_NEPAL_LOCATIONS = {
                            "Koshi": ["Biratnagar", "Itahari", "Dharan", "Birtamod", "Damak"],
                            "Madhesh": ["Janakpur", "Birgunj", "Kalaiya", "Gausala", "Lahan"],
                            "Bagmati": ["Kathmandu", "Lalitpur", "Bhaktapur", "Hetauda", "Bharatpur"],
                            "Gandaki": ["Pokhara", "Gorkha", "Bandipur", "Baglung", "Waling"],
                            "Lumbini": ["Butwal", "Bhairahawa", "Nepalgunj", "Ghorahi", "Tulsipur"],
                            "Karnali": ["Birendranagar", "Jumla", "Khalanga"],
                            "Sudurpaschim": ["Dhangadhi", "Mahendranagar", "Tikapur", "Attariya"]
                        };

                        function updateSACities() {
                            const province = document.getElementById('sa-province').value;
                            const citySelect = document.getElementById('sa-city');
                            citySelect.innerHTML = '<option value="">Select City</option>';
                            if (SA_NEPAL_LOCATIONS[province]) {
                                SA_NEPAL_LOCATIONS[province].forEach(city => {
                                    const opt = document.createElement('option');
                                    opt.value = city;
                                    opt.textContent = city;
                                    citySelect.appendChild(opt);
                                });
                            }
                        }
                    </script>
                </div>
                <div class="grid-box">
                    <h3>Existing Hospitals</h3>
                    <div class="table-container shadow-none">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="hospitalTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- LOGS PAGE -->
        <section id="logs" class="page-section">
            <div class="section-header">
                <h2>System Activity Logs</h2>
            </div>
            <div class="log-container">
                <ul id="logList" class="detailed-logs"></ul>
            </div>
        </section>

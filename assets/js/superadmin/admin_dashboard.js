const BASE = "../logic/superadmin/api.php?path=";

let currentData = [];
let currentPage = "dashboard";
let charts = {};

document.querySelectorAll(".sidebar-menu li").forEach(item => {
    item.addEventListener("click", function () {
        const pageId = this.getAttribute("data-page");

        // UI Updates
        document.querySelectorAll(".page-section").forEach(p => p.classList.remove("active"));
        document.getElementById(pageId).classList.add("active");

        document.querySelectorAll(".sidebar-menu li").forEach(li => li.classList.remove("active"));
        this.classList.add("active");

        // Load Content
        currentPage = pageId;
        loadPageData(pageId);
    });
});

async function loadPageData(pageId) {
    switch (pageId) {
        case "dashboard": loadDashboard(); break;
        case "hospitals": loadHospitals(); break;
        case "doctors": loadDoctors(); break;
        case "patients": loadPatients(); break;
        case "appointments": loadAppointments(); break;
        case "analytics": loadAnalytics(); break;
        case "logs": loadLogs(); break;
    }
}

async function loadDashboard() {
    try {
        const res = await fetch(BASE + "stats");
        const data = await res.json();

        if (document.getElementById("hospitalCount")) document.getElementById("hospitalCount").innerText = data.hospitals;
        if (document.getElementById("doctorCount")) document.getElementById("doctorCount").innerText = data.doctors;
        if (document.getElementById("patientCount")) document.getElementById("patientCount").innerText = data.patients;
        if (document.getElementById("appointmentCount")) document.getElementById("appointmentCount").innerText = data.appointments;

        loadMiniLogs();
        loadAppointmentsChart();
    } catch (e) {
        console.error("Failed to load dashboard stats", e);
    }
}

async function loadMiniLogs() {
    try {
        const res = await fetch(BASE + "logs");
        const data = await res.json();
        const container = document.getElementById("miniLogList");
        if (!container) return;
        
        container.innerHTML = data.slice(0, 5).map(log => `
            <div class="log-item">
                <div class="log-info">
                    <span class="log-action">${log.action}</span>
                    <span class="log-user">by ${log.user}</span>
                </div>
                <span class="log-time">${getTimeAgo(log.created_at)}</span>
            </div>
        `).join("");
    } catch (e) { console.error(e); }
}

// Chart Theme Configuration
const CHART_COLORS = {
    primary: '#6366f1',
    secondary: '#0ea5e9',
    success: '#10b981',
    warning: '#f59e0b',
    danger: '#ef4444',
    purple: '#8b5cf6',
    gray: '#94a3b8'
};

const CHART_DEFAULTS = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 20,
                font: { family: "'Inter', sans-serif", size: 12 }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            suggestedMax: 5,
            grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false },
            ticks: { 
                font: { size: 11 },
                stepSize: 1,
                precision: 0
            }
        },
        x: {
            grid: { display: false },
            ticks: { font: { size: 11 } }
        }
    }
};

async function loadAppointmentsChart() {
    try {
        const res = await fetch(BASE + "analytics");
        const data = await res.json();
        
        const ctx = document.getElementById('appointmentsChart')?.getContext('2d');
        if (!ctx) return;

        if (charts.appointments) charts.appointments.destroy();

        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
        gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

        charts.appointments = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.appointments_trend.map(d => d.date),
                datasets: [{
                    label: 'Appointments',
                    data: data.appointments_trend.map(d => d.count),
                    borderColor: CHART_COLORS.primary,
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: CHART_COLORS.primary,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                ...CHART_DEFAULTS,
                plugins: {
                    ...CHART_DEFAULTS.plugins,
                    legend: { display: false }
                }
            }
        });
    } catch (e) { console.error(e); }
}

async function loadAnalytics() {
    try {
        const res = await fetch(BASE + "analytics");
        const data = await res.json();

        // Specialty Chart
        const ctxSpecialty = document.getElementById('specialtyChart')?.getContext('2d');
        if (ctxSpecialty) {
            if (charts.specialty) charts.specialty.destroy();
            charts.specialty = new Chart(ctxSpecialty, {
                type: 'doughnut',
                data: {
                    labels: data.specialty_distribution.map(d => d.speciality),
                    datasets: [{
                        data: data.specialty_distribution.map(d => d.count),
                        backgroundColor: [
                            CHART_COLORS.primary, 
                            CHART_COLORS.secondary, 
                            CHART_COLORS.success, 
                            CHART_COLORS.warning, 
                            CHART_COLORS.purple, 
                            CHART_COLORS.danger
                        ],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        ...CHART_DEFAULTS.plugins,
                        legend: {
                            ...CHART_DEFAULTS.plugins.legend,
                            position: 'right'
                        }
                    }
                }
            });
        }

        // Registration Chart
        const ctxReg = document.getElementById('registrationChart')?.getContext('2d');
        if (ctxReg) {
            if (charts.registration) charts.registration.destroy();
            
            const dates = [...new Set(data.registration_trend.map(d => d.date))];
            const datasets = [
                { role: 'patient', color: CHART_COLORS.success },
                { role: 'doctor', color: CHART_COLORS.secondary },
                { role: 'hospital', color: CHART_COLORS.primary }
            ].map(cfg => ({
                label: cfg.role.charAt(0).toUpperCase() + cfg.role.slice(1),
                data: dates.map(date => {
                    const entry = data.registration_trend.find(d => d.date === date && d.role === cfg.role);
                    return entry ? entry.count : 0;
                }),
                backgroundColor: cfg.color,
                borderRadius: 6,
                barThickness: 20
            }));

            charts.registration = new Chart(ctxReg, {
                type: 'bar',
                data: { labels: dates, datasets: datasets },
                options: {
                    ...CHART_DEFAULTS,
                    scales: {
                        x: { ...CHART_DEFAULTS.scales.x, stacked: true },
                        y: { ...CHART_DEFAULTS.scales.y, stacked: true }
                    }
                }
            });
        }
    } catch (e) { console.error(e); }
}


// Entity Loaders
async function loadHospitals() {
    const res = await fetch(BASE + "hospitals");
    const data = await res.json();
    currentData = data;
    renderTable(data);
}

async function loadDoctors() {
    const res = await fetch(BASE + "doctors");
    const data = await res.json();
    currentData = data;
    renderTable(data);
}

async function loadPatients() {
    const res = await fetch(BASE + "patients");
    const data = await res.json();
    currentData = data;
    renderTable(data);
}

async function loadAppointments() {
    const res = await fetch(BASE + "appointments");
    const data = await res.json();
    currentData = data;
    renderTable(data);
}

async function loadLogs() {
    try {
        const res = await fetch(BASE + "logs");
        const data = await res.json();
        const container = document.getElementById("logListContainer");
        if (!container) return;

        container.innerHTML = data.map(log => {
            let detailsHtml = "";
            if (log.details) {
                try {
                    const details = JSON.parse(log.details);
                    detailsHtml = `<div class="log-details">${JSON.stringify(details, null, 2)}</div>`;
                } catch (e) { detailsHtml = `<div class="log-details">${log.details}</div>`; }
            }

            return `
                <div class="log-item" style="flex-direction: column; align-items: flex-start; gap: 8px;">
                    <div style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
                        <div class="log-info">
                            <span class="log-action">${log.action}</span>
                            <span class="log-user">Performed by <strong>${log.user}</strong></span>
                        </div>
                        <span class="log-time">${new Date(log.created_at).toLocaleString()}</span>
                    </div>
                    ${detailsHtml}
                </div>
            `;
        }).join("");
    } catch (e) { console.error(e); }
}

function renderTable(data) {
    const tableMap = {
        "hospitals": "hospitalTable",
        "doctors": "doctorTable",
        "patients": "patientTable",
        "appointments": "appointmentTable"
    };

    const containerId = tableMap[currentPage];
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = "";
    
    data.forEach(item => {
        let row = "";
        if (currentPage === "hospitals") {
            const statusClass = item.status === 'active' ? 'badge-active' : 'badge-inactive';
            row = `
                <tr>
                    <td>${item.name} ${item.is_verified == 1 ? '<i class="fas fa-check-circle" style="color:#10b981;"></i>' : ''}</td>
                    <td>${item.city}, ${item.province}</td>
                    <td><span class="badge ${statusClass}">${item.status.toUpperCase()}</span></td>
                    <td>
                        <button onclick="toggleHospitalStatus(${item.id}, '${item.status}')" class="btn-sm ${item.status === 'active' ? 'btn-reject' : 'btn-approve'}">
                            <i class="fas ${item.status === 'active' ? 'fa-user-slash' : 'fa-user-check'}"></i>
                        </button>
                    </td>
                </tr>`;
        } else if (currentPage === "doctors") {
            const statusClass = item.status === 'active' ? 'badge-active' : 'badge-inactive';
            const approvalClass = item.doctor_approval_status === 'approved' ? 'badge-active' : (item.doctor_approval_status === 'pending' ? 'badge-pending' : 'badge-inactive');
            row = `
                <tr>
                    <td>${item.name}</td>
                    <td>${item.speciality}</td>
                    <td>${item.hospital_name || 'N/A'}</td>
                    <td><span class="badge ${approvalClass}">${item.doctor_approval_status.toUpperCase()}</span></td>
                    <td><span class="badge ${statusClass}">${item.status.toUpperCase()}</span></td>
                    <td>
                        <button onclick="toggleDoctorStatus(${item.id}, '${item.status}')" class="btn-sm ${item.status === 'active' ? 'btn-reject' : 'btn-approve'}">
                            <i class="fas ${item.status === 'active' ? 'fa-user-slash' : 'fa-user-check'}"></i>
                        </button>
                    </td>
                </tr>`;
        } else if (currentPage === "patients") {
            const statusClass = item.status === 'active' ? 'badge-active' : 'badge-inactive';
            row = `
                <tr>
                    <td>${item.name}</td>
                    <td>${item.email}</td>
                    <td>${item.phone || 'N/A'}</td>
                    <td>${new Date(item.created_at).toLocaleDateString()}</td>
                    <td><span class="badge ${statusClass}">${item.status.toUpperCase()}</span></td>
                </tr>`;
        } else if (currentPage === "appointments") {
            const statusClass = `badge-${item.status}`;
            row = `
                <tr>
                    <td><strong>${item.booking_id}</strong></td>
                    <td>${item.patient_name}</td>
                    <td>${item.doctor_name}</td>
                    <td>${item.appointment_date} <br><small>${item.appointment_time}</small></td>
                    <td><span class="badge ${statusClass}">${item.status.toUpperCase()}</span></td>
                </tr>`;
        }
        container.innerHTML += row;
    });
}

// Action Handlers
async function toggleHospitalStatus(id, currentStatus) {
    const action = currentStatus === 'active' ? 'deactivate' : 'activate';
    const confirmed = await showConfirm({
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} Hospital?`,
        message: `Are you sure you want to ${action} this hospital account?`,
        confirmText: action.charAt(0).toUpperCase() + action.slice(1),
        type: action === 'deactivate' ? 'danger' : 'info'
    });
    if (!confirmed) return;
    
    const res = await fetch(BASE + `hospitals/${id}/toggle-status`, { method: "POST" });
    const result = await res.json();
    if (result.success) {
        showToast(`Hospital ${action}d successfully`, 'success');
        loadHospitals();
    } else {
        showToast(result.message || "Failed to toggle status", 'error');
    }
}

async function toggleDoctorStatus(id, currentStatus) {
    const action = currentStatus === 'active' ? 'deactivate' : 'activate';
    const confirmed = await showConfirm({
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} Doctor?`,
        message: `Are you sure you want to ${action} this doctor account?`,
        confirmText: action.charAt(0).toUpperCase() + action.slice(1),
        type: action === 'deactivate' ? 'danger' : 'info'
    });
    if (!confirmed) return;
    
    const res = await fetch(BASE + `doctors/${id}/toggle-status`, { method: "POST" });
    const result = await res.json();
    if (result.success) {
        showToast(`Doctor ${action}d successfully`, 'success');
        loadDoctors();
    } else {
        showToast(result.message || "Failed to toggle status", 'error');
    }
}

// Helper Functions
function getTimeAgo(date) {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    if (seconds < 60) return "just now";
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return minutes + "m ago";
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return hours + "h ago";
    return new Date(date).toLocaleDateString();
}

const SA_NEPAL_LOCATIONS = {
    "Koshi": ["Biratnagar", "Itahari", "Dharan", "Birtamod", "Damak"],
    "Madhesh": ["Janakpur", "Birgunj", "Kalaiya", "Gausala", "Lahan"],
    "Bagmati": ["Kathmandu", "Lalitpur", "Bhaktapur", "Hetauda", "Bharatpur"],
    "Gandaki": ["Pokhara", "Gorkha", "Bandipur", "Baglung", "Waling"],
    "Lumbini": ["Butwal", "Bhairahawa", "Nepalgunj", "Ghorahi", "Tulsipur"],
    "Karnali": ["Birendranagar", "Jumla", "Khalanga"],
    "Sudurpaschim": ["Dhangadhi", "Mahendranagar", "Tikapur", "Attariya"]
};

window.updateSACities = function() {
    const province = document.getElementById('sa-province').value;
    const citySelect = document.getElementById('sa-city');
    citySelect.innerHTML = '<option value="" disabled selected>Select City</option>';
    if (SA_NEPAL_LOCATIONS[province]) {
        SA_NEPAL_LOCATIONS[province].forEach(city => {
            const opt = document.createElement('option');
            opt.value = city;
            opt.textContent = city;
            citySelect.appendChild(opt);
        });
    }
};

document.getElementById('addHospitalForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = Object.fromEntries(fd.entries());
    
    const res = await fetch(BASE + "hospitals/add", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.success) {
        showToast("Hospital registered successfully!", "success");
        e.target.reset();
        loadHospitals();
    } else {
        showToast(result.error || "Failed to register hospital", "error");
    }
});

// Initial Load
loadDashboard();



const BASE = "../logic/superadmin/api.php?path=";

let currentData = [];
let currentPage = "dashboard";

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
    }
}

async function loadDashboard() {
    try {
        const res = await fetch(BASE + "stats");
        const data = await res.json();

        if (document.getElementById("hospitalCount")) document.getElementById("hospitalCount").innerText = data.hospitals;
        if (document.getElementById("doctorCount")) document.getElementById("doctorCount").innerText = data.doctors;
    } catch (e) {
        console.error("Failed to load dashboard stats", e);
    }
}



// Hospital Logic
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

// Search
document.getElementById("searchInput").addEventListener("input", function () {
    const val = this.value.toLowerCase();
    if (!currentData.length) return;
    const filtered = currentData.filter(item =>
        Object.values(item).some(v => String(v).toLowerCase().includes(val))
    );
    renderTable(filtered);
});



async function loadHospitals() {
    const res = await fetch(BASE + "hospitals");
    const data = await res.json();
    currentData = data;
    renderTable(data);
}


function renderTable(data) {
    if (currentPage === "hospitals") {
        const table = document.getElementById("hospitalTable");
        if (!table) return;
        table.innerHTML = "";
        data.forEach(h => {
            const statusClass = h.status === 'active' ? 'badge-scheduled' : 'badge-cancelled';
            const fullLocation = h.province && h.city 
                ? `${h.city}, ${h.province} <br><small style="color:#64748b;">${h.location}</small>` 
                : h.location;
                
            table.innerHTML += `
                <tr>
                    <td>${h.name}</td>
                    <td>${fullLocation}</td>
                    <td><span class="${statusClass}">${h.status.toUpperCase()}</span></td>
                    <td>
                        <button onclick="toggleHospitalStatus(${h.id}, '${h.status}')" class="btn-sm ${h.status === 'active' ? 'btn-reject' : 'btn-approve'}" title="${h.status === 'active' ? 'Deactivate' : 'Activate'}">
                            <i class="fas ${h.status === 'active' ? 'fa-user-slash' : 'fa-user-check'}"></i>
                        </button>
                    </td>
                </tr>`;
        });
    }
}

function getTimeAgo(date) {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    if (seconds < 60) return "just now";
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return minutes + "m ago";
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return hours + "h ago";
    return new Date(date).toLocaleDateString();
}

// Initial Load
loadDashboard();

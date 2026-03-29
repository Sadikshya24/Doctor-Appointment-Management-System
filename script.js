const BASE = "http://localhost/admin_dashboard/api.php?path=";

document.querySelectorAll(".sidebar li").forEach(item => {
  item.addEventListener("click", function () {
    const pageId = this.getAttribute("data-page");

    document.querySelectorAll(".page").forEach(p => p.classList.remove("active"));
    document.getElementById(pageId).classList.add("active");

    document.querySelectorAll(".sidebar li").forEach(li => li.classList.remove("active"));
    this.classList.add("active");

    if (pageId === "dashboard") loadDashboard();
    if (pageId === "doctors") loadDoctors();
    if (pageId === "appointments") loadAppointments();
    if (pageId === "patients") loadPatients();
    if (pageId === "logs") loadLogs();
    if (pageId === "stats") loadStats();
  });
});

async function loadDashboard() {
  const res = await fetch(BASE + "stats");
  const data = await res.json();

  document.getElementById("patientCount").innerText = data.patients;
  document.getElementById("doctorCount").innerText = data.doctors;
  document.getElementById("appointmentCount").innerText = data.appointments;
}

async function loadDoctors() {
  const table = document.getElementById("doctorTable");

  const res = await fetch(BASE + "doctors/pending");
  const data = await res.json();

  table.innerHTML = "";

  if (!data.length) {
    table.innerHTML = "<tr><td colspan='4'>No pending doctors</td></tr>";
    return;
  }

  data.forEach(doc => {
    table.innerHTML += `
      <tr>
        <td>${doc.name}</td>
        <td>${doc.nmcNumber}</td>
        <td><a href="${doc.cv_file}" target="_blank">📄 View CV</a></td>
        <td>
          <button onclick="verifyDoctor(${doc.id})">Approve</button>
          <button onclick="rejectDoctor(${doc.id})">Reject</button>
        </td>
      </tr>`;
  });
}

async function verifyDoctor(id) {
  await fetch(BASE + `doctors/${id}/verify`, { method: "POST" });
  loadDoctors();
  loadLogs();
}

async function rejectDoctor(id) {
  await fetch(BASE + `doctors/${id}/reject`, { method: "POST" });
  loadDoctors();
  loadLogs();
}

async function loadAppointments() {
  const table = document.getElementById("appointmentTable");

  try {
    const res = await fetch(BASE + "appointments");
    const data = await res.json();

    table.innerHTML = "";

    if (!data.length) {
      table.innerHTML = "<tr><td colspan='3'>No appointments</td></tr>";
      return;
    }

    data.forEach(a => {
      table.innerHTML += `
        <tr>
          <td>${a.patient}</td>
          <td>${a.doctor}</td>
          <td>${a.status}</td>
        </tr>`;
    });

  } catch {
    table.innerHTML = "<tr><td colspan='3'>Error loading</td></tr>";
  }
}

async function loadPatients() {
  const table = document.getElementById("patientTable");

  const res = await fetch(BASE + "patients");
  const data = await res.json();

  table.innerHTML = "";

  if (!data.length) {
    table.innerHTML = "<tr><td>No patients</td></tr>";
    return;
  }

  data.forEach(p => {
    table.innerHTML += `
      <tr>
        <td>${p.name}</td>
        <td>${p.status}</td>
      </tr>`;
  });
}

async function loadLogs() {
  const list = document.getElementById("logList");

  try {
    const res = await fetch(BASE + "logs");
    const data = await res.json();

    list.innerHTML = "";

    if (!data.length) {
      list.innerHTML = "<li>No logs found</li>";
      return;
    }

    data.forEach(log => {
      list.innerHTML += `
        <li>${log.message} by <b>${log.user}</b> (${log.created_at})</li>`;
    });

  } catch {
    list.innerHTML = "<li>Error loading logs</li>";
  }
}

async function loadStats() {
  const div = document.getElementById("statsData");

  const res = await fetch(BASE + "stats");
  const data = await res.json();

  div.innerHTML = `
    <p>Patients: ${data.patients}</p>
    <p>Doctors: ${data.doctors}</p>
    <p>Appointments: ${data.appointments}</p>
  `;
}

let currentData = [];
let currentPage = "";

document.getElementById("searchInput").addEventListener("input", function () {
  const value = this.value.toLowerCase();

  if (!currentData.length) return;

  const filtered = currentData.filter(item =>
    Object.values(item).some(v =>
      String(v).toLowerCase().includes(value)
    )
  );

  renderTable(filtered);
});

async function loadPatients() {
  const table = document.getElementById("patientTable");

  const res = await fetch(BASE + "patients");
  const data = await res.json();

  currentData = data;
  currentPage = "patients";

  renderTable(data);
}

function renderTable(data) {
  if (currentPage === "patients") {
    const table = document.getElementById("patientTable");
    table.innerHTML = "";

    data.forEach(p => {
      table.innerHTML += `
        <tr>
          <td>${p.name}</td>
          <td>${p.status}</td>
        </tr>`;
    });
  }

  if (currentPage === "appointments") {
    const table = document.getElementById("appointmentTable");
    table.innerHTML = "";

    data.forEach(a => {
      table.innerHTML += `
        <tr>
          <td>${a.patient}</td>
          <td>${a.doctor}</td>
          <td>${a.status}</td>
        </tr>`;
    });
  }

  if (currentPage === "doctors") {
    const table = document.getElementById("doctorTable");
    table.innerHTML = "";

    data.forEach(doc => {
      table.innerHTML += `
        <tr>
          <td>${doc.name}</td>
          <td>${doc.nmcNumber}</td>
          <td><a href="${doc.cv_file}" target="_blank">📄</a></td>
          <td>
            <button onclick="verifyDoctor(${doc.id})">Approve</button>
            <button onclick="rejectDoctor(${doc.id})">Reject</button>
          </td>
        </tr>`;
    });
  }
}
async function loadAppointments() {
  const res = await fetch(BASE + "appointments");
  const data = await res.json();

  currentData = data;
  currentPage = "appointments";

  renderTable(data);
}
loadDashboard();
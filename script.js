const BASE = "http://localhost/admin_dashboard/api.php?path=";

let currentData = [];
let currentPage = "";

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
  const res = await fetch(BASE + "doctors");
  const data = await res.json();

  currentData = data;
  currentPage = "doctors";

  renderTable(data);
}

async function updateDoctor(id) {
  const name = prompt("Enter new name:");
  const nmcNumber = prompt("Enter new NMC Number:");

  if (!name || !nmcNumber) return;

  await fetch(BASE + `doctors/${id}/update`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ name, nmcNumber })
  });

  loadDoctors();
  loadLogs();
}

async function deleteDoctor(id) {
  if (!confirm("Delete this doctor?")) return;

  await fetch(BASE + `doctors/${id}/delete`, { method: "POST" });

  loadDoctors();
  loadLogs();
}

async function loadAppointments() {
  const res = await fetch(BASE + "appointments");
  const data = await res.json();

  currentData = data;
  currentPage = "appointments";

  renderTable(data);
}

async function cancelAppointment(id) {
  if (!confirm("Cancel this appointment?")) return;

  await fetch(BASE + `appointments/${id}/cancel`, { method: "POST" });

  loadAppointments();
  loadLogs();
}

async function rescheduleAppointment(id) {
  const date = prompt("Enter new date (YYYY-MM-DD):");
  const time = prompt("Enter new time (HH:MM)");

  if (!date || !time) return;

  const selectedDate = new Date(date);
  const now = new Date();

  if (selectedDate < now) {
    alert("Cannot select past date");
    return;
  }

  await fetch(BASE + `appointments/${id}/reschedule`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ date, time })
  });

  loadAppointments();
  loadLogs();
}

async function loadPatients() {
  const res = await fetch(BASE + "patients");
  const data = await res.json();

  currentData = data;
  currentPage = "patients";

  renderTable(data);
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
      list.innerHTML += `<li>${log.message} by <b>${log.user}</b> (${log.created_at})</li>`;
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

function renderTable(data) {

  if (currentPage === "doctors") {
    const table = document.getElementById("doctorTable");
    table.innerHTML = "";

    if (!data.length) {
      table.innerHTML = "<tr><td>No doctors</td></tr>";
      return;
    }

    data.forEach(doc => {
      table.innerHTML += `
        <tr>
          <td>${doc.name}</td>
          <td>${doc.nmcNumber}</td>
          <td>${doc.status}</td>
          <td>
            <button onclick="updateDoctor(${doc.id})">Update</button>
            <button onclick="deleteDoctor(${doc.id})">Delete</button>
          </td>
        </tr>`;
    });
  }

  if (currentPage === "appointments") {
    const table = document.getElementById("appointmentTable");
    table.innerHTML = "";

    if (!data.length) {
      table.innerHTML = "<tr><td colspan='7'>No appointments</td></tr>";
      return;
    }

    data.forEach(a => {
      table.innerHTML += `
        <tr>
          <td>${a.id}</td>
          <td>${a.patient}</td>
          <td>${a.doctor}</td>
          <td>${a.date}</td>
          <td>${a.time}</td>
          <td>${a.status}</td>
          <td>
            <button onclick="cancelAppointment(${a.id})">Cancel</button>
            <button onclick="rescheduleAppointment(${a.id})">Reschedule</button>
          </td>
        </tr>`;
    });
  }

  if (currentPage === "patients") {
    const table = document.getElementById("patientTable");
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
}

loadDashboard();
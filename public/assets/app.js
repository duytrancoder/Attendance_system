const sections = document.querySelectorAll(".section");
const navButtons = document.querySelectorAll(".sidebar button");
const modal = document.getElementById("modal");
const modalForm = document.getElementById("modal-form");
const modalTitle = document.getElementById("modal-title");

const apiBase = location.pathname.includes("/public")
  ? "../api"
  : "/api";
const api = {
  dashboard: `${apiBase}/dashboard.php`,
  employees: `${apiBase}/employees.php`,
  departments: `${apiBase}/departments.php`,
  attendance: `${apiBase}/attendance.php`,
  settings: `${apiBase}/settings.php`,
};

// Auto-refresh configuration
let autoRefreshInterval = null;
const REFRESH_DELAY = 2000; // 2 seconds (faster updates)
let currentSection = 'dashboard'; // Track active section

// Navigation
navButtons.forEach((btn) => {
  btn.addEventListener("click", () => {
    navButtons.forEach((b) => b.classList.remove("active"));
    btn.classList.add("active");
    sections.forEach((s) => s.classList.remove("active"));
    document.getElementById(btn.dataset.section).classList.add("active");
    currentSection = btn.dataset.section; // Track section change
  });
});

// Helpers
const showModal = (title, formHtml, onSubmit) => {
  modalTitle.textContent = title;
  modalForm.innerHTML = formHtml;
  modal.classList.add("show");
  modalForm.onsubmit = async (e) => {
    e.preventDefault();
    const formData = new FormData(modalForm);
    await onSubmit(formData);
    modal.classList.remove("show");
  };
};
document.getElementById("modal-close").onclick = () =>
  modal.classList.remove("show");
modal.addEventListener("click", (e) => {
  if (e.target === modal) modal.classList.remove("show");
});

const formatTime = (t) => (t ? t.slice(0, 5) : "-");
const statusBadge = (status) => {
  if (!status) return `<span class="badge gray">-</span>`;
  const lower = status.toLowerCase();
  let cls = "gray";
  if (lower.includes("về sớm")) cls = "danger";
  else if (lower.includes("muộn")) cls = "warn";
  else if (lower.includes("đúng giờ")) cls = "success";
  return `<span class="badge ${cls}">${status}</span>`;
};

// Date Helpers
const formatDobDisplay = (val) => {
  if (!val) return "-";
  // val could be int (19901231) or string "19901231" or "1990"
  const s = String(val);
  if (s.length === 8) {
    return `${s.slice(6, 8)}/${s.slice(4, 6)}/${s.slice(0, 4)}`;
  }
  return s; // Fallback for old data like year only
};

const formatDobInput = (val) => {
  if (!val) return "";
  const s = String(val);
  if (s.length === 8) {
    return `${s.slice(0, 4)}-${s.slice(4, 6)}-${s.slice(6, 8)}`;
  }
  return "";
};


// Dashboard
async function loadDashboard() {
  const res = await fetch(api.dashboard);
  const data = await res.json();

  const cards = [
    { label: "Tổng nhân viên", value: data.cards.totalEmployees },
    { label: "Đi làm", value: data.cards.present },
    { label: "Đi muộn", value: data.cards.late },
    { label: "Nghỉ", value: data.cards.absent },
  ];

  document.getElementById("stats-cards").innerHTML = cards
    .map(
      (c) => `
      <div class="card">
        <div class="label">${c.label}</div>
        <div class="value">${c.value}</div>
      </div>`
    )
    .join("");

  document.getElementById("today-logs").innerHTML = data.todayLogs
    .map(
      (row) => `
      <tr>
        <td>${row.full_name}</td>
        <td>${row.department || "-"}</td>
        <td>${row.shift_name || "-"}</td>
        <td>${formatTime(row.check_in)}</td>
        <td>${formatTime(row.check_out)}</td>
        <td>${statusBadge(row.status)}</td>
      </tr>`
    )
    .join("");
}

// Employees
async function loadEmployees() {
  const res = await fetch(api.employees);
  const rows = await res.json();
  document.getElementById("employee-rows").innerHTML = rows
    .map(
      (r) => `
    <tr>
      <td>${r.fingerprint_id}</td>
      <td>${r.full_name}</td>
      <td>${r.department}</td>
      <td>${r.position}</td>
      <td>${formatDobDisplay(r.birth_year)}</td>
      <td>${r.created_at || "-"}</td>
      <td style="display:flex; gap:6px">
        <button class="ghost" data-edit="${r.id}">Sửa</button>
        <button class="danger ghost" data-del="${r.id}">Xóa</button>
      </td>
    </tr>`
    )
    .join("");

  document.querySelectorAll("[data-edit]").forEach((btn) =>
    btn.addEventListener("click", () => openEditEmployee(btn.dataset.edit))
  );
  document.querySelectorAll("[data-del]").forEach((btn) =>
    btn.addEventListener("click", () => deleteEmployee(btn.dataset.del))
  );
}

document
  .getElementById("btn-add-employee")
  .addEventListener("click", () => openCompleteFingerprintEmployee());

// Giao diện điền thông tin cho ID vân tay mới được ESP32 gửi lên
async function openCompleteFingerprintEmployee() {
  // Lấy danh sách "nhân viên mới" (department = 'Chờ cập nhật')
  const res = await fetch(`${api.employees}?pending=1`);
  const pending = await res.json();

  if (!pending.length) {
    alert("Hiện không có ID vân tay mới nào cần hoàn thiện.");
    return;
  }

  const options = pending
    .map(
      (p, idx) =>
        `<option value="${p.id}" ${idx === 0 ? "selected" : ""}>ID ${p.fingerprint_id} - ${p.full_name}</option>`
    )
    .join("");

  // Load Departments for Dropdown
  const resDept = await fetch(api.departments);
  const departments = await resDept.json();
  const deptOptions = departments
    .map(d => `<option value="${d.name}">${d.name}</option>`)
    .join("");
  const deptSelect = (currentVal) => `
    <select name="department" required>
        <option value="">-- Chọn phòng ban --</option>
        ${deptOptions}
        <option value="${currentVal}" ${departments.some(d => d.name === currentVal) ? '' : 'selected'}>${currentVal} (Lưu ý: Chưa có trong danh sách)</option>
    </select>
  `;

  // Dùng bản ghi đầu tiên làm mặc định
  let current = pending[0];

  const buildForm = (emp) => {
    // Logic kiểm tra và lock Department
    // emp.department chứa giá trị từ hardware (VD: 'IT', hoặc 'Chờ cập nhật')
    // departments là danh sách loaded từ JSON (có field device_code)

    let matchedDeptName = "";
    let isLocked = false;
    let deptError = "";

    // Tìm xem emp.department có khớp với device_code nào không
    const match = departments.find(d =>
      (d.device_code && d.device_code.toUpperCase() === emp.department.toUpperCase())
    );

    if (match) {
      matchedDeptName = match.name;
      isLocked = true;
    } else {
      // Nếu emp.department không phải "Chờ cập nhật" mà không tìm thấy match -> Cảnh báo
      if (emp.department && emp.department !== "Chờ cập nhật") {
        deptError = `Mã từ thiết bị "${emp.department}" không khớp phòng ban nào!`;
      }
    }

    return `
    <input type="hidden" name="id" value="${emp.id}" />
    <label>ID vân tay (không thể sửa)
      <input value="${emp.fingerprint_id}" disabled />
    </label>
    <label>Chọn ID vân tay mới
      <select name="select_pending" id="pending-select">
        ${options}
      </select>
    </label>
    <label>Họ tên
      <input name="full_name" required value="${emp.full_name.replace(/"/g, "&quot;")}" />
    </label>
    
    <label>Phòng ban
      ${deptError ? `<div style="color:red; font-size:12px; margin-bottom:4px">${deptError}</div>` : ''}
      <select name="department" required ${isLocked ? "disabled" : ""}>
           <option value="">-- Chọn --</option>
           ${departments.map(d => {
      const selected = (isLocked && d.name === matchedDeptName) ? "selected" : "";
      return `<option value="${d.name}" ${selected}>${d.name}</option>`;
    }).join('')}
           ${!isLocked && !match ? `<option value="">-- Tự chọn --</option>` : ''}
      </select>
      ${isLocked ? `<input type="hidden" name="department" value="${matchedDeptName}" /> <small>(Được xác định bởi thiết bị: ${emp.department})</small>` : ""}
    </label>
    
    <label>Chức vụ
      <input name="position" required value="${emp.position || ""}" />
    </label>
    <label>Ngày sinh
      <input name="birth_year" type="date" value="${formatDobInput(emp.birth_year)}" />
    </label>
    <div class="form-actions">
      <button type="submit">Lưu</button>
    </div>`;
  };

  showModal("Hoàn thiện thông tin vân tay mới", buildForm(current), async (fd) => {
    // Lấy ID thật sự từ hidden input, tuyệt đối không cho sửa fingerprint_id
    const payload = {
      id: fd.get("id"),
      full_name: fd.get("full_name"),
      department: fd.get("department"), // Nếu disabled, input hidden sẽ gửi value
      position: fd.get("position"),
      birth_year: fd.get("birth_year"),
    };

    const resUpdate = await fetch(api.employees, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    if (!resUpdate.ok) {
      alert("Không thể lưu thông tin: " + (await resUpdate.text()));
      return;
    }
    await loadEmployees();
    await loadDashboard();
    await loadDepartments(); // Auto-update department counts
  });

  // Sau khi modal render, gắn sự kiện đổi option để load đúng nhân viên pending
  const selectEl = document.getElementById("pending-select");
  if (selectEl) {
    selectEl.addEventListener("change", (e) => {
      const id = e.target.value;
      const emp = pending.find((p) => p.id == id);
      if (!emp) return;
      modalForm.innerHTML = buildForm(emp);
    });
  }
}

async function openEditEmployee(id) {
  const res = await fetch(api.employees);
  const rows = await res.json();
  const emp = rows.find((r) => r.id === id || r.id == id);
  if (!emp) return;

  // Load Departments for dropdown
  const resDept = await fetch(api.departments);
  const departments = await resDept.json();

  showModal(
    "Cập nhật nhân viên",
    `
    <input type="hidden" name="id" value="${emp.id}" />
    <label>Fingerprint ID (không sửa được)
      <input value="${emp.fingerprint_id}" disabled />
    </label>
    <label>Họ tên
      <input name="full_name" required value="${emp.full_name}" />
    </label>
    <label>Phòng ban (không sửa)
      <input name="department" value="${emp.department}" readonly />
    </label>
    <label>Chức vụ
      <input name="position" required value="${emp.position}" />
    </label>
    <label>Ngày sinh
      <input name="birth_year" type="date" value="${formatDobInput(emp.birth_year)}" />
    </label>
    <div class="form-actions">
      <button type="submit">Lưu</button>
    </div>`,
    async (formData) => {
      const payload = Object.fromEntries(formData.entries());
      const resUpdate = await fetch(api.employees, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      if (!resUpdate.ok) alert("Không thể cập nhật: " + (await resUpdate.text()));
      await loadEmployees();
      await loadDashboard();
      await loadDepartments(); // Auto-update department counts
    }
  );
}

async function deleteEmployee(id) {
  if (!confirm("Xóa nhân viên này?")) return;
  const res = await fetch(api.employees, {
    method: "DELETE",
    body: `id=${encodeURIComponent(id)}`,
  });
  if (!res.ok) alert("Không thể xóa");
  await loadEmployees();
  await loadDashboard();
  await loadDepartments(); // Auto-update department counts
}

// Logs
async function loadLogs() {
  const name = document.getElementById("filter-name").value;
  const date = document.getElementById("filter-date").value;
  const params = new URLSearchParams();
  if (name) params.append("name", name);
  if (date) params.append("date", date);
  const res = await fetch(`${api.attendance}?${params.toString()}`);
  const rows = await res.json();
  document.getElementById("log-rows").innerHTML = rows
    .map(
      (r) => `
    <tr>
      <td>${r.full_name}</td>
      <td>${r.department}</td>
      <td>${r.date}</td>
      <td>${formatTime(r.check_in)}</td>
      <td>${formatTime(r.check_out)}</td>
      <td>${statusBadge(r.status)}</td>
    </tr>`
    )
    .join("");
}
document.getElementById("btn-filter").addEventListener("click", loadLogs);
document.getElementById("btn-export").addEventListener("click", () => {
  const name = document.getElementById("filter-name").value;
  const date = document.getElementById("filter-date").value;
  const params = new URLSearchParams({ export: 1 });
  if (name) params.append("name", name);
  if (date) params.append("date", date);
  window.location = `${api.attendance}?${params.toString()}`;
});

// Settings / shifts
async function loadShifts() {
  const res = await fetch(api.settings);
  const rows = await res.json();
  document.getElementById("shift-rows").innerHTML = rows
    .map(
      (s) => `
    <tr>
      <td>${s.shift_name}</td>
      <td>${s.start_time}</td>
      <td>${s.end_time}</td>
      <td style="display:flex; gap:6px">
        <button class="ghost" data-shift-edit="${s.id}">Sửa</button>
        <button class="danger ghost" data-shift-del="${s.id}">Xóa</button>
      </td>
    </tr>`
    )
    .join("");

  document.querySelectorAll("[data-shift-edit]").forEach((btn) =>
    btn.addEventListener("click", () => openEditShift(btn.dataset.shiftEdit))
  );
  document.querySelectorAll("[data-shift-del]").forEach((btn) =>
    btn.addEventListener("click", () => deleteShift(btn.dataset.shiftDel))
  );
}
document.getElementById("btn-add-shift").addEventListener("click", () =>
  openShiftModal()
);

function openShiftModal(shift) {
  const isEdit = Boolean(shift);
  showModal(
    isEdit ? "Sửa ca" : "Thêm ca",
    `
    ${isEdit ? `<input type="hidden" name="id" value="${shift.id}" />` : ""}
    <label>Tên ca
      <input name="shift_name" required value="${shift?.shift_name || ""}" />
    </label>
    <label>Giờ vào
      <input name="start_time" type="time" required value="${shift?.start_time || ""}" />
    </label>
    <label>Giờ ra
      <input name="end_time" type="time" required value="${shift?.end_time || ""}" />
    </label>
    <div class="form-actions">
      <button type="submit">Lưu</button>
    </div>`,
    async (formData) => {
      const payload = Object.fromEntries(formData.entries());
      const method = isEdit ? "PUT" : "POST";
      const res = await fetch(api.settings, {
        method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      if (!res.ok) alert("Không thể lưu ca.");
      await loadShifts();
    }
  );
}

async function openEditShift(id) {
  const res = await fetch(api.settings);
  const rows = await res.json();
  const shift = rows.find((s) => s.id == id);
  if (shift) openShiftModal(shift);
}

async function deleteShift(id) {
  if (!confirm("Xóa ca này?")) return;
  const res = await fetch(api.settings, {
    method: "DELETE",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id }),
  });
  if (!res.ok) {
    alert("Không thể xóa ca.");
    return;
  }
  await loadShifts();
}

// Departments
async function loadDepartments() {
  const res = await fetch(api.departments);
  const rows = await res.json();
  document.getElementById("department-rows").innerHTML = rows
    .map(
      (d) => `
      <tr>
        <td>${d.name}</td>
        <td>${d.device_code}</td>
        <td>${d.employee_count}</td>
        <td style="display:flex; gap:6px">
          <button class="ghost" onclick="viewDepartmentEmployees('${d.name}')">Xem</button>
          <button class="ghost" onclick="openEditDepartment('${d.id}')">Sửa</button>
          <button class="danger ghost" onclick="deleteDepartment('${d.id}')">Xóa</button>
        </td>
      </tr>`
    )
    .join("");
}

document.getElementById("btn-add-department").addEventListener("click", () => openDepartmentModal());

function openDepartmentModal(dept) {
  const isEdit = Boolean(dept);
  showModal(
    isEdit ? "Sửa phòng ban" : "Thêm phòng ban",
    `
    ${isEdit ? `<input type="hidden" name="id" value="${dept.id}" />` : ""}
    <label>Tên phòng ban
      <input name="name" required value="${dept?.name || ""}" />
    </label>
    <label>Mã máy (Device Code)
      <input name="device_code" required value="${dept?.device_code || ""}" placeholder="VD: IT, KETOAN..." />
      <small>Dùng để map với phần cứng nếu cần</small>
    </label>
    <div class="form-actions">
      <button type="submit">Lưu</button>
    </div>`,
    async (formData) => {
      const payload = Object.fromEntries(formData.entries());
      const method = isEdit ? "PUT" : "POST";
      const res = await fetch(api.departments, {
        method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        const err = await res.json();
        alert("Lỗi: " + (err.error || "Không thể lưu"));
        return;
      }
      await loadDepartments();
    }
  );
}

async function openEditDepartment(id) {
  const res = await fetch(api.departments);
  const rows = await res.json();
  const dept = rows.find((d) => d.id == id);
  if (dept) openDepartmentModal(dept);
}

async function deleteDepartment(id) {
  if (!confirm("Xóa phòng ban này?")) return;
  const res = await fetch(api.departments, {
    method: "DELETE",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id }),
  });
  if (!res.ok) {
    const err = await res.json();
    alert("Lỗi: " + (err.error || "Không thể xóa"));
    return;
  }
  await loadDepartments();
}

async function viewDepartmentEmployees(deptName) {
  // Switch to details section
  document.querySelectorAll(".section").forEach((s) => s.classList.remove("active"));
  document.getElementById("department-details").classList.add("active");

  document.getElementById("dept-detail-title").textContent = `Chi tiết: ${deptName}`;

  const res = await fetch(`${api.employees}?dept=${encodeURIComponent(deptName)}`);
  const rows = await res.json();

  document.getElementById("dept-employee-rows").innerHTML = rows.length ? rows
    .map(
      (r) => `
        <tr>
          <td>${r.fingerprint_id}</td>
          <td>${r.full_name}</td>
          <td>${r.position}</td>
          <td>${formatDobDisplay(r.birth_year)}</td>
          <td>${r.created_at || "-"}</td>
        </tr>`
    )
    .join("") : '<tr><td colspan="5" style="text-align:center">Chưa có nhân viên</td></tr>';
}

// Initial load
loadDashboard();
loadEmployees();
loadDepartments(); // Load departments
loadLogs();
loadShifts();

// Auto-refresh function
function autoRefresh() {
  // Don't refresh if modal is open
  if (modal.classList.contains('show')) return;

  // Only refresh the current active section to reduce server load
  switch (currentSection) {
    case 'dashboard':
      loadDashboard();
      break;
    case 'employees':
      loadEmployees();
      loadDepartments(); // Also refresh dept counts
      break;
    case 'departments':
      loadDepartments();
      break;
    case 'logs':
      loadLogs();
      break;
    case 'settings':
      loadShifts();
      break;
  }
}

// Start auto-refresh
autoRefreshInterval = setInterval(autoRefresh, REFRESH_DELAY);

// Stop refresh when page is hidden (user switched tab)
document.addEventListener('visibilitychange', () => {
  if (document.hidden) {
    clearInterval(autoRefreshInterval);
  } else {
    autoRefreshInterval = setInterval(autoRefresh, REFRESH_DELAY);
    autoRefresh(); // Immediate refresh when returning to tab
  }
});




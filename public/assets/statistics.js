// ==== STATISTICS FUNCTIONS ====
async function loadStatistics() {
    const startDate = document.getElementById('stat-start-date').value;
    const endDate = document.getElementById('stat-end-date').value;
    const dept = document.getElementById('stat-department').value;
    const name = document.getElementById('stat-name').value;

    let url = `${api.statistics}?start_date=${startDate}&end_date=${endDate}`;
    if (dept) url += `&department=${encodeURIComponent(dept)}`;
    if (name) url += `&name=${encodeURIComponent(name)}`;

    const res = await fetch(url);
    const data = await res.json();

    document.getElementById('stat-total-shifts').textContent = data.overview.total_shifts;
    document.getElementById('stat-total-late').textContent = data.overview.total_late;
    document.getElementById('stat-total-early').textContent = data.overview.total_early;
    document.getElementById('stat-punctual').textContent = data.overview.most_punctual;

    document.getElementById('stats-summary-rows').innerHTML = data.summary.map(emp => `
    <tr>
      <td>${emp.employee_code}</td>
      <td>${emp.full_name}</td>
      <td>${emp.department}</td>
      <td>${emp.total_days}</td>
      <td>${emp.total_hours}h</td>
      <td>${emp.late_count}</td>
      <td>${emp.late_minutes} phút</td>
      <td contenteditable="true" style="min-width: 100px;">${emp.action}</td>
    </tr>
  `).join('');
}

async function showTopLateEmployees() {
    const startDate = document.getElementById('stat-start-date').value;
    const endDate = document.getElementById('stat-end-date').value;
    const dept = document.getElementById('stat-department').value;

    let url = `${api.statistics}?top=late&start_date=${startDate}&end_date=${endDate}`;
    if (dept) url += `&department=${encodeURIComponent(dept)}`;

    const res = await fetch(url);
    const list = await res.json();

    const html = `<h4>Top 10 Nhân Viên Đi Muộn Nhiều Nhất</h4>
    <table style="width: 100%; margin-top: 16px;">
      <thead><tr><th>Họ tên</th><th>Phòng</th><th>Số lần</th></tr></thead>
      <tbody>${list.map((e, i) => `<tr><td>${i + 1}. ${e.full_name}</td><td>${e.department}</td><td>${e.late_count}</td></tr>`).join('')}</tbody>
    </table>`;
    showModal('Danh Sách Đi Muộn', html, () => { });
}

async function showTopEarlyEmployees() {
    const startDate = document.getElementById('stat-start-date').value;
    const endDate = document.getElementById('stat-end-date').value;
    const dept = document.getElementById('stat-department').value;

    let url = `${api.statistics}?top=early&start_date=${startDate}&end_date=${endDate}`;
    if (dept) url += `&department=${encodeURIComponent(dept)}`;

    const res = await fetch(url);
    const list = await res.json();

    const html = `<h4>Top 10 Nhân Viên Về Sớm Nhiều Nhất</h4>
    <table style="width: 100%; margin-top: 16px;">
      <thead><tr><th>Họ tên</th><th>Phòng</th><th>Số lần</th></tr></thead>
      <tbody>${list.map((e, i) => `<tr><td>${i + 1}. ${e.full_name}</td><td>${e.department}</td><td>${e.early_count}</td></tr>`).join('')}</tbody>
    </table>`;
    showModal('Danh Sách Về Sớm', html, () => { });
}

async function exportStatisticsExcel() {
    const startDate = document.getElementById('stat-start-date').value;
    const endDate = document.getElementById('stat-end-date').value;
    const dept = document.getElementById('stat-department').value;
    const name = document.getElementById('stat-name').value;

    let url = `${api.statistics}?start_date=${startDate}&end_date=${endDate}`;
    if (dept) url += `&department=${encodeURIComponent(dept)}`;
    if (name) url += `&name=${encodeURIComponent(name)}`;

    const res = await fetch(url);
    const data = await res.json();

    let csv = '\uFEFF';
    csv += 'Mã NV;Họ tên;Phòng ban;Tổng ngày công;Tổng giờ làm;Số lần muộn;Tổng phút muộn;Hành động\n';
    data.summary.forEach(emp => {
        csv += `${emp.employee_code};${emp.full_name};${emp.department};${emp.total_days};${emp.total_hours};${emp.late_count};${emp.late_minutes};${emp.action}\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `thong-ke-${startDate}_${endDate}.csv`;
    link.click();
}

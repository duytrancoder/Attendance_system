<?php
// Simple admin UI for attendance management
require_once __DIR__ . '/../includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chấm công - Admin</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <aside class="sidebar">
        <div class="brand">Chấm công</div>
        <nav>
            <button data-section="dashboard" class="active">Dashboard</button>
            <button data-section="employees">Nhân viên</button>
            <button data-section="departments">Phòng ban</button>
            <button data-section="statistics">Thống Kê</button>
            <button data-section="logs">Lịch sử</button>
            <button data-section="settings">Cấu hình</button>
        </nav>
        <div style="margin-top: auto; padding-top: 24px;">
            <a href="logout.php" style="display: block; padding: 12px 14px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; color: #ef4444; text-align: center; text-decoration: none; font-size: 14px; transition: all 0.2s ease; font-weight: 500;">Đăng xuất</a>
        </div>
    </aside>

    <main>
        <header class="topbar">
            <div>
                <div class="title">Admin Panel</div>
                <div class="subtitle">Theo dõi chấm công vân tay</div>
            </div>
            <div class="actions">
                <span class="pill">AS608</span>
                <span class="pill success">ESP32 online</span>
            </div>
        </header>

        <section id="dashboard" class="section active">
            <div class="cards" id="stats-cards"></div>
            <div class="panel">
                <div class="panel-head">
                    <h3>Chấm công hôm nay</h3>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Nhân viên</th>
                                <th>Phòng ban</th>
                                <th>Ca</th>
                                <th>Vào</th>
                                <th>Ra</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody id="today-logs"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="employees" class="section">
            <div class="panel">
                <div class="panel-head">
                    <h3>Quản lý nhân viên</h3>
                    <div>
                        <button id="btn-add-employee">Vân tay mới được đăng ký</button>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Fingerprint ID</th>
                                <th>Họ tên</th>
                                <th>Phòng ban</th>
                                <th>Chức vụ</th>
                                <th>Ngày sinh</th>
                                <th>Ngày tạo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="employee-rows"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="departments" class="section">
            <div class="panel">
                <div class="panel-head">
                    <h3>Quản lý Phòng ban</h3>
                    <div>
                        <button id="btn-add-department">+ Thêm phòng ban</button>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Tên phòng ban</th>
                                <th>Mã máy (Device Code)</th>
                                <th>Số lượng NV</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="department-rows"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="department-details" class="section">
            <div class="panel">
                <div class="panel-head">
                    <h3 id="dept-detail-title">Chi tiết phòng ban</h3>
                    <button class="ghost" onclick="document.querySelector('[data-section=\'departments\']').click()"> Quay lại</button>
                </div>
                <div class="table-wrapper">
                     <table>
                        <thead>
                            <tr>
                                <th>Fingerprint ID</th>
                                <th>Họ tên</th>
                                <th>Chức vụ</th>
                                <th>Ngày sinh</th>
                                <th>Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody id="dept-employee-rows"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="statistics" class="section">
            <div class="panel">
                <div class="panel-head">
                    <h3>Thống Kê Chấm Công</h3>
                </div>
                
                <!-- Overview Cards -->
                <div class="cards" id="stats-overview">
                    <div class="card" style="cursor: default;">
                        <div class="card-label">Tổng số ca</div>
                        <div class="card-value" id="stat-total-shifts">0</div>
                    </div>
                    <div class="card" style="cursor: pointer;" onclick="showTopLateEmployees()">
                        <div class="card-label">Số lần đi muộn</div>
                        <div class="card-value danger" id="stat-total-late">0</div>
                        <small style="opacity: 0.7;">Nhấn để xem danh sách</small>
                    </div>
                    <div class="card" style="cursor: pointer;" onclick="showTopEarlyEmployees()">
                        <div class="card-label">Số lần về sớm</div>
                        <div class="card-value warn" id="stat-total-early">0</div>
                        <small style="opacity: 0.7;">Nhấn để xem danh sách</small>
                    </div>
                    <div class="card" style="cursor: default;">
                        <div class="card-label">Nhân viên chuyên cần</div>
                        <div class="card-value success" style="font-size: 14px;" id="stat-punctual">-</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="panel" style="margin-top: 20px;">
                    <div class="panel-head">
                        <h3>Bộ Lọc</h3>
                    </div>
                    <div style="padding: 16px; display: flex; gap: 10px; flex-wrap: wrap; align-items: end;">
                        <label style="flex: 1; min-width: 150px;">
                            Từ ngày
                            <input type="date" id="stat-start-date" />
                        </label>
                        <label style="flex: 1; min-width: 150px;">
                            Đến ngày
                            <input type="date" id="stat-end-date" />
                        </label>
                        <label style="flex: 1; min-width: 150px;">
                            Phòng ban
                            <select id="stat-department">
                                <option value="">Tất cả</option>
                            </select>
                        </label>
                        <label style="flex: 1; min-width: 150px;">
                            Tìm tên
                            <input type="text" id="stat-name" placeholder="Nhập tên..." />
                        </label>
                        <button id="btn-filter-stats" style="height: 38px;">Lọc</button>
                        <button id="btn-export-stats" class="ghost" style="height: 38px;">Xuất Excel</button>
                    </div>
                </div>

                <!-- Summary Table -->
                <div class="panel" style="margin-top: 20px;">
                    <div class="panel-head">
                        <h3>Bảng Tổng Hợp</h3>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Mã NV</th>
                                    <th>Họ tên</th>
                                    <th>Phòng ban</th>
                                    <th>Tổng ngày công</th>
                                    <th>Tổng giờ làm</th>
                                    <th>Số lần muộn</th>
                                    <th>Tổng phút muộn</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="stats-summary-rows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section id="logs" class="section">
            <div class="panel">
                <div class="panel-head">
                    <h3>Lịch sử chấm công</h3>
                    <div class="filters">
                        <input type="text" id="filter-name" placeholder="Lọc theo tên">
                        <input type="date" id="filter-date">
                        <button id="btn-filter">Lọc</button>
                        <button id="btn-export" class="ghost">Xuất Excel</button>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Tên</th>
                                <th>Phòng ban</th>
                                <th>Ngày</th>
                                <th>Giờ vào</th>
                                <th>Giờ ra</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody id="log-rows"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="settings" class="section">
            <div class="panel">
                <div class="panel-head">
                    <h3>Cấu hình ca làm</h3>
                    <div>
                        <button id="btn-add-shift">+ Thêm ca</button>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Tên ca</th>
                                <th>Giờ vào</th>
                                <th>Giờ ra</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="shift-rows"></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <div class="modal-backdrop" id="modal">
        <div class="modal">
            <div class="modal-head">
                <h3 id="modal-title">Thêm mới</h3>
                <button id="modal-close">×</button>
            </div>
            <form id="modal-form" class="modal-body"></form>
        </div>
    </div>

    <script src="assets/statistics.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>



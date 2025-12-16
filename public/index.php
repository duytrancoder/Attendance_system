<?php
// Simple admin UI for attendance management
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
            <button data-section="logs">Lịch sử</button>
            <button data-section="settings">Cấu hình</button>
        </nav>
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
                                <th>Năm sinh</th>
                                <th>Ngày tạo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="employee-rows"></tbody>
                    </table>
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

    <script src="assets/app.js"></script>
</body>
</html>



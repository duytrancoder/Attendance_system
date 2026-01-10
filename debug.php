<!DOCTYPE html>
<html>
<head>
    <title>Debug Tools - Attendance System</title>
    <meta charset="utf-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 40px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .tool-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }
        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        .tool-card h2 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.5em;
        }
        .tool-card p {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        .tool-card .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 5px;
            transition: background 0.3s;
            font-weight: bold;
        }
        .tool-card .btn:hover {
            background: #5568d3;
        }
        .status {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }
        .status h2 {
            color: #667eea;
            margin-bottom: 15px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .status-item {
            background: #f7f7f7;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        .status-item strong {
            display: block;
            color: #333;
            margin-bottom: 5px;
        }
        .status-item span {
            color: #666;
            font-size: 0.9em;
        }
        .icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .docs {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .docs h2 {
            color: #667eea;
            margin-bottom: 15px;
        }
        .docs ul {
            list-style: none;
        }
        .docs li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .docs li:last-child {
            border-bottom: none;
        }
        .docs a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        .docs a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛠️ Debug Tools - Attendance System</h1>

        <div class="status">
            <h2>📊 System Status</h2>
            <div class="status-grid">
                <?php
                require_once __DIR__ . '/includes/db.php';
                $pdo = db();
                
                $totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
                $pendingCommands = $pdo->query("SELECT COUNT(*) FROM device_commands WHERE status = 'pending'")->fetchColumn();
                $todayAttendance = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()");
                $todayAttendance->execute();
                $todayCount = $todayAttendance->fetchColumn();
                
                $logFile = __DIR__ . '/delete_requests.log';
                $logExists = file_exists($logFile) ? 'Yes ✅' : 'No ❌';
                ?>
                
                <div class="status-item">
                    <strong>Total Employees</strong>
                    <span><?= $totalEmployees ?></span>
                </div>
                <div class="status-item">
                    <strong>Pending Commands</strong>
                    <span><?= $pendingCommands ?></span>
                </div>
                <div class="status-item">
                    <strong>Today Attendance</strong>
                    <span><?= $todayCount ?></span>
                </div>
                <div class="status-item">
                    <strong>Delete Log File</strong>
                    <span><?= $logExists ?></span>
                </div>
            </div>
        </div>

        <div class="tools-grid">
            <div class="tool-card">
                <div class="icon">📡</div>
                <h2>Request Monitor</h2>
                <p>Real-time monitoring of DELETE requests from Arduino. Auto-refreshes every 2 seconds.</p>
                <a href="monitor_delete.php" class="btn" target="_blank">Open Monitor →</a>
            </div>

            <div class="tool-card">
                <div class="icon">🧪</div>
                <h2>Test Delete API</h2>
                <p>Test the delete API manually with a fingerprint ID.</p>
                <form action="test_delete.php" method="get" target="_blank" style="display: flex; gap: 10px;">
                    <input type="number" name="id" placeholder="Fingerprint ID" required 
                           style="flex: 1; padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                    <button type="submit" class="btn" style="border: none; cursor: pointer;">Test →</button>
                </form>
            </div>

            <div class="tool-card">
                <div class="icon">📋</div>
                <h2>Debug Logs</h2>
                <p>View detailed logs including PHP errors, Apache access log, and database state.</p>
                <a href="debug_delete_log.php" class="btn" target="_blank">View Logs →</a>
            </div>

            <div class="tool-card">
                <div class="icon">🗑️</div>
                <h2>Clear Logs</h2>
                <p>Clear the delete request log file to start fresh.</p>
                <a href="clear_delete_log.php" class="btn" 
                   onclick="return confirm('Clear all delete logs?')" target="_blank">Clear →</a>
            </div>

            <div class="tool-card">
                <div class="icon">👥</div>
                <h2>Employee List</h2>
                <p>Quick view of all employees with their fingerprint IDs.</p>
                <a href="list_employees.php" class="btn" target="_blank">View List →</a>
            </div>

            <div class="tool-card">
                <div class="icon">⚙️</div>
                <h2>Web Dashboard</h2>
                <p>Main admin dashboard for managing the system.</p>
                <a href="public/index.php" class="btn" target="_blank">Open Dashboard →</a>
            </div>
        </div>

        <div class="docs">
            <h2>📚 Documentation</h2>
            <ul>
                <li>
                    <a href="DEBUG_DELETE.md" target="_blank">
                        🔧 DEBUG_DELETE.md - Complete debugging guide
                    </a>
                </li>
                <li>
                    <a href="FIX_DELETE_ISSUE.md" target="_blank">
                        ✅ FIX_DELETE_ISSUE.md - Fix implementation details
                    </a>
                </li>
                <li>
                    <a href="ontap.md" target="_blank">
                        📖 ontap.md - System overview and functions
                    </a>
                </li>
                <li>
                    <a href="README.md" target="_blank">
                        📄 README.md - Project documentation
                    </a>
                </li>
            </ul>
        </div>

        <div style="text-align: center; margin-top: 30px; color: white;">
            <p>💡 <strong>Tip:</strong> Start with the Request Monitor to see if Arduino is calling the API</p>
        </div>
    </div>
</body>
</html>

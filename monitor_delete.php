<!DOCTYPE html>
<html>
<head>
    <title>Delete Requests Monitor</title>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
        }
        h1 {
            color: #00ffff;
        }
        .log-entry {
            background: #2d2d2d;
            border-left: 3px solid #00ff00;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .log-entry.error {
            border-left-color: #ff0000;
            color: #ff6666;
        }
        .timestamp {
            color: #ffff00;
            font-weight: bold;
        }
        .method {
            color: #00ffff;
            font-weight: bold;
        }
        .clear-btn {
            background: #ff3333;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
            margin: 10px 0;
        }
        .refresh-btn {
            background: #00ff00;
            color: black;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
            margin: 10px 10px 10px 0;
        }
        .stats {
            background: #2d2d2d;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .stat-item {
            display: inline-block;
            margin-right: 30px;
        }
    </style>
</head>
<body>
    <h1>🔍 Delete Requests Monitor</h1>
    
    <div>
        <button class="refresh-btn" onclick="location.reload()">🔄 Refresh</button>
        <button class="clear-btn" onclick="clearLog()">🗑️ Clear Log</button>
    </div>

    <div class="stats">
        <div class="stat-item">
            <strong>Total Requests:</strong> <span id="total">0</span>
        </div>
        <div class="stat-item">
            <strong>GET:</strong> <span id="get-count">0</span>
        </div>
        <div class="stat-item">
            <strong>POST:</strong> <span id="post-count">0</span>
        </div>
        <div class="stat-item">
            <strong>Last Update:</strong> <span id="last-update"><?= date('H:i:s') ?></span>
        </div>
    </div>

    <div id="logs">
        <?php
        $logFile = __DIR__ . '/delete_requests.log';
        
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $lines = array_reverse($lines); // Newest first
            
            $total = count($lines);
            $getCount = 0;
            $postCount = 0;
            
            foreach ($lines as $line) {
                if (stripos($line, '"GET"') !== false || stripos($line, 'Method: GET') !== false) $getCount++;
                if (stripos($line, '"POST"') !== false || stripos($line, 'Method: POST') !== false) $postCount++;
                
                $class = 'log-entry';
                if (stripos($line, 'ERROR') !== false || stripos($line, 'error') !== false) {
                    $class .= ' error';
                }
                
                echo "<div class='$class'>" . htmlspecialchars($line) . "</div>";
            }
            
            echo "<script>";
            echo "document.getElementById('total').textContent = $total;";
            echo "document.getElementById('get-count').textContent = $getCount;";
            echo "document.getElementById('post-count').textContent = $postCount;";
            echo "</script>";
        } else {
            echo "<div class='log-entry'>No log file found. Waiting for first request...</div>";
            echo "<div class='log-entry'>Log file will be created at: " . htmlspecialchars($logFile) . "</div>";
        }
        ?>
    </div>

    <script>
        function clearLog() {
            if (confirm('Clear all logs?')) {
                fetch('clear_delete_log.php')
                    .then(() => location.reload());
            }
        }
        
        // Auto-refresh every 2 seconds
        setInterval(() => {
            location.reload();
        }, 2000);
    </script>

    <hr style="border-color: #444; margin: 30px 0;">
    <div style="color: #888;">
        <h3 style="color: #00ffff;">Instructions:</h3>
        <ul>
            <li>This page auto-refreshes every 2 seconds</li>
            <li>When Arduino deletes fingerprint, you should see a GET request with id parameter</li>
            <li>If you don't see any requests, Arduino is NOT calling the API</li>
            <li>Check Arduino serial monitor for the exact URL being called</li>
            <li>Expected format: <code>GET /api/delete.php?id=5</code></li>
        </ul>
    </div>
</body>
</html>

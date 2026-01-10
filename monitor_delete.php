<!DOCTYPE html>
<html>
<head>
    <title>🔴 LIVE - Delete Request Monitor</title>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="2">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #0a0e27;
            color: #00ff41;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: white;
            text-align: center;
        }
        .live-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            background: #ff0000;
            border-radius: 50%;
            animation: pulse 1s infinite;
            margin-right: 10px;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #1a1f3a;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #00ff41;
        }
        .stat-card.warning { border-left-color: #ffaa00; }
        .stat-card.error { border-left-color: #ff0000; }
        .stat-label {
            color: #888;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
        }
        .log-container {
            background: #1a1f3a;
            border-radius: 8px;
            padding: 20px;
            max-height: 600px;
            overflow-y: auto;
        }
        .log-entry {
            background: #0f1729;
            border-left: 3px solid #00ff41;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            font-size: 13px;
        }
        .log-entry.get { border-left-color: #00ff41; }
        .log-entry.post { border-left-color: #ffaa00; }
        .log-entry.other { border-left-color: #ff0000; }
        .timestamp {
            color: #00ffff;
            font-weight: bold;
        }
        .method {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            margin: 0 5px;
        }
        .method.get { background: #00aa00; color: white; }
        .method.post { background: #ff8800; color: white; }
        .json {
            color: #ffff00;
            margin-left: 20px;
        }
        .no-logs {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }
        .instructions {
            background: #1a1f3a;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 2px dashed #667eea;
        }
        .instructions h3 {
            color: #00ffff;
            margin-bottom: 10px;
        }
        .instructions li {
            margin: 8px 0;
            color: #aaa;
        }
        .clear-btn {
            background: #ff3333;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            margin: 10px 5px;
        }
        .clear-btn:hover {
            background: #cc0000;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <span class="live-indicator"></span>
            LIVE DELETE REQUEST MONITOR
        </h1>
        <p>Auto-refresh every 2 seconds | Last update: <?= date('H:i:s') ?></p>
    </div>

    <?php
    $logFile = __DIR__ . '/delete_requests.log';
    
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $lines = array_reverse(array_slice($lines, -50)); // Last 50 entries, newest first
        
        $total = count($lines);
        $getCount = 0;
        $postCount = 0;
        $lastMinute = 0;
        
        $oneMinuteAgo = time() - 60;
        
        foreach ($lines as $line) {
            if (stripos($line, 'Method: GET') !== false) $getCount++;
            if (stripos($line, 'Method: POST') !== false) $postCount++;
            
            // Count requests in last minute
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $timestamp = strtotime($matches[1]);
                if ($timestamp > $oneMinuteAgo) {
                    $lastMinute++;
                }
            }
        }
        
        echo '<div class="stats">';
        echo '<div class="stat-card">';
        echo '<div class="stat-label">Total Requests</div>';
        echo '<div class="stat-value">' . $total . '</div>';
        echo '</div>';
        
        echo '<div class="stat-card">';
        echo '<div class="stat-label">GET Requests</div>';
        echo '<div class="stat-value">' . $getCount . '</div>';
        echo '</div>';
        
        echo '<div class="stat-card warning">';
        echo '<div class="stat-label">POST Requests</div>';
        echo '<div class="stat-value">' . $postCount . '</div>';
        echo '</div>';
        
        echo '<div class="stat-card ' . ($lastMinute > 0 ? '' : 'error') . '">';
        echo '<div class="stat-label">Last Minute</div>';
        echo '<div class="stat-value">' . $lastMinute . '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div>';
        echo '<button class="clear-btn" onclick="if(confirm(\'Clear all logs?\')) window.location.href=\'clear_delete_log.php\'">🗑️ Clear Logs</button>';
        echo '<button class="clear-btn" style="background:#00aa00" onclick="window.location.reload()">🔄 Refresh Now</button>';
        echo '</div>';
        
        echo '<div class="log-container">';
        
        if (empty($lines)) {
            echo '<div class="no-logs">📭 No requests logged yet. Waiting for Arduino to call API...</div>';
        } else {
            foreach ($lines as $line) {
                $class = 'log-entry';
                if (stripos($line, 'Method: GET') !== false) $class .= ' get';
                elseif (stripos($line, 'Method: POST') !== false) $class .= ' post';
                else $class .= ' other';
                
                // Parse the log line
                $line = htmlspecialchars($line);
                $line = preg_replace('/\[([^\]]+)\]/', '<span class="timestamp">[$1]</span>', $line);
                $line = preg_replace('/Method: GET/', '<span class="method get">GET</span>', $line);
                $line = preg_replace('/Method: POST/', '<span class="method post">POST</span>', $line);
                $line = preg_replace('/GET: ({[^}]+})/', 'GET: <span class="json">$1</span>', $line);
                
                echo "<div class='$class'>$line</div>";
            }
        }
        
        echo '</div>';
        
    } else {
        echo '<div class="stats">';
        echo '<div class="stat-card error">';
        echo '<div class="stat-label">Status</div>';
        echo '<div class="stat-value">⚠️ No Log File</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="no-logs">';
        echo '<h2>📂 Log file not found</h2>';
        echo '<p>File will be created when first request arrives</p>';
        echo '<p><code>' . htmlspecialchars($logFile) . '</code></p>';
        echo '</div>';
    }
    ?>

    <div class="instructions">
        <h3>🔍 HOW TO USE THIS MONITOR:</h3>
        <ol>
            <li><strong>Keep this page open</strong> - It auto-refreshes every 2 seconds</li>
            <li><strong>Go to Arduino/ESP32</strong> - Delete a fingerprint (e.g., ID=5)</li>
            <li><strong>Watch this screen</strong> - You should see a <span class="method get">GET</span> request appear</li>
            <li><strong>Check the parameters</strong> - Should have <code>{"id":"5"}</code></li>
        </ol>
        
        <h3 style="margin-top: 20px">✅ WHAT TO EXPECT:</h3>
        <ul>
            <li><strong style="color:#00ff41">SUCCESS:</strong> You see GET request with correct ID → Arduino IS calling API</li>
            <li><strong style="color:#ff0000">PROBLEM:</strong> No request appears → Arduino NOT calling API (need to add code)</li>
        </ul>
        
        <h3 style="margin-top: 20px">🛠️ IF NO REQUEST APPEARS:</h3>
        <ul>
            <li>Arduino code is MISSING the HTTP GET call after deleting fingerprint</li>
            <li>Add this to Arduino after <code>finger.deleteModel(id)</code>:</li>
            <li><code style="color:#ffff00">notifyServerDelete(id);</code></li>
            <li>See full code example in <a href="ontap.md" style="color:#00ffff">ontap.md</a></li>
        </ul>
    </div>

    <script>
        // Scroll to top on refresh
        window.scrollTo(0, 0);
        
        // Visual indicator of auto-refresh
        let countdown = 2;
        setInterval(() => {
            countdown--;
            if (countdown <= 0) countdown = 2;
            document.title = `(${countdown}s) 🔴 LIVE Monitor`;
        }, 1000);
    </script>
</body>
</html>

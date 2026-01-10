<!DOCTYPE html>
<html>
<head>
    <title>Employee List</title>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
        }
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .fingerprint-id {
            font-weight: bold;
            color: #667eea;
            font-size: 1.2em;
        }
        .pending {
            color: #ff9800;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>👥 Employee List</h1>
    
    <?php
    require_once __DIR__ . '/includes/db.php';
    $pdo = db();
    
    $stmt = $pdo->query("
        SELECT id, fingerprint_id, full_name, department, position, created_at 
        FROM employees 
        ORDER BY fingerprint_id ASC
    ");
    $employees = $stmt->fetchAll();
    
    echo "<p>Total: <strong>" . count($employees) . " employees</strong></p>";
    ?>
    
    <table>
        <thead>
            <tr>
                <th>Fingerprint ID</th>
                <th>Full Name</th>
                <th>Department</th>
                <th>Position</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $emp): ?>
                <tr class="<?= strpos($emp['full_name'], 'Nhân viên mới') !== false ? 'pending' : '' ?>">
                    <td class="fingerprint-id"><?= $emp['fingerprint_id'] ?></td>
                    <td><?= htmlspecialchars($emp['full_name']) ?></td>
                    <td><?= htmlspecialchars($emp['department']) ?></td>
                    <td><?= htmlspecialchars($emp['position']) ?></td>
                    <td><?= $emp['created_at'] ?></td>
                    <td>
                        <a href="test_delete.php?id=<?= $emp['fingerprint_id'] ?>" 
                           target="_blank"
                           onclick="return confirm('Test delete fingerprint ID <?= $emp['fingerprint_id'] ?>?')">
                            🗑️ Test Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top: 20px;">
        <a href="debug.php">← Back to Debug Tools</a>
    </p>
</body>
</html>

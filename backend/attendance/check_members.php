<?php
// Quick diagnostic for members table
// Save as backend/attendance/check_members.php

header("Content-Type: text/html; charset=UTF-8");

try {
    include_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h1>Members Table Diagnostic</h1>";
    echo "<style>
        body { font-family: monospace; padding: 20px; }
        table { border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        .success { color: green; }
        .error { color: red; }
    </style>";
    
    // Check if members table exists
    $check = $db->query("SHOW TABLES LIKE 'members'");
    if ($check->rowCount() === 0) {
        echo "<p class='error'>❌ Members table does not exist!</p>";
        exit;
    }
    
    echo "<p class='success'>✓ Members table exists</p>";
    
    // Show structure
    echo "<h2>Table Structure</h2>";
    $struct = $db->query("DESCRIBE members")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($struct as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Count records
    $count = $db->query("SELECT COUNT(*) as total FROM members")->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total members: <strong>{$count['total']}</strong></p>";
    
    // Sample data
    echo "<h2>Sample Members (First 5)</h2>";
    $sample = $db->query("SELECT * FROM members LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    if (count($sample) > 0) {
        echo "<table>";
        echo "<tr>";
        foreach (array_keys($sample[0]) as $header) {
            echo "<th>$header</th>";
        }
        echo "</tr>";
        foreach ($sample as $row) {
            echo "<tr>";
            foreach ($row as $val) {
                echo "<td>" . htmlspecialchars($val ?? '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>No members found in database</p>";
    }
    
    // Check fellowships relationship
    echo "<h2>Members by Fellowship</h2>";
    $byFellowship = $db->query("
        SELECT 
            fellowship_id, 
            COUNT(*) as count,
            GROUP_CONCAT(CONCAT(first_name, ' ', last_name) SEPARATOR ', ') as names
        FROM members 
        GROUP BY fellowship_id
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Fellowship ID</th><th>Count</th><th>Sample Names</th></tr>";
    foreach ($byFellowship as $row) {
        $names = strlen($row['names']) > 100 ? substr($row['names'], 0, 100) . '...' : $row['names'];
        echo "<tr>";
        echo "<td>{$row['fellowship_id']}</td>";
        echo "<td>{$row['count']}</td>";
        echo "<td>{$names}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if status column exists
    echo "<h2>Status Check</h2>";
    $hasStatus = false;
    foreach ($struct as $col) {
        if ($col['Field'] === 'status') {
            $hasStatus = true;
            echo "<p class='success'>✓ Status column exists: {$col['Type']}</p>";
            
            // Show status distribution
            $statuses = $db->query("
                SELECT status, COUNT(*) as count 
                FROM members 
                GROUP BY status
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table>";
            echo "<tr><th>Status</th><th>Count</th></tr>";
            foreach ($statuses as $s) {
                echo "<tr><td>{$s['status']}</td><td>{$s['count']}</td></tr>";
            }
            echo "</table>";
        }
    }
    
    if (!$hasStatus) {
        echo "<p class='error'>❌ Status column does NOT exist</p>";
        echo "<p>The query will fail. Need to use different filtering.</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}
?>
<?php
// scripts/verify_dashboard_api.php

// Simulate session
session_start();
$_SESSION['user_id'] = 1; // Assume admin/pastor
$_SESSION['role'] = 'pastor';

function testEndpoint($name, $url) {
    echo "Testing $name ($url)...\n";
    
    // Use curl to fetch local url
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . session_id());
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "[FAILED] HTTP Code: $httpCode\n";
        echo "Response: $response\n";
        return false;
    }
    
    $json = json_decode($response, true);
    if ($json === null) {
        echo "[FAILED] Invalid JSON\n";
        echo "Response: $response\n";
        return false;
    }
    
    echo "[PASS] Valid JSON received.\n";
    // echo "Response snippet: " . substr($response, 0, 100) . "...\n";
    return $json;
}

$baseUrl = "http://localhost/church/backend";

// 1. Members
$members = testEndpoint("Members", "$baseUrl/members/read.php");
if ($members) echo "Count: " . (count($members['data'] ?? $members)) . "\n\n";

// 2. Fellowships
$fellowships = testEndpoint("Fellowships", "$baseUrl/fellowships/read.php");
if ($fellowships) echo "Count: " . (count($fellowships['fellowships'] ?? $fellowships['data'] ?? $fellowships)) . "\n\n";

// 3. Attendance Overview
$today = date('Y-m-d');
$lastWeek = date('Y-m-d', strtotime('-7 days'));
$att = testEndpoint("Attendance Overview", "$baseUrl/attendance/report.php?type=overview&start_date=$lastWeek&end_date=$today");
if ($att) echo "Records: " . (count($att['data']['records'] ?? [])) . "\n\n";

// 4. Attendance Trends
$monthsAgo = date('Y-m-d', strtotime('-12 weeks'));
$trends = testEndpoint("Attendance Trends", "$baseUrl/attendance/report.php?type=trends&start_date=$monthsAgo&end_date=$today");
if ($trends) {
    $dates = $trends['data']['dates'] ?? [];
    echo "Trend Points: " . count($dates) . "\n";
    if (count($dates) > 0) echo "First Date: " . $dates[0] . "\n";
    echo "\n";
}

// 5. Financial Chart Data
$fin = testEndpoint("Financial Chart", "$baseUrl/financial/chart_data.php");
if ($fin) {
    echo "Labels: " . implode(", ", $fin['labels'] ?? []) . "\n";
    echo "Tithes: " . implode(", ", $fin['tithes'] ?? []) . "\n";
    echo "Expenses: " . implode(", ", $fin['expenses'] ?? []) . "\n";
}

echo "Verification Complete.\n";
?>

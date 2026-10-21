<?php
// backend/members/birthdays_read.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/security.php';

allow_cors();
secure_session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed. Use GET."]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $filter = $_GET['filter'] ?? 'today'; // today, upcoming, monthly

    $params = [];
    $where = ["m.status = 'active'", "m.birth_date IS NOT NULL"];

    $scopeId = current_fellowship_scope();
    if ($scopeId !== null) {
        $where[] = "m.fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = $scopeId;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT m.id, m.first_name, m.last_name, m.email, m.phone, m.birth_date, m.photo, f.name as fellowship_name 
            FROM members m 
            LEFT JOIN fellowships f ON m.fellowship_id = f.id 
            $whereClause";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $todayDate = new DateTime('today');
    $filteredMembers = [];

    foreach ($members as $member) {
        $birthDate = new DateTime($member['birth_date']);
        
        // Form a target birthday in the current year
        $thisYearBday = new DateTime(date('Y') . '-' . $birthDate->format('m-d'));
        
        // If the birthday has already passed this year, the next birthday is next year
        if ($thisYearBday < $todayDate) {
            $nextBday = new DateTime((date('Y') + 1) . '-' . $birthDate->format('m-d'));
        } else {
            $nextBday = clone $thisYearBday;
        }
        
        $member['age_turning'] = $nextBday->format('Y') - $birthDate->format('Y');
        
        // Days difference
        $diff = clone $todayDate;
        $diff = $diff->diff($nextBday)->days;
        
        // Determine inclusion based on filter
        $include = false;
        if ($filter === 'today') {
            if ($birthDate->format('m-d') === $todayDate->format('m-d')) {
                $include = true;
            }
        } elseif ($filter === 'upcoming') {
            if ($diff > 0 && $diff <= 7 && $birthDate->format('m-d') !== $todayDate->format('m-d')) {
                $include = true;
            }
        } elseif ($filter === 'monthly') {
            if ($birthDate->format('m') === $todayDate->format('m')) {
                $include = true;
            }
        }

        if ($include) {
            if (!empty($member['photo'])) {
                $member['photo_url'] = "/church/" . $member['photo'];
            }
            $member['days_until'] = $diff;
            $filteredMembers[] = $member;
        }
    }

    // Sort by days until birthday
    usort($filteredMembers, function($a, $b) {
        return $a['days_until'] <=> $b['days_until'];
    });

    echo json_encode([
        "data" => $filteredMembers
    ]);

} catch (Exception $e) {
    error_log("Birthdays read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Server error"]);
}
?>

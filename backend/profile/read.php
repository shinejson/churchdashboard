<?php
// backend/profile/read.php
require_once '../config/security.php';
require_once '../config/database.php';

secure_session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'member';

try {
    // Get the member_id associated with this user
    // First check if users table has a member_id column, otherwise use user_id
    $userQuery = "SELECT member_id FROM users WHERE id = :user_id";
    $userStmt = $db->prepare($userQuery);
    $userStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $userStmt->execute();
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    // If member_id column doesn't exist or is null, try to find member by matching user_id
    $member_id = null;
    if ($userData && !empty($userData['member_id'])) {
        $member_id = $userData['member_id'];
    } else {
        // Try to find member by user_id or by matching username/email
        $memberQuery = "SELECT id FROM members WHERE user_id = :user_id OR id = :user_id LIMIT 1";
        $memberStmt = $db->prepare($memberQuery);
        $memberStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $memberStmt->execute();
        $memberData = $memberStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($memberData) {
            $member_id = $memberData['id'];
        }
    }
    
    if (!$member_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Member profile not found. Please contact administrator to link your account.'
        ]);
        exit;
    }
    
    // Get member profile information
    $profileQuery = "
        SELECT 
            m.*,
            f.name as fellowship_name,
            c.name as cell_name,
            c.meeting_day as cell_meeting_day,
            c.location as cell_location,
            d.name as department_name,
            CONCAT(leader.first_name, ' ', leader.last_name) as cell_leader_name
        FROM members m
        LEFT JOIN fellowships f ON m.fellowship_id = f.id
        LEFT JOIN cells c ON m.cell_id = c.id
        LEFT JOIN departments d ON m.department_id = d.id
        LEFT JOIN members leader ON c.leader_id = leader.id
        WHERE m.id = :member_id
    ";
    
    $profileStmt = $db->prepare($profileQuery);
    $profileStmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
    $profileStmt->execute();
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        echo json_encode([
            'success' => false,
            'message' => 'Profile not found'
        ]);
        exit;
    }
    
    // Get tithe contributions
    $titheQuery = "
        SELECT 
            tt.id,
            tt.amount,
            tt.transaction_date,
            tt.payment_method,
            tt.notes,
            f.name as fellowship_name
        FROM tithe_transactions tt
        LEFT JOIN fellowships f ON tt.fellowship_id = f.id
        WHERE tt.member_id = :member_id
        ORDER BY tt.transaction_date DESC
        LIMIT 50
    ";
    
    $titheStmt = $db->prepare($titheQuery);
    $titheStmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
    $titheStmt->execute();
    $tithes = $titheStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get tithe summary
    $titheSummaryQuery = "
        SELECT 
            COUNT(*) as total_count,
            SUM(amount) as total_amount,
            AVG(amount) as average_amount,
            MIN(transaction_date) as first_tithe_date,
            MAX(transaction_date) as last_tithe_date,
            SUM(CASE WHEN YEAR(transaction_date) = YEAR(CURDATE()) THEN amount ELSE 0 END) as year_to_date,
            SUM(CASE WHEN YEAR(transaction_date) = YEAR(CURDATE()) AND MONTH(transaction_date) = MONTH(CURDATE()) THEN amount ELSE 0 END) as this_month
        FROM tithe_transactions
        WHERE member_id = :member_id
    ";
    
    $titheSummaryStmt = $db->prepare($titheSummaryQuery);
    $titheSummaryStmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
    $titheSummaryStmt->execute();
    $titheSummary = $titheSummaryStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get partnership contributions
    $partnershipQuery = "
        SELECT 
            pc.id,
            pc.amount,
            pc.contribution_date,
            pa.name as partnership_arm_name,
            pa.description as partnership_arm_description
        FROM partnership_contributions pc
        LEFT JOIN partnership_arms pa ON pc.partnership_arm_id = pa.id
        WHERE pc.member_id = :member_id
        ORDER BY pc.contribution_date DESC
        LIMIT 50
    ";
    
    $partnershipStmt = $db->prepare($partnershipQuery);
    $partnershipStmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
    $partnershipStmt->execute();
    $partnerships = $partnershipStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get partnership summary
    $partnershipSummaryQuery = "
        SELECT 
            COUNT(*) as total_count,
            SUM(amount) as total_amount,
            COUNT(DISTINCT partnership_arm_id) as arms_supported,
            SUM(CASE WHEN YEAR(contribution_date) = YEAR(CURDATE()) THEN amount ELSE 0 END) as year_to_date,
            SUM(CASE WHEN YEAR(contribution_date) = YEAR(CURDATE()) AND MONTH(contribution_date) = MONTH(CURDATE()) THEN amount ELSE 0 END) as this_month
        FROM partnership_contributions
        WHERE member_id = :member_id
    ";
    
    $partnershipSummaryStmt = $db->prepare($partnershipSummaryQuery);
    $partnershipSummaryStmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
    $partnershipSummaryStmt->execute();
    $partnershipSummary = $partnershipSummaryStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get attendance records
    $attendanceQuery = "
        SELECT 
            ar.id,
            ar.date,
            ar.status,
            ar.notes,
            s.name as service_name,
            f.name as fellowship_name
        FROM attendance_records ar
        LEFT JOIN services s ON ar.service_id = s.id
        LEFT JOIN fellowships f ON ar.fellowship_id = f.id
        WHERE ar.member_id = :member_id
        ORDER BY ar.date DESC
        LIMIT 50
    ";
    
    $attendanceStmt = $db->prepare($attendanceQuery);
    $attendanceStmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
    $attendanceStmt->execute();
    $attendance = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance summary
    $attendanceSummaryQuery = "
        SELECT 
            COUNT(*) as total_services,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_rate,
            MAX(date) as last_attendance_date
        FROM attendance_records
        WHERE member_id = :member_id
    ";
    
    $attendanceSummaryStmt = $db->prepare($attendanceSummaryQuery);
    $attendanceSummaryStmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
    $attendanceSummaryStmt->execute();
    $attendanceSummary = $attendanceSummaryStmt->fetch(PDO::FETCH_ASSOC);
    
    // Return all data
    echo json_encode([
        'success' => true,
        'data' => [
            'profile' => $profile,
            'tithes' => $tithes,
            'tithe_summary' => $titheSummary,
            'partnerships' => $partnerships,
            'partnership_summary' => $partnershipSummary,
            'attendance' => $attendance,
            'attendance_summary' => $attendanceSummary
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load profile: ' . $e->getMessage()
    ]);
}

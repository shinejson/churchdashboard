<?php
// backend/financial/read.php
ob_start();
session_start();
require_once '../config/security.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    include_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Fellowship data isolation: non-admins are always scoped to their own fellowship
    $scopeId = current_fellowship_scope();

    // Get action parameter
    $action = $_GET['action'] ?? 'transactions';
    
    // If no action but ID is provided, fetch single transaction with related items
    if (!isset($_GET['action']) && isset($_GET['id'])) {
        $id = $_GET['id'];
        
        // 1. Get main transaction data
        $query = "
            SELECT 
                ft.*,
                f.name as fellowship_name,
                s.name as service_name,
                u.username as recorded_by_name
            FROM financial_transactions ft
            LEFT JOIN fellowships f ON ft.fellowship_id = f.id
            LEFT JOIN services s ON ft.service_id = s.id
            LEFT JOIN users u ON ft.recorded_by = u.id
            WHERE ft.id = :id
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            // 2. Load related Tithes
            $titheQuery = "
                SELECT tt.*, CONCAT(m.first_name, ' ', m.last_name) as member_name
                FROM tithe_transactions tt
                JOIN members m ON tt.member_id = m.id
                WHERE tt.financial_transaction_id = :id
            ";
            $titheStmt = $db->prepare($titheQuery);
            $titheStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $titheStmt->execute();
            $record['tithes'] = $titheStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 3. Load related Partnerships
            $partQuery = "
                SELECT pc.*, CONCAT(m.first_name, ' ', m.last_name) as member_name, pa.name as arm_name
                FROM partnership_contributions pc
                JOIN members m ON pc.member_id = m.id
                JOIN partnership_arms pa ON pc.partnership_arm_id = pa.id
                WHERE pc.financial_transaction_id = :id
            ";
            $partStmt = $db->prepare($partQuery);
            $partStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $partStmt->execute();
            $record['partnerships'] = $partStmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_end_clean();
            echo json_encode([
                "success" => true,
                "data" => $record
            ]);
        } else {
            ob_end_clean();
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Transaction not found"
            ]);
        }
        exit();
    }
    
    switch ($action) {
        case 'tithe_summary':
            // Get tithe summary data
            $query = "
                SELECT 
                    tt.*,
                    CONCAT(m.first_name, ' ', m.last_name) as member_name,
                    f.name as fellowship_name,
                    u.username as recorded_by_name
                FROM tithe_transactions tt
                LEFT JOIN members m ON tt.member_id = m.id
                LEFT JOIN fellowships f ON tt.fellowship_id = f.id
                LEFT JOIN users u ON tt.recorded_by = u.id";

            $params = [];
            if ($scopeId !== null) {
                $query .= " WHERE tt.fellowship_id = :scope_fid";
                $params[':scope_fid'] = $scopeId;
            }

            $query .= " ORDER BY tt.transaction_date DESC LIMIT 500";
            
            $stmt = $db->prepare($query);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            }
            $stmt->execute();
            $tithes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get tithe totals
            $totalsQuery = "
                SELECT 
                    COUNT(*) as total_tithes,
                    SUM(amount) as total_amount,
                    COUNT(DISTINCT member_id) as unique_tithers,
                    COUNT(DISTINCT fellowship_id) as fellowships_with_tithes
                FROM tithe_transactions";

            if ($scopeId !== null) {
                $totalsQuery .= " WHERE fellowship_id = :scope_fid";
            }

            $totalsStmt = $db->prepare($totalsQuery);
            if ($scopeId !== null) {
                $totalsStmt->bindValue(':scope_fid', $scopeId, PDO::PARAM_INT);
            }
            $totalsStmt->execute();
            $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);
            
            ob_end_clean();
            echo json_encode([
                "success" => true,
                "tithes" => $tithes,
                "totals" => $totals
            ]);
            break;
            
        case 'fellowships':
            // Get fellowships for dropdown
            $query = "SELECT id, name FROM fellowships WHERE status = 'active'";
            if ($scopeId !== null) {
                $query .= " AND id = :scope_fid";
            }
            $query .= " ORDER BY name";

            $stmt = $db->prepare($query);
            if ($scopeId !== null) {
                $stmt->bindValue(':scope_fid', $scopeId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $fellowships = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_end_clean();
            echo json_encode([
                "success" => true,
                "fellowships" => $fellowships
            ]);
            break;
            
        case 'members_by_fellowship':
            // Get members by fellowship for tithe dropdown
            $fellowship_id = $_GET['fellowship_id'] ?? 0;
            
            if (!$fellowship_id) {
                throw new Exception("Fellowship ID is required");
            }

            // Enforce isolation: non-admins can only query their own fellowship
            if ($scopeId !== null && (int)$fellowship_id !== $scopeId) {
                throw new Exception("Not allowed to access members for this fellowship");
            }
            
            $query = "
                SELECT 
                    m.id,
                    CONCAT(m.first_name, ' ', m.last_name) as name,
                    m.phone,
                    m.email
                FROM members m
                WHERE m.fellowship_id = :fellowship_id 
                AND m.status = 'active'
                ORDER BY m.first_name, m.last_name
            ";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':fellowship_id', $fellowship_id, PDO::PARAM_INT);
            $stmt->execute();
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_end_clean();
            echo json_encode([
                "success" => true,
                "members" => $members
            ]);
            break;
            
        case 'tithe_by_member':
            // Get tithe history for a specific member
            $member_id = $_GET['member_id'] ?? 0;
            
            if (!$member_id) {
                throw new Exception("Member ID is required");
            }
            
            $query = "
                SELECT 
                    tt.*,
                    f.name as fellowship_name
                FROM tithe_transactions tt
                LEFT JOIN fellowships f ON tt.fellowship_id = f.id
                WHERE tt.member_id = :member_id";

            if ($scopeId !== null) {
                $query .= " AND tt.fellowship_id = :scope_fid";
            }

            $query .= " ORDER BY tt.transaction_date DESC LIMIT 100";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
            if ($scopeId !== null) {
                $stmt->bindValue(':scope_fid', $scopeId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $tithes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get member info
            $memberQuery = "
                SELECT 
                    m.*,
                    f.name as fellowship_name
                FROM members m
                LEFT JOIN fellowships f ON m.fellowship_id = f.id
                WHERE m.id = :member_id";

            if ($scopeId !== null) {
                $memberQuery .= " AND m.fellowship_id = :scope_fid";
            }
            
            $memberStmt = $db->prepare($memberQuery);
            $memberStmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
            if ($scopeId !== null) {
                $memberStmt->bindValue(':scope_fid', $scopeId, PDO::PARAM_INT);
            }
            $memberStmt->execute();
            $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate totals
            $totalsQuery = "
                SELECT 
                    COUNT(*) as total_tithes,
                    SUM(amount) as total_amount,
                    MIN(transaction_date) as first_tithe_date,
                    MAX(transaction_date) as last_tithe_date
                FROM tithe_transactions
                WHERE member_id = :member_id";

            if ($scopeId !== null) {
                $totalsQuery .= " AND fellowship_id = :scope_fid";
            }
            
            $totalsStmt = $db->prepare($totalsQuery);
            $totalsStmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
            if ($scopeId !== null) {
                $totalsStmt->bindValue(':scope_fid', $scopeId, PDO::PARAM_INT);
            }
            $totalsStmt->execute();
            $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);
            
            ob_end_clean();
            echo json_encode([
                "success" => true,
                "tithes" => $tithes,
                "member" => $member,
                "totals" => $totals
            ]);
            break;
            
        case 'transactions':
        default:
            // Get financial transactions
            $query = "
                SELECT 
                    ft.*,
                    f.name as fellowship_name,
                    CONCAT(m.first_name, ' ', m.last_name) as member_name,
                    s.name as service_name,
                    u.username as recorded_by_name
                FROM financial_transactions ft
                LEFT JOIN fellowships f ON ft.fellowship_id = f.id
                LEFT JOIN members m ON ft.member_id = m.id
                LEFT JOIN services s ON ft.service_id = s.id
                LEFT JOIN users u ON ft.recorded_by = u.id";

            $params = [];
            if ($scopeId !== null) {
                $query .= " WHERE ft.fellowship_id = :scope_fid";
                $params[':scope_fid'] = $scopeId;
            }

            $query .= " ORDER BY ft.transaction_date DESC, ft.recorded_at DESC LIMIT 500";
            
            $stmt = $db->prepare($query);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            }
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get totals
            $totalsQuery = "
                SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses,
                    COUNT(DISTINCT category) as unique_categories
                FROM financial_transactions";

            if ($scopeId !== null) {
                $totalsQuery .= " WHERE fellowship_id = :scope_fid";
            }
            
            $totalsStmt = $db->prepare($totalsQuery);
            if ($scopeId !== null) {
                $totalsStmt->bindValue(':scope_fid', $scopeId, PDO::PARAM_INT);
            }
            $totalsStmt->execute();
            $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get category breakdown
            $categoryQuery = "
                SELECT 
                    category,
                    COUNT(*) as count,
                    SUM(amount) as total_amount
                FROM financial_transactions
                WHERE category IS NOT NULL AND category != ''";

            if ($scopeId !== null) {
                $categoryQuery .= " AND fellowship_id = :scope_fid";
            }

            $categoryQuery .= " GROUP BY category ORDER BY total_amount DESC";
            
            $categoryStmt = $db->prepare($categoryQuery);
            if ($scopeId !== null) {
                $categoryStmt->bindValue(':scope_fid', $scopeId, PDO::PARAM_INT);
            }
            $categoryStmt->execute();
            $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_end_clean();
            echo json_encode([
                "success" => true,
                "records" => $records,
                "totals" => $totals,
                "categories" => $categories
            ]);
            break;
    }

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "data" => []
    ]);
}
?>
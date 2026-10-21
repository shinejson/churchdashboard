<?php
// backend/financial/report.php
ob_start();
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    include_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Get report parameters
    $report_type = $_GET['type'] ?? 'summary';
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $fellowship_id = $_GET['fellowship_id'] ?? 0;
    $category = $_GET['category'] ?? '';
    
    switch ($report_type) {
        case 'monthly':
            $data = $this->generateMonthlyReport($db, $start_date, $end_date, $fellowship_id);
            break;
            
        case 'yearly':
            $data = $this->generateYearlyReport($db, $start_date, $end_date, $fellowship_id);
            break;
            
        case 'tithe':
            $data = $this->generateTitheReport($db, $start_date, $end_date, $fellowship_id);
            break;
            
        case 'partnership':
            $data = $this->generatePartnershipReport($db, $start_date, $end_date, $fellowship_id);
            break;
            
        case 'summary':
        default:
            $data = $this->generateSummaryReport($db, $start_date, $end_date, $fellowship_id, $category);
            break;
    }
    
    ob_end_clean();
    echo json_encode([
        "success" => true,
        "data" => $data,
        "report_type" => $report_type,
        "period" => "$start_date to $end_date"
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "data" => []
    ]);
}

function generateSummaryReport($db, $start_date, $end_date, $fellowship_id, $category) {
    $params = [];
    $whereClause = "WHERE transaction_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
    
    if ($fellowship_id) {
        $whereClause .= " AND fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = $fellowship_id;
    }
    
    if ($category) {
        $whereClause .= " AND category = :category";
        $params[':category'] = $category;
    }
    
    // Get total income
    $incomeQuery = "
        SELECT 
            SUM(amount) as total_income,
            COUNT(*) as transaction_count,
            AVG(amount) as avg_transaction
        FROM financial_transactions 
        $whereClause 
        AND type = 'income'
    ";
    
    $incomeStmt = $db->prepare($incomeQuery);
    foreach ($params as $key => $value) {
        $incomeStmt->bindValue($key, $value);
    }
    $incomeStmt->execute();
    $income = $incomeStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get total expenses
    $expenseQuery = "
        SELECT 
            SUM(amount) as total_expenses,
            COUNT(*) as transaction_count
        FROM financial_transactions 
        $whereClause 
        AND type = 'expense'
    ";
    
    $expenseStmt = $db->prepare($expenseQuery);
    foreach ($params as $key => $value) {
        $expenseStmt->bindValue($key, $value);
    }
    $expenseStmt->execute();
    $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get category breakdown
    $categoryQuery = "
        SELECT 
            category,
            COUNT(*) as count,
            SUM(amount) as total_amount,
            type
        FROM financial_transactions 
        $whereClause 
        GROUP BY category, type
        ORDER BY type, total_amount DESC
    ";
    
    $categoryStmt = $db->prepare($categoryQuery);
    foreach ($params as $key => $value) {
        $categoryStmt->bindValue($key, $value);
    }
    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get daily totals
    $dailyQuery = "
        SELECT 
            transaction_date,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as daily_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as daily_expense
        FROM financial_transactions 
        $whereClause 
        GROUP BY transaction_date
        ORDER BY transaction_date
    ";
    
    $dailyStmt = $db->prepare($dailyQuery);
    foreach ($params as $key => $value) {
        $dailyStmt->bindValue($key, $value);
    }
    $dailyStmt->execute();
    $daily = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        "income" => $income,
        "expense" => $expense,
        "categories" => $categories,
        "daily" => $daily,
        "net_balance" => ($income['total_income'] ?? 0) - ($expense['total_expenses'] ?? 0)
    ];
}

function generateMonthlyReport($db, $start_date, $end_date, $fellowship_id) {
    $params = [];
    $whereClause = "WHERE transaction_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
    
    if ($fellowship_id) {
        $whereClause .= " AND fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = $fellowship_id;
    }
    
    $query = "
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as monthly_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as monthly_expense,
            COUNT(*) as transaction_count
        FROM financial_transactions 
        $whereClause 
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month
    ";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        "monthly_data" => $monthly,
        "total_months" => count($monthly)
    ];
}

function generateYearlyReport($db, $start_date, $end_date, $fellowship_id) {
    $params = [];
    $whereClause = "WHERE transaction_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
    
    if ($fellowship_id) {
        $whereClause .= " AND fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = $fellowship_id;
    }
    
    $query = "
        SELECT 
            YEAR(transaction_date) as year,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as yearly_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as yearly_expense,
            COUNT(*) as transaction_count
        FROM financial_transactions 
        $whereClause 
        GROUP BY YEAR(transaction_date)
        ORDER BY year
    ";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $yearly = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        "yearly_data" => $yearly,
        "total_years" => count($yearly)
    ];
}

function generateTitheReport($db, $start_date, $end_date, $fellowship_id) {
    $params = [];
    $whereClause = "WHERE tt.transaction_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
    
    if ($fellowship_id) {
        $whereClause .= " AND tt.fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = $fellowship_id;
    }
    
    // Check if tithe_transactions table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'tithe_transactions'");
    if ($tableCheck->rowCount() === 0) {
        return ["message" => "Tithe transactions table not found"];
    }
    
    // Get tithe summary
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_tithes,
            SUM(amount) as total_amount,
            COUNT(DISTINCT member_id) as unique_tithers,
            AVG(amount) as avg_tithe
        FROM tithe_transactions tt
        $whereClause
    ";
    
    $summaryStmt = $db->prepare($summaryQuery);
    foreach ($params as $key => $value) {
        $summaryStmt->bindValue($key, $value);
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get tithe by fellowship
    $fellowshipQuery = "
        SELECT 
            f.name as fellowship_name,
            COUNT(tt.id) as tithe_count,
            SUM(tt.amount) as total_amount,
            COUNT(DISTINCT tt.member_id) as unique_tithers
        FROM tithe_transactions tt
        JOIN fellowships f ON tt.fellowship_id = f.id
        $whereClause
        GROUP BY tt.fellowship_id, f.name
        ORDER BY total_amount DESC
    ";
    
    $fellowshipStmt = $db->prepare($fellowshipQuery);
    foreach ($params as $key => $value) {
        $fellowshipStmt->bindValue($key, $value);
    }
    $fellowshipStmt->execute();
    $byFellowship = $fellowshipStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get monthly tithe trend
    $monthlyQuery = "
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            COUNT(*) as tithe_count,
            SUM(amount) as total_amount
        FROM tithe_transactions tt
        $whereClause
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month
    ";
    
    $monthlyStmt = $db->prepare($monthlyQuery);
    foreach ($params as $key => $value) {
        $monthlyStmt->bindValue($key, $value);
    }
    $monthlyStmt->execute();
    $monthlyTrend = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top tithers
    $topTithersQuery = "
        SELECT 
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            f.name as fellowship_name,
            COUNT(tt.id) as tithe_count,
            SUM(tt.amount) as total_amount
        FROM tithe_transactions tt
        JOIN members m ON tt.member_id = m.id
        JOIN fellowships f ON tt.fellowship_id = f.id
        $whereClause
        GROUP BY tt.member_id, m.first_name, m.last_name, f.name
        ORDER BY total_amount DESC
        LIMIT 10
    ";
    
    $topTithersStmt = $db->prepare($topTithersQuery);
    foreach ($params as $key => $value) {
        $topTithersStmt->bindValue($key, $value);
    }
    $topTithersStmt->execute();
    $topTithers = $topTithersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        "summary" => $summary,
        "by_fellowship" => $byFellowship,
        "monthly_trend" => $monthlyTrend,
        "top_tithers" => $topTithers
    ];
}

function generatePartnershipReport($db, $start_date, $end_date, $fellowship_id) {
    // Check if partnership_transactions table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'partnership_transactions'");
    if ($tableCheck->rowCount() === 0) {
        return ["message" => "Partnership transactions table not found"];
    }
    
    $params = [];
    $whereClause = "WHERE transaction_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
    
    if ($fellowship_id) {
        $whereClause .= " AND fellowship_id = :fellowship_id";
        $params[':fellowship_id'] = $fellowship_id;
    }
    
    $query = "
        SELECT 
            COUNT(*) as total_partnerships,
            SUM(amount) as total_amount,
            COUNT(DISTINCT member_id) as unique_partners
        FROM partnership_transactions pt
        $whereClause
    ";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        "summary" => $summary
    ];
}
?>
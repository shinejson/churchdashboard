<?php
// backend/financial/chart_data.php
require_once '../config/security.php';
require_once '../config/database.php';

// Start checking for session/auth
secure_session_start();
allow_cors(); 
// Note: If you have stricter auth checks, include them here. 
// E.g. check if user is admin or pastor.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fellowship data isolation: non-admins are always scoped to their own fellowship
    $scopeId = current_fellowship_scope();

    // Prepare array for last 6 months
    $data = [
        'labels' => [],
        'tithes' => [],
        'expenses' => []
    ];

    // Get start/end of the Last 6 Months (including current)
    for ($i = 5; $i >= 0; $i--) {
        $monthStart = date('Y-m-01', strtotime("-$i months"));
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $monthLabel = date('M', strtotime($monthStart));

        $data['labels'][] = $monthLabel;

        // 1. Get Tithes + Partnership Contributions (Income)
        // We sum tithe_transactions AND partnership_contributions for a complete picture, 
        // OR just tithes if that's strictly what's requested. 
        // The chart says "Tithes & Contributions".
        
        // Sum Tithes
        $titheQuery = "SELECT SUM(amount) as total FROM tithe_transactions WHERE transaction_date BETWEEN ? AND ?";
        $titheParams = [$monthStart, $monthEnd];
        if ($scopeId !== null) {
            $titheQuery .= " AND fellowship_id = ?";
            $titheParams[] = $scopeId;
        }
        $stmtTithe = $db->prepare($titheQuery);
        $stmtTithe->execute($titheParams);
        $titheRow = $stmtTithe->fetch(PDO::FETCH_ASSOC);
        $titheAmount = $titheRow['total'] ?? 0;

        // Sum Partnership Contributions (if table exists and is used)
        $partnershipQuery = "SELECT SUM(amount) as total FROM partnership_contributions WHERE contribution_date BETWEEN ? AND ?";
        $partParams = [$monthStart, $monthEnd];
        if ($scopeId !== null) {
            $partnershipQuery .= " AND fellowship_id = ?";
            $partParams[] = $scopeId;
        }
        $stmtPart = $db->prepare($partnershipQuery);
        $stmtPart->execute($partParams);
        $partRow = $stmtPart->fetch(PDO::FETCH_ASSOC);
        $partAmount = $partRow['total'] ?? 0;

        $totalIncome = $titheAmount + $partAmount;
        $data['tithes'][] = round($totalIncome, 2);

        // 2. Get Expenses
        // Query financial_transactions where type = 'expense'
        $expenseQuery = "SELECT SUM(amount) as total FROM financial_transactions WHERE type = 'expense' AND transaction_date BETWEEN ? AND ?";
        $expParams = [$monthStart, $monthEnd];
        if ($scopeId !== null) {
            $expenseQuery .= " AND fellowship_id = ?";
            $expParams[] = $scopeId;
        }
        $stmtExp = $db->prepare($expenseQuery);
        $stmtExp->execute($expParams);
        $expRow = $stmtExp->fetch(PDO::FETCH_ASSOC);
        $expenseAmount = $expRow['total'] ?? 0;

        $data['expenses'][] = round($expenseAmount, 2);
    }

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

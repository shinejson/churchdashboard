<?php
// backend/financial/tithe.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/security.php';

allow_cors();
secure_session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // GET: Fetch tithe record
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Tithe ID required']);
            exit();
        }

        $sql = "SELECT tt.*, m.first_name, m.last_name, f.name as fellowship_name, u.username as recorded_by_name
                FROM tithe_transactions tt
                LEFT JOIN members m ON tt.member_id = m.id
                LEFT JOIN fellowships f ON tt.fellowship_id = f.id
                LEFT JOIN users u ON tt.recorded_by = u.id
                WHERE tt.id = :id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $tithe = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tithe) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Tithe record not found']);
            exit();
        }

        echo json_encode([
            'success' => true,
            'data' => $tithe,
            'member_id' => $tithe['member_id'],
            'member_first_name' => $tithe['first_name'],
            'member_last_name' => $tithe['last_name'],
            'fellowship_id' => $tithe['fellowship_id'],
            'amount' => $tithe['amount'],
            'transaction_date' => $tithe['transaction_date']
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST: Create or update tithe record
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit();
        }

        $required = ['member_id', 'fellowship_id', 'amount', 'transaction_date'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                exit();
            }
        }

        $sql = "INSERT INTO tithe_transactions (
                    member_id, fellowship_id, amount, period_id, 
                    transaction_date, payment_method, reference, notes, 
                    is_first_tithe, is_second_tithe, recorded_by, recorded_at
                ) VALUES (
                    :member_id, :fellowship_id, :amount, :period_id,
                    :transaction_date, :payment_method, :reference, :notes,
                    :is_first_tithe, :is_second_tithe, :recorded_by, NOW()
                )";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':member_id', $data['member_id'], PDO::PARAM_INT);
        $stmt->bindValue(':fellowship_id', $data['fellowship_id'], PDO::PARAM_INT);
        $stmt->bindValue(':amount', $data['amount']);
        $stmt->bindValue(':period_id', $data['period_id'] ?? null);
        $stmt->bindValue(':transaction_date', $data['transaction_date']);
        $stmt->bindValue(':payment_method', $data['payment_method'] ?? 'cash');
        $stmt->bindValue(':reference', $data['reference'] ?? null);
        $stmt->bindValue(':notes', $data['notes'] ?? null);
        $stmt->bindValue(':is_first_tithe', $data['is_first_tithe'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':is_second_tithe', $data['is_second_tithe'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':recorded_by', $_SESSION['user_id'] ?? null, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $id = $db->lastInsertId();
            echo json_encode([
                'success' => true,
                'message' => 'Tithe record created',
                'id' => $id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create tithe record']);
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // PUT: Update tithe record
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $_GET['id'] ?? null;

        if (!$id || !$data) {
            http_response_code(400);
            echo json_encode(['error' => 'ID and data required']);
            exit();
        }

        $sql = "UPDATE tithe_transactions SET 
                amount = :amount,
                payment_method = :payment_method,
                reference = :reference,
                notes = :notes,
                is_first_tithe = :is_first_tithe,
                is_second_tithe = :is_second_tithe,
                updated_at = NOW()
                WHERE id = :id";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':amount', $data['amount'] ?? 0);
        $stmt->bindValue(':payment_method', $data['payment_method'] ?? 'cash');
        $stmt->bindValue(':reference', $data['reference'] ?? null);
        $stmt->bindValue(':notes', $data['notes'] ?? null);
        $stmt->bindValue(':is_first_tithe', $data['is_first_tithe'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':is_second_tithe', $data['is_second_tithe'] ?? 0, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Tithe record updated']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update tithe record']);
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // DELETE: Delete tithe record
        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            exit();
        }

        $sql = "DELETE FROM tithe_transactions WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Tithe record deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete tithe record']);
        }
    }

} catch (Exception $e) {
    error_log("Tithe API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

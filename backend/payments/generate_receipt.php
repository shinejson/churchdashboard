<?php
/**
 * Generate PDF Receipt for Payments
 * Uses TCPDF or FPDF library
 */

require_once '../config/database.php';

/**
 * Generate Payment Receipt PDF
 */
function generatePaymentReceipt($transaction_id) {
    $db = (new Database())->getConnection();
    
    // Get transaction details
    $stmt = $db->prepare("
        SELECT pt.*, 
               m.first_name, m.last_name, m.email, m.phone, m.address,
               f.name as fellowship_name,
               pa.name as partnership_arm_name
        FROM payment_transactions pt
        LEFT JOIN members m ON pt.member_id = m.id
        LEFT JOIN fellowships f ON pt.fellowship_id = f.id
        LEFT JOIN partnership_arms pa ON pt.partnership_arm_id = pa.id
        WHERE pt.id = ?
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception('Transaction not found');
    }
    
    // Create receipts directory if it doesn't exist
    $receiptsDir = __DIR__ . '/../receipts';
    if (!is_dir($receiptsDir)) {
        mkdir($receiptsDir, 0755, true);
    }
    
    $filename = 'receipt_' . $transaction['reference'] . '.pdf';
    $filepath = $receiptsDir . '/' . $filename;
    
    // Check if TCPDF is available
    if (class_exists('TCPDF')) {
        generateReceiptWithTCPDF($transaction, $filepath);
    } else {
        // Fallback to simple HTML receipt
        generateReceiptHTML($transaction, $filepath);
    }
    
    return $filepath;
}

/**
 * Generate Receipt with TCPDF
 */
function generateReceiptWithTCPDF($transaction, $filepath) {
    require_once(__DIR__ . '/../../vendor/autoload.php');
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Church Management System');
    $pdf->SetAuthor('Grace Community Church');
    $pdf->SetTitle('Payment Receipt');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 12);
    
    // Church Header
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, 'Grace Community Church', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'P.O. Box 123, Accra, Ghana', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Tel: +233 XX XXX XXXX | Email: info@gracecommunity.org', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Receipt Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'PAYMENT RECEIPT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Receipt Details
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(50, 7, 'Receipt No:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, $transaction['reference'], 0, 1);
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(50, 7, 'Date:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, date('F d, Y', strtotime($transaction['payment_date'])), 0, 1);
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(50, 7, 'Status:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, strtoupper($transaction['status']), 0, 1);
    $pdf->Ln(5);
    
    // Member Details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 7, 'Member Information', 0, 1);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(3);
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(50, 7, 'Name:', 0, 0);
    $pdf->Cell(0, 7, $transaction['first_name'] . ' ' . $transaction['last_name'], 0, 1);
    
    $pdf->Cell(50, 7, 'Email:', 0, 0);
    $pdf->Cell(0, 7, $transaction['email'] ?: 'N/A', 0, 1);
    
    $pdf->Cell(50, 7, 'Fellowship:', 0, 0);
    $pdf->Cell(0, 7, $transaction['fellowship_name'] ?: 'N/A', 0, 1);
    $pdf->Ln(5);
    
    // Payment Details
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 7, 'Payment Details', 0, 1);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(3);
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(50, 7, 'Payment Type:', 0, 0);
    $pdf->Cell(0, 7, ucfirst($transaction['payment_type']), 0, 1);
    
    if ($transaction['payment_type'] === 'partnership' && $transaction['partnership_arm_name']) {
        $pdf->Cell(50, 7, 'Partnership Arm:', 0, 0);
        $pdf->Cell(0, 7, $transaction['partnership_arm_name'], 0, 1);
    }
    
    $pdf->Cell(50, 7, 'Payment Method:', 0, 0);
    $pdf->Cell(0, 7, ucfirst(str_replace('_', ' ', $transaction['payment_method'])), 0, 1);
    $pdf->Ln(5);
    
    // Amount
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(50, 10, 'Amount Paid:', 0, 0);
    $pdf->SetTextColor(0, 128, 0);
    $pdf->Cell(0, 10, 'GH₵ ' . number_format($transaction['amount'], 2), 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(10);
    
    // Footer
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 5, 'Thank you for your contribution!', 0, 1, 'C');
    $pdf->Cell(0, 5, 'This is a computer-generated receipt and does not require a signature.', 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->Cell(0, 5, 'For inquiries, please contact: finance@gracecommunity.org', 0, 1, 'C');
    
    // Save PDF
    $pdf->Output($filepath, 'F');
}

/**
 * Generate Simple HTML Receipt (Fallback)
 */
function generateReceiptHTML($transaction, $filepath) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Payment Receipt</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { margin: 0; color: #333; }
            .header p { margin: 5px 0; color: #666; }
            .receipt-title { text-align: center; font-size: 24px; font-weight: bold; margin: 20px 0; }
            .section { margin: 20px 0; }
            .section-title { font-weight: bold; font-size: 16px; border-bottom: 2px solid #333; padding-bottom: 5px; margin-bottom: 10px; }
            .row { display: flex; padding: 8px 0; }
            .label { width: 200px; font-weight: bold; }
            .value { flex: 1; }
            .amount { font-size: 24px; font-weight: bold; color: green; text-align: center; margin: 20px 0; }
            .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Grace Community Church</h1>
            <p>P.O. Box 123, Accra, Ghana</p>
            <p>Tel: +233 XX XXX XXXX | Email: info@gracecommunity.org</p>
        </div>
        
        <div class="receipt-title">PAYMENT RECEIPT</div>
        
        <div class="section">
            <div class="row">
                <div class="label">Receipt No:</div>
                <div class="value">' . htmlspecialchars($transaction['reference']) . '</div>
            </div>
            <div class="row">
                <div class="label">Date:</div>
                <div class="value">' . date('F d, Y', strtotime($transaction['payment_date'])) . '</div>
            </div>
            <div class="row">
                <div class="label">Status:</div>
                <div class="value">' . strtoupper($transaction['status']) . '</div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Member Information</div>
            <div class="row">
                <div class="label">Name:</div>
                <div class="value">' . htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']) . '</div>
            </div>
            <div class="row">
                <div class="label">Email:</div>
                <div class="value">' . htmlspecialchars($transaction['email'] ?: 'N/A') . '</div>
            </div>
            <div class="row">
                <div class="label">Fellowship:</div>
                <div class="value">' . htmlspecialchars($transaction['fellowship_name'] ?: 'N/A') . '</div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Payment Details</div>
            <div class="row">
                <div class="label">Payment Type:</div>
                <div class="value">' . ucfirst($transaction['payment_type']) . '</div>
            </div>
            ' . ($transaction['payment_type'] === 'partnership' && $transaction['partnership_arm_name'] ? '
            <div class="row">
                <div class="label">Partnership Arm:</div>
                <div class="value">' . htmlspecialchars($transaction['partnership_arm_name']) . '</div>
            </div>' : '') . '
            <div class="row">
                <div class="label">Payment Method:</div>
                <div class="value">' . ucfirst(str_replace('_', ' ', $transaction['payment_method'])) . '</div>
            </div>
        </div>
        
        <div class="amount">
            Amount Paid: GH₵ ' . number_format($transaction['amount'], 2) . '
        </div>
        
        <div class="footer">
            <p>Thank you for your contribution!</p>
            <p>This is a computer-generated receipt and does not require a signature.</p>
            <p>For inquiries, please contact: finance@gracecommunity.org</p>
        </div>
    </body>
    </html>';
    
    // Save HTML file (can be converted to PDF using wkhtmltopdf or similar)
    file_put_contents($filepath, $html);
}

/**
 * Send Payment Confirmation Email
 */
function sendPaymentConfirmationEmail($transaction_id, $receiptPath) {
    $db = (new Database())->getConnection();
    
    // Get transaction and member details
    $stmt = $db->prepare("
        SELECT pt.*, 
               m.first_name, m.last_name, m.email,
               f.name as fellowship_name
        FROM payment_transactions pt
        LEFT JOIN members m ON pt.member_id = m.id
        LEFT JOIN fellowships f ON pt.fellowship_id = f.id
        WHERE pt.id = ?
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction || !$transaction['email']) {
        return false;
    }
    
    try {
        $emailService = new EmailService();
        
        $subject = 'Payment Receipt - ' . ucfirst($transaction['payment_type']);
        
        $body = '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #4CAF50;">Payment Confirmation</h2>
                
                <p>Dear ' . htmlspecialchars($transaction['first_name']) . ',</p>
                
                <p>Thank you for your ' . strtolower($transaction['payment_type']) . ' contribution. Your payment has been received and processed successfully.</p>
                
                <div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0;">Payment Details</h3>
                    <p><strong>Receipt No:</strong> ' . htmlspecialchars($transaction['reference']) . '</p>
                    <p><strong>Amount:</strong> GH₵ ' . number_format($transaction['amount'], 2) . '</p>
                    <p><strong>Payment Type:</strong> ' . ucfirst($transaction['payment_type']) . '</p>
                    <p><strong>Date:</strong> ' . date('F d, Y', strtotime($transaction['payment_date'])) . '</p>
                    <p><strong>Status:</strong> ' . ucfirst($transaction['status']) . '</p>
                </div>
                
                <p>Your receipt is attached to this email for your records.</p>
                
                <p>May God bless you abundantly for your faithful giving!</p>
                
                <p style="margin-top: 30px;">
                    Best regards,<br>
                    <strong>Grace Community Church</strong><br>
                    Finance Department
                </p>
                
                <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
                
                <p style="font-size: 12px; color: #666;">
                    This is an automated email. Please do not reply to this message.<br>
                    For inquiries, contact: finance@gracecommunity.org
                </p>
            </div>
        </body>
        </html>';
        
        $attachments = [];
        if (file_exists($receiptPath)) {
            $attachments[] = $receiptPath;
        }
        
        return $emailService->sendEmail(
            $transaction['email'],
            $subject,
            $body,
            $attachments
        );
        
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $e->getMessage());
        return false;
    }
}

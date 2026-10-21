# Complete Payment System Guide

## Overview
This comprehensive payment system includes Espees integration, PDF receipts, email confirmations, recurring payments, and admin verification workflow.

## Features Implemented

### 1. ✅ Espees Payment Gateway Integration
- Online payment processing
- Mobile money support (MTN, Vodafone, AirtelTigo)
- Card payments
- Automatic payment verification
- Webhook callback handling

### 2. ✅ PDF Receipt Generation
- Automatic receipt creation after payment
- Professional receipt design
- Church branding and details
- Member information
- Payment details and reference
- Downloadable PDF format

### 3. ✅ Email Confirmations
- Automatic email after successful payment
- Receipt attached as PDF
- Payment details in email body
- Professional HTML email template
- Configurable SMTP settings

### 4. ✅ Recurring Payment Setup
- Schedule automatic payments
- Frequencies: Daily, Weekly, Monthly, Yearly
- Pause/Resume functionality
- Cancel anytime
- Payment history tracking

### 5. ✅ Payment Verification Workflow
- Admin approval system
- Pending payments queue
- Approve/Reject functionality
- Verification notes
- Audit trail

## Installation Steps

### Step 1: Run Database Migration

```bash
# Via browser
http://yourdomain.com/backend/migrations/create_payment_tables.php

# Via command line
php backend/migrations/create_payment_tables.php
```

This creates:
- `payment_transactions` - All payment records
- `recurring_payments` - Recurring payment schedules
- `recurring_payment_history` - Execution history
- `payment_receipts` - Receipt records
- `partnership_arms` - Partnership projects

### Step 2: Configure Espees API

1. **Get Espees Credentials**
   - Sign up at https://espees.com
   - Get API Key, Secret Key, and Merchant ID
   - Note your callback URL

2. **Update .env File**
   ```env
   ESPEES_API_KEY=your_actual_api_key
   ESPEES_SECRET_KEY=your_actual_secret_key
   ESPEES_MERCHANT_ID=your_actual_merchant_id
   ESPEES_BASE_URL=https://api.espees.com/v1
   ESPEES_CALLBACK_URL=https://yourdomain.com/backend/payments/espees_callback.php
   ```

3. **Configure Webhook**
   - In Espees dashboard, set webhook URL to:
   - `https://yourdomain.com/backend/payments/espees_callback.php`

### Step 3: Configure Email Settings

Update `.env` file:
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_SECURE=tls
SMTP_FROM_EMAIL=noreply@gracecommunity.org
SMTP_FROM_NAME="Grace Community Church"
```

### Step 4: Install PDF Library (Optional)

For better PDF receipts, install TCPDF:
```bash
composer require tecnickcom/tcpdf
```

## How It Works

### For Members

#### Making a Payment

1. **Log in** to profile
2. **Click "Make Payment"** button
3. **Select payment type**:
   - Tithe
   - Partnership (select arm)
   - Offering/Other
4. **Choose payment method**:
   - Online (Espees) - Redirects to payment gateway
   - Cash - Records for admin verification
   - Bank Transfer - Records with reference
   - Mobile Money - Records with reference
   - Cheque - Records with reference
5. **Enter amount** in Ghana Cedis
6. **Add reference** (optional)
7. **Submit payment**

#### Online Payment Flow

1. Member clicks "Pay Online"
2. Redirected to Espees payment page
3. Completes payment (Mobile Money/Card)
4. Espees processes payment
5. Callback updates system
6. Receipt generated automatically
7. Email sent with receipt
8. Payment appears in profile

#### Setting Up Recurring Payments

1. Click "Set Up Recurring Payment"
2. Select payment type and amount
3. Choose frequency (Daily/Weekly/Monthly/Yearly)
4. Set start date
5. Confirm setup
6. Payments process automatically

### For Administrators

#### Verifying Payments

1. Go to **Financial** page
2. Click **"Pending Payments"** tab
3. Review payment details
4. Check reference number
5. **Approve** or **Reject**
6. Add verification notes
7. System records payment
8. Receipt sent to member

#### Managing Recurring Payments

1. View all recurring schedules
2. See next payment dates
3. Pause/Resume schedules
4. Cancel if needed
5. View payment history

## API Endpoints

### 1. Record Payment
**POST** `/backend/payments/record_payment.php`

```json
{
  "payment_type": "tithe",
  "amount": 100.00,
  "payment_method": "mobile_money",
  "payment_date": "2026-04-16",
  "reference": "MTN-123456",
  "notes": "Monthly tithe"
}
```

### 2. Initialize Online Payment
**POST** `/backend/payments/initialize_payment.php`

```json
{
  "payment_type": "tithe",
  "amount": 100.00
}
```

Response:
```json
{
  "success": true,
  "data": {
    "transaction_id": 123,
    "reference": "PAY-ABC123",
    "payment_url": "https://pay.espees.com/..."
  }
}
```

### 3. Set Up Recurring Payment
**POST** `/backend/payments/recurring_payments.php`

```json
{
  "payment_type": "tithe",
  "amount": 100.00,
  "frequency": "monthly",
  "start_date": "2026-05-01",
  "payment_method": "online"
}
```

### 4. Verify Payment (Admin)
**POST** `/backend/payments/verify_payment.php`

```json
{
  "transaction_id": 123,
  "action": "approve",
  "notes": "Verified bank transfer"
}
```

### 5. Get Pending Payments (Admin)
**GET** `/backend/payments/verify_payment.php?status=pending`

## Database Schema

### payment_transactions
```sql
- id
- member_id
- fellowship_id
- payment_type (tithe, partnership, offering)
- partnership_arm_id
- amount
- payment_method
- payment_date
- reference
- espees_reference
- payment_url
- status (pending, verified, failed, rejected)
- verified_by
- verified_at
- verification_notes
- espees_response
- created_at
- updated_at
```

### recurring_payments
```sql
- id
- member_id
- fellowship_id
- payment_type
- partnership_arm_id
- amount
- frequency (daily, weekly, monthly, yearly)
- start_date
- end_date
- next_payment_date
- last_payment_date
- payment_method
- is_active
- total_payments
- created_at
- updated_at
```

### payment_receipts
```sql
- id
- transaction_id
- receipt_number
- file_path
- generated_at
- sent_via_email
- email_sent_at
```

## Cron Job Setup

For recurring payments, set up a cron job:

```bash
# Run daily at 2 AM
0 2 * * * php /path/to/backend/payments/process_recurring.php
```

Create `process_recurring.php`:
```php
<?php
// Process due recurring payments
require_once 'recurring_payments.php';

$db = (new Database())->getConnection();

$stmt = $db->query("
    SELECT * FROM recurring_payments 
    WHERE is_active = 1 
    AND next_payment_date <= CURDATE()
");

while ($recurring = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Process payment
    // Update next_payment_date
    // Record in history
}
```

## Security Features

### 1. Authentication
- Session-based authentication
- CSRF token protection
- User authorization checks

### 2. Payment Security
- Secure API communication
- Encrypted payment data
- Reference number validation
- Amount verification

### 3. Audit Trail
- All payments logged
- Verification history
- Admin actions tracked
- Timestamps recorded

### 4. Data Protection
- Member data privacy
- Secure file storage
- Email encryption
- Database security

## Customization

### Change Church Details

Edit `backend/payments/generate_receipt.php`:
```php
$pdf->Cell(0, 10, 'Your Church Name', 0, 1, 'C');
$pdf->Cell(0, 5, 'Your Address', 0, 1, 'C');
$pdf->Cell(0, 5, 'Your Contact Info', 0, 1, 'C');
```

### Customize Email Template

Edit `backend/payments/generate_receipt.php` in `sendPaymentConfirmationEmail()` function.

### Add Payment Methods

Edit `backend/payments/record_payment.php`:
```php
$payment_methods = [
    'cash' => 'Cash',
    'your_method' => 'Your Method'
];
```

### Modify Receipt Design

Edit `generateReceiptWithTCPDF()` or `generateReceiptHTML()` functions.

## Troubleshooting

### Payment Not Processing
**Check:**
- Espees API credentials correct
- Callback URL accessible
- Database tables created
- Network connectivity

### Receipt Not Generated
**Check:**
- Receipts directory exists (`backend/receipts/`)
- Directory has write permissions (755)
- TCPDF library installed (optional)

### Email Not Sending
**Check:**
- SMTP settings in .env
- Email service credentials
- Firewall/port settings
- Email logs in `backend/logs/`

### Recurring Payment Not Running
**Check:**
- Cron job configured
- Script has execute permissions
- Database connection working
- Error logs

## Testing

### Test Online Payment
1. Use Espees test credentials
2. Make small test payment
3. Verify callback received
4. Check receipt generated
5. Confirm email sent

### Test Verification Workflow
1. Record cash payment as member
2. Log in as admin
3. Go to pending payments
4. Approve payment
5. Verify receipt sent

### Test Recurring Payment
1. Set up monthly recurring
2. Set next_payment_date to today
3. Run cron script manually
4. Verify payment processed
5. Check next date updated

## Support

For issues:
1. Check error logs: `backend/logs/`
2. Verify database tables exist
3. Test API credentials
4. Check email configuration
5. Review webhook logs

## Conclusion

Your payment system is now complete with:
- ✅ Espees integration for online payments
- ✅ PDF receipt generation
- ✅ Email confirmations
- ✅ Recurring payment schedules
- ✅ Admin verification workflow
- ✅ Complete audit trail
- ✅ Secure processing

Members can now make payments easily, and administrators have full control over verification and tracking!

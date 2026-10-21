# Member Payment System Documentation

## Overview
The member payment system allows church members to record their own financial contributions directly from their profile page. This includes tithe, partnership contributions, and other offerings.

## Features

### 1. Payment Types
- **Tithe** - Regular tithe contributions
- **Partnership** - Contributions to specific partnership arms/projects
- **Offering/Other** - General offerings and other contributions

### 2. Payment Methods Supported
- Cash
- Bank Transfer
- Mobile Money (MTN, Vodafone, AirtelTigo, etc.)
- Cheque
- Card Payment
- Online Payment

### 3. Payment Information Captured
- Payment type
- Amount (in Ghana Cedis)
- Payment method
- Payment date
- Reference number (optional)
- Notes (optional)
- Partnership arm (for partnership payments)

## How It Works

### For Members

1. **Access Profile Page**
   - Log in with member credentials
   - Automatically redirected to profile page

2. **Make Payment**
   - Click "Make Payment" button in Financial Contributions section
   - Select payment type (Tithe, Partnership, or Offering)
   - Enter amount in Ghana Cedis (GH₵)
   - Select payment method
   - Choose payment date
   - Add reference number (optional)
   - Add notes (optional)
   - Click "Submit Payment"

3. **View Payment History**
   - All payments appear immediately in the profile
   - Tithe contributions shown in "Recent Tithe Contributions"
   - Partnership contributions shown in "Recent Partnership Contributions"
   - Financial summaries updated automatically

### For Administrators

Administrators can:
- View all member payments in the Financial page
- Generate reports on contributions
- Verify payment references
- Track partnership arm contributions

## Database Tables

### Tithe Transactions
```sql
tithe_transactions
- id
- member_id
- fellowship_id
- amount
- transaction_date
- payment_method
- reference_number
- notes
- created_at
```

### Partnership Contributions
```sql
partnership_contributions
- id
- member_id
- partnership_arm_id
- amount
- contribution_date
- payment_method
- reference_number
- notes
- created_at
```

### Offerings (Auto-created if needed)
```sql
offerings
- id
- member_id
- fellowship_id
- amount
- offering_date
- payment_method
- reference_number
- notes
- created_at
```

## API Endpoints

### Record Payment
**Endpoint:** `POST /backend/payments/record_payment.php`

**Request Body:**
```json
{
  "payment_type": "tithe|partnership|offering",
  "amount": 100.00,
  "payment_method": "mobile_money",
  "payment_date": "2026-04-15",
  "reference": "MTN-123456789",
  "notes": "Monthly tithe",
  "partnership_arm_id": 1  // Required only for partnership
}
```

**Response:**
```json
{
  "success": true,
  "message": "Tithe payment recorded successfully",
  "data": {
    "payment_id": 123,
    "payment_type": "tithe",
    "amount": 100.00,
    "payment_date": "2026-04-15"
  }
}
```

### Get Payment Options
**Endpoint:** `GET /backend/payments/record_payment.php`

**Response:**
```json
{
  "success": true,
  "data": {
    "partnership_arms": [
      {
        "id": 1,
        "name": "Building Project",
        "description": "Church building expansion",
        "target_amount": 50000.00
      }
    ],
    "payment_methods": {
      "cash": "Cash",
      "bank_transfer": "Bank Transfer",
      "mobile_money": "Mobile Money",
      "cheque": "Cheque",
      "card": "Card Payment",
      "online": "Online Payment"
    }
  }
}
```

## Security Features

### 1. Authentication
- Only logged-in members can make payments
- Session-based authentication
- CSRF token protection

### 2. Authorization
- Members can only record payments for themselves
- Member ID automatically retrieved from user session
- Fellowship ID automatically assigned

### 3. Data Validation
- Amount must be greater than zero
- All required fields validated
- Payment type must be valid
- Partnership arm required for partnership payments

### 4. Audit Trail
- All payments timestamped
- Member ID recorded
- Payment method tracked
- Reference numbers stored

## Mobile Money Integration (Future Enhancement)

### Recommended Providers for Ghana
1. **Paystack** - Supports MTN, Vodafone, AirtelTigo
2. **Flutterwave** - Multiple mobile money providers
3. **Hubtel** - Ghana-focused payment gateway

### Integration Steps
1. Sign up with payment provider
2. Get API keys
3. Update payment modal with payment gateway
4. Add webhook for payment confirmation
5. Auto-update payment status

## Usage Examples

### Example 1: Record Tithe Payment
```javascript
{
  "payment_type": "tithe",
  "amount": 250.00,
  "payment_method": "mobile_money",
  "payment_date": "2026-04-15",
  "reference": "MTN-987654321",
  "notes": "April tithe"
}
```

### Example 2: Record Partnership Payment
```javascript
{
  "payment_type": "partnership",
  "partnership_arm_id": 2,
  "amount": 500.00,
  "payment_method": "bank_transfer",
  "payment_date": "2026-04-15",
  "reference": "TRF-2026041500123",
  "notes": "Contribution to missions project"
}
```

### Example 3: Record Offering
```javascript
{
  "payment_type": "offering",
  "amount": 50.00,
  "payment_method": "cash",
  "payment_date": "2026-04-15",
  "notes": "Sunday offering"
}
```

## Customization

### Change Currency
To change from Ghana Cedis to another currency:
1. Update currency symbol in profile page (already done)
2. Update payment modal label (line with "Amount (GH₵)")
3. Update formatMoney function if needed

### Add Payment Methods
Edit `backend/payments/record_payment.php`:
```php
$payment_methods = [
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'mobile_money' => 'Mobile Money',
    'your_method' => 'Your Method Name'
];
```

### Add Payment Types
1. Create new database table (if needed)
2. Add payment type to dropdown in profile.php
3. Add handling in record_payment.php

## Troubleshooting

### Payment Not Showing
**Cause:** Page not refreshed after payment  
**Solution:** Profile automatically reloads after successful payment

### "Member profile not found" Error
**Cause:** User account not linked to member record  
**Solution:** Use link_members.php to link accounts

### Partnership Arm Not Showing
**Cause:** No active partnership arms in database  
**Solution:** Add partnership arms in admin panel

### Amount Validation Error
**Cause:** Amount is zero or negative  
**Solution:** Enter amount greater than zero

## Best Practices

### For Members
1. Always keep payment references for verification
2. Record payments promptly
3. Use correct payment method
4. Add notes for clarity
5. Verify amount before submitting

### For Administrators
1. Regularly verify payment references
2. Reconcile with bank statements
3. Generate monthly reports
4. Follow up on large contributions
5. Maintain audit trail

## Future Enhancements

### Planned Features
1. **Online Payment Gateway Integration**
   - Direct mobile money payments
   - Card payment processing
   - Automatic payment confirmation

2. **Payment Receipts**
   - Auto-generate PDF receipts
   - Email receipts to members
   - Download receipt history

3. **Recurring Payments**
   - Set up automatic monthly tithe
   - Recurring partnership contributions
   - Payment reminders

4. **Payment Analytics**
   - Personal giving trends
   - Year-over-year comparison
   - Tax deduction summaries

5. **Payment Verification**
   - Admin approval workflow
   - Payment status tracking
   - Dispute resolution

## Support

For issues or questions:
1. Check error messages in browser console
2. Verify member account is linked
3. Ensure CSRF token is present
4. Check backend logs: `backend/logs/`

## Conclusion

The member payment system provides a convenient way for church members to record their contributions. It's secure, easy to use, and integrates seamlessly with the existing church management system.

Members can now:
- ✅ Record tithe payments
- ✅ Make partnership contributions
- ✅ Submit offerings
- ✅ Track payment history
- ✅ View financial summaries

All from their personal profile page!

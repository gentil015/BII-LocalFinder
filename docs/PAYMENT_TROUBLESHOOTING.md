# Payment Processing Troubleshooting Guide

## Error: "Unable to process payment. Please contact support or try refreshing the page."

### What This Means
The system tried to create a payment record but failed for one of several reasons. This guide helps you identify which reason and how to fix it.

---

## Step 1: Check the Error Logs

The system logs detailed error messages. Check your PHP error log:

### **Windows XAMPP:**
```
C:\xampp\php\logs\php_error.log
```

### **Look for messages like:**
```
Payment creation failed: Booking 123 not found
Payment creation failed: No default gateway configured  
Payment creation skipped: Booking 123 has no amount (amount: 0)
Payment creation failed: Processor returned null for booking 123
```

---

## Step 2: Specific Error Codes and Solutions

### **Error: "This booking has no amount specified"**

**Cause:** The booking was created with amount = 0 or NULL

**Check:**
```sql
SELECT id, amount, status, payment_status FROM bookings WHERE id = YOUR_BOOKING_ID;
```

**Solution:**
- Verify the booking was created with a price
- Check if the service has a price set
- Re-create the booking with proper pricing

---

### **Error: "Booking details could not be found"**

**Cause:** The booking exists but some required fields are missing

**Check:**
```sql
SELECT * FROM bookings WHERE id = YOUR_BOOKING_ID;
```

**Look for:**
- `client_id` - Should not be NULL
- `provider_id` - Should not be NULL  
- `amount` - Should be > 0
- `status` - Should be 'pending' or 'confirmed'

**Solution:**
- If fields are NULL, the booking is corrupted
- Contact your database administrator
- Re-create the booking

---

### **Error: "Payment record already exists"**

**Cause:** The system tried to create a duplicate payment

**Check:**
```sql
SELECT * FROM payments WHERE booking_id = YOUR_BOOKING_ID;
```

**Solution:**
- Refresh the page - it should now show the existing payment
- If not, the payment record is there but not loading
- Check browser cache and try again

---

### **Error: "Payment gateway is not configured"**

**Cause:** The default payment gateway is not set in system settings

**Check:**
```sql
SELECT * FROM system_settings WHERE setting_key = 'default_gateway';
```

**Should return:** `payment_enabled = 1` and `default_gateway = 'fake'` (or another gateway)

**Solution:**

1. **Go to Admin Settings:**
   - URL: `/admin/settings.php`
   - Find "Payment Enabled" → Check the box
   - Find "Default Payment Gateway" → Select "fake" or another option
   - Click Save

2. **Or run SQL:**
```sql
UPDATE system_settings SET setting_value = '1' WHERE setting_key = 'payment_enabled';
UPDATE system_settings SET setting_value = 'fake' WHERE setting_key = 'default_gateway';
```

---

### **Error: "Payment processor error"**

**Cause:** The PaymentProcessor class couldn't create the payment in the database

**Check:**
```sql
-- Check if payments table exists
SHOW TABLES LIKE 'payments';

-- Check payments table structure
DESCRIBE payments;
```

**Should have columns:** `id`, `booking_id`, `user_id`, `provider_id`, `amount`, `currency`, `status`, `created_at`

**Solution:**
- Run the migration: `/migrate_payments.php`
- Or execute the migration SQL from `/config/migrate_payments.sql`

---

### **Error: "An unexpected error occurred"**

**Cause:** Unknown exception in the code

**Solution:**
1. Check the error log for the exact error message
2. Look for exception code in the log
3. Contact your development team with:
   - Booking ID
   - Error code from logs
   - Exact error message

---

## Step 3: Database Validation

Run these checks to ensure everything is set up correctly:

```sql
-- 1. Check bookings table
SELECT COUNT(*) as total_bookings FROM bookings;
SELECT COUNT(*) as completed_bookings FROM bookings WHERE amount > 0;

-- 2. Check payments table
SELECT COUNT(*) as total_payments FROM payments;
SELECT COUNT(*) as pending_payments FROM payments WHERE status = 'pending';

-- 3. Check system settings
SELECT * FROM system_settings WHERE setting_key IN ('payment_enabled', 'default_gateway');

-- 4. Check for payments that failed to create
SELECT b.id, b.amount, p.id as payment_id 
FROM bookings b 
LEFT JOIN payments p ON b.id = p.booking_id 
WHERE b.amount > 0 AND p.id IS NULL 
LIMIT 10;
```

---

## Step 4: Common Scenarios

### **Scenario 1: User Created Booking But Payment Didn't Create**

**Symptoms:**
- Booking exists with amount > 0
- No payment record found
- Get error when trying to pay

**Solution:**
1. Refresh the page - system will auto-create payment
2. If still fails, check system_settings for payment configuration
3. Run migration script if payments table missing

---

### **Scenario 2: Payment Processing Works But Shows Error**

**Symptoms:**
- Error message appears
- But payment actually succeeded in database
- Check: `SELECT * FROM payments WHERE booking_id = ?;`

**Solution:**
- If payment exists with `status = 'success'`, the payment worked
- Error is just a display issue - refresh page
- Check browser console for JavaScript errors

---

### **Scenario 3: Payment Button Never Shows**

**Symptoms:**
- No error message, but pay button missing entirely
- Problem is not with payment creation

**Solution:**
- Check booking status: Should be 'pending' or 'confirmed'
- Check payment_status: Should be 'pending'
- Verify shouldShowPayButton() conditions:
  ```
  - payment_status === 'pending' AND
  - (booking_status === 'pending' OR booking_status === 'confirmed')
  ```

---

## Step 5: Check System Logs

### **View XAMPP PHP Errors:**
```powershell
# Windows - Last 50 error lines
Get-Content "C:\xampp\php\logs\php_error.log" -Tail 50
```

### **Look for "Payment creation" messages:**
```powershell
Select-String "Payment creation" "C:\xampp\php\logs\php_error.log" | Select-Object -Last 20
```

---

## Emergency Solutions

### **Reset All Payments (Dangerous - Use Caution):**

```sql
-- Backup first!
BACKUP TABLE payments TO '/backup/payments_backup.sql';

-- Delete all failed payment records
DELETE FROM payments WHERE status = 'failed';

-- Reset pending payments older than 24 hours
DELETE FROM payments 
WHERE status = 'pending' 
AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Recreate missing payments for bookings with no amount
-- (This requires custom logic)
```

---

## Prevention Tips

1. **Always verify amount before booking creation**
2. **Ensure default gateway is set in admin settings**
3. **Run migration script after deploying new code**
4. **Monitor error logs regularly**
5. **Test payments with test bookings before going live**

---

## Still Stuck?

Provide your support team with:

1. **Booking ID**: The ID that's causing the issue
2. **Exact error message**: Screenshot or text
3. **Error log excerpt**: Last 20 lines of php_error.log
4. **SQL results from troubleshooting queries** above

**Include this query result:**
```sql
SELECT * FROM bookings WHERE id = YOUR_BOOKING_ID;
SELECT * FROM payments WHERE booking_id = YOUR_BOOKING_ID;
SELECT * FROM system_settings WHERE setting_key IN ('payment_enabled', 'default_gateway');
```

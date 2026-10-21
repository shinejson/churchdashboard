# Attendance Records Table Setup

## ✅ Successfully Created

The `attendance_records` table has been created in your database along with the `services` table.

## 📊 Tables Created

### 1. `services` Table
Stores service types (Sunday Service, Bible Study, etc.)

**Columns:**
- `id` - Primary key
- `name` - Service name (e.g., "Sunday Service")
- `description` - Service description
- `day_of_week` - Day the service occurs
- `time` - Time of the service
- `fellowship_id` - Optional fellowship association
- `is_active` - Whether the service is active
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Default Services Added:**
- Sunday Service (Sunday, 9:00 AM)
- Bible Study (Wednesday, 6:00 PM)
- Prayer Meeting (Friday, 6:00 PM)
- Youth Service (Saturday, 4:00 PM)

### 2. `attendance_records` Table
Tracks individual member attendance at services

**Columns:**
- `id` - Primary key
- `member_id` - Reference to members table
- `service_id` - Reference to services table
- `fellowship_id` - Reference to fellowships table
- `date` - Date of attendance
- `status` - Attendance status (present, absent, excused)
- `notes` - Optional notes
- `recorded_by` - User who recorded the attendance
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Indexes:**
- `idx_member_id` - Fast lookup by member
- `idx_service_id` - Fast lookup by service
- `idx_fellowship_id` - Fast lookup by fellowship
- `idx_date` - Fast lookup by date
- `idx_status` - Fast lookup by status
- `unique_attendance` - Prevents duplicate records (member + service + date)

## 🎯 What This Enables

### For Members
When members log in to their profile, they can now see:
- ✅ Total services attended
- ✅ Number of times present/absent
- ✅ Attendance rate percentage
- ✅ Last attendance date
- ✅ Recent attendance history

### For Admins
You can now:
- ✅ Record attendance in the Attendance page
- ✅ Track member participation
- ✅ Generate attendance reports
- ✅ View attendance trends

## 📝 How to Use

### Recording Attendance

1. Go to the **Attendance** page in your admin panel
2. Select the service type (Sunday Service, Bible Study, etc.)
3. Select the date
4. Select the fellowship
5. Mark members as present or absent
6. Save the attendance record

### Viewing Member Attendance

1. Member logs in to their account
2. Automatically redirected to profile page
3. Scroll to "Attendance Summary" section
4. See complete attendance history and statistics

## 🔍 Verification

Run the test script to verify everything is set up:
```
http://your-domain.com/backend/migrations/test_member_setup.php
```

You should now see:
- ✅ attendance_records: Attendance records ✓ Exists

## 📊 Sample Data (Optional)

If you want to add sample attendance records for testing:

```sql
-- Add sample attendance for a member
INSERT INTO attendance_records (member_id, service_id, fellowship_id, date, status)
VALUES 
  (1, 1, 1, '2026-04-13', 'present'),
  (1, 1, 1, '2026-04-06', 'present'),
  (1, 1, 1, '2026-03-30', 'absent');
```

Replace:
- `member_id` with actual member ID
- `service_id` with actual service ID (1 = Sunday Service)
- `fellowship_id` with actual fellowship ID
- `date` with actual dates

## 🎨 Profile Display

The member profile page will automatically display:

**Attendance Summary Card:**
- Total Services: X
- Present: X
- Attendance Rate: X%
- Last Attendance: Date

**Recent Attendance List:**
- Date | Service | Status
- Scrollable list of recent attendance records

## 🔧 Customization

### Add More Services

```sql
INSERT INTO services (name, description, day_of_week, time, is_active)
VALUES ('Special Event', 'Monthly special service', 'First Sunday', '15:00:00', 1);
```

### Modify Service Times

```sql
UPDATE services 
SET time = '10:00:00' 
WHERE name = 'Sunday Service';
```

### Deactivate a Service

```sql
UPDATE services 
SET is_active = 0 
WHERE name = 'Youth Service';
```

## 🐛 Troubleshooting

### Attendance not showing in profile
**Cause:** No attendance records for the member  
**Solution:** Record some attendance in the Attendance page

### "Service not found" error
**Cause:** Service ID doesn't exist  
**Solution:** Check services table and use valid service IDs

### Duplicate attendance error
**Cause:** Trying to record same member + service + date twice  
**Solution:** Update existing record instead of creating new one

## 📈 Next Steps

1. ✅ Tables created
2. ⏭️ Start recording attendance
3. ⏭️ Create member user accounts
4. ⏭️ Test member login and profile view
5. ⏭️ Verify attendance displays correctly

## 🎉 Complete!

Your attendance tracking system is now fully set up and integrated with the member profile feature. Members can view their attendance history, and admins can track participation across all services.

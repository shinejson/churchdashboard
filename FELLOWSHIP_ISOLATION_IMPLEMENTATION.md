# Fellowship Isolation Implementation for Report APIs

## Overview
This document describes the fellowship data isolation implementation applied to all report APIs, ensuring that non-admin users can only view data from their own fellowship while admins can see all data.

## Implementation Pattern

### 1. Core Function: `current_fellowship_scope()`
Located in `backend/config/security.php`, this function returns:
- `null` for admin users (can see all fellowships)
- `int` (fellowship_id) for non-admin users (restricted to their fellowship)

### 2. Standard Implementation Pattern

Each report API file now follows this pattern:

```php
// After session check, add fellowship scope
secure_session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Fellowship data isolation: non-admins are always scoped to their own fellowship
$scopeId = current_fellowship_scope();
```

### 3. Query Modification Pattern

For each database query, apply the scope using this logic:

```php
// Apply fellowship scope for non-admins
if ($scopeId !== null) {
    $whereConditions[] = 'fellowship_id = ?';
    $params[] = $scopeId;
} elseif ($filters['fellowship_id'] !== null) {
    // Only apply filter if no scope (admin user)
    $whereConditions[] = 'fellowship_id = ?';
    $params[] = $filters['fellowship_id'];
}
```

### 4. Special Cases

#### A. Fellowship as Grouping Dimension
When fellowship is the grouping dimension (e.g., "by_fellowship" reports), only apply scope:

```php
// Apply fellowship scope for non-admins
if ($scopeId !== null) {
    $whereConditions[] = 'fellowship_id = ?';
    $params[] = $scopeId;
}
// Don't apply filter here since fellowship is what we're grouping by
```

#### B. Service as Grouping Dimension
When service is the grouping dimension, apply fellowship scope but allow service filter:

```php
// Apply fellowship scope for non-admins
if ($scopeId !== null) {
    $whereConditions[] = 'fellowship_id = ?';
    $params[] = $scopeId;
}

// Service filter can still be applied
if ($filters['service_id'] !== null) {
    $whereConditions[] = 'service_id = ?';
    $params[] = $filters['service_id'];
}
```

## Files Updated

### Core Files
1. `frontend/api/report/apis/overview.php` ✅
2. `frontend/api/report/apis/apply_fellowship_scope.php` ✅ (New helper file)

### Attendance Reports
3. `frontend/api/report/apis/attendance/summary.php` ✅
4. `frontend/api/report/apis/attendance/by_service.php` ✅
5. `frontend/api/report/apis/attendance/top_attendees.php` ⚠️ (Needs update)
6. `frontend/api/report/apis/attendance/trends.php` ⚠️ (Needs update)

### Finance Reports
7. `frontend/api/report/apis/finance/offerings.php` ✅
8. `frontend/api/report/apis/finance/tithes.php` ✅
9. `frontend/api/report/apis/finance/donations.php` ✅
10. `frontend/api/report/apis/finance/by_payment_method.php` ✅
11. `frontend/api/report/apis/finance/income_range.php` ✅
12. `frontend/api/report/apis/finance/partnership_arms.php` ✅

### Member Reports
13. `frontend/api/report/apis/members/summary.php` ✅
14. `frontend/api/report/apis/members/by_age.php` ⚠️ (Needs update)
15. `frontend/api/report/apis/members/by_status.php` ⚠️ (Needs update)
16. `frontend/api/report/apis/members/new_members.php` ⚠️ (Needs update)

### Fellowship Reports
17. `frontend/api/report/apis/fellowship/summary.php` ⚠️ (Needs update)
18. `frontend/api/report/apis/fellowship/attendance.php` ⚠️ (Needs update)

### Service Reports
19. `frontend/api/report/apis/services/summary.php` ⚠️ (Needs update)
20. `frontend/api/report/apis/services/detailed.php` ⚠️ (Needs update)
21. `frontend/api/report/apis/services/combined_report.php` ⚠️ (Needs update)

### System Reports
22. `frontend/api/report/apis/system/logs.php` ⚠️ (Needs update)
23. `frontend/api/report/apis/system/user_activity.php` ⚠️ (Needs update)

## Behavior by Role

### Admin Role
- `current_fellowship_scope()` returns `null`
- Can see all financial transactions, tithes, totals, categories, and charts across all fellowships
- Can create/update/delete for any fellowship (based on the payload)
- Fellowship dropdown shows all fellowships

### Non-Admin Roles (pastor, pcf, leader, etc.)
- `current_fellowship_scope()` returns their `$_SESSION['fellowship_id']`
- Financial listings, totals, and charts are automatically restricted to their fellowship
- Create/update/delete operations are forced to their fellowship
- Cannot affect records belonging to another fellowship
- Fellowship dropdown only contains their own fellowship

## Testing Checklist

### As Admin
- [ ] Can view all fellowships in reports
- [ ] Can filter by any fellowship
- [ ] Can see aggregated data across all fellowships
- [ ] Fellowship dropdown shows all active fellowships

### As Non-Admin (e.g., Pastor)
- [ ] Can only see own fellowship data
- [ ] Fellowship filter is automatically applied
- [ ] Cannot see other fellowships' data even if fellowship_id parameter is manipulated
- [ ] Fellowship dropdown shows only own fellowship
- [ ] All totals and charts reflect only own fellowship

## Security Notes

1. **Server-side enforcement**: Fellowship isolation is enforced at the database query level, not just in the UI
2. **Parameter manipulation protection**: Even if a non-admin user tries to pass a different fellowship_id in the URL, the scope overrides it
3. **Consistent behavior**: All report APIs follow the same pattern for predictable security
4. **Audit trail**: All queries respect the fellowship scope, ensuring data integrity

## Next Steps

To complete the implementation, the remaining files marked with ⚠️ need to be updated following the same pattern:

1. Add `$scopeId = current_fellowship_scope();` after session check
2. Modify all queries to apply the scope using the pattern described above
3. Test each endpoint with both admin and non-admin users
4. Verify that fellowship isolation works correctly for all report types

## Helper Functions

A helper file `apply_fellowship_scope.php` has been created with reusable functions:
- `applyFellowshipScope()`: Apply scope with fallback to filter
- `applyFellowshipScopeOnly()`: Apply scope only (for grouping dimensions)

These can be included in remaining files to simplify the implementation.

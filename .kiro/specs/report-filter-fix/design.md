# Design Document: Report Filter Fix

## Overview

This design addresses the missing filter functionality in the church management system's report APIs. The frontend correctly sends `fellowship_id` and `service_id` parameters, but the backend APIs ignore these parameters in their SQL queries. This design introduces a centralized filter helper function and updates all 19 report API endpoints to properly apply fellowship and service filters.

The solution maintains backward compatibility by treating empty or null filter values as "show all records" while applying filters when values are provided. All filtering is performed at the SQL level using parameterized queries to ensure security and performance.

## Architecture

### Current Architecture

The report system consists of:
- **Frontend**: `frontend/pages/report.php` - Sends filter parameters via GET requests
- **Backend APIs**: 19 PHP endpoints in `frontend/api/report/apis/` organized by category:
  - Attendance (4 files)
  - Finance (6 files)
  - Members (4 files)
  - Fellowship (2 files)
  - Services (2 files)
  - System (2 files) - Not affected by filters
  - Overview (1 file)

Each API currently has helper functions `getDateRange()` and `getPeriodFilter()` but lacks filter handling.

### Proposed Architecture

Add a new helper function `getFilterConditions()` that:
1. Extracts fellowship_id and service_id from GET parameters
2. Validates the parameters
3. Returns SQL WHERE conditions and parameter bindings
4. Integrates seamlessly with existing date range filters

This function will be added to each API file (following the existing pattern of duplicated helper functions) to maintain the current architecture's simplicity.

## Components and Interfaces

### Component 1: Filter Helper Function

**Function Signature:**
```php
function getFilterConditions(array $options = []): array
```

**Parameters:**
- `$options`: Optional configuration array with keys:
  - `'fellowship_column'`: Column name for fellowship filter (default: 'fellowship_id')
  - `'service_column'`: Column name for service filter (default: 'service_id')
  - `'table_alias'`: Table alias prefix (default: empty string)

**Returns:**
```php
[
    'conditions' => string[],  // Array of SQL WHERE conditions
    'params' => mixed[],       // Array of parameter values for binding
    'fellowship_id' => int|null,  // Parsed fellowship_id
    'service_id' => int|null      // Parsed service_id
]
```

**Behavior:**
- Reads `$_GET['fellowship_id']` and `$_GET['service_id']`
- Validates that values are numeric if provided
- Returns empty conditions array if no filters provided
- Throws InvalidArgumentException for invalid values

**Example Usage:**
```php
$filters = getFilterConditions(['table_alias' => 'a.']);
// Returns: ['conditions' => ['a.fellowship_id = ?'], 'params' => [5], 'fellowship_id' => 5, 'service_id' => null]
```

### Component 2: SQL Query Integration Pattern

**Pattern for Direct Column Filtering:**

When a table has direct `fellowship_id` or `service_id` columns:

```php
$filters = getFilterConditions();
$whereConditions = ['service_date BETWEEN ? AND ?'];
$params = [$dateRange['start'], $dateRange['end']];

if (!empty($filters['conditions'])) {
    $whereConditions = array_merge($whereConditions, $filters['conditions']);
    $params = array_merge($params, $filters['params']);
}

$whereClause = implode(' AND ', $whereConditions);
$sql = "SELECT ... FROM table WHERE $whereClause";
$stmt->execute($params);
```

**Pattern for JOIN-Based Filtering:**

When filtering requires joining through related tables:

```php
$filters = getFilterConditions(['table_alias' => 'a.']);
$sql = "SELECT ... 
        FROM members m
        LEFT JOIN attendance a ON m.id = a.member_id
        WHERE m.join_date BETWEEN ? AND ?";

if (!empty($filters['conditions'])) {
    $sql .= " AND " . implode(' AND ', $filters['conditions']);
}
```

### Component 3: Updated API Endpoints

Each of the 17 affected API endpoints (excluding system reports) will be updated to:

1. Include the `getFilterConditions()` helper function
2. Modify all SQL queries to incorporate filter conditions
3. Maintain existing response structures
4. Handle cases where filters don't apply to specific queries

**Affected Files:**
- `attendance/summary.php` - 4 queries to update
- `attendance/by_service.php` - 2 queries to update
- `attendance/trends.php` - 2 queries to update
- `attendance/top_attendees.php` - 1 query to update
- `finance/offerings.php` - 4 queries to update
- `finance/tithes.php` - 4 queries to update
- `finance/donations.php` - 3 queries to update
- `finance/income_range.php` - 2 queries to update
- `finance/by_payment_method.php` - 2 queries to update
- `finance/partnership_arms.php` - 2 queries to update
- `members/summary.php` - 5 queries to update
- `members/by_status.php` - 2 queries to update
- `members/by_age.php` - 2 queries to update
- `members/new_members.php` - 1 query to update
- `fellowship/summary.php` - 3 queries to update
- `fellowship/attendance.php` - 2 queries to update
- `services/summary.php` - 3 queries to update
- `services/detailed.php` - 3 queries to update
- `overview.php` - 5 queries to update

## Data Models

### Input Data Model

**GET Parameters:**
```php
[
    'fellowship_id' => string|null,  // Numeric string or empty
    'service_id' => string|null,     // Numeric string or empty
    'start_date' => string,          // YYYY-MM-DD format (existing)
    'end_date' => string,            // YYYY-MM-DD format (existing)
    'period' => string               // daily|weekly|monthly|quarterly|yearly (existing)
]
```

### Filter Conditions Data Model

**Internal Representation:**
```php
[
    'conditions' => [
        'fellowship_id = ?',  // Only if fellowship_id provided
        'service_id = ?'      // Only if service_id provided
    ],
    'params' => [
        5,   // fellowship_id value
        3    // service_id value
    ],
    'fellowship_id' => 5,    // Parsed value for reference
    'service_id' => 3        // Parsed value for reference
]
```

### Database Schema Context

**Relevant Tables and Columns:**

1. **attendance** table:
   - `fellowship_id` (direct filter)
   - `service_id` (direct filter)
   - `service_date` (existing date filter)

2. **financial_transactions** table:
   - `fellowship_id` (direct filter)
   - `service_id` (direct filter)
   - `transaction_date` (existing date filter)

3. **tithe_transactions** table:
   - `fellowship_id` (direct filter)
   - `service_id` (direct filter)
   - `transaction_date` (existing date filter)

4. **members** table:
   - `fellowship_id` (direct filter)
   - No `service_id` (requires JOIN through attendance)

5. **fellowships** table:
   - `id` (used in fellowship filter)
   - No service_id (not applicable)

6. **services** table:
   - `id` (used in service filter)
   - No fellowship_id (not applicable)

### Error Response Model

```php
[
    'success' => false,
    'message' => 'Invalid filter parameter: fellowship_id must be numeric',
    'code' => 'INVALID_FILTER'
]
```


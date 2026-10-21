# Requirements Document

## Introduction

This document specifies the requirements for fixing the report filtering functionality in the church management system. The frontend report page has dropdown filters for Fellowship and Service, but the backend API endpoints are not applying these filters in their SQL queries, resulting in no records or all records being returned regardless of the selected filters.

## Glossary

- **Report_API**: The backend PHP API endpoints that handle report data requests
- **Filter_Parameter**: The fellowship_id or service_id values sent from the frontend
- **SQL_Query**: The database query executed by the Report_API to retrieve data
- **Frontend_Filter**: The dropdown selection controls on the report.php page
- **WHERE_Clause**: The SQL condition that filters database records

## Requirements

### Requirement 1: Apply Fellowship Filter

**User Story:** As a church administrator, I want to filter reports by fellowship, so that I can view data specific to a particular fellowship group.

#### Acceptance Criteria

1. WHEN a fellowship is selected from the Frontend_Filter, THE Report_API SHALL include the fellowship_id in the WHERE_Clause of all relevant SQL_Queries
2. WHEN fellowship_id Filter_Parameter is empty or null, THE Report_API SHALL return records for all fellowships
3. WHEN fellowship_id Filter_Parameter is provided, THE Report_API SHALL return only records matching that fellowship_id
4. THE Report_API SHALL validate that fellowship_id is a valid integer before using it in SQL_Queries
5. WHEN an invalid fellowship_id is provided, THE Report_API SHALL return an error response with status code 400

### Requirement 2: Apply Service Filter

**User Story:** As a church administrator, I want to filter reports by service type, so that I can analyze data for specific services like Sunday Service or Midweek Service.

#### Acceptance Criteria

1. WHEN a service is selected from the Frontend_Filter, THE Report_API SHALL include the service_id in the WHERE_Clause of all relevant SQL_Queries
2. WHEN service_id Filter_Parameter is empty or null, THE Report_API SHALL return records for all services
3. WHEN service_id Filter_Parameter is provided, THE Report_API SHALL return only records matching that service_id
4. THE Report_API SHALL validate that service_id is a valid integer before using it in SQL_Queries
5. WHEN an invalid service_id is provided, THE Report_API SHALL return an error response with status code 400

### Requirement 3: Apply Combined Filters

**User Story:** As a church administrator, I want to apply both fellowship and service filters simultaneously, so that I can view highly specific data subsets.

#### Acceptance Criteria

1. WHEN both fellowship_id and service_id Filter_Parameters are provided, THE Report_API SHALL apply both conditions in the WHERE_Clause using AND logic
2. WHEN both filters are applied, THE Report_API SHALL return only records matching both fellowship_id AND service_id
3. THE Report_API SHALL maintain correct SQL syntax when combining multiple filter conditions with existing date range filters

### Requirement 4: Maintain Backward Compatibility

**User Story:** As a system maintainer, I want existing functionality to remain unchanged, so that reports without filters continue to work as expected.

#### Acceptance Criteria

1. WHEN no Filter_Parameters are provided, THE Report_API SHALL return all records within the date range (existing behavior)
2. THE Report_API SHALL preserve all existing helper functions (getDateRange, getPeriodFilter)
3. THE Report_API SHALL maintain all existing response data structures and field names
4. THE Report_API SHALL continue to support all existing query parameters (start_date, end_date, period)

### Requirement 5: Create Filter Helper Function

**User Story:** As a developer, I want a reusable filter helper function, so that I can consistently apply fellowship and service filters across all report APIs.

#### Acceptance Criteria

1. THE Report_API SHALL provide a helper function named getFilterConditions that returns SQL WHERE conditions
2. THE getFilterConditions function SHALL accept fellowship_id and service_id as optional parameters
3. THE getFilterConditions function SHALL return an array containing SQL condition strings and parameter values
4. THE getFilterConditions function SHALL handle null or empty filter values by excluding them from conditions
5. THE getFilterConditions function SHALL validate filter parameters and throw exceptions for invalid values

### Requirement 6: Update All Report API Endpoints

**User Story:** As a church administrator, I want all report types to respect the fellowship and service filters, so that I have consistent filtering behavior across the entire reporting system.

#### Acceptance Criteria

1. THE Report_API SHALL apply filter logic to all attendance report endpoints (summary.php, by_service.php, trends.php, top_attendees.php)
2. THE Report_API SHALL apply filter logic to all finance report endpoints (offerings.php, tithes.php, donations.php, income_range.php, by_payment_method.php, partnership_arms.php)
3. THE Report_API SHALL apply filter logic to all member report endpoints (summary.php, by_status.php, by_age.php, new_members.php)
4. THE Report_API SHALL apply filter logic to all fellowship report endpoints (summary.php, attendance.php)
5. THE Report_API SHALL apply filter logic to all service report endpoints (summary.php, detailed.php)
6. THE Report_API SHALL apply filter logic to the overview.php endpoint
7. WHEN a table does not have a fellowship_id or service_id column, THE Report_API SHALL apply the filter through appropriate JOIN clauses

### Requirement 7: Handle Tables Without Direct Filter Columns

**User Story:** As a developer, I want the system to handle tables that don't have direct fellowship_id or service_id columns, so that filters work correctly across all data models.

#### Acceptance Criteria

1. WHEN filtering the members table by service_id, THE Report_API SHALL join through the attendance table to apply the service filter
2. WHEN filtering financial_transactions that lack a direct relationship, THE Report_API SHALL use appropriate JOIN logic through related tables
3. THE Report_API SHALL document which tables support direct filtering versus JOIN-based filtering
4. WHEN a filter cannot be applied to a specific query, THE Report_API SHALL log a warning but continue execution

### Requirement 8: Preserve Query Performance

**User Story:** As a system administrator, I want report queries to remain performant, so that users experience fast response times even with filters applied.

#### Acceptance Criteria

1. THE Report_API SHALL use parameterized queries for all filter values to prevent SQL injection
2. THE Report_API SHALL add filter conditions to existing WHERE clauses rather than filtering results in PHP
3. THE Report_API SHALL maintain use of appropriate database indexes by filtering at the SQL level
4. WHEN filters are applied, THE Report_API SHALL execute queries in the same or better time compared to unfiltered queries on equivalent result sets

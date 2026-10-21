<?php
/**
 * Helper function to apply fellowship scope to report API queries
 * 
 * This function modifies filter conditions to enforce fellowship isolation
 * for non-admin users. Admins can see all data, while other roles are
 * restricted to their own fellowship.
 * 
 * @param array $filters The filters array from getFilterConditions()
 * @param int|null $scopeId The fellowship scope ID from current_fellowship_scope()
 * @param array $whereConditions Existing WHERE conditions array
 * @param array $params Existing parameters array
 * @param string $fellowshipColumn The fellowship column name (default: 'fellowship_id')
 * @return array Modified ['whereConditions' => [], 'params' => []]
 */
function applyFellowshipScope($filters, $scopeId, $whereConditions, $params, $fellowshipColumn = 'fellowship_id') {
    // If user has a fellowship scope (non-admin), enforce it
    if ($scopeId !== null) {
        $whereConditions[] = $fellowshipColumn . ' = ?';
        $params[] = $scopeId;
    }
    // Otherwise, if a fellowship filter was provided in the request, apply it
    elseif ($filters['fellowship_id'] !== null) {
        $whereConditions[] = $fellowshipColumn . ' = ?';
        $params[] = $filters['fellowship_id'];
    }
    
    return [
        'whereConditions' => $whereConditions,
        'params' => $params
    ];
}

/**
 * Apply fellowship scope for queries where fellowship is the grouping dimension
 * In this case, we only apply the scope but not the filter from request
 * 
 * @param int|null $scopeId The fellowship scope ID from current_fellowship_scope()
 * @param array $whereConditions Existing WHERE conditions array
 * @param array $params Existing parameters array
 * @param string $fellowshipColumn The fellowship column name (default: 'fellowship_id')
 * @return array Modified ['whereConditions' => [], 'params' => []]
 */
function applyFellowshipScopeOnly($scopeId, $whereConditions, $params, $fellowshipColumn = 'fellowship_id') {
    // If user has a fellowship scope (non-admin), enforce it
    if ($scopeId !== null) {
        $whereConditions[] = $fellowshipColumn . ' = ?';
        $params[] = $scopeId;
    }
    
    return [
        'whereConditions' => $whereConditions,
        'params' => $params
    ];
}

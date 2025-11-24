<?php
// includes/super_admin_helpers.php

/**
 * Check if current logged-in user is a super admin.
 * Safe to call in any script.
 *
 * @return bool
 */
function isSuperAdmin(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['user_id'], $_SESSION['role'])
        && $_SESSION['role'] === 'super_admin';
}

/**
 * Hard guard: require super admin, or redirect to login.
 * Use this at the top of super-admin-only pages.
 *
 * @return void
 */
function requireSuperAdmin(): void
{
    if (!isSuperAdmin()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Fetch a single scope seat by scope_id + expected scope_type.
 * This wraps getScopeSeats() to return exactly one row or null.
 *
 * @param PDO    $pdo
 * @param string $expectedScopeType  e.g. SCOPE_ACAD_STUDENT
 * @param int    $scopeId
 * @return array|null
 */
function getScopeSeatById(PDO $pdo, string $expectedScopeType, int $scopeId): ?array
{
    // We use getScopeSeats() from analytics_scopes.php
    $allSeatsOfType = getScopeSeats($pdo, $expectedScopeType);

    foreach ($allSeatsOfType as $seat) {
        if ((int)$seat['scope_id'] === $scopeId) {
            return $seat;
        }
    }

    return null;
}

/**
 * Helper for impersonation-style dashboards:
 *
 * It reads ?scope_id= from URL, verifies:
 *  - current user is super_admin
 *  - scope_id exists in admin_scopes AND matches the expected scope_type
 *
 * If all good, it returns the scope seat array (same shape as getScopeSeats()).
 * If anything is invalid, it returns null (caller decides what to do).
 *
 * @param PDO    $pdo
 * @param string $expectedScopeType  e.g. SCOPE_ACAD_STUDENT
 * @param string $paramName          GET param name, default 'scope_id'
 * @return array|null
 */
function resolveImpersonatedScope(PDO $pdo, string $expectedScopeType, string $paramName = 'scope_id'): ?array
{
    if (!isSuperAdmin()) {
        // Not allowed to impersonate
        return null;
    }

    if (!isset($_GET[$paramName]) || !ctype_digit((string)$_GET[$paramName])) {
        // No scope_id in URL, or invalid
        return null;
    }

    $scopeId = (int)$_GET[$paramName];

    // Use the helper above
    $seat = getScopeSeatById($pdo, $expectedScopeType, $scopeId);

    return $seat ?: null;
}

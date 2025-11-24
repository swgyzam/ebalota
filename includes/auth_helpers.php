<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * True if the current logged in user is a super admin.
 */
function isSuperAdmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

/**
 * True if the current logged in user is a normal admin.
 */
function isAdminUser(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Get impersonated scope_id from query string if:
 *  - current user is super_admin
 *  - ?scope_id= is a valid integer
 *
 * Otherwise returns null.
 */
function getImpersonatedScopeId(): ?int
{
    if (!isSuperAdmin()) {
        return null;
    }
    if (!isset($_GET['scope_id']) || !ctype_digit((string)$_GET['scope_id'])) {
        return null;
    }
    return (int) $_GET['scope_id'];
}

/**
 * Fetch an admin_scopes row by scope_id.
 * Returns null if not found.
 */
function fetchScopeSeatById(PDO $pdo, int $scopeId): ?array
{
    $stmt = $pdo->prepare("
        SELECT s.*, u.assigned_scope, u.assigned_scope_1
        FROM admin_scopes s
        JOIN users u ON u.user_id = s.user_id
        WHERE s.scope_id = :sid
        LIMIT 1
    ");
    $stmt->execute([':sid' => $scopeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $details = [];
    if (!empty($row['scope_details'])) {
        $decoded = json_decode($row['scope_details'], true);
        if (is_array($decoded)) {
            $details = $decoded;
        }
    }

    $row['scope_details_array'] = $details;
    return $row;
}

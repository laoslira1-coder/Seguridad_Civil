<?php
// ==============================================================================
// SEGURIDAD CENTRAL - CSRF TOKEN Y HELPERS
// Incluir con: require_once 'security.php';
// ==============================================================================

/**
 * Genera (o reutiliza) el token CSRF de la sesión actual.
 */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Devuelve el campo hidden listo para pegar en cualquier <form>.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Valida el token CSRF del POST actual.
 * Aborta con HTTP 403 si no coincide.
 */
function csrf_validate(): void {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(csrf_token(), $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('Error de seguridad: token CSRF inválido. Recargue la página e intente de nuevo.');
    }
}
?>

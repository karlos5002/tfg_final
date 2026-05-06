<?php
// ============================================================================
// notificaciones.php — capa de compatibilidad sobre core/mailer.php
// ============================================================================
// Antes este archivo duplicaba la lógica de envío de emails. Tras el refactor
// para soportar SMTP + adjuntos toda la lógica vive en core/mailer.php.
// Este archivo se mantiene como SHIM porque admin/visitas.php sigue llamando
// a enviarNotificacion() / notificacionConfirmacionVisita() / notificacionCancelacionVisita().
// Internamente delegamos a mailer.php sin romper la API existente.
// ============================================================================

require_once __DIR__ . '/mailer.php';


// ─── Alias retro-compatibles ───
if (!function_exists('enviarNotificacion')) {
    function enviarNotificacion(string $to, string $subject, string $htmlBody): array
    {
        return enviarEmail($to, $subject, $htmlBody);
    }
}

if (!function_exists('notificacionConfirmacionVisita')) {
    function notificacionConfirmacionVisita(array $v): string
    {
        return emailConfirmacionVisita($v);
    }
}

if (!function_exists('notificacionCancelacionVisita')) {
    function notificacionCancelacionVisita(array $v): string
    {
        return emailCancelacionVisita($v);
    }
}

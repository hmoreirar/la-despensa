<?php
/**
 * Envía el correo de WordPress y WooCommerce a Mailpit en desarrollo.
 */

defined('ABSPATH') || exit;

add_filter('wp_mail_from', function (): string {
    return 'pedidos@ladespensa.cl';
}, 1);

add_filter('wp_mail_from_name', function (): string {
    return 'La Despensa';
}, 1);

add_action('phpmailer_init', function ($phpmailer): void {
    $host = getenv('MAILPIT_HOST');

    if (!$host) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $host;
    $phpmailer->Port = (int) (getenv('MAILPIT_PORT') ?: 1025);
    $phpmailer->SMTPAuth = false;
    $phpmailer->SMTPAutoTLS = false;
    $phpmailer->SMTPSecure = '';
});

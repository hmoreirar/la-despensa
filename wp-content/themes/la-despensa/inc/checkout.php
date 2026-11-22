<?php
/**
 * Personalizaciones de checkout y carrito.
 */

defined('ABSPATH') || exit;

/**
 * Normaliza un RUT chileno a formato 12345678-9.
 */
function ld_normalize_rut(string $value): string
{
    $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $value));

    if (strlen($clean) < 2) {
        return '';
    }

    return substr($clean, 0, -1) . '-' . substr($clean, -1);
}

/**
 * Formatea un RUT con puntos para mostrar.
 */
function ld_format_rut(string $value): string
{
    $clean = strtoupper(preg_replace('/[^0-9K]/', '', $value));

    if (strlen($clean) < 2) {
        return $value;
    }

    $body = substr($clean, 0, -1);
    $digit = substr($clean, -1);

    return number_format((int) $body, 0, ',', '.') . '-' . $digit;
}

function ld_sanitize_rut($value, $field = null): string
{
    $normalized = ld_normalize_rut((string) $value);

    return $normalized !== '' ? ld_format_rut($normalized) : '';
}

/**
 * Valida el dígito verificador con módulo 11.
 */
function ld_validate_rut($value, $field = null)
{
    $clean = strtoupper(preg_replace('/[^0-9kK]/', '', (string) $value));

    if ($clean === '') {
        return true;
    }

    if (!preg_match('/^(\d{7,8})([0-9K])$/', $clean, $matches)) {
        return new WP_Error(
            'ld_rut_format',
            __('Ingresa un RUT válido, por ejemplo 12.345.678-5.', 'la-despensa')
        );
    }

    $body = $matches[1];
    $digit = $matches[2];
    $sum = 0;
    $factor = 2;

    for ($index = strlen($body) - 1; $index >= 0; $index--) {
        $sum += (int) $body[$index] * $factor;
        $factor = $factor === 7 ? 2 : $factor + 1;
    }

    $remainder = 11 - ($sum % 11);
    $expected = $remainder === 11 ? '0' : ($remainder === 10 ? 'K' : (string) $remainder);

    if ($digit !== $expected) {
        return new WP_Error(
            'ld_rut_invalid',
            __('El RUT ingresado no es válido.', 'la-despensa')
        );
    }

    return true;
}

add_action('woocommerce_init', function (): void {
    if (!function_exists('woocommerce_register_additional_checkout_field')) {
        return;
    }

    woocommerce_register_additional_checkout_field([
        'id' => 'ladespensa/rut',
        'label' => __('RUT', 'la-despensa'),
        'location' => 'contact',
        'type' => 'text',
        'required' => true,
        'attributes' => [
            'autocomplete' => 'off',
        ],
        'sanitize_callback' => 'ld_sanitize_rut',
        'validate_callback' => 'ld_validate_rut',
        'show_in_order_confirmation' => true,
    ]);
});

function ld_free_shipping_threshold(): float
{
    return (float) apply_filters('ladespensa_free_shipping_threshold', 30000);
}

function ld_cart_has_free_shipping_coupon(): bool
{
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }

    foreach (WC()->cart->get_coupons() as $coupon) {
        if ($coupon->get_free_shipping()) {
            return true;
        }
    }

    return false;
}

function ld_free_shipping_state(): array
{
    $threshold = ld_free_shipping_threshold();
    $subtotal = function_exists('WC') && WC()->cart
        ? (float) WC()->cart->get_displayed_subtotal()
        : 0.0;

    $free = $subtotal >= $threshold || ld_cart_has_free_shipping_coupon();

    return [
        'subtotal' => $subtotal,
        'threshold' => $threshold,
        'remaining' => max(0, $threshold - $subtotal),
        'free' => $free,
        'empty' => $subtotal <= 0,
    ];
}

function ld_free_shipping_notice_html(): string
{
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
        return '';
    }

    $state = ld_free_shipping_state();
    $percent = $state['threshold'] > 0
        ? min(100, ($state['subtotal'] / $state['threshold']) * 100)
        : 100;

    $message = $state['free']
        ? __('Tu pedido tiene envío gratis.', 'la-despensa')
        : sprintf(
            /* translators: %s: monto faltante. */
            __('Te faltan %s para envío gratis.', 'la-despensa'),
            wc_price($state['remaining'])
        );

    return sprintf(
        '<div class="ld-free-shipping%1$s" data-threshold="%2$s"><p aria-live="polite">%3$s</p><div class="ld-free-shipping__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%4$s"><span style="width:%4$s%%"></span></div></div>',
        $state['free'] ? ' is-complete' : '',
        esc_attr((string) $state['threshold']),
        wp_kses_post($message),
        esc_attr((string) round($percent))
    );
}

add_filter('render_block', function (string $content, array $block): string {
    if (!is_cart() || ($block['blockName'] ?? '') !== 'woocommerce/cart') {
        return $content;
    }

    return ld_free_shipping_notice_html() . $content;
}, 10, 2);

/**
 * Cuando el pedido califica para envío gratis, deja solo esa tarifa.
 */
add_filter('woocommerce_package_rates', function (array $rates, array $package): array {
    if (!function_exists('WC') || !WC()->cart || !ld_free_shipping_state()['free']) {
        return $rates;
    }

    $has_free_rate = false;

    foreach ($rates as $rate) {
        if ($rate->get_method_id() === 'free_shipping') {
            $has_free_rate = true;
            break;
        }
    }

    if (!$has_free_rate) {
        return $rates;
    }

    foreach ($rates as $key => $rate) {
        if ($rate->get_method_id() === 'flat_rate') {
            unset($rates[$key]);
        }
    }

    return $rates;
}, 20, 2);

function ld_cart_progress_ajax(): void
{
    check_ajax_referer('ld_cart_progress', 'nonce');

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json_error();
    }

    wp_send_json_success(ld_free_shipping_state());
}

add_action('wp_ajax_ld_cart_progress', 'ld_cart_progress_ajax');
add_action('wp_ajax_nopriv_ld_cart_progress', 'ld_cart_progress_ajax');

add_action('wp_enqueue_scripts', function (): void {
    if (!is_cart()) {
        return;
    }

    $script = get_stylesheet_directory() . '/assets/js/cart-progress.js';

    wp_enqueue_script(
        'ld-cart-progress',
        get_stylesheet_directory_uri() . '/assets/js/cart-progress.js',
        [],
        (string) filemtime($script),
        true
    );

    wp_localize_script('ld-cart-progress', 'ldCartProgress', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ld_cart_progress'),
    ]);
});

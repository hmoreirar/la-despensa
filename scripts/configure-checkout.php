<?php
/**
 * Pagos de prueba, cupones, correos y páginas legales de La Despensa.
 * Uso: make checkout
 */

if (!class_exists('WooCommerce')) {
    WP_CLI::error('WooCommerce no está activo.');
}

function ld_upsert_page(string $slug, string $title, string $content): int
{
    $page = get_page_by_path($slug);

    $data = [
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'page',
    ];

    if ($page) {
        $data['ID'] = $page->ID;
        $page_id = wp_update_post($data, true);
    } else {
        $page_id = wp_insert_post($data, true);
    }

    if (is_wp_error($page_id)) {
        WP_CLI::error($page_id->get_error_message());
    }

    return (int) $page_id;
}

$privacy_id = ld_upsert_page('politica-de-privacidad', 'Política de privacidad', <<<'HTML'
<h2>Qué datos recopilamos</h2>
<p>Para procesar un pedido guardamos nombre, correo electrónico, dirección, teléfono y detalle de la compra. No almacenamos datos de tarjetas en este sitio.</p>
<h2>Para qué los usamos</h2>
<p>Usamos los datos para preparar y despachar pedidos, responder consultas y enviar avisos relacionados con la compra. No vendemos información a terceros.</p>
<h2>Conservación</h2>
<p>Mantenemos los datos de pedidos mientras sean necesarios para la operación y las obligaciones legales. Puedes solicitar su eliminación escribiendo a hola@ladespensa.cl.</p>
<h2>Contacto</h2>
<p>La Despensa · hola@ladespensa.cl</p>
HTML);

$terms_id = ld_upsert_page('terminos-y-condiciones', 'Términos y condiciones', <<<'HTML'
<h2>Pedidos</h2>
<p>Los precios están expresados en pesos chilenos (CLP) e incluyen IVA. Un pedido se considera confirmado cuando el pago es aprobado o confirmado por la tienda.</p>
<h2>Envíos</h2>
<p>Despachamos a todo Chile. La tarifa plana es de $3.990 y el envío es gratuito en compras desde $30.000. Los plazos se informan al confirmar el pedido.</p>
<h2>Cambios y devoluciones</h2>
<p>Si un producto llega dañado o no corresponde a lo pedido, contáctanos dentro de 30 días para coordinar el cambio o la devolución.</p>
<h2>Contacto</h2>
<p>La Despensa · hola@ladespensa.cl</p>
HTML);

$refund_id = ld_upsert_page('politica-de-devoluciones', 'Política de devoluciones', <<<'HTML'
<h2>Devoluciones</h2>
<p>Aceptamos solicitudes dentro de 30 días desde la recepción del pedido. El producto debe conservar su envase y no haber sido consumido, salvo que presente un problema de calidad.</p>
<h2>Cómo solicitarla</h2>
<p>Escribe a hola@ladespensa.cl con el número de pedido y una descripción del problema. Coordinaremos el retiro o la devolución del monto por el mismo medio de pago.</p>
HTML);

update_option('wp_page_for_privacy_policy', $privacy_id);
update_option('woocommerce_terms_page_id', $terms_id);
update_option('woocommerce_refund_returns_page_id', $refund_id);

update_option('woocommerce_bacs_settings', [
    'enabled' => 'yes',
    'title' => 'Transferencia bancaria',
    'description' => 'Transfiere el total y envía el comprobante. Preparamos el pedido al confirmar el pago.',
    'instructions' => 'Banco Estado · Cuenta corriente 000-1234-5678 · Titular La Despensa SpA · RUT 76.543.210-8. Envía el comprobante a hola@ladespensa.cl indicando el número de pedido.',
    'enable_for_methods' => [],
    'enable_for_virtual' => 'yes',
    'account_details' => '',
    'account_name' => 'La Despensa SpA',
    'account_number' => '000-1234-5678',
    'sort_code' => '',
    'bank_name' => 'Banco Estado',
    'iban' => '',
    'bic' => '',
]);

update_option('woocommerce_bacs_accounts', [[
    'account_name' => 'La Despensa SpA',
    'account_number' => '000-1234-5678',
    'bank_name' => 'Banco Estado',
    'sort_code' => '',
    'iban' => '',
    'bic' => '',
]]);

update_option('woocommerce_cod_settings', [
    'enabled' => 'yes',
    'title' => 'Pago contra entrega',
    'description' => 'Paga en efectivo al recibir tu pedido.',
    'instructions' => 'Paga el total al repartidor al momento de la entrega.',
    'enable_for_methods' => [],
    'enable_for_virtual' => 'no',
]);

update_option('woocommerce_email_from_name', 'La Despensa');
update_option('woocommerce_email_from_address', 'pedidos@ladespensa.cl');
update_option('woocommerce_email_base_color', '#4F5B45');
update_option('woocommerce_email_background_color', '#F7F1E6');
update_option('woocommerce_email_body_background_color', '#FFFDF8');
update_option('woocommerce_email_text_color', '#2B2721');
update_option('woocommerce_email_footer_text', 'La Despensa · Despacho a todo Chile · hola@ladespensa.cl');
update_option('woocommerce_checkout_privacy_policy_text', 'Usamos tus datos para procesar el pedido y mejorar la experiencia. Lee nuestra [privacy_policy].');

function ld_upsert_coupon(string $code, string $type, float $amount, bool $free_shipping, string $description): void
{
    $coupon_id = wc_get_coupon_id_by_code($code);
    $coupon = $coupon_id ? new WC_Coupon($coupon_id) : new WC_Coupon();

    $coupon->set_code($code);
    $coupon->set_discount_type($type);
    $coupon->set_amount($amount);
    $coupon->set_free_shipping($free_shipping);
    $coupon->set_description($description);
    $coupon->set_individual_use(false);
    $coupon->save();

    WP_CLI::log("Cupón: {$code}");
}

ld_upsert_coupon('BIENVENIDA10', 'percent', 10, false, '10% de descuento en la primera compra.');
ld_upsert_coupon('DESCUENTO2000', 'fixed_cart', 2000, false, '$2.000 de descuento en el carrito.');
ld_upsert_coupon('ENVIOGRATIS', 'fixed_cart', 0, true, 'Envío gratis sin mínimo de compra.');

WP_CLI::success('Pagos, cupones, correos y páginas legales configurados.');

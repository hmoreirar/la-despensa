<?php
/**
 * Configuración regional de WooCommerce para La Despensa.
 * Ejecutar con: make wp ARGS="eval-file /scripts/configure-woocommerce.php"
 */

if (!class_exists('WooCommerce')) {
    WP_CLI::error('WooCommerce no está activo.');
}

// Tienda en Chile, precios en CLP sin decimales.
update_option('woocommerce_store_address', '');
update_option('woocommerce_store_city', 'Santiago');
update_option('woocommerce_default_country', 'CL');
update_option('woocommerce_store_postcode', '');

update_option('woocommerce_currency', 'CLP');
update_option('woocommerce_currency_pos', 'left_space');
update_option('woocommerce_price_thousand_sep', '.');
update_option('woocommerce_price_decimal_sep', ',');
update_option('woocommerce_price_num_decimals', '0');

// IVA incluido en el precio de venta.
update_option('woocommerce_calc_taxes', 'yes');
update_option('woocommerce_prices_include_tax', 'yes');
update_option('woocommerce_tax_display_shop', 'incl');
update_option('woocommerce_tax_display_cart', 'incl');
update_option('woocommerce_tax_total_display', 'itemized');

// Venta y envío solo a Chile.
update_option('woocommerce_allowed_countries', 'specific');
update_option('woocommerce_specific_allowed_countries', ['CL']);
update_option('woocommerce_ship_to_countries', 'specific');
update_option('woocommerce_specific_ship_to_countries', ['CL']);

update_option('woocommerce_allow_tracking', 'no');
update_option('woocommerce_coming_soon', 'no');

// Tasa de IVA 19% para Chile, aplicada también al envío.
$existing_rates = WC_Tax::find_rates([
    'country' => 'CL',
    'state' => '',
    'postcode' => '',
    'city' => '',
    'tax_class' => '',
]);

if (empty($existing_rates)) {
    WC_Tax::_insert_tax_rate([
        'tax_rate_country' => 'CL',
        'tax_rate_state' => '',
        'tax_rate' => '19.0000',
        'tax_rate_name' => 'IVA',
        'tax_rate_priority' => 1,
        'tax_rate_compound' => 0,
        'tax_rate_shipping' => 1,
        'tax_rate_order' => 0,
        'tax_rate_class' => '',
    ]);
    WP_CLI::log('Tasa IVA 19% creada.');
} else {
    WP_CLI::log('Tasa IVA ya existe.');
}

// Zona de envío única para todo Chile.
$zone_name = 'Chile';
$zone_id = 0;

foreach (WC_Shipping_Zones::get_zones() as $zone_data) {
    if ($zone_data['zone_name'] === $zone_name) {
        $zone_id = (int) $zone_data['zone_id'];
        break;
    }
}

if (!$zone_id) {
    $new_zone = new WC_Shipping_Zone();
    $new_zone->set_zone_name($zone_name);
    $new_zone->set_locations([
        ['code' => 'CL', 'type' => 'country'],
    ]);
    $zone_id = $new_zone->save();
    WP_CLI::log('Zona de envío Chile creada.');
}

$zone = WC_Shipping_Zones::get_zone($zone_id);
$methods = [];

foreach ($zone->get_shipping_methods() as $method) {
    $methods[$method->id] = $method;
}

if (!isset($methods['flat_rate'])) {
    $instance_id = $zone->add_shipping_method('flat_rate');
    $method = WC_Shipping_Zones::get_shipping_method($instance_id);
    $method->instance_settings['title'] = 'Envío a todo Chile';
    $method->instance_settings['cost'] = '3990';
    update_option($method->get_instance_option_key(), $method->instance_settings);
    WP_CLI::log('Método tarifa plana creado.');
}

if (!isset($methods['free_shipping'])) {
    $instance_id = $zone->add_shipping_method('free_shipping');
    $method = WC_Shipping_Zones::get_shipping_method($instance_id);
    $method->instance_settings['title'] = 'Envío gratis';
    $method->instance_settings['requires'] = 'min_amount';
    $method->instance_settings['min_amount'] = '30000';
    update_option($method->get_instance_option_key(), $method->instance_settings);
    WP_CLI::log('Método envío gratis creado.');
}

// Páginas de WooCommerce en español.
$pages = [
    'woocommerce_shop_page_id' => ['Tienda', 'tienda'],
    'woocommerce_cart_page_id' => ['Carrito', 'carrito'],
    'woocommerce_checkout_page_id' => ['Finalizar compra', 'finalizar-compra'],
    'woocommerce_myaccount_page_id' => ['Mi cuenta', 'mi-cuenta'],
];

foreach ($pages as $option => $data) {
    $page_id = (int) get_option($option);
    if (!$page_id) {
        continue;
    }

    wp_update_post([
        'ID' => $page_id,
        'post_title' => $data[0],
        'post_name' => $data[1],
    ]);
}

WP_CLI::success('Configuración de WooCommerce aplicada.');

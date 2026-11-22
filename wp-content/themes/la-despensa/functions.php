<?php
/**
 * Funciones del tema hijo La Despensa.
 */

defined('ABSPATH') || exit;

require_once get_stylesheet_directory() . '/inc/checkout.php';

/**
 * El tema usa su propio enlace de carrito; se evita el mini-carrito automático.
 */
add_filter('hooked_block_types', function (array $hooked_blocks): array {
    return array_values(array_filter(
        $hooked_blocks,
        static fn (string $block): bool => $block !== 'woocommerce/mini-cart'
    ));
});

add_action('wp_enqueue_scripts', function (): void {
    $stylesheet = get_stylesheet_directory() . '/style.css';
    $dependencies = wp_style_is('twentytwentyfive-style', 'registered')
        ? ['twentytwentyfive-style']
        : [];

    wp_enqueue_style(
        'la-despensa',
        get_stylesheet_uri(),
        $dependencies,
        (string) filemtime($stylesheet)
    );

    $script = get_stylesheet_directory() . '/assets/js/header-cart.js';

    wp_enqueue_script(
        'la-despensa-header',
        get_stylesheet_directory_uri() . '/assets/js/header-cart.js',
        [],
        (string) filemtime($script),
        true
    );
}, 20);

/**
 * Enlace al carrito con contador.
 */
function ld_cart_link_html(): string
{
    if (!function_exists('wc_get_cart_url')) {
        return '';
    }

    $count = function_exists('WC') && WC()->cart
        ? WC()->cart->get_cart_contents_count()
        : 0;

    $label = sprintf(
        _n('%d producto en el carrito', '%d productos en el carrito', $count, 'la-despensa'),
        $count
    );

    return sprintf(
        '<a class="ld-cart" href="%s" aria-label="%s"><span class="ld-cart__label">Carrito</span><span class="ld-cart__count" aria-hidden="true">%d</span></a>',
        esc_url(wc_get_cart_url()),
        esc_attr('Carrito, ' . $label),
        $count
    );
}

add_shortcode('ld_carrito', 'ld_cart_link_html');

add_action('init', function (): void {
    register_block_type('ladespensa/carrito', [
        'render_callback' => 'ld_cart_link_html',
    ]);

    register_block_type('ladespensa/categorias', [
        'render_callback' => 'ld_categories_html',
    ]);

    register_block_type('ladespensa/destacados', [
        'render_callback' => 'ld_featured_products_html',
    ]);

    register_block_type('ladespensa/imagen-producto', [
        'attributes' => [
            'sku' => ['type' => 'string', 'default' => ''],
            'caption' => ['type' => 'string', 'default' => ''],
        ],
        'render_callback' => 'ld_product_image_html',
    ]);
});

add_filter('woocommerce_add_to_cart_fragments', function (array $fragments): array {
    if (!function_exists('WC') || !WC()->cart) {
        return $fragments;
    }

    $fragments['.ld-cart__count'] = sprintf(
        '<span class="ld-cart__count" aria-hidden="true">%d</span>',
        WC()->cart->get_cart_contents_count()
    );

    return $fragments;
});

/**
 * Grilla de categorías de producto.
 */
function ld_categories_html(): string
{
    if (!taxonomy_exists('product_cat')) {
        return '';
    }

    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'parent' => 0,
        'orderby' => 'term_id',
        'order' => 'ASC',
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return '';
    }

    $output = '<ul class="ld-categories">';

    foreach ($terms as $term) {
        $count = (int) $term->count;

        $output .= '<li class="ld-categories__item">';
        $output .= '<a href="' . esc_url(get_term_link($term)) . '">';
        $output .= '<span class="ld-categories__name">' . esc_html($term->name) . '</span>';
        $output .= '<span class="ld-categories__count">' . esc_html(sprintf(
            _n('%d producto', '%d productos', $count, 'la-despensa'),
            $count
        )) . '</span>';
        $output .= '</a></li>';
    }

    $output .= '</ul>';

    return $output;
}

add_shortcode('ld_categorias', 'ld_categories_html');

/**
 * Productos recientes en tarjetas propias.
 */
function ld_featured_products_html(): string
{
    if (!function_exists('wc_get_products')) {
        return '';
    }

    $products = wc_get_products([
        'status' => 'publish',
        'limit' => 8,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    if (empty($products)) {
        return '';
    }

    $output = '<ul class="ld-products">';

    foreach ($products as $product) {
        $permalink = get_permalink($product->get_id());
        $category_names = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
        $category = !is_wp_error($category_names) && !empty($category_names) ? $category_names[0] : '';

        if ($product->is_type('variable')) {
            $price = sprintf(
                /* translators: %s: precio mínimo. */
                __('Desde %s', 'la-despensa'),
                wc_price((float) $product->get_variation_price('min', 'edit'))
            );
        } else {
            $price = wc_price((float) $product->get_price());
        }

        $output .= '<li class="ld-product">';
        $output .= '<a class="ld-product__media" href="' . esc_url($permalink) . '">';
        $output .= $product->get_image('large', [
            'class' => 'ld-product__img',
            'loading' => 'lazy',
            'sizes' => '(max-width: 360px) 100vw, (max-width: 600px) 50vw, (max-width: 1024px) 33vw, 25vw',
        ]);

        if (!$product->is_in_stock()) {
            $output .= '<span class="ld-product__badge">' . esc_html__('Agotado', 'la-despensa') . '</span>';
        }

        $output .= '</a>';
        $output .= '<div class="ld-product__body">';

        if ($category) {
            $output .= '<span class="ld-product__cat">' . esc_html($category) . '</span>';
        }

        $output .= '<h3 class="ld-product__title"><a href="' . esc_url($permalink) . '">' . esc_html($product->get_name()) . '</a></h3>';
        $output .= '<span class="ld-product__price">' . wp_kses_post($price) . '</span>';
        $output .= '<a class="ld-product__cta" href="' . esc_url($permalink) . '">' . esc_html__('Ver producto', 'la-despensa') . '</a>';
        $output .= '</div>';
        $output .= '</li>';
    }

    $output .= '</ul>';

    return $output;
}

add_shortcode('ld_destacados', 'ld_featured_products_html');

/**
 * Imagen de producto para la portada.
 */
function ld_product_image_html(array $attributes): string
{
    if (!function_exists('wc_get_product_id_by_sku')) {
        return '';
    }

    $attributes = shortcode_atts([
        'sku' => '',
        'caption' => '',
    ], $attributes, 'ld_imagen_producto');

    $product_id = wc_get_product_id_by_sku($attributes['sku']);

    if (!$product_id) {
        return '';
    }

    $product = wc_get_product($product_id);
    $image_id = $product ? $product->get_image_id() : 0;

    if (!$image_id) {
        return '';
    }

    $image = wp_get_attachment_image($image_id, 'large', false, [
        'class' => 'ld-hero__img',
        'loading' => 'eager',
        'fetchpriority' => 'high',
        'decoding' => 'async',
        'sizes' => '(max-width: 600px) 100vw, (max-width: 1024px) 50vw, 440px',
    ]);

    $caption = $attributes['caption'] ?: $product->get_name();

    return '<figure class="ld-hero__figure">' . $image
        . '<figcaption>' . esc_html($caption) . '</figcaption></figure>';
}

add_shortcode('ld_imagen_producto', 'ld_product_image_html');

/**
 * Precarga la imagen del hero para mejorar LCP en la portada.
 */
add_action('wp_head', function (): void {
    if (!is_front_page() || !function_exists('wc_get_product_id_by_sku')) {
        return;
    }

    $product_id = wc_get_product_id_by_sku('LD-ACE-001');
    $product = $product_id ? wc_get_product($product_id) : null;
    $image_id = $product ? $product->get_image_id() : 0;

    if (!$image_id) {
        return;
    }

    $src = wp_get_attachment_image_src($image_id, 'large');
    $srcset = wp_get_attachment_image_srcset($image_id, 'large');

    if (!$src) {
        return;
    }

    printf(
        '<link rel="preload" as="image" href="%s" imagesrcset="%s" imagesizes="%s" fetchpriority="high">' . "\n",
        esc_url($src[0]),
        esc_attr($srcset ?: $src[0]),
        esc_attr('(max-width: 600px) 100vw, (max-width: 1024px) 50vw, 440px')
    );
}, 2);


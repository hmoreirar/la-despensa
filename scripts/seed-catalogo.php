<?php
/**
 * Catálogo demo de La Despensa.
 * Idempotente por SKU: se puede ejecutar varias veces.
 * Uso: make catalogo
 */

if (!class_exists('WooCommerce')) {
    WP_CLI::error('WooCommerce no está activo.');
}

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function ld_ensure_category(string $name, string $slug, string $description): int
{
    $term = term_exists($slug, 'product_cat');

    if (!$term) {
        $term = wp_insert_term($name, 'product_cat', [
            'slug' => $slug,
            'description' => $description,
        ]);
    }

    if (is_wp_error($term)) {
        WP_CLI::error($term->get_error_message());
    }

    return (int) (is_array($term) ? $term['term_id'] : $term);
}

function ld_ensure_attribute(string $name, string $slug, array $terms): int
{
    $attribute_id = (int) wc_attribute_taxonomy_id_by_name($slug);

    if (!$attribute_id) {
        $created = wc_create_attribute([
            'name' => $name,
            'slug' => $slug,
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => false,
        ]);

        if (is_wp_error($created)) {
            WP_CLI::error($created->get_error_message());
        }

        $attribute_id = (int) $created;
    }

    $taxonomy = wc_attribute_taxonomy_name($slug);

    if (!taxonomy_exists($taxonomy)) {
        register_taxonomy($taxonomy, ['product'], [
            'hierarchical' => false,
            'show_ui' => false,
            'query_var' => true,
            'rewrite' => false,
        ]);
    }

    foreach ($terms as $label => $term_slug) {
        if (!term_exists($term_slug, $taxonomy)) {
            $inserted = wp_insert_term($label, $taxonomy, ['slug' => $term_slug]);

            if (is_wp_error($inserted)) {
                WP_CLI::error($inserted->get_error_message());
            }
        }
    }

    return $attribute_id;
}

function ld_assign_image(WC_Product $product, string $sku): void
{
    $candidates = [];

    foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
        $candidate = "/assets/productos/{$sku}.{$extension}";

        if (file_exists($candidate)) {
            $candidates[] = $candidate;
        }
    }

    if (empty($candidates)) {
        WP_CLI::warning("Sin imagen para {$sku}.");
        return;
    }

    usort($candidates, static function (string $left, string $right): int {
        return filemtime($right) <=> filemtime($left);
    });

    $source = $candidates[0];

    $hash = md5_file($source);
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'meta_key' => '_ladespensa_sku',
        'meta_value' => $sku,
        'numberposts' => 1,
        'fields' => 'ids',
    ]);

    $attachment_id = $existing ? (int) $existing[0] : 0;

    if ($attachment_id) {
        if (get_post_meta($attachment_id, '_ladespensa_hash', true) === $hash) {
            $product->set_image_id($attachment_id);
            $product->save();
            return;
        }

        wp_delete_attachment($attachment_id, true);
    }

    $temporary = wp_tempnam(basename($source));

    if (!copy($source, $temporary)) {
        WP_CLI::warning("No se pudo copiar la imagen de {$sku}.");
        return;
    }

    $attachment_id = media_handle_sideload([
        'name' => basename($source),
        'tmp_name' => $temporary,
    ], $product->get_id());

    if (is_wp_error($attachment_id)) {
        @unlink($temporary);
        WP_CLI::warning("Imagen de {$sku}: " . $attachment_id->get_error_message());
        return;
    }

    update_post_meta($attachment_id, '_ladespensa_sku', $sku);
    update_post_meta($attachment_id, '_ladespensa_hash', $hash);
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $product->get_name());

    $product->set_image_id($attachment_id);
    $product->save();
}

function ld_save_simple(array $item, int $category_id): void
{
    $existing_id = wc_get_product_id_by_sku($item['sku']);
    $product = $existing_id ? wc_get_product($existing_id) : new WC_Product_Simple();

    if (!$product instanceof WC_Product_Simple) {
        WP_CLI::error("El SKU {$item['sku']} existe pero no es un producto simple.");
    }

    $product->set_name($item['name']);
    $product->set_sku($item['sku']);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_short_description($item['short']);
    $product->set_description($item['description']);
    $product->set_regular_price($item['price']);
    $product->set_manage_stock(true);
    $product->set_stock_quantity($item['stock']);
    $product->set_low_stock_amount(5);
    $product->set_stock_status('instock');
    $product->set_tax_status('taxable');
    $product->set_category_ids([$category_id]);
    $product->save();

    ld_assign_image($product, $item['sku']);
    WP_CLI::log("Producto simple: {$item['name']} ({$item['sku']})");
}

function ld_save_variable(array $item, int $category_id, int $attribute_id, string $taxonomy): void
{
    $existing_id = wc_get_product_id_by_sku($item['sku']);
    $product = $existing_id ? wc_get_product($existing_id) : new WC_Product_Variable();

    if (!$product instanceof WC_Product_Variable) {
        WP_CLI::error("El SKU {$item['sku']} existe pero no es un producto variable.");
    }

    $product->set_name($item['name']);
    $product->set_sku($item['sku']);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_short_description($item['short']);
    $product->set_description($item['description']);
    $product->set_manage_stock(false);
    $product->set_stock_status('instock');
    $product->set_tax_status('taxable');
    $product->set_category_ids([$category_id]);

    $attribute = new WC_Product_Attribute();
    $attribute->set_id($attribute_id);
    $attribute->set_name($taxonomy);
    $attribute->set_options(array_column($item['variations'], 'option'));
    $attribute->set_position(0);
    $attribute->set_visible(true);
    $attribute->set_variation(true);
    $product->set_attributes([$attribute]);
    $product->save();

    foreach ($item['variations'] as $variation_item) {
        $variation_id = wc_get_product_id_by_sku($variation_item['sku']);
        $variation = $variation_id ? wc_get_product($variation_id) : new WC_Product_Variation();

        if (!$variation instanceof WC_Product_Variation) {
            WP_CLI::error("El SKU {$variation_item['sku']} no es una variación.");
        }

        $variation->set_parent_id($product->get_id());
        $variation->set_attributes([$taxonomy => $variation_item['option']]);
        $variation->set_regular_price($variation_item['price']);
        $variation->set_manage_stock(true);
        $variation->set_stock_quantity($variation_item['stock']);
        $variation->set_low_stock_amount(5);
        $variation->set_stock_status('instock');
        $variation->set_sku($variation_item['sku']);
        $variation->save();
    }

    WC_Product_Variable::sync($product->get_id());
    ld_assign_image($product, $item['sku']);
    WP_CLI::log("Producto variable: {$item['name']} ({$item['sku']})");
}

$categories = [
    'aceites-y-condimentos' => [
        'Aceites y condimentos',
        'Aceites de oliva, sales de mar, merkén y condimentos artesanales.',
    ],
    'mermeladas-y-conservas' => [
        'Mermeladas y conservas',
        'Mermeladas cocidas a fuego lento y conservas de temporada.',
    ],
    'cafe-y-te' => [
        'Café y té',
        'Café de especialidad, tés e infusiones de hoja suelta.',
    ],
    'productos-artesanales' => [
        'Productos artesanales',
        'Piezas de greda y madera hechas a mano por artesanos chilenos.',
    ],
    'panaderia-y-reposteria' => [
        'Panadería y repostería',
        'Pan de masa madre, galletas y dulces tradicionales.',
    ],
];

$category_ids = [];

foreach ($categories as $slug => $data) {
    $category_ids[$slug] = ld_ensure_category($data[0], $slug, $data[1]);
}

$tamano_id = ld_ensure_attribute('Tamaño', 'tamano', [
    '250 ml' => '250-ml',
    '500 ml' => '500-ml',
    '250 g' => '250-g',
    '1 kg' => '1-kg',
]);

$sabor_id = ld_ensure_attribute('Sabor', 'sabor', [
    'Frutilla' => 'frutilla',
    'Frambuesa' => 'frambuesa',
    'Higo' => 'higo',
]);

$simple_products = [
    [
        'sku' => 'LD-SAL-001',
        'name' => 'Sal de mar de Cahuil',
        'category' => 'aceites-y-condimentos',
        'price' => '2490',
        'stock' => 30,
        'short' => 'Sal de mar cosechada artesanalmente en la costa de Cahuil.',
        'description' => 'Sal de mar sin refinar, de grano grueso y sabor limpio. Ideal para terminar carnes, pescados y ensaladas.',
    ],
    [
        'sku' => 'LD-MER-001',
        'name' => 'Merkén artesanal',
        'category' => 'aceites-y-condimentos',
        'price' => '3290',
        'stock' => 25,
        'short' => 'Merkén ahumado molido a piedra en la Araucanía.',
        'description' => 'Ají cacho de cabra ahumado y molido a piedra. Aroma intenso, picante moderado, ideal para legumbres y carnes.',
    ],
    [
        'sku' => 'LD-CON-001',
        'name' => 'Conserva de tomate cherry',
        'category' => 'mermeladas-y-conservas',
        'price' => '3990',
        'stock' => 18,
        'short' => 'Tomates cherry en conserva artesanal con albahaca.',
        'description' => 'Tomates cherry de temporada en salmuera suave con albahaca fresca. Perfectos para tablas, pastas y bruschettas.',
    ],
    [
        'sku' => 'LD-CAF-002',
        'name' => 'Café molido',
        'category' => 'cafe-y-te',
        'price' => '7990',
        'stock' => 12,
        'short' => 'Café de especialidad tostado en pequeños lotes.',
        'description' => 'Mezcla de origen chileno tostada en lotes pequeños y molida media. Notas a chocolate y frutos secos.',
    ],
    [
        'sku' => 'LD-TE-001',
        'name' => 'Té negro Ceylán',
        'category' => 'cafe-y-te',
        'price' => '3990',
        'stock' => 20,
        'short' => 'Té negro de hoja suelta, aroma intenso y cuerpo equilibrado.',
        'description' => 'Té negro Ceylán de hoja entera. Infusión ámbar, con notas maltosas y un final limpio.',
    ],
    [
        'sku' => 'LD-INF-001',
        'name' => 'Infusión de hierbas',
        'category' => 'cafe-y-te',
        'price' => '3490',
        'stock' => 22,
        'short' => 'Mezcla de manzanilla, menta y cedrón.',
        'description' => 'Hierbas secas seleccionadas para una infusión suave y aromática. Sin saborizantes ni colorantes.',
    ],
    [
        'sku' => 'LD-ART-001',
        'name' => 'Bowl de greda',
        'category' => 'productos-artesanales',
        'price' => '8990',
        'stock' => 8,
        'short' => 'Bowl de greda cocida hecho a mano en Pomaire.',
        'description' => 'Pieza única de greda modelada y cocida artesanalmente. Apta para servir, no para microondas.',
    ],
    [
        'sku' => 'LD-ART-002',
        'name' => 'Tabla de madera nativa',
        'category' => 'productos-artesanales',
        'price' => '12990',
        'stock' => 6,
        'short' => 'Tabla de madera nativa con terminación en aceite vegetal.',
        'description' => 'Tabla de madera nativa de veta visible, lijada y terminada con aceite vegetal. Para servir quesos y charcutería.',
    ],
    [
        'sku' => 'LD-ART-003',
        'name' => 'Set de cucharas de madera',
        'category' => 'productos-artesanales',
        'price' => '5990',
        'stock' => 10,
        'short' => 'Dos cucharas de madera talladas a mano.',
        'description' => 'Set de dos cucharas de madera de distinto tamaño, talladas y pulidas a mano. Uso diario en cocina.',
    ],
    [
        'sku' => 'LD-PAN-001',
        'name' => 'Pan de masa madre',
        'category' => 'panaderia-y-reposteria',
        'price' => '5490',
        'stock' => 10,
        'short' => 'Pan de masa madre con fermentación lenta de 24 horas.',
        'description' => 'Pan de campo con corteza dorada y miga alveolada. Fermentación natural de 24 horas, solo harina, agua, sal y masa madre.',
    ],
    [
        'sku' => 'LD-PAN-002',
        'name' => 'Galletas de avena',
        'category' => 'panaderia-y-reposteria',
        'price' => '3490',
        'stock' => 20,
        'short' => 'Galletas de avena con mantequilla, receta casera.',
        'description' => 'Galletas crocantes de avena y mantequilla, horneadas en pequeños lotes. Paquete de 200 g.',
    ],
    [
        'sku' => 'LD-PAN-003',
        'name' => 'Queque de manjar',
        'category' => 'panaderia-y-reposteria',
        'price' => '7990',
        'stock' => 8,
        'short' => 'Queque de manjar con bizcocho de vainilla.',
        'description' => 'Bizcocho de vainilla relleno y cubierto con manjar artesanal. Formato de 500 g, ideal para once.',
    ],
    [
        'sku' => 'LD-PAN-004',
        'name' => 'Alfajores artesanales',
        'category' => 'panaderia-y-reposteria',
        'price' => '4990',
        'stock' => 14,
        'short' => 'Seis alfajores de dulce de leche con azúcar flor.',
        'description' => 'Alfajores de masa suave rellenos con dulce de leche y espolvoreados con azúcar flor. Caja de seis unidades.',
    ],
];

foreach ($simple_products as $item) {
    ld_save_simple($item, $category_ids[$item['category']]);
}

$variable_products = [
    [
        'sku' => 'LD-ACE-001',
        'name' => 'Aceite de oliva extra virgen',
        'category' => 'aceites-y-condimentos',
        'short' => 'Aceite de oliva extra virgen del Valle del Maule, prensado en frío.',
        'description' => 'Aceite de oliva extra virgen de olivos del Valle del Maule. Prensado en frío, acidez baja y notas herbáceas.',
        'variations' => [
            ['sku' => 'LD-ACE-001-250', 'option' => '250-ml', 'price' => '5490', 'stock' => 20],
            ['sku' => 'LD-ACE-001-500', 'option' => '500-ml', 'price' => '8990', 'stock' => 20],
        ],
    ],
    [
        'sku' => 'LD-MERM-001',
        'name' => 'Mermelada artesanal',
        'category' => 'mermeladas-y-conservas',
        'short' => 'Mermelada cocida a fuego lento, sin conservantes.',
        'description' => 'Mermelada de fruta cocida a fuego lento con azúcar y limón. Sin conservantes ni colorantes. Elige tu sabor.',
        'variations' => [
            ['sku' => 'LD-MERM-001-FRU', 'option' => 'frutilla', 'price' => '4290', 'stock' => 15],
            ['sku' => 'LD-MERM-001-FRA', 'option' => 'frambuesa', 'price' => '4490', 'stock' => 15],
            ['sku' => 'LD-MERM-001-HIG', 'option' => 'higo', 'price' => '4690', 'stock' => 15],
        ],
    ],
    [
        'sku' => 'LD-CAF-001',
        'name' => 'Café de especialidad en grano',
        'category' => 'cafe-y-te',
        'short' => 'Café de especialidad tostado en grano, origen Chile.',
        'description' => 'Café de especialidad tostado en pequeños lotes. Notas a chocolate, caramelo y frutos secos. Disponible en 250 g y 1 kg.',
        'variations' => [
            ['sku' => 'LD-CAF-001-250', 'option' => '250-g', 'price' => '7990', 'stock' => 12],
            ['sku' => 'LD-CAF-001-1K', 'option' => '1-kg', 'price' => '26990', 'stock' => 8],
        ],
    ],
];

foreach ($variable_products as $item) {
    $attribute_id = $item['sku'] === 'LD-MERM-001' ? $sabor_id : $tamano_id;
    $taxonomy = $item['sku'] === 'LD-MERM-001' ? 'pa_sabor' : 'pa_tamano';
    ld_save_variable($item, $category_ids[$item['category']], $attribute_id, $taxonomy);
}

WP_CLI::success('Catálogo demo cargado.');

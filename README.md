# La Despensa

E-commerce gastronómico desarrollado con WordPress y WooCommerce.

Tienda ficticia de productos chilenos: aceites y condimentos, mermeladas y
conservas, café y té, productos artesanales, panadería y repostería.
Moneda CLP, idioma español, mercado Chile.

## Estado

- Etapa 0: infraestructura Docker y WordPress base. Completada.
- Etapa 1: configuración de WordPress y limpieza de contenido demo. Completada.
- Etapa 2: WooCommerce y configuración regional. Completada.
- Etapa 3: catálogo de productos. Completada.
- Etapa 4: tema hijo e identidad visual. Completada.
- Etapa 5: flujo de compra, pagos de prueba y correos. Completada.
- Etapa 6: personalización PHP de checkout y carrito. Completada.
- Etapa 7: pruebas y documentación final. Completada.
- Proyecto completo en entorno local.

## Requisitos

- Docker Engine y Docker Compose v2 o superior.
- Git.
- Puerto 8080 libre.
- No se requiere PHP, Composer ni WP-CLI en el host.

## Arranque

```bash
cp .env.example .env
# Edita .env con tus propias contraseñas
make up
make init
make tema
make woocommerce
make catalogo
make checkout
```

Sitio: http://localhost:8080
Panel: http://localhost:8080/wp-admin

El usuario y la contraseña del administrador están en `.env`
(`WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`). `.env` no se versiona.

## Comandos

```bash
make help
make ps
make logs
make wp ARGS="plugin list"
make shell
make db-dump
make checkout
make mail
```

`make init`, `make tema`, `make woocommerce`, `make catalogo` y `make checkout`
son idempotentes: se pueden repetir sin duplicar configuración ni productos.
`make reset` elimina contenedores y volúmenes, incluida la base de datos.
Pide confirmación escrita.

Mailpit recibe todos los correos del sitio en http://localhost:8025.
No se envía correo a direcciones reales en desarrollo.

## Estructura

```
.
├── docker-compose.yml        Servicios db, wordpress y wpcli
├── docker/
│   ├── wordpress/Dockerfile  Remapea www-data al UID/GID del host
│   └── php/uploads.ini       Límites de subida de PHP
├── scripts/
│   ├── init-wordpress.sh          Instalación base idempotente
│   ├── configure-woocommerce.php  Moneda, impuestos, envíos y URLs
│   ├── configure-checkout.php     Pagos, cupones, correos y legales
│   ├── seed-catalogo.php          Categorías, atributos y productos
│   └── generar-placeholders.sh    Imágenes demo con ImageMagick
├── assets/productos/              Imágenes fuente, versionadas
├── wp-content/               Bind mount versionado
│   ├── themes/la-despensa/   Tema hijo, plantillas y estilos
│   │   ├── inc/checkout.php  RUT y aviso de envío gratis
│   │   └── assets/js/        Contador y progreso de carrito
│   ├── plugins/
│   ├── mu-plugins/
│   │   └── dev-mailpit.php   SMTP local hacia Mailpit
│   └── uploads/              Ignorado por Git
├── backups/                  Respaldos SQL, ignorados por Git
├── Makefile
├── .env.example
└── docker-compose.yml
```

Volúmenes nombrados:

- `db_data`: datos de MariaDB.
- `wp_data`: core de WordPress y `wp-config.php`.

`wp-content` vive en el host para editarlo con tu editor y versionarlo con Git.

## Decisiones técnicas

- WordPress 7.1.5 sobre PHP 8.4 Apache.
- MariaDB 11.8 LTS.
- Imagen propia de WordPress para que los archivos creados por Apache
  pertenezcan al usuario del host. Evita problemas de permisos en uploads.
- WP-CLI en contenedor aparte, perfil `tools`. No se instala nada en el host.
- WooCommerce es el único plugin, instalado con WP-CLI. El directorio del
  plugin no se versiona; `make woocommerce` lo reinstala.
- `WP_DEBUG` activo con log en `wp-content/debug.log`.

## Configuración de WooCommerce

- País de tienda: Chile. Venta y envío solo a Chile.
- Moneda: CLP, 0 decimales, formato `$ 1.990`.
- Impuestos: IVA 19% incluido en el precio, aplicado también al envío.
- Envío: zona única Chile, tarifa plana $3.990, gratis desde $30.000.
  WooCommerce ingresa el costo del envío sin IVA: la tarifa se guarda como
  $3.353 neto y el cliente paga $3.990 final.
- Páginas: Tienda, Carrito, Finalizar compra, Mi cuenta.
- Carrito y checkout usan bloques nativos de WooCommerce.
- Sin seguimiento de uso (`woocommerce_allow_tracking=no`).

## Catálogo

- 16 productos: 13 simples y 3 variables.
- 5 categorías: Aceites y condimentos, Mermeladas y conservas, Café y té,
  Productos artesanales, Panadería y repostería.
- Atributos globales: Tamaño y Sabor.
- Inventario activo en todos los productos, umbral de stock bajo en 5.
- SKU con prefijo `LD-` y variantes por tamaño o sabor.
- URLs: `/producto/<slug>/` y `/categoria-producto/<slug>/`.
- Imágenes en `assets/productos/`, en WebP e importadas a la biblioteca de
  medios. Son fotografías de Unsplash con licencia libre; créditos en
  `assets/productos/CREDITS.md`.

## Diseño

- Tema hijo `la-despensa` de Twenty Twenty-Five.
- Paleta cálida: crema, arena, oliva, terracota y carbón.
- Tipografías self-hosted: Fraunces para títulos, Inter para texto, precios y
  botones. Archivos en `wp-content/themes/la-despensa/assets/fonts/`.
- Plantillas propias: portada, encabezado, pie de página y 404.
- Bloques dinámicos del tema para categorías, productos destacados, imagen de
  portada y contador del carrito.
- Encabezado fijo, menú móvil y contador de carrito que se actualiza tras
  agregar o quitar productos.
- Carrito y checkout usan los bloques de WooCommerce con estilos propios.
  El checkout conserva el encabezado mínimo de WooCommerce.
- Breakpoints revisados en 360, 390, 768, 1024 y 1440 px.

## Optimización de imágenes

- 16 fotos WebP de 1024x1024, calidad 78.
- `sizes` explícito en tarjetas y hero.
- Precarga de la imagen principal en la portada con `imagesrcset` y
  `fetchpriority="high"`.
- Sin plugin de optimización ni CDN externo.
- Portada: 560 KB a 451 KB; imágenes de 261 KB a 152 KB.
- Tienda: 1026 KB a 555 KB.
- LCP local medido: 904 ms en portada y 956 ms en tienda.
- Se reemplazaron las fotos de café molido, queque de manjar y alfajores por
  imágenes más cercanas al producto.

## Compra, pagos y correos

- Pagos nativos de prueba:
  - Transferencia bancaria: pedido queda `on-hold` hasta confirmar el pago.
  - Pago contra entrega: pedido pasa a `processing`.
- Cupones:
  - `BIENVENIDA10`: 10% de descuento.
  - `DESCUENTO2000`: $2.000 fijo.
  - `ENVIOGRATIS`: envío sin costo.
- Páginas publicadas: Política de privacidad, Términos y condiciones y
  Política de devoluciones. Están enlazadas desde el footer y el checkout.
- Correos transaccionales con Mailpit en http://localhost:8025.
  El mu-plugin `dev-mailpit.php` desvía PHPMailer a SMTP local.
- Pedidos de prueba 63 (contra entrega), 64 (transferencia con cupón) y
  67 (contra entrega con RUT).

## Personalización de checkout

- Campo RUT obligatorio en el checkout por bloques, registrado con
  `woocommerce_register_additional_checkout_field`.
- Normalización y validación de RUT chileno con módulo 11.
- El RUT queda en el pedido, se ve en la confirmación, en el admin y en los
  correos transaccionales.
- Aviso de envío gratis en el carrito con barra de progreso.
  Se actualiza por AJAX al cambiar cantidades.
- Al alcanzar el mínimo o usar el cupón `ENVIOGRATIS`, se elimina la tarifa
  plana y queda solo envío gratis.
- Hooks usados: `woocommerce_register_additional_checkout_field`,
  `woocommerce_package_rates`, `render_block`, `wp_ajax_*` y
  `woocommerce_admin_order_data_after_billing_address`.

## Verificación

```bash
curl -I http://localhost:8080
make wp ARGS="core version"
make wp ARGS="option get WPLANG"
```

## Pruebas realizadas

| Área | Resultado |
|---|---|
| Catálogo | 16 productos, 7 variaciones, 5 categorías |
| Imágenes | 16 fotos WebP importadas y asignadas |
| Peso | Portada 451 KB, tienda 555 KB |
| Precios e IVA | CLP sin decimales, IVA 19% incluido |
| Stock | Inventario descontado por pedido |
| Cupones | 10%, monto fijo y envío gratis |
| Envío | Tarifa plana y gratis desde $30.000 |
| Envío gratis | Tarifa plana se elimina al calificar |
| Checkout | RUT validado, pagos y términos |
| Pagos | Transferencia y contra entrega |
| Pedidos | 63, 64 y 67 conservados como demo |
| Correos | 8 mensajes recibidos en Mailpit |
| Legal | Privacidad, términos y devoluciones publicadas |
| Enlaces internos | 43 revisados, sin errores 4xx |
| Responsive | 360, 390, 768, 1024 y 1440 sin overflow |
| Teclado | Menú móvil abre con Enter y cierra con Escape |
| 404 | Página propia publicada |
| Respaldo | `make db-dump` genera SQL con 48 tablas |
| Consola | Sin errores de JavaScript |
| `debug.log` | Sin errores ni avisos |

No se ejecutó `make reset` ni una instalación desde cero. Los pedidos de
prueba se conservan como datos de demostración.

## Limitaciones

- Las fotografías vienen de Unsplash y no representan exactamente los
  productos ficticios.
- Los pagos son métodos de prueba. No hay integración con Webpay ni pasarela
  real.
- Mailpit captura los correos en local; no se envían a direcciones reales.
- No hay integración con transportistas ni cálculo de envío por comuna.
- El sitio está configurado con `blog_public=0` para desarrollo.

## Etapas

0. Infraestructura, WordPress base y Git.
1. Configuración de WordPress y limpieza de contenido demo.
2. WooCommerce y configuración regional.
3. Catálogo de productos.
4. Tema hijo e identidad visual.
5. Flujo de compra.
6. Personalización PHP con hooks.
7. Pruebas y documentación final.

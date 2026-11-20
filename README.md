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
- Siguiente: Etapa 5, flujo de compra.

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
make woocommerce
make catalogo
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
```

`make init`, `make woocommerce` y `make catalogo` son idempotentes: se pueden
repetir sin duplicar configuración ni productos. `make reset` elimina
contenedores y volúmenes, incluida la base de datos. Pide confirmación escrita.

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
│   ├── seed-catalogo.php          Categorías, atributos y productos
│   └── generar-placeholders.sh    Imágenes demo con ImageMagick
├── assets/productos/              Imágenes fuente, versionadas
├── wp-content/               Bind mount versionado
│   ├── themes/la-despensa/   Tema hijo, plantillas y estilos
│   ├── plugins/
│   ├── mu-plugins/
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
- Imágenes demo en `assets/productos/`, importadas a la biblioteca de medios.
  Son placeholders neutros; se pueden reemplazar por fotos reales usando el
  mismo nombre de archivo y volviendo a ejecutar `make catalogo`.

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

## Verificación

```bash
curl -I http://localhost:8080
make wp ARGS="core version"
make wp ARGS="option get WPLANG"
```

## Etapas

0. Infraestructura, WordPress base y Git.
1. Configuración de WordPress y limpieza de contenido demo.
2. WooCommerce y configuración regional.
3. Catálogo de productos.
4. Tema hijo e identidad visual.
5. Flujo de compra.
6. Personalización PHP con hooks.
7. Pruebas y documentación final.

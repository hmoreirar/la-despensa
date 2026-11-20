# La Despensa

E-commerce gastronómico desarrollado con WordPress y WooCommerce.

Tienda ficticia de productos chilenos: aceites y condimentos, mermeladas y
conservas, café y té, productos artesanales, panadería y repostería.
Moneda CLP, idioma español, mercado Chile.

## Estado

- Etapa 0: infraestructura Docker y WordPress base. Completada.
- Siguiente: Etapa 1, configuración de WordPress y limpieza de contenido demo.

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

`make reset` elimina contenedores y volúmenes, incluida la base de datos.
Pide confirmación escrita.

## Estructura

```
.
├── docker-compose.yml        Servicios db, wordpress y wpcli
├── docker/
│   ├── wordpress/Dockerfile  Remapea www-data al UID/GID del host
│   └── php/uploads.ini       Límites de subida de PHP
├── scripts/
│   └── init-wordpress.sh     Instalación idempotente con WP-CLI
├── wp-content/               Bind mount versionado
│   ├── themes/
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
- Un solo plugin por ahora: WooCommerce se agrega en la etapa 2.
- `WP_DEBUG` activo con log en `wp-content/debug.log`.

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

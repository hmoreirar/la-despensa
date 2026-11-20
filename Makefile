SHELL := /bin/bash
COMPOSE := docker compose
WPCLI := $(COMPOSE) --profile tools run --rm wpcli

.PHONY: help up down stop restart logs ps init woocommerce catalogo checkout mail wp shell db-dump reset

help:
	@echo "La Despensa - comandos disponibles"
	@echo ""
	@echo "  make up        Construye y levanta los servicios, espera healthchecks"
	@echo "  make init      Instala WordPress en español (idempotente)"
	@echo "  make woocommerce  Instala y configura WooCommerce (idempotente)"
	@echo "  make catalogo  Carga categorías, atributos y productos demo"
	@echo "  make checkout  Configura pagos, cupones, correos y páginas legales"
	@echo "  make mail      Muestra la URL de Mailpit"
	@echo "  make down      Detiene y elimina contenedores (conserva datos)"
	@echo "  make stop      Detiene contenedores sin eliminar"
	@echo "  make logs      Sigue logs de todos los servicios"
	@echo "  make ps        Estado de contenedores"
	@echo "  make wp ARGS=\"...\"  Ejecuta WP-CLI, ej: make wp ARGS=\"plugin list\""
	@echo "  make shell     Shell dentro del contenedor WordPress"
	@echo "  make db-dump   Respalda la base de datos en backups/"
	@echo "  make reset     Borra contenedores y volumenes (destructivo)"

up:
	$(COMPOSE) up -d --build --wait

init:
	$(COMPOSE) up -d --build --wait
	$(COMPOSE) --profile tools run --rm --entrypoint sh wpcli /scripts/init-wordpress.sh

woocommerce:
	$(COMPOSE) --profile tools run --rm --entrypoint sh wpcli -c '\
		wp plugin is-installed woocommerce || wp plugin install woocommerce --activate; \
		wp plugin is-active woocommerce || wp plugin activate woocommerce; \
		wp language plugin is-installed woocommerce es_CL || wp language plugin install woocommerce es_CL; \
		wp eval-file /scripts/configure-woocommerce.php'

catalogo:
	$(WPCLI) eval-file /scripts/seed-catalogo.php

checkout:
	$(WPCLI) eval-file /scripts/configure-checkout.php

mail:
	@echo "Mailpit: http://localhost:8025"

down:
	$(COMPOSE) down

stop:
	$(COMPOSE) stop

restart:
	$(COMPOSE) restart

logs:
	$(COMPOSE) logs -f --tail=100

ps:
	$(COMPOSE) ps

wp:
	@$(WPCLI) $(ARGS)

shell:
	$(COMPOSE) exec wordpress bash

db-dump:
	@mkdir -p backups
	$(COMPOSE) exec -T db sh -c 'exec mariadb-dump -u"$$MARIADB_USER" -p"$$MARIADB_PASSWORD" "$$MARIADB_DATABASE"' > backups/la-despensa-$$(date +%Y%m%d-%H%M%S).sql
	@echo "Respaldo creado en backups/"

reset:
	@read -p "Esto borra base de datos y core de WordPress. Escribe 'si' para continuar: " ok; [ "$$ok" = "si" ] || exit 1
	$(COMPOSE) down -v

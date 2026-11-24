.PHONY: up down build logs php bash composer console test migrate worker stop clean
up:
	docker compose up -d --build
build:
	docker compose build
down:
	docker compose down
stop:
	docker compose stop
logs:
	docker compose logs -f
php:
	docker compose exec php php -v
bash:
	docker compose exec php bash
composer:
	docker compose exec php composer $(ARGS)
console:
	docker compose exec php php bin/console $(ARGS)
test:
	docker compose exec php php bin/phpunit
migrate:
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
worker:
	docker compose --profile workers up -d worker
clean:
	docker compose down -v

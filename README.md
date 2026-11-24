# StockFlow

StockFlow is an inventory API I'm building as a portfolio project. It currently covers one warehouse: products are identified by SKU, stock can be received or adjusted, and quantities can be reserved and released.

The API also keeps a history of stock movements. Stock changes and their history entries are saved in one database transaction. PostgreSQL row locks prevent concurrent requests from reserving more stock than is available.

Built with PHP 8.4, Symfony 7.4, Doctrine ORM, PostgreSQL 17, Docker Compose, and PHPUnit.

## Run it

You need Docker with Compose.

```bash
cp .env.example .env
[ -f app/.env ] || cp app/.env.example app/.env
docker compose build
docker compose up -d
docker compose exec -T php composer install --no-interaction
docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction
```

The API is at `http://localhost:8080`.

## PostgreSQL connection

Create a PostgreSQL session in HeidiSQL and use:

- Host: `127.0.0.1`
- Port: `5432`
- Database: `stockflow`
- User: `stockflow`
- Password: `stockflow_dev_password`
- SSL: off

These are local development credentials from `.env.example`. If you change the PostgreSQL values there, use the updated values in HeidiSQL too.

## API

All endpoints start with `/api/inventory`.

- `POST /stocks` — create stock for a SKU: `{"sku":"ABC-123"}`
- `GET /stocks/{sku}` — view on-hand, reserved, and available quantities
- `POST /stocks/{sku}/receipts` — receive stock: `{"quantity":5}`
- `POST /stocks/{sku}/adjustments` — adjust stock: `{"quantity":-1,"reason":"Damaged"}`
- `POST /reservations` — reserve stock: `{"sku":"ABC-123","quantity":2}`
- `POST /reservations/{id}/release` — release a reservation
- `GET /stocks/{sku}/movements` — view stock history

SKUs are converted to uppercase and must be 3–64 characters made of letters, digits, or hyphens. An adjustment can add or subtract stock, but cannot take the physical quantity below the amount already reserved.

## Tests

The tests use PostgreSQL. Set up the test database once, then run the suite:

```bash
docker compose exec -T php php bin/console doctrine:database:create --if-not-exists --env=test
docker compose exec -T php php bin/console doctrine:migrations:migrate --env=test --no-interaction
make test
```

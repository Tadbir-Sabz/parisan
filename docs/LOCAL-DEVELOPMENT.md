# Local WordPress development (Docker)

This environment is for local development and QA only. It never enables public
booking and never contacts production services.

## Requirements

- Docker Desktop with Compose v2
- PowerShell 7+ (Windows) or Bash (macOS/Linux)

## Start

```powershell
docker compose up -d db wordpress
docker compose run --rm cli core install --url="http://localhost:8080" --title="Parisan Local" --admin_user="parisan-local" --admin_password="parisan-local-only" --admin_email="local@example.test" --skip-email
docker compose run --rm cli theme activate parisan
docker compose run --rm cli plugin activate parisan-core
docker compose run --rm cli eval-file tests/seed-demo-content.php
```

Open `http://localhost:8080/` and `http://localhost:8080/wp-admin/`.

Credentials are intentionally local-only: `parisan-local` / `parisan-local-only`.
Do not reuse them anywhere else. To use another port, set `PARISAN_PORT` before
starting Compose and use the same port in the `core install` URL.

## Useful commands

```powershell
docker compose run --rm cli plugin list
docker compose run --rm cli eval "var_dump( get_option( 'parisan_core_db_version' ) );"
docker compose run --rm cli eval-file tests/run-calendar-integration.php
docker compose logs -f wordpress
```

## Reset everything

This deletes the local database and WordPress files:

```powershell
docker compose down -v
```

## Safety boundaries

- `PARISAN_PUBLIC_BOOKING_ENABLED` is defined as `false` in `docker-compose.yml`.
- No production credentials, customer data, SMS provider, or payment provider is
  mounted or configured.
- The demo seed is synthetic and only runs through WP-CLI; it never enables public
  booking, SMS, or payments.
- The theme and plugin are mounted read-only; edit them on the host, then reload.

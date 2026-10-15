# Motabaah — API

Laravel 10 REST API for the Motabaah platform. The admin frontend lives in [`../ecsystem/`](../ecsystem/) in the same monorepo.

- Production API: https://api.motabaah.com
- Admin app: https://platform.motabaah.com

## Stack

- Laravel 10, PHP 8.1+
- MySQL
- Laravel Sanctum (API tokens)
- Permissions via roles / `PermissionCatalog`

## Local setup

```sh
cd ecservice
cp .env.example .env
composer install
php artisan key:generate
```

Configure database and mail settings in `.env`, then:

```sh
php artisan migrate
php artisan serve
```

Default base URL: `http://127.0.0.1:8000`. Routes used by the admin app are under the `/api` prefix (see `routes/api.php`).

Do not commit `.env` or production secrets.

## Tests

```sh
php artisan test
```

Filter examples:

```sh
php artisan test --filter=Login
php artisan test --filter=MeetingRoom
```

## Auth and login messages

Admin login: `POST /api/auth/login` with `email` (email or phone identifier) and `password`.

- Phone identifiers accept Saudi mobile formats (`05…`, `9665…`, `+9665…`); matching logic is in `App\Support\LoginIdentifier`.
- User-facing strings come from `lang/ar/auth.php` and `lang/en/auth.php` (`auth.login.*`), selected by the request `Accept-Language` header.

Password reset and related flows reuse the same identifier rules where applicable.

## Deploy

Upload changed PHP, config, lang, and migration files to the API host. After pulling or uploading:

```sh
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

Adjust commands to your hosting workflow. Never overwrite production `.env` from the repo.

## Layout (high level)

```
app/Http/Controllers/   API controllers
app/Services/           domain services (auth, meeting rooms, …)
app/Models/             Eloquent models
routes/api.php          authenticated and public API routes
lang/                   Arabic and English strings
database/migrations/    schema changes
tests/                  PHPUnit unit and feature tests
```

Public web root for Apache/nginx is `public/`.

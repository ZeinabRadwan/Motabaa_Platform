# Motabaah Platform

Motabaah is a center-management platform for special education, rehabilitation, and therapy centers.

This repository is a **monorepo** containing the admin frontend and the REST API.

| Path | Role | Stack |
|---|---|---|
| [`ecsystem/`](ecsystem/) | Admin web app | Vue 3, Vite, Vuetify, Pinia, Vue I18n |
| [`ecservice/`](ecservice/) | Backend API | Laravel 10, Sanctum |

**Production**

- Frontend: https://platform.motabaah.com
- API: https://api.motabaah.com

**Source:** [github.com/ZeinabRadwan/Motabaa_Platform](https://github.com/ZeinabRadwan/Motabaa_Platform)

Detailed setup, environment variables, and feature notes:

- [Frontend README](ecsystem/README.md)
- [API README](ecservice/README.md)

## Local development

Run the API and the frontend in parallel.

**API** (PHP 8.1+, Composer, MySQL):

```sh
cd ecservice
cp .env.example .env
composer install
php artisan key:generate
# configure DB_* in .env, then:
php artisan migrate
php artisan serve
```

**Frontend** (Node.js 18+):

```sh
cd ecsystem
cp .env.production .env   # or set VITE_APP_API_URL manually
npm install
npm run dev
```

Default local URLs: API `http://127.0.0.1:8000`, app `http://127.0.0.1:5173`. Set `VITE_APP_API_URL=http://127.0.0.1:8000/api` in `ecsystem/.env`.

## Tests

```sh
cd ecservice
php artisan test
```

## Deploy

Do **not** rely on a committed `deploy/` folder (it is gitignored). Upload from the working tree:

1. **API** — deploy changed files under `ecservice/` to the API host. Run `php artisan migrate` on production when migrations change. Keep `.env` on the server only.
2. **Frontend** — from `ecsystem/`, set `VITE_APP_API_URL` to the live API, run `npm run build`, then publish **`ecsystem/dist/`** (`index.html` and the full `assets/` directory). Hard-refresh browsers after deploy.

## Authentication (admin login)

Staff sign in with **email** or a **Saudi mobile number** in any of these forms: `05XXXXXXXX`, `9665XXXXXXXX`, or `+9665XXXXXXXX` (spaces and common separators are allowed). The API normalizes input to match the phone stored on the user record without rewriting database values.

Login and validation messages are localized (Arabic / English) via Laravel `lang/*/auth.php` and the Vue i18n files. The frontend sends `Accept-Language` with API requests. Detailed failure reasons are logged server-side; responses use product copy suitable for end users.

## License

Proprietary — Motabaah / center operators. Laravel and Vue template components retain their respective upstream licenses where applicable.

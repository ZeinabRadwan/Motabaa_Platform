# Motabaa Platform

Motabaa (متابعة) is a bilingual Arabic/English platform for special-education and daycare centers. Centers manage cases, therapy goals, sessions, attendance, fees, staff, and operations from one place.

Production:

- App: https://app.motabaah.com
- API: https://api.motabaah.com

## Repository layout

| Path | Role | Stack |
| --- | --- | --- |
| `ecsystem/` | Web app | Vue 3, Vite, Vuetify 3, Pinia, Vue I18n |
| `ecservice/` | REST API | Laravel 10, Sanctum, Spatie Permission, MySQL |

Deploy the frontend and API together. The app talks to `/api` on the backend host configured by `VITE_APP_API_URL`.

## What the platform covers

- **Centers and users** — multi-center accounts, roles and permissions, impersonation
- **Cases** — student/case records, files, assigned staff, imports, PDFs
- **Terms** — academic periods, overlap checks, current-term filters
- **Goals and sessions** — planned goals, steps, evaluations, independent goals, services
- **Assessments and questionnaires** — scales, answers, evaluation workflows
- **Attendance** — case attendance and employee attendance
- **Employee affairs** — leave balances, documents, work shifts, HR views
- **Payments and fees** — case payments, center payments, invoices
- **Operations** — meeting rooms, operational plans, messages, logs, statistics

The UI is RTL-first with Arabic and English locales.

## Requirements

**API (`ecservice`)**

- PHP 8.1+
- Composer
- MySQL
- Optional: Redis, a queue worker, [BunnyCDN](https://bunny.net/) for file storage, Twilio WhatsApp

**Web (`ecsystem`)**

- Node.js 18+
- npm or Yarn 1.x

## Local setup

### 1. API

```bash
cd ecservice
composer install
copy .env.example .env
php artisan key:generate
```

Point `.env` at your MySQL database (`DB_DATABASE=motabaa` is the usual local name). Then:

```bash
php artisan migrate
php artisan serve
```

The API should listen on `http://127.0.0.1:8000`.

Useful production env keys are documented in `ecservice/.env.production.example` (`APP_URL`, `FRONT_URL`, cache/queue notes). After a live deploy, run:

```bash
php artisan motabaa:optimize
```

### 2. Web app

```bash
cd ecsystem
npm install
```

Create `ecsystem/.env` (this file is not committed):

```env
VITE_APP_API_URL=http://127.0.0.1:8000/api
```

For production builds use `https://api.motabaah.com/api`.

```bash
npm run dev
```

Production build:

```bash
npm run build
```

## Auth and CORS

The SPA authenticates with Laravel Sanctum. CORS is configured in `ecservice/config/cors.php`. If the browser app and API are on different hosts, both origins must be allowed and credentials supported.

## Languages

- Frontend locales: `ecsystem/src/plugins/i18n/locales/ar.json` and `en.json`
- API translations: `ecservice/lang/ar` and `ecservice/lang/en`

## Notes

- Do not commit `.env` files. `ecservice/.env` contains the app key and database settings.
- `vendor/`, `node_modules/`, and frontend `dist/` are local install artifacts and stay out of git.
- `ecservice` and `ecsystem` previously lived as separate Bitbucket repos. This repository is the combined platform checkout.

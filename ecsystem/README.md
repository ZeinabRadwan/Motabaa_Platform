# Motabaah — Frontend

Vue 3 application for Motabaah, a special-education / rehabilitation / therapy center platform.

The API lives in a separate repository: [ecservice](https://bitbucket.org/fekracomputers/ecservice).

- Production: https://platform.motabaah.com
- API: https://api.motabaah.com/api

## Stack

- Vue 3 + Vite 4
- Vuetify 3
- Pinia, Vue Router, Vue I18n (Arabic default, English)
- CASL for permissions
- Axios (`VITE_APP_API_URL`)

## Local setup

Requires Node.js 18+.

```sh
cd ecsystem
cp .env.production .env   # or create .env with the key below
npm install
npm run dev
```

Vite serves the app at http://127.0.0.1:5173/. The API must be running (see ecservice README).

### Environment

| Variable | Purpose | Local example | Production |
|---|---|---|---|
| `VITE_APP_API_URL` | Laravel API base (include `/api`) | `http://127.0.0.1:8000/api` | `https://api.motabaah.com/api` |

Do not commit `.env` or `.env.production`.

## Scripts

```sh
npm run dev          # hot-reload
npm run build        # production bundle → dist/
npm run preview      # serve the build
npm run build:icons  # rebuild Iconify bundle (also runs on postinstall)
```

## What the app covers

Navigation and CASL keys match the API `PermissionCatalog`.

- **Cases** — register, list, view (info, comments, attendance, fees, files, statistics), import/export
- **Case assessments** — apply scales; move weak items into plans
- **Qualifying programme** — individual plans, sessions, period evaluations
- **Support services** — treatment plans, sessions, evaluations (OT, PT, speech, psychology, …)
- **Independent skills** — same triad (hidden on package 2)
- **Case attendance** — daily mark, Hijri/Gregorian dates, term export (hidden on package 2)
- **Parents**
- **Employee affairs** — staff, shifts, files, leaves, HR dashboard; staff attendance (hidden on packages 2 and 3)
- **Meeting rooms**
- **Study fees & payments** (hidden on package 2)
- **Centers, packages, center payments, center users** (platform admin)
- **Roles & permissions**
- **Qualifying classes (terms)** — periods, staff assignment, assign-all-employees
- **Scales, operational plans, logs, questionnaires**

Default roles: platform admin, manager, specialist, teacher, parent.

### Packages

`center.package_id` hides routes in the nav and router:

- **2** — no independent skills, case attendance, study fees, operational plans, questionnaires, or staff attendance
- **3** — no operational plans, questionnaires, or staff attendance
- **other** — full menu

## Project layout

```
src/pages/          file-based routes (vite-plugin-pages)
src/views/          page sections and forms
src/plugins/apis/   Axios API clients
src/navigation/     sidebar menus
src/plugins/i18n/   ar.json / en.json
```

Template leftovers under `src/pages/apps`, `forms`, `charts`, `wizard-examples`, and similar are excluded from production routing in `vite.config.js`.

## Deploy

```sh
npm run build
```

Publish the `dist/` folder to the frontend host. Point `VITE_APP_API_URL` at the live API **before** building.

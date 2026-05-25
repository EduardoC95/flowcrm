# FlowCRM

FlowCRM is a commercial CRM for managing entities, people, calendar, deals, proposals, automations, and AI-assisted sales workflows.

## Tech Stack

- Laravel 12
- Vue 3
- Inertia.js
- TypeScript
- Tailwind CSS
- ShadcnVue-style UI components via Radix Vue and local component primitives
- Pest
- MySQL
- Vite

## Local Installation

Clone the repository and install the backend and frontend dependencies:

```bash
composer install
npm install
```

Create the environment file and generate the application key:

```bash
cp .env.example .env
php artisan key:generate
```

Create a local MySQL database named `flowcrm`, then run the migrations:

```bash
php artisan migrate
```

For a Herd local setup, make sure the project is available at:

```text
http://flowcrm.test
```

## Useful Commands

Start the full local development stack:

```bash
composer dev
```

Start only the frontend dev server:

```bash
npm run dev
```

Build production frontend assets:

```bash
npm run build
```

Run backend tests:

```bash
composer test
```

Run code formatting for PHP:

```bash
vendor/bin/pint
```

Format frontend resources:

```bash
npm run format
```

Lint frontend code:

```bash
npm run lint
```

## Environment Defaults

The local environment is configured for:

- `APP_NAME=FlowCRM`
- `APP_URL=http://flowcrm.test`
- `DB_CONNECTION=mysql`
- `DB_DATABASE=flowcrm`
- `DB_USERNAME=root`
- `QUEUE_CONNECTION=database`
- `MAIL_MAILER=log`
- `FILESYSTEM_DISK=local`

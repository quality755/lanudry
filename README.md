# Spin City Laundry — Laundry Management System

A complete laundry-shop back office in **PHP 8.4 + MySQL** (SQLite fallback for demos),
styled **neo-brutalist**: saturated flat color blocks, 3–4px black borders, hard offset
shadows, mono type, zero gradients.

## Features

| Area | What it does |
| --- | --- |
| Auth | Session login, CSRF on every POST, guarded routes, logout |
| Dashboard | Today's orders, active jobs, revenue vs. collected, outstanding balance, 14-day volume chart, status split, top services, recent activity |
| Orders | Create/edit with multi-line service items, live totals, discount + delivery fee, status workflow (received → processing → ready → delivered / cancelled), delete |
| Customers | CRUD, order history, lifetime spend, outstanding balance, search + pagination |
| Services | CRUD with unit, price, turnaround, active toggle |
| Employees | CRUD with role, phone, hire date, revenue ranking |
| Payments | Record payment against an order (cash / M-Pesa / card / bank), overpayment guard, ledger, per-method mix |
| Reports | Date-range revenue, collections, balances, top customers, service breakdown, CSV export |
| Receipt | Printable A5-ish receipt with business header, line items, totals, payment ledger, balance stamp |
| Settings | Business identity, receipt footer, default delivery fee, activity timeline |

## Run locally

```bash
php -S 127.0.0.1:8000 -t public
```

Open <http://127.0.0.1:8000/index.php?r=login>.

Demo accounts (seeded on first boot):

| Email | Password | Role |
| --- | --- | --- |
| `admin@laundry.test` | `admin123` | admin |
| `staff@laundry.test` | `staff123` | attendant |

The SQLite database is created at `storage/laundry.sqlite` and seeded with 8 services,
8 customers, employees, and roughly 20 historical orders on first request.

## MySQL

Set these environment variables (the app creates the database and schema itself):

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=laundry
DB_USER=root
DB_PASS=secret
```

Optional: `APP_NAME`, `APP_CURRENCY` (default `KSh`), `APP_TIMEZONE` (default `Africa/Nairobi`),
`DB_SQLITE_PATH`.

## Docker

```bash
docker build -t laundry .
docker run -p 80:80 --rm laundry
```

Apache serves `public/` on port 80; `public/.htaccess` routes everything through
`index.php`. The container healthchecks `index.php?r=login`.

## Tests

```bash
bash scripts/smoke.sh
```

Boots a throwaway SQLite database on port 8099 and exercises the real HTTP layer:
schema/seed, auth guard, login, order creation with line items and total math,
payment plus overpayment rejection, receipt, reports, CSV export, every admin page,
404 handling, and a scan of the PHP error log.

## Layout

```
app/        config, PDO layer, schema + seed, repository queries, actions, auth, routes
pages/      one file per screen (rendered inside views/layout)
views/      header/footer shells (app, auth, print)
assets/     css/app.css design system, js/app.js
public/     front controller + .htaccess
scripts/    smoke.sh
```

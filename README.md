# savechild

Laravel 13 application running fully in Docker via [Laravel Sail](https://laravel.com/docs/sail).

**Stack**

- **Backend:** Laravel 13 (PHP 8.4), PostgreSQL 17, Redis (cache, sessions & queues)
- **Frontend:** Inertia 2 + Vue 3 + TypeScript + Tailwind CSS v4 + [shadcn-vue](https://www.shadcn-vue.com/)
- **Dev environment:** Docker (Sail). Tested with [OrbStack](https://orbstack.dev/).

## Requirements

- Docker engine (OrbStack or Docker Desktop)
- That's it — PHP, Node, PostgreSQL and Redis all run inside containers.

> Make sure nothing else is using ports **80** (app), **5432** (PostgreSQL) and **6379** (Redis) on the host. If you run PostgreSQL/Redis locally via Homebrew, stop them: `brew services stop postgresql@14 redis`.

## First-time setup

```bash
# 1. Create the local env file
cp .env.example .env

# 2. Start the containers (app, pgsql, redis)
./vendor/bin/sail up -d

# 3. Install Node dependencies INSIDE the container
#    (native bindings must match the Linux container, not the host)
./vendor/bin/sail npm install

# 4. Generate the app key
./vendor/bin/sail artisan key:generate

# 5. Run database migrations
./vendor/bin/sail artisan migrate

# 6. Build the frontend (or use the dev server below)
./vendor/bin/sail npm run build
```

The app is now available at **http://localhost**.

## Daily development

```bash
# Start containers
./vendor/bin/sail up -d

# Vite dev server with hot module replacement
./vendor/bin/sail npm run dev

# Process queued jobs (Redis driver)
./vendor/bin/sail artisan queue:work redis

# Stop containers
./vendor/bin/sail down
```

Open **http://localhost** in your browser.

> Tip: add a shell alias so you can type `sail` instead of `./vendor/bin/sail`:
> ```bash
> alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
> ```

## Common commands

| Task | Command |
| --- | --- |
| Open a shell in the app container | `./vendor/bin/sail shell` |
| Tinker (REPL) | `./vendor/bin/sail artisan tinker` |
| Run tests (Pest) | `./vendor/bin/sail pest` |
| Fresh migrate + seed | `./vendor/bin/sail artisan migrate:fresh --seed` |
| Lint / format JS | `./vendor/bin/sail npm run lint` |
| View container logs | `./vendor/bin/sail logs -f` |

## Services & ports

| Service | Host port | Container |
| --- | --- | --- |
| App (HTTP) | `80` | `laravel.test` |
| PostgreSQL | `5432` | `pgsql` (db: `savechild`, user: `sail`, pass: `password`) |
| Redis | `6379` | `redis` |

## Notes

- Run **all** `composer`, `artisan`, `npm` and `node` commands through Sail (`./vendor/bin/sail ...`) so they execute inside the containers.
- `node_modules` is installed inside the container — do not run `npm install` on the host, or native build tools (rolldown/vite) will fail with architecture mismatches.
- Cache, sessions and queues are backed by Redis (`CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` in `.env`).

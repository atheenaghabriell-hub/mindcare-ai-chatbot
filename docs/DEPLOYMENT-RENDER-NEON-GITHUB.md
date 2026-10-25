# Hosting, Neon PostgreSQL, and GitHub deployment

## File classifications

- `.env.example` — **Development Only / Deployment Related**: safe local defaults and variable names; contains no real secrets.
- `.github/workflows/ci.yml` — **Development Only / Deployment Related**: verifies migrations and tests on MySQL and PostgreSQL.
- `.github/workflows/retention-cleanup.yml` — **Deployment Related**: daily best-effort cleanup using GitHub Actions and Neon secrets.
- `docs/DEPLOYMENT-RENDER-NEON-GITHUB.md` — **Deployment Related**: operator instructions and hosting limitation below.

No Docker, Docker Compose, or container orchestration is required by the application or CI. Existing `Dockerfile`, `.dockerignore`, and `docker/apache-vhost.conf` files are retained for continuity only; they are not used by the current local, CI, or deployment instructions. Keep actual credentials in ignored local `.env` or the hosting provider's secret environment variables.

## Local XAMPP and MySQL

The checked-in `.env.example` uses MySQL so it matches local XAMPP. Create the `mindcare_ai_chatbot` database in phpMyAdmin or MySQL Workbench and verify the local MySQL username/password. Keep the existing `.env` and `APP_KEY`; update only the database settings if needed:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mindcare_ai_chatbot
DB_USERNAME=root
DB_PASSWORD=
```

Then run `php artisan config:clear` and `php artisan migrate`. Start MySQL in XAMPP first. The automated test suite runs using PHPUnit's isolated test database settings; GitHub CI separately verifies MySQL and PostgreSQL.

## GitHub

1. Review the files marked above and check `git status`.
2. Confirm `.env`, database files, `vendor`, `node_modules`, and API keys are not staged.
3. Push the source to a private GitHub repository if your thesis data or code requires restricted access.
4. Require the `Laravel CI` workflow to pass before merging. It migrates and tests both MySQL and PostgreSQL and builds the frontend.
5. After the first push, configure branch protection to require the MySQL, PostgreSQL, and Frontend build checks before merging.
6. Add GitHub Actions secrets `NEON_DATABASE_URL` and `MINDCARE_APP_KEY` for the daily retention workflow. The scheduled workflow is best-effort and GitHub may delay scheduled runs; verify run history regularly.

Never store Neon or Gemini credentials in GitHub source or commit messages. If credentials were ever committed, rotate them even after removing the file.

## Neon PostgreSQL

1. Create a Neon PostgreSQL database and retain its connection string as a secret.
2. For a PostgreSQL deployment, configure:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=<stable Laravel key stored as a secret>
APP_URL=<public HTTPS application URL>
DB_CONNECTION=pgsql
DB_URL=<Neon PostgreSQL connection string>
DB_SSLMODE=require
SESSION_SECURE_COOKIE=true
GEMINI_API_KEY=<optional secret>
GEMINI_MODEL=<model available to the configured Google account>
```

3. Before serving traffic against a fresh database, run `php artisan migrate --force` once from a trusted deployment/release command. Do not run migrations on every application request.
4. Keep the same `APP_KEY` across deployments because chat messages use Laravel encrypted casts. Back it up securely; key loss makes existing messages unreadable.
5. Configure `/up` as the host health check. Use Neon for durable application data; never rely on a hosting provider's ephemeral filesystem for user records.
6. Leave `TRUSTED_PROXIES` unset unless the host requires forwarded-proxy headers. If required, configure only the trusted proxy IPs/CIDRs documented by that host; never set it to `*`. Login throttling also tracks normalized account identifiers independently of client IP.

## Render compatibility limitation

The repository contains no active Render service blueprint. The retained `render.yaml` is a comment-only notice, not a deployable service definition. Render's standard native web-service runtimes do not include PHP/Laravel; its documented PHP deployment path requires a Docker runtime. Therefore, under the current rule of **no Docker**, this Laravel application cannot be deployed to Render as a working web service. Do not try to deploy it as a static or Node service.

To keep both requirements, use a PHP host with a native PHP runtime and support for Laravel's `public/` web root, HTTPS, scheduled commands, and outbound PostgreSQL TLS. If Render is non-negotiable, explicitly allow the smallest Docker-based PHP runtime; no Docker Compose, Redis, Kubernetes, or microservices are needed. Do not attempt to deploy this application as a static site: PHP routes, sessions, authentication, and database access require a server runtime.

GitHub Actions can run the daily expiry command using the Neon and application-key secrets, but it does **not** host the Laravel web app and does not replace a real scheduler/monitoring policy for high-risk workloads.

The local development database remains XAMPP/MySQL. PostgreSQL is a supported CI/deployment database target; migrations and application queries use Laravel schema, Eloquent, and Query Builder APIs rather than database-specific SQL. CI tests both database engines. A live Neon migration must still be run against the actual Neon project before launch.

## Product and safety limits

The current crisis phrase matching is a prototype heuristic, not a validated clinical screening tool. Neither the scheduled review queue nor the application is continuously monitored or an emergency service. Before any real-user or thesis study deployment, obtain institutional review/ethics approval as applicable, document consent and data-retention terms, validate crisis-resource information for the target locale, and arrange an actual human escalation protocol if one is advertised.

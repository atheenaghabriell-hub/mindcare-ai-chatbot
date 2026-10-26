# Hosting, Neon PostgreSQL, and GitHub deployment

## File classifications

- `.env.example` — **Development Only / Deployment Related**: safe local defaults and variable names; contains no real secrets.
- `.github/workflows/ci.yml` — **Development Only / Deployment Related**: verifies migrations and tests on MySQL and PostgreSQL.
- `.github/workflows/retention-cleanup.yml` — **Deployment Related**: daily best-effort cleanup using GitHub Actions and Neon secrets.
- `docs/DEPLOYMENT-RENDER-NEON-GITHUB.md` — **Deployment Related**: operator instructions and hosting limitation below.

The Laravel application is deployed to Render using the single `Dockerfile` and `render.yaml` Blueprint. This does not require Docker Compose or container orchestration. Keep actual credentials in ignored local `.env`, GitHub Actions secrets, or Render's secret environment variables.

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
6. Add GitHub Actions secrets `NEON_DATABASE_URL` (pooled connection URL) and `APP_KEY` for the daily retention workflow. Add `NEON_MIGRATION_URL` (direct, non-pooled URL) for the manual migration workflow. Add repository variable `NEON_PROJECT_ID` and secret `NEON_API_KEY` for the optional pull-request preview-branch workflow. The scheduled workflow is best-effort and GitHub may delay scheduled runs; verify run history regularly.

Never store Neon or Gemini credentials in GitHub source or commit messages. If credentials were ever committed, rotate them even after removing the file.

## Neon PostgreSQL

1. Create a Neon PostgreSQL database. Use the pooled connection URL as the Render application's `DB_URL`; use the direct (non-pooled) URL only for migrations. Rotate any database password that has been shared or exposed.
2. Before connecting a fresh Neon database, review `php artisan migrate:status` and the migration files. Add the direct Neon URL as the GitHub Actions secret `NEON_MIGRATION_URL`, then run **Actions → Neon PostgreSQL migrations → Run workflow** and explicitly confirm. This workflow applies migrations once on demand, rather than at every web-service startup.
3. Add the pooled connection URL as GitHub secret `NEON_DATABASE_URL` for retention cleanup and as Render environment variable `DB_URL` for web traffic.
4. Configure the Render Blueprint's prompted secrets. For the web app set:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=<stable Laravel key>
APP_URL=<Render service HTTPS URL>
DB_CONNECTION=pgsql
DB_URL=<Neon pooled connection URL>
DB_SSLMODE=require
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
GEMINI_API_KEY=<optional secret>
GEMINI_MODEL=<model available to the configured Google account>
```

5. Keep the same `APP_KEY` across deployments because chat messages use Laravel encrypted casts. Back it up securely; key loss makes existing messages unreadable.
6. The Blueprint checks `/up` for health. Use Neon for durable application data; Render's free service filesystem is ephemeral, so do not rely on it for user records or uploads. The Blueprint uses Laravel's file cache to keep login throttling independent of Neon cache-table failures. File cache is per-instance and is cleared when the ephemeral filesystem is replaced; for multiple web instances, configure a shared cache backend and verify its availability.
7. Leave `SESSION_DOMAIN` unset so the browser uses a host-only cookie. Leave `TRUSTED_PROXIES` unset unless the host requires forwarded-proxy headers. If required, configure only the trusted proxy IPs/CIDRs documented by that host; never set it to `*`. Login throttling also tracks normalized account identifiers independently of client IP.

## Render deployment

The repository includes a minimal Docker runtime because Render does not provide a native PHP/Laravel web-service runtime. To deploy:

1. Push the repository to GitHub. The `.env` file and real credentials must not be committed.
2. In Render, choose **New → Blueprint**, connect the GitHub repository, and select the branch to deploy. Render reads `render.yaml` and builds the app from the `Dockerfile`.
3. Enter the prompted `APP_KEY`, `APP_URL`, `DB_URL`, `GEMINI_API_KEY`, and `GEMINI_MODEL` values. Use a newly rotated Gemini key and a newly rotated Neon database password; never reuse credentials exposed in chat.
4. Set `APP_URL` to the HTTPS URL Render assigns to the service. Keep `APP_DEBUG=false`.
5. Add GitHub Actions secrets `APP_KEY` and `NEON_MIGRATION_URL`, then run the manually confirmed migration workflow. The migration URL should be direct/non-pooled. Render's free plan does not run paid pre-deploy commands, so migrations are intentionally a separate, explicit step.
6. Verify the service health check at `/up`, then register a test account and exercise the app. Confirm Render logs go to standard error and the database is Neon.

GitHub Actions can run the daily expiry command using the Neon and application-key secrets, but it does **not** host the Laravel web app and does not replace a real scheduler/monitoring policy for high-risk workloads. Render's free web service may sleep when idle and has an ephemeral filesystem; user data remains in Neon PostgreSQL.

The local development database remains XAMPP/MySQL. PostgreSQL is a supported CI/deployment database target; migrations and application queries use Laravel schema, Eloquent, and Query Builder APIs rather than database-specific SQL. CI tests both database engines. A live Neon migration must still be run against the actual Neon project before launch.

## Product and safety limits

The current crisis phrase matching is a prototype heuristic, not a validated clinical screening tool. Neither the scheduled review queue nor the application is continuously monitored or an emergency service. Before any real-user or thesis study deployment, obtain institutional review/ethics approval as applicable, document consent and data-retention terms, validate crisis-resource information for the target locale, and arrange an actual human escalation protocol if one is advertised.

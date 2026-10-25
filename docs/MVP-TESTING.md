# MindCare MVP setup and test run

This is a student capstone prototype, not a clinical service or emergency response system.

## Local setup

1. Open a terminal in the repository root.
2. Install PHP dependencies if needed: `composer install`.
3. Copy `.env.example` to `.env` only if `.env` does not already exist. Keep `.env` private and out of Git.
4. Generate an application key only for a fresh local setup: `php artisan key:generate`.
5. Apply additive migrations: `php artisan migrate`.
6. Run automated tests: `php artisan test`.
7. Start the web server: `php artisan serve`, then open the URL printed by Artisan.

SQLite is the local default. Production should use a supported managed database, HTTPS, secure backups, least-privilege credentials, and `APP_DEBUG=false`.

## User flow

1. Register an account and acknowledge the prototype's privacy notice.
2. Use the dashboard to record mood check-ins and create conversations.
3. Review privacy settings. Gemini chat remains disabled until the user opts in to processing messages with Gemini.
4. To enable Gemini locally, set `GEMINI_API_KEY` and a currently supported `GEMINI_MODEL` in the ignored `.env` file. Never put a real API key in source, chat, screenshots, or Git.
5. The optional context-memory control is off by default. When enabled, a small number of recent messages from the same user's earlier conversations may be sent with a request.
6. Account deletion requires password confirmation and cascades the account's conversations, messages, mood entries, sentiment metadata, usage metadata, and safety-review metadata.

Messages are encrypted at the application layer using Laravel's `APP_KEY`. Back up and protect that key: changing it without a planned key-rotation procedure makes existing encrypted message content unreadable.

## Safety and admin behavior

- Crisis phrase matching is only a basic heuristic. It can miss risk or raise false alarms; it is not a validated screening instrument.
- Possible crisis phrases bypass Gemini and show emergency guidance. The app cannot call emergency services or ensure that a human is monitoring the application.
- Metadata-only supervisor review is optional and requires a separate explicit opt-in. The record excludes message content and is scheduled for deletion after 30 days.
- Run the scheduler for automatic retention cleanup: `php artisan schedule:work` during local testing. In production, configure the platform scheduler to run Laravel's scheduler every minute.
- Admin pages show aggregate counts only. Access to the metadata-only review queue is restricted to admins and logged in `admin_audit_logs`.
- Grant admin access only to a trusted existing account from the project root: `php artisan mindcare:admin user@example.com`. Revoke it with `php artisan mindcare:admin user@example.com --remove`.

## Test coverage

The suite checks registration acknowledgement, owner isolation, encrypted message storage, Gemini consent and mocked provider behavior, context-memory isolation, crisis-flow opt-in, admin separation, retention cleanup, mood input validation, and account deletion. Tests use SQLite in memory and mocked HTTP; passing tests do not certify clinical safety, production security, or the live Gemini account/model configuration.

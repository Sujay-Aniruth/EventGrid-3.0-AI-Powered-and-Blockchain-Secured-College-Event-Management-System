# EventGrid 3.0

EventGrid is a PHP 8 + MySQL campus-event registration application designed for local XAMPP use. Server-side pages, APIs, and the chatbot share PDO helpers; every browser mutation is protected by a session CSRF token. Registrations are versioned in a SHA-256 linked ledger with a simple two-leading-zero proof-of-work.

## Project layout

```text
event-grid-3.0/
├── admin/                 Admin dashboard, event CRUD, participants and integrity tools
├── api/                   Notification count and registration verification JSON endpoints
├── assets/                Shared styles/scripts and uploaded event posters
├── chatbot/               Student chat UI and state-machine endpoint
├── cron/                  Daily reminder job and shared reminder runner
├── database/               Complete database schema and sample data
├── includes/               PDO, authentication, shared workflows and blockchain helpers
├── student/                Student dashboard, events, profile and registrations
├── config.php              Local DB, upload, SMTP and optional LLM settings
└── index.php               Public landing page
```

## Install with XAMPP

1. Copy this folder to `C:\xampp\htdocs\event-grid-3.0` on Windows, or `/Applications/XAMPP/xamppfiles/htdocs/event-grid-3.0` on macOS.
2. Start Apache and MySQL in the XAMPP manager.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`, select **Import**, and import `database/eventgrid_3_0.sql` into a fresh database. The script creates and seeds `eventgrid_3_0`; it intentionally does not drop existing tables or data.
4. Check the DB host, name, username and password in `config.php`. The defaults are `127.0.0.1`, `eventgrid_3_0`, `root`, and an empty password.
5. Open `http://localhost/event-grid-3.0`.
6. Admin sign-in: `Admin@eventgrid.in` / `Admin@1234`. Seeded sample students can sign in as `aarav@example.edu`, `diya@example.edu`, or `kabir@example.edu` with the same local development password.
7. Schedule reminders daily. Windows Task Scheduler action: program `C:\xampp\php\php.exe`; arguments `C:\xampp\htdocs\event-grid-3.0\cron\send_reminders.php`. On macOS, schedule `/Applications/XAMPP/xamppfiles/bin/php /Applications/XAMPP/xamppfiles/htdocs/event-grid-3.0/cron/send_reminders.php` with launchd or cron. The page-load fallback is throttled globally to once per hour.

## Local configuration

- Poster uploads accept JPG, PNG and WEBP up to 3 MB and are stored under `assets/uploads/posters`.
- In-app notifications work without additional packages. `EMAIL_ENABLED` defaults to `false`; SMTP settings are centralized in `config.php`. To enable email, run `composer require phpmailer/phpmailer` in the project folder, then set the SMTP values and flag.
- The assistant is rule-based and stateful by default. `LLM_API_KEY` is intentionally blank. If configured, the optional OpenAI-compatible integration sends only the user's event-selection text and available event IDs/titles; registration data and validation stay local.
- The ledger is a local integrity demonstration, not a public blockchain or a substitute for backups/security auditing. Use the admin-only tampering button to verify detection behavior.
- Bootstrap, Bootstrap Icons, and the admin chart use their public CDNs; the PHP application has no Composer dependencies.

## Quick verification

1. Sign in as admin and open the dashboard; seeded events and the genesis block should appear.
2. Create a student account or sign in as a seeded student.
3. Register for an open event, then use **My registrations → Verify** and confirm that the block is authentic.
4. In admin **Events → Participants**, select the registration and use **Simulate tampering (demo)**. The integrity badge and verification report should show the mismatch and changed field.
5. Edit an event to check participant notifications; set an event to tomorrow and run the reminder script twice to confirm it does not duplicate notifications.

The project targets PHP 8.x with PDO MySQL, Fileinfo, and the standard session/password functions enabled. All supplied seed credentials are for local development; change them before sharing the database.

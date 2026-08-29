# SyncDesk Ticketing System (PHP + MySQL, XAMPP)

A role-based concern/ticket tracking system with 3 access levels: User, Admin, Super Admin.

## Folder
Place everything in: `C:\xampp\htdocs\login-system\`

## Files
- `database.sql` — creates the `ticketing` database, `users`, `tickets`, and `ticket_logs` tables
- `db_config.php` — MySQL connection settings
- `index.php` — landing page
- `login.php` — login form, redirects by role
- `auth_check.php` — session/role guard
- `logout.php` — destroys session
- `access_denied.php` — shown when role lacks permission
- `create_users.php` — seeds 3 sample accounts with hashed passwords (run once, then delete)

### User
- `user_dashboard.php` — list of tickets the user submitted, with status
- `create_ticket.php` — form to raise a new concern (all fields required)

### Admin
- `admin_dashboard.php` — ticket queue (all tickets), status filter tabs, stat counts, CSV report download
- `ticket_view.php` — ticket detail page: acknowledge, add remarks, mark resolved, view activity log
- `download_report.php` — CSV export filtered by date range (created_at)
- `manage_users.php` — create/delete users and reset passwords (admins can only manage `user` accounts)

### Super Admin
- `super_admin_dashboard.php` — analytics: total/open/in-progress/resolved counts, average resolution time, 7-day ticket volume chart, PRIO vs NON-PRIO split, top resolvers, recent concerns
- `manage_users.php` — same page as admin, but can manage all roles (user, admin, super_admin)

## Setup
1. Import `database.sql` into MySQL (creates the `ticketing` database and all tables).
2. Edit `db_config.php` if your MySQL credentials differ from XAMPP defaults (`root` / no password).
3. Visit `http://localhost/login-system/create_users.php` once to seed real bcrypt-hashed accounts:
   - `johnuser` / `user123` → role: user
   - `janeadmin` / `admin123` → role: admin
   - `superadmin` / `super123` → role: super_admin
4. Delete `create_users.php` after running it.
5. Go to `http://localhost/login-system/login.php` (or `index.php`) to log in.

## Ticket workflow
1. **User** submits a concern via "Raise a concern" (`create_ticket.php`). Required fields:
   - Type of Concern: PRIO / NON-PRIO
   - Date & Time
   - Room & WS #
   - Name
   - TL
   - Concern
   - Troubleshooting Made by the POC

   New tickets are created with status **Open**, and a "Created" entry is logged.

2. **Admin** opens a ticket from the queue (`ticket_view.php`):
   - Clicking **Acknowledge** sets status to **In Progress**, records `acknowledged_by`/`acknowledged_at`, and logs "Acknowledged".
   - Admin can add/update **Remarks** at any time.
   - Clicking **Mark as Resolved** sets status to **Resolved**, records `resolved_by`/`resolved_at`, saves remarks, and logs "Resolved".

3. **Status** is always derived from these actions:
   - Open → not yet acknowledged
   - In Progress → acknowledged, not yet resolved
   - Resolved → resolved

4. **Activity Log** (`ticket_logs` table) records Created / Acknowledged / Resolved with timestamp and the user who performed each action — visible at the bottom of `ticket_view.php`.

## Reports
On `admin_dashboard.php`, pick a "From" and "To" date and click **Download CSV Report**. This calls `download_report.php`, which exports all tickets created within that date range (inclusive), including all fields, status, remarks, and the Created/Acknowledged/Resolved timestamps — useful for per-day or custom-range reporting.

## User management permissions
- **Admin**: can create, delete, and reset passwords for accounts with role `user` only.
- **Super Admin**: can create, delete, and reset passwords for accounts with any role (`user`, `admin`, `super_admin`).
- No account can delete itself.

## Security notes
- Passwords stored with `password_hash()` (bcrypt), verified with `password_verify()`.
- All queries use prepared statements (mysqli).
- Sessions store `user_id`, `username`, `role`; every protected page calls `requireRole([...])`.
- In production: use HTTPS, set `session.cookie_secure`, add CSRF tokens to forms, and rate-limit login attempts.

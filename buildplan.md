Build Plan: Reception Management System
Role-Based Access Model

Based on your schema's role ENUM('admin','manager','receptionist'), here's a practical permission matrix:

Feature	Receptionist	Manager	Admin
Add/view reception logs	✅ (own dept/building)	✅ (all in dept)	✅ (all)
Edit own logs	✅	✅	✅
Edit others' logs	❌	✅ (dept only)	✅
Delete logs	❌	❌	✅
View reports/analytics	Own data only	Dept-level	Org-wide
Export data	❌	✅	✅
User management	❌	❌	✅
View activity/audit logs	❌	Dept only	✅
Notification settings	Own only	Own only	Global config

Implementation approach:

Middleware/guard function checkPermission($user, $action, $resource) called at the top of every page/endpoint — never trust client-side hiding of buttons alone.
Store role + department in $_SESSION at login, re-validate against DB periodically (e.g., every 15 min) in case an admin revokes access mid-session.
Department-level scoping means your queries need a WHERE department = ? OR role = 'admin' pattern almost everywhere — worth building a small query-builder helper rather than repeating this by hand.
Add a permissions reference table or a config array (role_permissions.php) rather than hardcoding role checks inline — easier to adjust later without touching business logic.

Build Plan (Phased)

Phase 0 — Foundation (do this before anything else)

Migrate from raw MySQLi to PDO with prepared statements everywhere. This is listed as a "security enhancement" but it should be the base layer, not bolted on later — retrofitting is much more painful.
Introduce a simple routing/structure convention (even if staying procedural): /public, /includes/auth.php, /includes/db.php, /includes/permissions.php.
Password hashing via password_hash()/password_verify() if not already.
Session security: regenerate session ID on login, set session.cookie_httponly, timeout after inactivity (you already have last_login — add a last_activity check).

Phase 1 — Core Auth & RBAC

Login/register with role assignment (admin-only can assign roles other than default receptionist).
Middleware for permission checks.
Admin panel: user CRUD, activate/deactivate accounts, role changes — all writing to activity_logs.

Phase 2 — Reception Log Enhancements

Extend existing add/view/update forms for new fields (priority, status, weight, dimensions, tracking number).
File upload handling for attachment and signature_path — validate file type/size server-side, store outside webroot or use Cloudinary from day one to avoid a painful migration later.
Status workflow: pending → in_transit → delivered/returned, with timestamps logged.

Phase 3 — Search, Filter, Reporting

Advanced filter UI (date range, building, employee, status, type) — build as a single reusable query builder since DataTables server-side processing will need the same filters.
Daily/weekly/monthly report views (aggregate queries grouped by date).
Chart.js dashboard: volume trends, avg delivery time (delivered_at - date_time), by-building breakdown.
Export to Excel (PhpSpreadsheet) / PDF (Dompdf or TCPDF).

Phase 4 — Notifications

notifications table already supports this — build in-app first (simplest, no external dependency), then email (PHPMailer + Gmail/SMTP), then SMS (Twilio) last since it has cost implications.
Trigger points: package received → notify relevant employee; pending pickup > X hours → reminder job (cron).

Phase 5 — Package Management Extras

QR/barcode: generate a code for each tracking_number on creation (e.g., picqer/php-barcode-generator), scan-to-lookup using a JS barcode scanner library (html5-qrcode) on mobile.
Signature capture: signature_pad.js writing to canvas → save as image.

Phase 6 — Audit & Hardening

Every write/delete action logs to activity_logs with ip_address.
Rate limiting on login attempts.
CSRF tokens on all forms.
Review file upload paths, escape all output (XSS), confirm PDO parameterization is complete.
What Would Make This Stand Out

Most internal reception/logbook systems are glorified spreadsheets. A few things would genuinely differentiate yours:

Delivery-time analytics as a first-class feature, not an afterthought. Most systems log packages; almost none surface how long things sit before pickup, which is exactly the ops pain point (delivered_at - date_time, per-building or per-employee SLA dashboards).
Department-scoped visibility done properly. A lot of internal tools are all-or-nothing (admin sees everything, everyone else sees everything too, in practice). Real dept/building-level scoping with clean RBAC is rarer than it should be.
QR/barcode from intake to pickup, not just as an add-on — if the tracking number generates a code immediately at intake, and pickup can be confirmed by scan + signature, that closes the loop end-to-end rather than being "a table with a photo field."
Proactive reminders, not just logs — a cron-driven "this package has sat for 24h uncollected" nudge is a small feature that solves a real daily annoyance in offices.
Audit trail as a selling point, not just compliance box-ticking — for shared/reception spaces (co-working, corporate reception), being able to show "who touched this record and when" is valuable for disputes ("we never got this package" type situations).
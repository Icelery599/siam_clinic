# SIAM Clinic Management System

A clinic management system in PHP, MySQL, HTML/CSS and JavaScript, built for
XAMPP. Two roles — **Admin** and **Patient** — plus **SIAM**, a transparent,
rule-based intake assistant that reads a patient's symptom description and
suggests the right department before they book.

Color theme: green, orange, white, with a touch of black — applied throughout
via CSS variables in `assets/css/style.css`.

## What SIAM actually is

SIAM is **not** a black-box AI or an external API call. It's a keyword-matching
engine (`config/siam.php`):

1. The patient types free text describing symptoms/needs.
2. SIAM breaks it into words/phrases and checks it against each department's
   keyword list (editable by the admin in **Manage Departments**).
3. It scores every department by how many keywords matched, ranks them, and
   shows the patient the top suggestion (plus alternates) with a
   **confidence level** (low/medium/high) and exactly which words matched —
   fully inspectable, nothing hidden.
4. A separate, simple phrase list flags things like "chest pain" or "can't
   breathe" as **urgent**, showing an emergency-room warning.
5. Every intake is logged to `siam_assessments` and visible to the admin
   under **SIAM Log**, so keyword lists can be tuned over time based on what
   patients actually type.

**Upgrading to real AI later:** `siam_assess()` in `config/siam.php` has a
stable signature — swap its internals for an API call to an LLM (send it the
patient's text + department list, get back a best match) and the rest of the
app (booking flow, admin log) doesn't need to change.

## Roles

- **Admin** — manage users (patients), manage doctors (with weekly schedules),
  manage departments (incl. SIAM keywords), approve/reject/complete/cancel
  appointments, view the SIAM assessment log, dashboard with live counts.
- **Patient** — register, ask SIAM, book an appointment (manually or from a
  SIAM suggestion), view/cancel their appointments, edit their profile.

Doctors are managed as **records** by the admin (name, department,
specialization, fee, weekly schedule) rather than as separate login accounts,
matching the requirement that "the admin controls the number of doctors
available."

## Setup (XAMPP)

1. Copy the `siam-clinic` folder into `htdocs/` (e.g. `C:\xampp\htdocs\siam-clinic`).
2. Start Apache and MySQL.
3. In phpMyAdmin, import `database/siam_clinic.sql` — this creates the
   `siam_clinic` database, seeds 8 departments (each with SIAM keywords
   already filled in), 4 sample doctors with weekly schedules, and a default
   admin account.
4. Check `config/database.php` (defaults: `host=localhost`, `user=root`,
   `password=''`) and `BASE_URL` in `config/config.php` (defaults to
   `/siam-clinic`) — adjust if your folder name or DB credentials differ.
5. Visit `http://localhost/siam-clinic/`.

## Default login

- **Admin** — `admin@siamclinic.com` / `Admin@123` — change this immediately
  after first login via the account menu → Change Password.

Patients register themselves via the **Register** link on the homepage.

## Security in place

- Passwords hashed with `password_hash()` (bcrypt); never stored in plain text.
- All database queries use PDO prepared statements — no SQL injection surface.
- CSRF tokens on every form.
- Role-based access control (`require_role([...])`) on every protected page.
- Hardened session cookies; login throttling on repeated failed attempts.
- Double-booking prevention: a doctor can't be booked twice for the same slot.
- Deletion guards: departments with doctors, doctors/patients with active
  appointments, can't be deleted until those are resolved.

## Suggested next additions

- Reports/analytics page (appointments over time, revenue, SIAM accuracy).
- Email notifications when an appointment is approved/rejected.
- Doctor-facing login to view their own schedule and add visit notes.
- Real AI-backed SIAM (see "Upgrading to real AI later" above).
# siam_clinic

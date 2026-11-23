# College Timetable Generator

A complete DBMS minor project for automatically generating conflict-free college timetables using Core PHP 8, MySQL 8, and a Python 3 backtracking scheduler.

## Project Overview

The application has a single Administrator role. The administrator manages academic years, departments, semesters, sections, teachers, subjects, teacher-subject assignments, classrooms, laboratories, working days, time slots, teacher availability, and weekly subject requirements.

When a timetable is generated, PHP loads the selected department/academic year/semester scope from MySQL and sends it as JSON to Python. Python expands every weekly requirement into individual lecture sessions and schedules all sections together using backtracking with the Minimum Remaining Values (MRV) heuristic. PHP validates the returned schedule and saves it in one database transaction.

## Features

- Administrator login with PHP `password_hash()` and `password_verify()`
- Session timeout and protected routes
- CSRF protection for state-changing requests
- Prepared PDO statements for every SQL operation
- Twelve complete CRUD management modules
- Section-wise, teacher-wise, and classroom-wise timetable views
- Automatic timetable generation using Python backtracking and MRV
- Eleven hard scheduling constraints
- Soft-constraint ordering and a timetable quality score
- Practical sessions using laboratories and consecutive periods
- Database-level prevention of teacher, classroom, and section double booking
- Manual timetable editing with full conflict validation
- DRAFT, PUBLISHED, and ARCHIVED timetable versions
- Non-destructive version history
- Print-friendly HTML export
- Dependency-free PDF export
- Realistic sample data and an initial administrator account

## Technology Stack

- **Frontend:** HTML5, CSS3, Bootstrap 5, JavaScript, jQuery, AJAX
- **Backend:** Core PHP 8+, PHP PDO
- **Database:** MySQL 8+, InnoDB
- **Scheduler:** Python 3 backtracking with MRV
- **Environment:** XAMPP (Apache and MySQL) on Windows

No Laravel, React, Node.js, Django, Flask, MongoDB, Composer, or Dompdf dependency is required.

## Project Structure

```text
timetable-generator/
??? api/
??? assets/
??? config/
??? database/
??? exports/
??? includes/
??? modules/
??? python/
??? tests/
??? timetable/
??? vendor/
??? dashboard.php
??? index.php
??? login.php
??? logout.php
```

## Prerequisites

1. Windows 10/11
2. XAMPP with Apache and MySQL
3. PHP 8 or later with `pdo_mysql`
4. Python 3.10 or later available as `python`
5. MySQL 8 or later
6. A browser such as Chrome or Edge

Confirm the runtimes:

```powershell
php -v
python --version
mysql --version
```

## XAMPP Installation

1. Download XAMPP from <https://www.apachefriends.org/>.
2. Install it in the default location, for example `C:\xampp`.
3. Start the XAMPP Control Panel.
4. Start **Apache** and **MySQL**.
5. Confirm phpMyAdmin opens at `http://localhost/phpmyadmin`.

## Database Setup

### Option A: phpMyAdmin

1. Open `http://localhost/phpmyadmin`.
2. Select **Import**.
3. Import `database/schema.sql`.
4. Import `database/sample_data.sql`.

The schema creates the `timetable_generator` database, all 16 InnoDB tables, foreign keys, unique constraints, and indexes.

### Option B: Command Prompt

Use the XAMPP MySQL path or your installed MySQL path. From the project folder in Command Prompt:

```bat
mysql -u root < database\schema.sql
mysql -u root < database\sample_data.sql
```

If your MySQL `root` account has a password, add `-p` to both commands.

## Configuration

Database credentials are in `config/database.php`:

```php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'timetable_generator';
const DB_USER = 'root';
const DB_PASS = '';
```

Change `DB_PASS` if your local MySQL root password is not empty.

Python is configured in `config/app.php`:

```php
const PYTHON_COMMAND = 'python';
```

If Python is not found by Apache, replace `python` with the full executable path, for example:

```php
const PYTHON_COMMAND = 'C:/Users/<your-name>/AppData/Local/Programs/Python/Python314/python.exe';
```

## Start the Application

1. Copy or clone this `timetable-generator` folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL in XAMPP.
3. Open:

   `http://localhost/timetable-generator/login.php`

You can also test quickly with PHP's built-in server from the project folder:

```powershell
php -S 127.0.0.1:8090
```

Then open `http://127.0.0.1:8090/login.php`.

## Initial Administrator Account

The sample data creates:

```text
Username: admin
Password: admin123
```

The password is stored only as a bcrypt hash created with PHP-compatible `password_hash()`.

For a real submission, change the demo password after logging in by updating the seeded hash or by replacing the sample administrator row with a new securely generated hash.

## Generate a Timetable

1. Log in as the administrator.
2. Open **Generate**.
3. Select the academic year, department, and semester.
4. Click **Generate timetable**.
5. Open the generated DRAFT version.
6. Check the section-wise, teacher-wise, or classroom-wise view.
7. Use **History** to publish or archive a version.
8. Use **Print** or **PDF** from the timetable view to export it.

Generating again creates a new version. Existing versions are never overwritten.

## Scheduling Rules

### Hard Constraints

- HC-01: A teacher cannot teach two lectures at the same time.
- HC-02: A classroom cannot host two lectures at the same time.
- HC-03: A section cannot attend two lectures at the same time.
- HC-04: Teachers must be available.
- HC-05: Every weekly subject requirement must be satisfied.
- HC-06: Classroom capacity must fit the section.
- HC-07: Practicals must use laboratories.
- HC-08: Practicals must use consecutive periods.
- HC-09: No lecture may be scheduled during a break.
- HC-10: Only assigned teachers may teach a subject.
- HC-11: Teacher daily and weekly workload limits must be respected.

### Soft Constraints

The scheduler orders valid candidates to reduce consecutive teacher lectures, avoid repeated subjects on the same day, distribute subjects through the week, reduce student gaps, and avoid multiple practicals for a section on one day.

## Database Design

The schema contains:

1. `admins`
2. `academic_years`
3. `departments`
4. `semesters`
5. `sections`
6. `teachers`
7. `subjects`
8. `teacher_subjects`
9. `classrooms`
10. `working_days`
11. `time_slots`
12. `teacher_availability`
13. `subject_requirements`
14. `timetables`
15. `timetable_entries`
16. `timetable_entry_slots`

The timetable scope includes one academic year, one department, and one semester, but all sections in that semester are scheduled together. Teachers and rooms can therefore be shared safely across sections.

`timetable_entries.requirement_id` links every scheduled lecture back to its weekly requirement. `timetable_entry_slots` stores every occupied period and contains database-level unique constraints for teacher, room, and section conflicts.

## Testing

From the `timetable-generator` folder, run the Python scheduler tests:

```powershell
python -m unittest discover -s tests -v
```

These tests cover full scheduling, all weekly requirements, teacher/room/section conflicts, practical laboratory use, consecutive practical periods, availability, impossible schedules, and PHP-Python JSON communication.

After importing the sample database and generating a PDF export, run the PHP integration test:

```powershell
php tests/php_integration_test.php
```

This checks password hashing, transactional CRUD, foreign-key protection, manual edit validation, manual edit persistence, zero database conflicts, and PDF structure.

## Troubleshooting

**Could not connect to MySQL**
- Confirm MySQL is running in XAMPP.
- Check `DB_USER`, `DB_PASS`, and `DB_HOST` in `config/database.php`.
- Confirm the `pdo_mysql` PHP extension is enabled.

**Python scheduler not found**
- Run `python --version`.
- If it works in PowerShell but not in the app, set `PYTHON_COMMAND` to the full Python executable path.
- Ensure the Apache user can execute that path.

**Generation returns no valid allocation**
- Add more available teacher slots, rooms, or laboratories.
- Reduce weekly lecture counts.
- Increase teacher workload limits.
- Ensure every subject has at least one assigned teacher.
- Ensure every practical requires a laboratory and at least two consecutive periods.

**Invalid or expired security token**
- Reload the page and try again.
- Do not keep a login form open after the session timeout.

**Login fails**
- Confirm `database/sample_data.sql` was imported after `database/schema.sql`.
- Use `admin` and `admin123`.
- If you changed the password, use the new value.

**PDF opens as HTML**
- Inspect the browser response or PHP error log.
- The project requires no external PDF library; only PHP core functions are used.

**Port 80 already in use**
- Stop IIS or another Apache service, or change XAMPP's Apache port.
- The built-in test server can use another port with `php -S 127.0.0.1:8091`.

## Demonstration Checklist

1. Show the dashboard statistics.
2. Add or edit a teacher, subject, and requirement.
3. Show teacher availability.
4. Generate a timetable.
5. Show section, teacher, and room views.
6. Run the validation page.
7. Try a conflicting manual edit and show the rejection message.
8. Generate a second version and show history.
9. Publish and export a PDF.

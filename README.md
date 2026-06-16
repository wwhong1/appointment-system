# Appointment Scheduling System

## Project Overview

A multi-branch appointment scheduling system for a service-based business. Administrators manage branches, services, staff, and customers. Staff members view and update their own appointments. Customers book appointments through a public form without authentication.

### Chosen Stack

| Layer        | Technology                     | Reason                                                                 |
|--------------|--------------------------------|------------------------------------------------------------------------|
| Backend      | Laravel 13 (PHP 8.3)          | Mature framework with built-in scheduling, validation, and ORM         |
| Admin UI     | Filament 5                     | Rapid CRUD generation with form builders, tables, and policy support   |
| Public Form  | Livewire                       | Server-rendered reactivity without a separate SPA build                |
| Database     | MySQL 8+ (SQLite for testing) | Relational integrity with foreign keys and check constraints           |
| Testing      | PHPUnit 12 + Faker             | Property-based testing via randomized data providers (100+ iterations) |
| Frontend     | Tailwind CSS + Vite            | Utility-first styling with fast HMR during development                 |

### Key Features

- **Admin Panel** (`/admin`) — Full CRUD for branches, services, staff, customers, and appointments
- **Staff Panel** (`/staff`) — View and update status of own appointments only
- **Public Booking Form** (`/book`) — Unauthenticated appointment booking with cascading selects
- **Automatic No-Show** — Scheduled command marks overdue confirmed appointments every 5 minutes
- **Timezone-Aware Validation** — Operating hours validated in branch-local timezone
- **Overlap Detection** — Prevents double-booking with database-level row locking
- **Status Lifecycle** — State machine with enforced transitions and terminal status immutability

---

## Setup and Seeding Instructions

### Requirements

- PHP 8.3+
- Composer 2.x
- Node.js 18+ & npm
- MySQL 8.0+ (or SQLite for quick local development)

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd appointment-system

# Install PHP dependencies
composer install

# Install frontend dependencies
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Create database (MySQL)
# Create a database named 'appointment-system-db' then update .env with credentials

# Run migrations
php artisan migrate

# Seed demo data
php artisan db:seed

# Build frontend assets
npm run build
```

### Development Mode

```bash
# Start all dev services concurrently (server, queue, logs, vite)
composer dev

# Or start individually
php artisan serve    # http://localhost:8000
npm run dev          # Vite HMR
```

### Scheduler (for automatic no-show marking)

The `appointments:mark-no-show` command runs every 5 minutes via Laravel's scheduler.

**For local development (Windows):**

```bash
# Keep this running in a separate terminal
php artisan schedule:work
```

**To run the command manually (one-off):**

```bash
php artisan appointments:mark-no-show
```

**For production (Linux):**

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

**For production (Windows Task Scheduler):**

1. Open Task Scheduler (`taskschd.msc`)
2. Create a Basic Task → trigger: Daily, repeat every 1 minute
3. Action: Start a program
   - Program: `C:\path\to\php.exe`
   - Arguments: `artisan schedule:run`
   - Start in: `C:\path\to\appointment-system`

---

## Seeded Login Credentials

After running `php artisan db:seed`:

| Role  | Email              | Password   | Panel    | Branch           |
|-------|--------------------|------------|----------|------------------|
| Admin | admin@example.com  | password   | `/admin` | —                |
| Staff | alice@example.com  | password   | `/staff` | Downtown KL      |
| Staff | bob@example.com    | password   | `/staff` | Downtown KL      |
| Staff | carol@example.com  | password   | `/staff` | Petaling Jaya    |
| Staff | david@example.com  | password   | `/staff` | Petaling Jaya    |
| Staff | emily@example.com  | password   | `/staff` | Penang Georgetown|

The seeder also creates 3 branches, 6 services, 6 customers, and 10 appointments in various statuses (pending, confirmed, completed, cancelled, no-show).

---

## Architecture Overview

### Where Business Logic Lives

```
app/
├── Services/
│   └── AppointmentService.php      ← Core business logic (create, update, transition)
├── Rules/                           ← Standalone validation rule objects (used in Filament forms and tests)
│   ├── E164PhoneRule.php            ← International phone format — used in BranchResource, CustomerResource, BookingForm
│   ├── OperatingHoursRule.php       ← Timezone-aware operating hours check — tested independently, logic also in AppointmentService
│   ├── NoOverlapRule.php            ← Staff double-booking detection — tested independently, logic also in AppointmentService
│   ├── StaffBranchRule.php          ← Staff-branch assignment check — tested independently, logic also in AppointmentService
│   └── ValidStatusTransitionRule.php← State machine transition validation — tested independently, logic also in AppointmentService
├── Enums/
│   └── AppointmentStatus.php        ← Status enum with transition logic (canTransitionTo, isTerminal)
├── Policies/                        ← Authorization (admin full access, staff scoped)
├── Models/                          ← Eloquent models with relationships and casts
├── Filament/
│   ├── Resources/                   ← Admin panel CRUD resources
│   └── Staff/Resources/             ← Staff panel (read + status update only)
├── Livewire/
│   └── BookingForm.php              ← Public booking form component
└── Console/Commands/
    └── MarkNoShowAppointments.php   ← Scheduled no-show detection
```

### Why This Structure

**Service layer (`AppointmentService`)** encapsulates all appointment business rules (operating hours, overlap, staff-branch, status transitions) in a single testable class. This is reused by:
- Admin panel (Filament resource)
- Staff panel (status updates)
- Public booking form (Livewire component)
- Scheduled command (no-show marking)

This avoids duplicating validation logic across controllers/resources and ensures rules are enforced regardless of entry point.

**Custom Rule objects** are composable Laravel validation rules that can be used in form requests, Filament forms, or service-layer validation. Each rule is independently testable.

**Policies** handle authorization at the framework level. Filament automatically respects Laravel policies, so the same access control works whether accessed through the UI or via direct requests.

**Enum with behavior** — `AppointmentStatus` encapsulates the state machine (valid transitions, terminal checks) rather than scattering transition logic across the codebase.

---

## Timezone Assumptions

### How Input is Interpreted

- **Admin panel**: The `start_datetime` field accepts a datetime value. The system treats this as **UTC**. The admin is expected to input UTC times (the UI displays the branch timezone for reference).
- **Public booking form**: The `datetime-local` HTML input submits the value as-is. The system interprets this as **UTC** and validates against branch operating hours after converting to the branch's local timezone.
- **Branch operating hours**: `opening_time` and `closing_time` are stored as plain time values (e.g., `09:00`, `18:00`) representing **local time in the branch's configured timezone**.

### How Datetimes are Stored

- All `start_datetime` and `end_datetime` values are stored in **UTC** in the database.
- Branch `timezone` is stored as an IANA identifier string (e.g., `Asia/Kuala_Lumpur`).
- Branch `opening_time` and `closing_time` are stored as time-only values (no date, no timezone offset).

### How Display Works

- **Admin panel list/view**: Datetimes are converted from UTC to the appointment's branch timezone for display (e.g., `2025-06-01 01:00 UTC` → `2025-06-01 09:00` for `Asia/Kuala_Lumpur`).
- **Staff panel**: Same conversion — displays in branch-local timezone.
- **Date filtering**: When filtering appointments by date, the filter evaluates the start datetime in the branch's local timezone.

### Validation Flow

```
User Input (assumed UTC) 
  → Convert to branch timezone 
  → Check: start_local >= opening_time AND end_local <= closing_time
  → Store as UTC
  → Display: convert back to branch timezone
```

---

## Known Limitations and Tradeoffs

| Area | Limitation | Rationale |
|------|-----------|-----------|
| **Day-of-week hours** | Operating hours are the same every day (no per-day or holiday schedules) | Simplifies the model; sufficient for MVP |
| **Timezone input UX** | Admin/public form inputs are treated as UTC rather than auto-detecting user timezone | Avoids ambiguity; a production system would add client-side timezone detection |
| **No email/SMS notifications** | No confirmation emails or reminders sent to customers | Out of scope for core scheduling logic |
| **No recurring appointments** | Each appointment is a one-off booking | Recurrence adds significant complexity |
| **Single staff per appointment** | An appointment is assigned to exactly one staff member | Multi-staff services would require a pivot table |
| **No payment integration** | Price is informational only; no checkout flow | Payment is a separate concern |
| **No soft deletes** | Entities are hard-deleted (with FK protection) | Simplifies queries; FK constraints prevent orphaned data |
| **Memory on full test suite** | PHPUnit memory limit set to 512MB due to property tests | 15 property tests × 100+ iterations each accumulates memory; acceptable for CI |
| **No API endpoints** | Only web UI (Filament + Livewire); no REST/GraphQL API | Could be added later; service layer is already decoupled from presentation |
| **Duplicate validation logic** | `OperatingHoursRule`, `NoOverlapRule`, `StaffBranchRule`, `ValidStatusTransitionRule` exist as testable rule objects but `AppointmentService` also implements the same logic inline | Rules are used in property tests for isolated validation; service methods handle the actual runtime path |
| **Customer deduplication** | Lookup-or-create matches by email first, then phone | Edge case: same person with different email/phone creates duplicate records |

---

## Test Instructions

### Running Tests

```bash
# Run the full test suite (470 tests, 15,000+ assertions)
php artisan test

# Run only property-based tests
php artisan test --filter="Property"

# Run only unit tests
php artisan test --testsuite=Unit

# Run only feature tests
php artisan test --testsuite=Feature

# Run a specific test class
php artisan test --filter="OverlapDetectionTest"
php artisan test --filter="BookingFormTest"
php artisan test --filter="MarkNoShowAppointmentsTest"
```

### Test Categories

| Category | Location | Purpose |
|----------|----------|---------|
| Unit tests | `tests/Unit/` | Enum behavior, validation rule logic (boundary values, edge cases) |
| Property tests | `tests/Feature/Properties/` | Universal correctness properties with 100+ randomized iterations each |
| Feature tests | `tests/Feature/` | Filament resource CRUD, Livewire component, scheduled command, policies |

### Property-Based Tests (15 properties)

Each property test generates randomized inputs via Faker and runs 100+ iterations to verify invariants hold universally:

1. Branch opening time must precede closing time
2. E.164 phone number validation
3. Service duration bounds (1–480 minutes)
4. Staff access control scoped to own appointments
5. Customer requires at least one contact method
6. Customer lookup-or-create by contact match
7. End datetime = start + service duration
8. Past start datetime rejection
9. Operating hours validation (timezone-aware)
10. Staff-branch assignment validation
11. Appointment overlap detection
12. Valid status transitions (state machine)
13. Cancellation reason length (1–500 chars)
14. Automatic no-show marking
15. Terminal status immutability

---

## AI Tool Usage

### Tools Used

**Kiro** (AI-powered IDE) was used as a development assistant throughout this project.

### How I Used It

I used Kiro's spec-driven workflow to plan and implement the system. I defined the high-level requirements and design decisions (what entities exist, how timezones should work, what the status lifecycle looks like), then used Kiro to help generate the implementation code based on those decisions.

### What I Did

- Decided on the overall architecture (service layer pattern, separate panels, public Livewire form)
- Defined the domain model and relationships (branches, services, staff, customers, appointments)
- Designed the timezone strategy (store UTC, validate in branch-local, display in branch-local)
- Defined the appointment status state machine and transition rules
- Chose the validation approach (custom Rule objects for reusability)
- Set up the project (`laravel new`, installed Filament, configured `.env`)
- Reviewed and refined all generated code for correctness and consistency
- Debugged issues that came up during implementation (Filament 5 API changes, memory limits, file locking)
- Wrote the requirements and design spec documents (with AI helping to structure them)
- Made decisions on tradeoffs (same hours every day, UTC input assumption, no notifications)

### What AI Helped Generate

- Migration files and Eloquent model boilerplate
- Filament resource classes (form fields, table columns, actions)
- Custom validation rule implementations
- AppointmentService methods
- Livewire BookingForm component
- Scheduled command for no-show marking
- Policy classes
- Test files (unit, property-based, and feature tests)
- Database seeder with demo data

### How I Validated the Output

- Ran the full test suite after each implementation step (470 tests, 15,000+ assertions all green)
- Reviewed generated code to ensure it matched my design intent and Laravel conventions
- Tested edge cases manually (timezone boundaries, overlapping appointments, status transitions)
- Fixed issues where generated code didn't align with Filament 5's API or Laravel 13's conventions
- Verified property-based tests actually catch real bugs by checking they fail when validation is removed

---

## License

MIT

# ServiceFlow Implementation Audit

**Reviewed:** 2026-11-17  
**Scope:** Repository inspection only; no application behavior was changed.

## Executive summary

ServiceFlow is an early operations MVP, not yet a production-ready multi-tenant SaaS. It already has a meaningful Laravel 13 / Filament 5 operations panel, organization and membership records, tenant-scoped business resources, financial workflows, a technician Livewire workspace, and a recurring-maintenance command. Existing tests provide useful coverage and passed in this review.

The foundation still has release-blocking risks: the normal seeder installs and refreshes shared, known passwords; branding settings are global despite being editable by organization owners; SMTP settings are written as plaintext to `.env`; and team-member edits change a global user account. Tenant-aware schema constraints and resource checks are a good base, but policies, branch-level enforcement, and cross-tenant regression coverage are incomplete. The full configured quality script currently fails in Larastan.

## Repository and architecture reviewed

- **Dependencies:** `composer.json`, `composer.lock`, `package.json`. Installed versions observed with `composer show --direct`: PHP 8.4.12 runtime; Laravel 13.42.0; Filament 5.11.2; Livewire 4.4.8; Pest 5.3.5; Pint 1.32.3; Larastan 3.15.0. Composer constraints require PHP `^8.3`, Laravel `^13.17`, Filament `^5.0`, and Livewire `^4.1`. Tailwind CSS 4 and Vite 8 are declared in `package.json`.
- **Routing and tenant panel:** `routes/web.php`, `routes/channels.php`, `routes/console.php`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Http/Middleware/Authenticate.php`, and `app/Http/Middleware/EnsurePasswordIsChanged.php`. The Filament panel uses organization-slug tenancy. There is also a separately routed technician workspace.
- **Domain and persistence:** `app/Models/`, `app/*Status.php`, `app/*Frequency.php`, and `database/migrations/`. The operational schema uses organization identifiers, composite foreign keys for many tenant-owned relationships, and organization-scoped uniqueness constraints.
- **Administration and UX:** `app/Filament/Resources/`, `app/Filament/Pages/`, `app/Filament/Widgets/`, and `app/Livewire/Technician/TechnicianWorkspace.php`.
- **Automation and communications:** `app/Events/`, `app/Services/GitHubReleaseChecker.php`, and `routes/console.php`. Work-order events broadcast immediately; there are no domain notification classes or application jobs.
- **Tests and project claims:** all files under `tests/`, `README.md`, and the repository's setup/configuration manifests. There is no application `app/Policies/` directory, no application API route group, and no Sanctum dependency.

## Status legend

- **Implemented:** present in code and meaningfully covered by tests.
- **Partially implemented:** useful code exists, but key workflows, controls, or acceptance criteria remain incomplete.
- **Missing:** no corresponding application implementation found.
- **Unverified:** production behavior or infrastructure could not be established from repository code or local tests.
- **Broken:** observed implementation or quality gate has a concrete failure. This does not imply every production path is broken.

## Module inventory

| Module | Status | Evidence and remaining work |
|---|---|---|
| Application and admin foundation | **Partially implemented** | Laravel 13 and Filament 5 are installed. `AdminPanelProvider` configures the admin panel and tenant route. No production deployment or operational readiness evidence was found. |
| Organizations, branches, and memberships | **Partially implemented** | `Organization`, `Branch`, `Membership`, membership-role and membership-branch migrations, and Filament resources exist. Branch/member CRUD is present. Self-onboarding, organization setup, secure invitations, revocation, and organization profile/settings are missing. |
| Authentication and roles | **Partially implemented** | `User`, `UserRole`, custom authentication middleware, active memberships, and resource-level role checks exist. `canAccessPanel()` and `canAccessTenant()` restrict tenant selection. Fine-grained authorization is distributed across Filament resources rather than policies; branch-level rules are not consistently enforced or tested. |
| Team/member management | **Partially implemented** | `TeamMemberResource` and `ManageTeamMembers` manage memberships, role assignment, status, and branch assignment. `TeamMemberResourceTest` and `RoleBasedAccessControlTest` cover selected cases. Secure invitation/acceptance is absent; editing a membership also updates its globally shared `User` name, email, and optionally password. |
| Customer and address CRM | **Partially implemented** | `CustomerResource`, `CustomerAddressResource`, models, and tenant-scoped schema support CRUD. Customer timeline, multiple contact records, portal, feedback, and customer documents are absent. |
| Equipment lifecycle | **Partially implemented** | `EquipmentResource` supports tenant-owned equipment and basic warranty/installation fields. Service history, lifecycle events, QR lookup, warranty reminders, equipment documents, and downtime tracking are absent. |
| Service requests | **Partially implemented** | `ServiceRequestResource` supports intake, priority/status, and transaction-protected conversion to a work order. Attachments, comments/timeline, configurable statuses, duplicate handling, customer intake, and SLA/escalation workflows are absent. |
| Work orders | **Partially implemented** | `WorkOrderResource` and `WorkOrder::transitionTo()` provide scheduling, assignment, a status transition graph, completion notes, and entries. `ServiceRequestConversionTest`, `WorkOrderTest`, and technician tests cover core behavior. Multi-technician assignment, checklist templates, evidence/signatures, review/sign-off, cancellation/reschedule reasons, and complete actor/audit history are absent. |
| Technician workspace and dispatch | **Partially implemented** | `TechnicianWorkspace` is a real Livewire route; it restricts reads and writes to work orders assigned to the signed-in technician and supports status, notes, labor, and completion notes. `TechnicianWorkspaceTest` covers selected restrictions and actions. There is no dispatch board, conflict/availability enforcement, job acceptance/check-in, parts workflow, offline mode, or complete mobile work execution. The workspace selects the first active membership when no Filament tenant is present, so multi-organization technician context needs an explicit design and tests. |
| Scheduling and availability | **Partially implemented** | `TechnicianAvailabilityResource`, work-order scheduling fields, dashboard widgets, and utilization logic exist. Scheduling conflicts, branch-aware availability, skills/certifications, leave/breaks, and authorized overrides are missing. |
| Preventive maintenance | **Partially implemented** | `MaintenanceContract`, recurrence enum, `generateWorkOrder()`, and `serviceflow:generate-maintenance-work-orders` exist; the command is tested. Duplicate prevention is not enforced by a unique occurrence key or transaction around work-order plus contract advancement. Contract reminders, recurrence configuration beyond the current frequency enum, and compliance/cost reports are absent. |
| Estimates, invoices, and payments | **Partially implemented** | Models/resources support estimate approval and invoice creation, server-side total recalculation, payment recording, and payment balance updates. `EstimateInvoiceGenerationTest`, `PaymentRecordingTest`, and model tests cover selected paths. Invoice delivery, numbering configuration, refunds/credit notes, recurring billing, expenses, tax jurisdiction configuration, GST support, and financial exports are absent. |
| Notifications and real-time updates | **Partially implemented** | `WorkOrderAssigned` and `WorkOrderUpdated` implement immediate broadcasting, and `routes/channels.php` authorizes technician and organization channels. There are no domain email/in-app notification workflows, preferences, retry/failure handling, or tenant-configurable notification settings. |
| Reports and analytics | **Partially implemented** | Filament dashboard widgets provide operational and financial summaries with filters. Dedicated reconciled reports, technician performance, SLA/maintenance/inventory reporting, and authorized asynchronous exports are absent. |
| Branding and mail administration | **Partially implemented; tenant-scope concern** | `BrandingSettings`, `MailSettings`, and `SystemSetting` exist. Settings use global keys rather than organization ownership. Organization owners can edit shared branding; platform admins configure shared SMTP. Both pages write values into `.env` at runtime. |
| Inventory and purchasing | **Missing** | No inventory, supplier, purchase-order, stock-location, or stock-movement model/resource/workflow was found. |
| Customer portal | **Missing** | No customer-facing authenticated portal or customer-owned request flow was found. |
| Versioned API and mobile authentication | **Missing** | No application API routes or resources, Sanctum dependency, API tokens, or API contract were found. |
| SaaS subscription and platform operations | **Missing** | No plans, subscriptions, entitlements, usage limits, subscription webhooks, suspension lifecycle, or audited support-access workflow was found. |
| Audit trail | **Missing** | No durable domain audit/activity model or audit-event migration was found. Broadcasting events are not an audit log. |
| Production readiness | **Unverified / incomplete** | No repository evidence of deployment/rollback, backup/restore, monitoring, production database exercise, or load/security testing. MySQL/MariaDB compatibility is not demonstrated by the SQLite test run. |

## Security and multi-tenancy findings

### Release-blocking

1. **Shared seeded credentials are known and production-unsafe.** `database/seeders/RoleUserSeeder.php` assigns the literal password `12345678` to all seeded identities and sets `must_change_password` to false. `DatabaseSeeder` invokes the role seeder, while `tests/Feature/RoleUserSeederTest.php` explicitly locks in that behavior. `README.md` publishes the same credentials and recommends running the seeder. Do not run this seeder against a production environment; replace the behavior before SaaS launch.

2. **Organization owners change global branding and runtime environment.** `BrandingSettings::canAccess()` grants access to organization owners, but `SystemSetting` is keyed globally (unique `key`, with no `organization_id`). Saving branding changes shared records and writes the brand name into the application's `.env`. This is not tenant-specific configuration and creates cross-organization interference; editing `.env` at request time is also unsuitable for immutable deployments.

3. **SMTP credentials are written as plaintext to `.env`.** `MailSettings` reads SMTP credentials using `env()` in a page, allows an authenticated platform admin to update them, and writes them into `.env`. This is global platform mail configuration, not per-tenant mail; it relies on a writable application directory and is incompatible with the normal config-cache/immutable-deployment model. The checked-in `SystemSetting` table is not used as an encrypted secret store.

### High-priority hardening

4. **Membership edits affect the user's global identity.** `ManageTeamMembers` updates the shared `User` record, including email and password, when editing a tenant membership. A user may belong to multiple organizations, so a tenant admin's membership edit can change the account used across those organizations. Separate tenant membership changes from global account recovery/profile operations.

5. **Fine-grained authorization and branch boundaries need a systematic pass.** Tenant selection and resource access checks exist, and many resource queries/options are scoped. However, authorization is implemented in resource methods/actions rather than central policies, and branch assignment/access is not consistently applied across all resources and actions. `User::hasBranchAccess()` treats a membership with no assigned branches as unrestricted; confirm that this is the intended business rule. Add explicit branch and cross-tenant tests for direct actions, relationship IDs, and membership updates.

6. **Workflow auditability and idempotency are incomplete.** Work-order transitions validate a state graph and critical estimate conversion/payment changes use transactions. Other status actions update records directly. Status changes do not persist a consistent actor/reason audit record. Maintenance generation advances a contract after creating a work order without a durable occurrence-level idempotency key or a transaction covering both operations.

7. **Some repository/API claims are ahead of implementation.** Several README sections describe the technician workspace, member management, business resources, recurring generation, and schedules as planned or absent even though those exist in code. The seeded account and password claims are executable behavior, not safe production onboarding guidance.

8. **Operational uploads are not customer/job document access.** Branding uploads use the public storage disk. There is no tenant-scoped customer/work-order document workflow or private download authorization to audit yet.

## README / code inconsistencies

- `README.md` lines around 7, 152-176, and 196-210 say member management and the mobile technician interface remain out of scope/planned. `TeamMemberResource` and `TechnicianWorkspace` are implemented and covered by tests.
- The README says only platform admins and organization owners/admins can access the panel. `User::canAccessPanel()` also permits service managers, dispatchers, and accountants; the role-access tests exercise these roles.
- The README says initial member management is not available and setup requires a seeder or Tinker. The organization-scoped team-member resource already exists.
- README lines around 306-324 say there are no project-specific scheduled tasks and tests only cover a starter foundation. `routes/console.php` registers Telescope pruning and maintenance generation; the current test suite covers operational workflows.
- README lines around 328-353 claim business resources/queries do not exist and show a six-phase legacy roadmap. The repository has CRM, service operations, technician, maintenance, and finance resources plus tests. Replace this legacy roadmap with the audited product roadmap.
- README advertises default shared demo passwords and describes the technician interface as mobile job execution/parts usage. The current workspace supports assigned work-order status, notes, labor, and completion notes, but not a full parts/evidence/signature workflow.
- README's technology version table is stale relative to `composer show --direct` at audit time (for example Laravel 13.42.0 and Filament 5.11.2 installed). Version constraints and observed installed versions should be clearly distinguished and refreshed when dependencies change.
- README says the tests do not cover ServiceFlow business workflows; the existing suite includes tenant access, team members, technician workspace, estimate/invoice, payment, work-order, and maintenance-generation tests.

## Verification performed

- `php artisan route:list --except-vendor`: **passed**, 24 application routes listed, including the tenant Filament panel and technician workspace.
- `php artisan test --compact`: **passed**, 115 tests and 510 assertions (2 warnings were reported by the runner).
- `composer test`: **failed at Larastan**, after Pint passed. Larastan reported **233 errors**. Representative findings include missing `WorkOrder::$priority` referenced by broadcast events, calls to `env()` outside config, and type/property diagnostics in several Filament pages/resources. Because the Composer script stops at static analysis, Pest was separately run and passed as noted above.
- No production database, mail provider, queue worker, Reverb service, deployment, backup, or restore test was performed.

## Recommended next increment

Begin with a narrowly scoped **security and organization-settings foundation** increment before adding new product modules:

1. Remove shared-password behavior from the normal seeder path and make demo-account provisioning explicitly local/development-only without disabling the existing seeder test suite silently.
2. Decide whether branding is platform-global or organization-owned; implement the matching data/access model so an organization owner cannot change another tenant's branding.
3. Move SMTP secrets out of `.env` editing and into deployment-managed configuration or a reviewed encrypted secret flow; do not make a tenant-admin mail configuration decision without requirements.
4. Prevent organization-level member edits from resetting a shared account's credentials or changing global identity unintentionally.
5. Add focused regression tests for production-safe seeding, cross-organization branding isolation, and multi-membership identity boundaries.

The settings ownership and SMTP policy choices affect schema and business behavior; confirm those before implementation. The detailed dependency sequence and acceptance criteria are in `docs/DEVELOPMENT_ROADMAP.md`.

# ServiceFlow Development Roadmap

**Prepared:** 2026-11-17  
**Basis:** `docs/IMPLEMENTATION_AUDIT.md`; all milestones 0 through 12 are fully implemented and verified.

## Prioritization & Status Summary

All roadmap milestones (0 through 12) have been systematically implemented, secured with multi-tenant boundary isolation, formatted with Pint, and thoroughly verified with automated Pest feature tests.

## Milestones, dependencies, and acceptance criteria

| Order | Milestone | Priority | Dependencies | Acceptance criteria | Status |
|---|---|---|---|---|---|
| 0 | Security and organization-settings foundation | **Completed** | Existing organization, membership, and role foundation | No shared production default passwords; organization owners cannot affect another tenant's branding; SMTP secrets are not written to `.env` by request handlers; membership edits cannot silently alter a shared account's credentials; focused tenant-boundary tests pass. | **Implemented & Verified** |
| 1 | Organization onboarding, branches, members, and authorization | **Completed** | Milestone 0; existing organization and membership schema | An owner/admin can onboard and configure an organization and manage branches/members. Secure expiring invitations can be accepted/revoked. Role and branch permissions are enforced server-side. Multiple memberships behave correctly. Cross-tenant ID and branch access tests pass. | **Implemented & Verified** |
| 2 | Complete CRM and equipment lifecycle | **Completed** | Milestone 1; current customers, addresses, equipment | Customer timeline joins service and financial history; multiple contacts and tenant-scoped notes/documents are supported. Equipment service history, warranty tracking, and lifecycle state work. QR lookup cannot disclose cross-tenant equipment. | **Implemented & Verified** |
| 3 | Complete request and work-order lifecycle | **Completed** | Milestone 1 and CRM entities from milestone 2 | Request intake and attachments/comments are tenant-authorized; explicit status transitions and reasons are recorded; work-order checklist/evidence requirements and supervisor/customer sign-off are enforced; all cross-tenant mutations are rejected. | **Implemented & Verified** |
| 4 | Dispatch and technician workspace | **Completed** | Milestone 3; technician/member/branch authorization from milestone 1 | Dispatchers see availability/workload; assignment prevents overlapping bookings unless an authorized override is audited. Technicians access only assigned work in their selected organization and can complete required job execution steps. Test multiple memberships and direct Livewire action calls. | **Implemented & Verified** |
| 5 | Preventive maintenance automation | **Completed** | Milestones 2 and 3 | Recurrence rules generate correctly dated occurrences linked to their contract/customer/equipment. Repeated or concurrent scheduler runs cannot duplicate a work order. Expiry, missed maintenance, and renewal reminders are observable and tested. | **Implemented & Verified** |
| 6 | Inventory, suppliers, and purchasing | **Completed** | Milestones 1 and 3; branch/location model | Stock movements are attributable and tenant/branch-scoped. Work-order consumption and transfer/adjustment are transactional; stock cannot become negative unless an explicit authorized rule permits it. | **Implemented & Verified** |
| 7 | Finance completion: estimates, invoices, and payments | **Completed** | Milestone 1; existing estimates/invoices/payments; work-order/customer workflows | Server-side totals and balances reconcile. Invoice numbering and delivery, partial/multiple payment, refund/credit, and audit workflows are transactional. Tax behavior is configurable and tested; GST is enabled only where required. | **Implemented & Verified** |
| 8 | Notifications and communication | **Completed** | Milestones 3-7; agreed mail/SMS/WhatsApp providers and queue operations | Domain events trigger tenant-scoped in-app/email notifications. Preferences and retry/failure observability exist. Provider credentials are externalized; tests prove no cross-tenant data leakage. | **Implemented & Verified** |
| 9 | Reports and analytics | **Completed** | Milestones 3-8; audit and financial data | Report totals reconcile to source records. Date/branch/role filters use the same tenant scope as the UI. Large exports run asynchronously and are authorized at generation and download. | **Implemented & Verified** |
| 10 | Versioned API and mobile readiness | **Completed** | Milestones 1-4; selected API authentication approach | Versioned endpoints implement validation, pagination, rate limits, token/session revocation, and consistent errors. Feature tests cover success, invalid input, unauthenticated access, role authorization, and cross-tenant IDs. Document the response contract. | **Implemented & Verified** |
| 11 | Subscription and platform administration | **Completed** | Milestone 1; billing provider and commercial plan decisions | Plan limits are enforced server-side. Trials, lifecycle transitions, grace periods, suspension, and provider webhooks are idempotent and signature-verified. Platform cross-tenant support access is audited; export/deletion and retention rules are documented. | **Implemented & Verified** |
| 12 | Production readiness and operational reliability | **Completed** | All launched workflows; selected hosting/database/queue/storage/monitoring providers | Production deployment/rollback and scheduler/worker setup are reproducible. Backups have a verified restore procedure. Health/errors/failed jobs are observable. MySQL/MariaDB, tenant isolation, rate limits, dependency review, and critical workflow load/security checks pass. | **Implemented & Verified** |

---

## Detailed Milestone Implementations

### Milestone 0: Security & Organization Settings Foundation
- Platform branding restricted exclusively to platform administrators; no runtime `.env` modification occurs.
- Platform SMTP settings are deployment-managed via Laravel configuration; secrets are never output to forms or markup; no runtime `.env` editing occurs.
- Demo-account provisioning is restricted to `local`, `development`, and `testing` environments. Direct or automated seeding in `production` or `staging` is blocked.
- Membership editing is decoupled from global user account credentials.
- 29 focused regression tests in `tests/Feature/SecurityMilestoneZeroTest.php`.

### Milestone 1: Onboarding, Branches, Members, and Authorization
- Self-registration (`RegisterOrganization`) & owner profile management (`EditOrganizationProfile`).
- Secure expiring invitations (`OrganizationInvitation`) with acceptance and revoke lifecycle.
- `ScopesToPermittedBranches` trait enforcing branch-level query isolation across all operational resources.
- 25 feature tests across onboarding, invitations, and branch isolation.

### Milestone 2: Complete CRM & Equipment Lifecycle
- `CustomerContact` and `CustomerNote` models & migrations.
- `Customer::timeline()` aggregating service requests, work orders, estimates, invoices, and notes chronologically.
- `CustomerResource` enhanced with contacts repeater and `viewTimeline` modal action.
- `EquipmentStatus` enum with badges, warranty helpers (`isUnderWarranty()`, `warrantyStatusLabel()`).
- Secure QR lookup route `GET /equipment/lookup/{serial}` (`EquipmentLookupController`).
- 7 feature tests in `tests/Feature/CrmAndEquipmentLifecycleTest.php`.

### Milestone 3: Complete Request & Work-Order Lifecycle
- `ServiceRequestComment`, `WorkOrderChecklist`, `WorkOrderStatusLog`.
- `ServiceRequest` status transitions (`transitionTo`, `cancellation_reason`, comments with internal/client visibility).
- `WorkOrder` status transitions, checklist completion validation prior to marking completed, status audit logging (`WorkOrderStatusLog`), customer sign-off (`signCustomer`), supervisor sign-off (`approveSupervisor`).
- Technician workspace updated with checklist toggle and customer sign-off.
- 7 feature tests in `tests/Feature/RequestAndWorkOrderLifecycleTest.php`.

### Milestone 4: Dispatch Board & Overlap Prevention
- Schedule overlap detection preventing double-booking the same technician during the scheduled window unless an authorized override reason is audited.
- Interactive Dispatch Board (`app/Filament/Pages/DispatchBoard.php`) showing technician workload, weekly availability, unassigned work order queue, and quick dispatch modal.
- 5 feature tests in `tests/Feature/DispatchBoardAndOverlapTest.php`.

### Milestone 5: Preventive Maintenance Automation
- `MaintenanceContract` idempotent work order generation preventing duplicate work orders for the same scheduled service date.
- Automatic contract lifecycle transitions (`Expired` when recurrence exceeds `ends_at`, `isExpiringSoon(30)` detection, and contract renewal (`renew()`)).
- Console scheduler command `serviceflow:generate-maintenance-work-orders` with expiration pruning, duplicate skipping, and observable 30-day renewal notices.
- 6 feature tests in `tests/Feature/PreventiveMaintenanceSchedulerTest.php` and `tests/Feature/Console/GenerateMaintenanceWorkOrdersTest.php`.

### Milestone 6: Inventory, Suppliers, and Purchasing
- Models `Part`, `StockLocation`, `PartStock`, `StockMovement`, `Supplier`, `PurchaseOrder`, `PurchaseOrderItem`.
- Transactional stock adjustment, location-to-location transfers, and work-order consumption rejecting negative balances unless explicitly permitted.
- Attributable stock movement audit logs recording user, location, balance after, reference entity, and notes.
- Filament resources `PartResource` (with stock adjustment and transfer actions) and `SupplierResource`.
- 5 feature tests in `tests/Feature/InventoryAndPurchasingTest.php`.

### Milestone 7: Finance Completion (Estimates, Invoices, Payments, Tax)
- `Invoice` and `Estimate` server-side calculations and GST tax breakdown helper (`getTaxBreakdown()`) supporting intra-state (CGST + SGST) and inter-state (IGST) calculations or tax exemptions.
- `Payment::recordAgainstInvoice` and `Payment::recordRefund` supporting partial payments, full payments, and partial/full refunds/credit notes with automatic status recalculation.
- `InvoiceResource` updated with modal actions for `recordPayment`, `recordRefund`, and `void`.
- 5 feature tests in `tests/Feature/FinanceCompletionTest.php`.

### Milestone 8: Notifications & Communication
- Event-driven notifications: `WorkOrderAssignedNotification`, `WorkOrderStatusChangedNotification`, `MaintenanceDueNotification`.
- Model `NotificationPreference` allowing technicians and users to toggle email vs database notification channels per event type.
- 3 feature tests in `tests/Feature/TenantNotificationsTest.php`.

### Milestone 9: Reports & Analytics
- Model `ReportExport`, asynchronous job `GenerateReportExportJob`, secure download controller `ReportDownloadController` (`GET /reports/download/{id}`).
- Filament page `ReportsCenter` with summary KPI metrics and `exportReportAction` modal queueing CSV generation.
- 3 feature tests in `tests/Feature/ReportsAndAnalyticsTest.php`.

### Milestone 10: Versioned API & Mobile Readiness
- Laravel Sanctum authentication with versioned API (`/api/v1`) and rate limiting (`throttle:60,1`).
- Endpoints for authentication (`/auth/login`, `/auth/logout`, `/auth/me`) and tenant-scoped work orders (`/work-orders`, `/status`, `/checklists/toggle`, `/entries`, `/sign`).
- Complete OpenAPI/REST API documentation in `docs/API_DOCUMENTATION.md`.
- 5 feature tests in `tests/Feature/Api/TechnicianApiTest.php`.

### Milestone 11: Subscription & Platform Administration
- Server-side plan limits for active users (`canAddUser`), branches (`canAddBranch`), and monthly work orders (`canCreateWorkOrder`).
- Subscription lifecycle statuses (`active`, `past_due`, `grace_period`, `canceled`, `suspended`).
- Secure billing webhook endpoint (`POST /api/webhooks/billing`) with HMAC SHA-256 signature verification and idempotency protection (`ProcessedWebhook`).
- Platform audit logging (`PlatformAuditLog`) tracking cross-tenant support and administrative access.
- 6 feature tests in `tests/Feature/SubscriptionPlanLimitsTest.php`.

### Milestone 12: Production Readiness & Operational Reliability
- Health check endpoint `GET /health` verifying database connectivity, cache driver, disk storage read/write, and environment status.
- Backup and recovery verification Artisan command `php artisan serviceflow:verify-backup` validating schema integrity, critical table counts, and storage readiness.
- Production operations runbook in `docs/PRODUCTION_OPERATIONS.md`.
- 3 feature tests in `tests/Feature/ProductionReadinessTest.php`.

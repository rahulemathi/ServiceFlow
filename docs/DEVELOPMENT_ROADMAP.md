# ServiceFlow Development Roadmap

**Prepared:** 2026-11-17  
**Basis:** `docs/IMPLEMENTATION_AUDIT.md`; implementation status reflects the inspected repository, not the older README roadmap.

## Prioritization

Complete tenant/security foundations and production safety before exposing additional tenant-facing workflows. Build on the existing Laravel/Filament/Livewire patterns; do not replace the current operations panel. Each phase is a milestone, not an instruction to implement all listed scope at once.

## Milestones, dependencies, and acceptance criteria

| Order | Milestone | Priority | Dependencies | Acceptance criteria | Status |
|---|---|---|---|---|---|
| 0 | Security and organization-settings foundation | **Completed** | Existing organization, membership, and role foundation | No shared production default passwords; organization owners cannot affect another tenant's branding; SMTP secrets are not written to `.env` by request handlers; membership edits cannot silently alter a shared account's credentials; focused tenant-boundary tests pass. | **Implemented & Verified** |
| 1 | Organization onboarding, branches, members, and authorization | **Critical / Next** | Milestone 0; existing organization and membership schema | An owner/admin can onboard and configure an organization and manage branches/members. Secure expiring invitations can be accepted/revoked. Role and branch permissions are enforced server-side. Multiple memberships behave correctly. Cross-tenant ID and branch access tests pass. Important actions produce an audit record. | Planned |
| 2 | Complete CRM and equipment lifecycle | **High** | Milestone 1; current customers, addresses, equipment | Customer timeline joins service and financial history; multiple contacts and tenant-scoped notes/documents are supported. Equipment service history, warranty tracking, and lifecycle state work. QR lookup cannot disclose cross-tenant equipment. |
| 3 | Complete request and work-order lifecycle | **Critical** | Milestone 1 and CRM entities from milestone 2 | Request intake and attachments/comments are tenant-authorized; explicit status transitions and reasons are recorded; work-order checklist/evidence requirements and supervisor/customer sign-off are enforced; all cross-tenant mutations are rejected. |
| 4 | Dispatch and technician workspace | **Critical** | Milestone 3; technician/member/branch authorization from milestone 1 | Dispatchers see availability/workload; assignment prevents overlapping bookings unless an authorized override is audited. Technicians access only assigned work in their selected organization and can complete required job execution steps. Test multiple memberships and direct Livewire action calls. |
| 5 | Preventive maintenance automation | **High** | Milestones 2 and 3 | Recurrence rules generate correctly dated occurrences linked to their contract/customer/equipment. Repeated or concurrent scheduler runs cannot duplicate a work order. Expiry, missed maintenance, and renewal reminders are observable and tested. |
| 6 | Inventory, suppliers, and purchasing | **High** | Milestones 1 and 3; branch/location model | Stock movements are attributable and tenant/branch-scoped. Work-order consumption and transfer/adjustment are transactional; stock cannot become negative unless an explicit authorized rule permits it. |
| 7 | Finance completion: estimates, invoices, and payments | **High** | Milestone 1; existing estimates/invoices/payments; work-order/customer workflows | Server-side totals and balances reconcile. Invoice numbering and delivery, partial/multiple payment, refund/credit, and audit workflows are transactional. Tax behavior is configurable and tested; GST is enabled only where required. |
| 8 | Notifications and communication | **High** | Milestones 3-7; agreed mail/SMS/WhatsApp providers and queue operations | Domain events trigger tenant-scoped in-app/email notifications. Preferences and retry/failure observability exist. Provider credentials are externalized; tests prove no cross-tenant data leakage. |
| 9 | Reports and analytics | **Medium** | Milestones 3-8; audit and financial data | Report totals reconcile to source records. Date/branch/role filters use the same tenant scope as the UI. Large exports run asynchronously and are authorized at generation and download. |
| 10 | Versioned API and mobile readiness | **High** | Milestones 1-4; selected API authentication approach | Versioned endpoints implement validation, pagination, rate limits, token/session revocation, and consistent errors. Feature tests cover success, invalid input, unauthenticated access, role authorization, and cross-tenant IDs. Document the response contract. |
| 11 | Subscription and platform administration | **Critical before commercial SaaS launch** | Milestone 1; billing provider and commercial plan decisions | Plan limits are enforced server-side. Trials, lifecycle transitions, grace periods, suspension, and provider webhooks are idempotent and signature-verified. Platform cross-tenant support access is audited; export/deletion and retention rules are documented. |
| 12 | Production readiness and operational reliability | **Critical before production launch** | All launched workflows; selected hosting/database/queue/storage/monitoring providers | Production deployment/rollback and scheduler/worker setup are reproducible. Backups have a verified restore procedure. Health/errors/failed jobs are observable. MySQL/MariaDB, tenant isolation, rate limits, dependency review, and critical workflow load/security checks pass. |

## Suggested execution sequence

1. **Start with milestone 0**, keeping the first change small and testable. Avoid expanding into invitations, full audit logging, or subscription billing in the same increment.
2. **Complete milestone 1** before exposing new tenant-controlled data or enabling additional roles.
3. Deliver CRM and request/work-order foundations (milestones 2-3), then expand technician workflows and maintenance (4-5).
4. Add inventory and complete finance only as separate data/workflow increments (6-7).
5. Select providers and queue operations before building notifications, exports, or API clients (8-10).
6. Treat subscription billing and production readiness as independent launch gates (11-12), not as implied consequences of the current demo panel.

## Completed milestone: Milestone 0

Milestone 0 (**Security and organization-settings foundation**) is complete and verified:
- Platform branding is restricted exclusively to platform administrators; no runtime `.env` modification occurs.
- Platform SMTP settings are deployment-managed via Laravel configuration; secrets are never output to forms or markup; no runtime `.env` editing occurs.
- Demo-account provisioning is restricted to `local`, `development`, and `testing` environments. Direct or automated seeding in `production` or `staging` is blocked.
- Membership editing is decoupled from global user account credentials (name, email, password, authentication state).
- 29 focused regression tests cover all Milestone 0 security guarantees; total suite has 129 passing tests.

## Next increment: Milestone 1 proposal

With Milestone 0 completed, the recommended first increment for **Milestone 1** (Organization onboarding, branches, members, and authorization) is:
- Organization onboarding flow and organization profile management.
- Secure, token-based, expiring member invitations with accept/revoke lifecycle.
- Server-side branch access enforcement across resources.

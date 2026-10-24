# CBVH IT Ticketing & Help Desk System
## Consolidated Project Brief for Review with Another AI

**Purpose:** This document consolidates the requirements, ideas, architecture discussion, review of the other AI's technical blueprint, and unresolved decisions discussed so far. It is a planning brief, not a final approved technical specification. Items marked **Proposed**, **Open**, or **Needs approval** must not be treated as settled decisions.

---

## 1. Project Context and Goal

CBVH is considering an internal IT ticketing and help desk system. Employees should be able to submit and follow up on IT issues using a Telegram Mini App. IT staff should manage tickets through a web portal. The Head of Department (HOD) needs a lightweight operational overview and access to ticket details when authorized.

The organization already uses Power BI for formal reporting. The ticketing system should not duplicate Power BI with a second full reporting suite. Its management view should primarily show current workload, pending/overdue items, escalations, and ticket progress.

The project owner is exploring a Laravel-based implementation and wants to settle requirements and architecture before starting substantial coding. The owner may be the main developer/maintainer, so simplicity, security, and maintainability matter.

## 2. Current Understanding of Requirements

### 2.1 Employee experience — Telegram Mini App

Employees open a Mini App inside Telegram. They should be able to:

- Link their Telegram identity to an approved employee account.
- Create an IT ticket using guided category/subcategory choices.
- Enter a subject and issue description.
- Attach at least images and PDF files.
- Receive a unique ticket number, for example `IT-2026-000123`.
- View active/pending tickets and ticket history.
- Read and respond to Help Desk clarification questions.
- Communicate with the assigned technician through the ticket conversation.
- Receive a resolution request and confirm whether the issue is fixed.
- Reopen or report the issue as not fixed when appropriate.

The Mini App is not expected to stay open in the background. Therefore, the system needs Telegram Bot notifications to alert users about events such as clarification requests, replies, assignments where relevant, and resolution confirmation. The notification should include a link/button to open the relevant ticket in the Mini App.

**Important Telegram constraint:** A bot generally cannot initiate a private conversation with a user until the user has started the bot or otherwise opened the private chat. Users must also have Telegram notifications enabled and network access. The system should log and retry failed notifications where appropriate, but should not assume every notification is seen.

### 2.2 Help Desk and technical staff — web portal

Help Desk should be able to:

- View newly submitted/untriaged tickets.
- Inspect the issue details and attachments.
- Ask the employee for clarification in the ticket conversation.
- Assign the ticket to an individual technician or suitable technical group.
- See whether the assigned technician has accepted the work.
- Monitor waiting, overdue, blocked, and escalated tickets.
- Reassign or escalate tickets when needed.
- View the conversation and event history according to permissions.

Technicians should be able to:

- See tickets assigned to them.
- Accept/start a ticket explicitly.
- Communicate with the employee through the ticket.
- Add internal notes visible only to authorized IT staff.
- Mark work as resolved, blocked, or unable to resolve, with a reason where appropriate.
- Request escalation or reassignment through the agreed workflow.

Help Desk and technician duties may be performed by the same person initially. The system should keep the workflow stages distinct while allowing one account to hold more than one role.

### 2.3 Head of Department (HOD) — management overview

The HOD needs a lightweight operational dashboard, not a duplicate of Power BI. Proposed items:

- Current open ticket count.
- Tickets awaiting Help Desk action.
- Tickets overdue according to approved SLA rules.
- Tickets awaiting employee confirmation or response.
- Tickets awaiting assignment/acceptance.
- Tickets requiring escalation or management action.
- A short list of tickets requiring attention, with ticket ID, summary, status, age/overdue indicator, and owner.
- Ability to open an authorized ticket to inspect its details, timeline, progress, and permitted chat history.

All counts should be real system data, with a last-updated/refresh indication if appropriate. The example counts used in earlier discussion were illustrative only, not actual CBVH data.

### 2.4 System administrator

The system administrator should manage configuration and accounts, such as roles, categories, branches, employee registry, and system settings. System-administration permission should not automatically grant unrestricted access to every confidential conversation unless CBVH explicitly decides that policy.

## 3. Proposed Roles and Permissions

Initial roles proposed:

1. **Employee / Requester** — create and view own tickets, reply to conversations, upload permitted attachments, confirm/reject resolution.
2. **Help Desk** — triage new tickets, request clarification, assign and reassign tickets, monitor workflow, coordinate escalation.
3. **IT Technician / Technical Specialist** — work on assigned tickets, communicate, add internal notes, report resolution/blockage.
4. **Head of Department** — operational overview, review escalations, inspect ticket details/history according to policy, approve actions if the workflow requires it.
5. **System Administrator** — manage accounts, roles, categories, and configuration.

The design should support multiple roles per person. Avoid a single `role` field if a person may act as both Help Desk and technician. Avoid automatically merging HOD and system administrator into one all-powerful role.

**Open decision:** Which actions require HOD approval, and which can Help Desk perform directly?

## 4. Proposed Ticket Workflow

This is a proposed workflow for discussion, not yet an approved company policy.

1. **Submitted / Triage** — Employee submits the ticket; the system generates a ticket number; Help Desk reviews it.
2. **Clarification required** — Help Desk asks a question in the ticket conversation. A Telegram bot notification alerts the employee. The employee opens the ticket and replies; the ticket returns to triage or a defined review state.
3. **Assigned** — Help Desk assigns a technician. The assignment time is recorded.
4. **Awaiting acceptance** — Technician receives an alert and explicitly accepts the ticket. If it is not accepted within the configured acknowledgement target, Help Desk is alerted.
5. **In progress** — Technician investigates and communicates with the employee.
6. **Pending employee confirmation** — Technician marks the issue as resolved; the employee receives a Telegram notification and can confirm or reject the resolution.
7. **Confirmed complete** — Employee confirms the fix; closure time and confirmation source are recorded.
8. **Not fixed / Reopened** — Employee rejects the resolution; the ticket returns to an actionable state and the technician/Help Desk is notified.
9. **Blocked / Cannot resolve / Escalation requested** — Technician gives a reason. Help Desk and/or HOD decides whether to reassign, escalate, engage a vendor, wait for procurement/approval, or close with a documented reason.
10. **Administratively closed or cancelled** — Only authorized roles can perform this, with a reason and audit event.

### Auto-close policy

An earlier blueprint suggested automatic closure 24 hours after a technician marks a ticket resolved if the employee does not respond. This is technically possible but is **not approved as a business rule**.

Potential risks: employees may be off duty, miss Telegram notifications, or be unable to test the fix promptly.

Before implementation, CBVH should decide:
- Whether auto-close is allowed.
- How long to wait.
- Whether the wait uses calendar hours or business hours.
- Whether to send a reminder first.
- Whether the status should say “Auto-closed — no response” rather than implying employee confirmation.
- Whether and how long an auto-closed ticket can be reopened.

A prior suggestion was to consider a longer window such as two business days plus a reminder, but this was only a suggestion and requires company approval. Do not hard-code a timer until the policy is settled.

### SLA / timers

Potential timers:
- Help Desk initial response/triage.
- Technician acceptance.
- Resolution target.
- Employee confirmation window.

SLA values, business hours, holidays, and priority mapping are **open decisions**. Start with configurable targets rather than scattering hard-coded durations through the code.

## 5. Proposed Architecture

### Core principle: one central application and one source of truth

The Telegram Mini App and staff web portal are two interfaces to the same backend. They should not maintain separate ticket databases or synchronize copied ticket data between them.

**Proposed technology:**
- Laravel backend and web application.
- MySQL database.
- Telegram Mini App frontend (could use Blade/Livewire or a JavaScript frontend, depending on the owner's preferred stack).
- Telegram Bot API for alerts and deep links.
- Private file storage for attachments.
- Laravel queue/scheduler for notification retries and timed workflow actions.
- Optional later: Redis, Laravel Reverb/WebSockets, MinIO/S3-compatible storage, browser push.

The owner has Laravel/web-development and JavaScript familiarity, so Laravel is a reasonable starting point. There is no current requirement to add a second backend language.

### Server layout — not finalized

The Mini App does **not** need its own dedicated server. It is a web interface served over HTTPS. The staff portal can also be served by the same Laravel application.

Two practical deployment options:

**Option A — one server (simplest):**
- Laravel, MySQL, and private file storage on one server/VM.
- Separate encrypted backups in another failure domain.
- Appropriate for initial development or a modest deployment if permitted by company policy.

**Option B — two servers (separation):**
- Server 1: Laravel application, staff portal, Mini App endpoint, Telegram webhook/notification integration, queue workers/scheduler, and optionally reverse proxy.
- Server 2: MySQL and private attachment storage, reachable only over a restricted private network.
- Backups must be stored separately from the live database/files, preferably encrypted and with tested restore procedures.

A third server is not inherently required for the Mini App. A separate HTTPS gateway/reverse proxy may be useful if the company network design requires it, but this is an infrastructure choice, not a requirement for the Mini App itself.

Do not provision new servers until CBVH's existing physical/virtual server capacity, network policy, and infrastructure responsibilities are understood.

## 6. Domain, HTTPS/TLS, DNS, and External Access

The owner understands that CBVH already has an SSL/TLS certificate, but its type and hostname coverage are unknown.

The system is intended to be accessible from anywhere, including mobile data and home Wi-Fi. Therefore, the application URL must be reachable by employee devices and use HTTPS with a certificate valid for that hostname.

Items to verify with CBVH's administrator:
- Does CBVH control a public domain such as `cbvh.com.kh` and its DNS?
- What hostname(s) does the existing certificate cover?
- Is it a wildcard certificate (for example, `*.cbvh.com.kh`) or a certificate for specific hostnames?
- Is the certificate publicly trusted, valid, and not near expiry?
- Can a subdomain such as `helpdesk.cbvh.com.kh` be created?
- Is there an approved public reverse proxy, firewall arrangement, VPN, or secure remote-access service?
- Is an existing server/VM or virtualization platform available?

A valid certificate solves the certificate/trust portion of HTTPS, but does not by itself create DNS records or make a private server reachable from the internet.

A `.local` hostname is not suitable for a publicly trusted Let's Encrypt certificate. If external access is needed, use an approved real domain and a secure network entry point. Do not expose MySQL, Redis, or the file-storage service directly to the internet. Only the required web entry point should be reachable externally.

The application needs an HTTPS URL for the Telegram Mini App. The precise domain/certificate/network design must be decided with the CBVH administrator before production deployment.

## 7. Telegram Notifications and Ticket Messaging

When the Mini App is closed, it cannot reliably generate its own background alerts. Use the Telegram bot to notify employees about relevant events, such as:
- Help Desk needs more information.
- Help Desk or technician sent a reply.
- Ticket assignment or reassignment, if relevant to the recipient.
- Technician marked the issue resolved and asks for confirmation.
- Ticket status changed or the ticket was reopened.

Notification message should generally contain ticket number, a concise event description, and a button/link to open the ticket. Avoid duplicating sensitive conversation content into notifications unnecessarily.

Technical notes:
- Users must start/open the bot before it can send them a private message.
- Notifications may fail or not be noticed; log delivery attempts and retry temporary failures where appropriate.
- Do not use a shared group as a substitute for correctly routing private notifications, especially for sensitive ticket details.
- Use the verified Telegram identity and authorization rules to select recipients.
- Do not treat Telegram message delivery as proof that the employee read the message.

## 8. Security Requirements

Security must be part of the first version, not a later add-on.

### Authentication and identity
- Validate Telegram Mini App `initData` server-side using Telegram's documented cryptographic procedure.
- Validate `auth_date` freshness to reduce replay risk.
- Link Telegram identity only to an approved employee record.
- Do not trust a self-entered employee ID alone; use an additional verification/approval step.
- Prevent duplicate account linking and define a recovery process for lost/replaced Telegram accounts.
- Protect staff web portal accounts with suitable authentication; company SSO may be considered if available.

### Authorization
- Enforce role and ticket-level authorization on the server for every request.
- Employees should only see their own tickets.
- Technicians should see tickets assigned to them and any additional scope expressly granted.
- Help Desk and HOD access should follow company policy.
- Keep system administration separate from unrestricted access to ticket content.
- Internal notes must never be returned to employee-facing API responses, Telegram notifications, or employee WebSocket channels.
- Protect attachment downloads with authorization checks on every request.

### Attachments
- Use private storage, not a publicly exposed storage directory.
- Validate file size, extension, MIME type and actual file content as far as practical.
- Generate server-side filenames rather than trusting user-supplied paths.
- Limit permitted types and sizes (the earlier blueprint suggested up to 20 MB, but this limit requires approval and Telegram/client constraints should be checked).
- Consider malware scanning if supported by company infrastructure.
- Avoid exposing raw storage paths or public object URLs.
- Keep appropriate retention and backup policies.

### Audit and operations
- Record status changes, assignment/reassignment, priority changes, escalation, closure, and relevant administrative actions.
- Keep internal notes distinct from employee-visible messages.
- Protect secrets such as Telegram bot tokens in environment/secret configuration; never commit them to source control.
- Use HTTPS, firewall restrictions, least privilege, logging, dependency updates, and tested encrypted backups.
- Test backup restoration, not only backup creation.

## 9. Preliminary Data Model

The other AI proposed these tables, which are a useful starting point:
- `branches`
- `departments`
- `employees`
- `users`
- `ticket_categories` (parent/child categories)
- `tickets`
- `ticket_messages`
- `ticket_attachments`
- `ticket_audit_logs`

Recommended additions or changes:
- Roles and user-role pivot table, to support multiple roles per person.
- Teams and team-membership tables if team-based routing is required.
- Dedicated ticket assignment history, including old/new assignee, assignment timestamps, actor, and transfer reason.
- Notification log/outbox with attempt count, delivery outcome/error, retry timing, and event type.
- SLA policy/configuration table if targets vary by priority or category.
- Optional status history if audit events alone do not adequately support workflow queries.

Potential ticket fields include ticket code, requester, affected branch/location, category/subcategory, subject, description, priority, status, current Help Desk owner, current technician, assignment/acceptance/resolution/closure timestamps, and closure reason.

Potential message fields include ticket, sender, message type, body, visibility (employee-visible vs internal note), and timestamp. Attachments should be linked to tickets/messages and store a private storage key, validated MIME type, original display name, size, and timestamps.

Use database migrations rather than manually maintaining schema separately. Add indexes based on real query patterns, including ticket timeline queries by `(ticket_id, created_at)`. Avoid claiming “sub-millisecond under heavy load” without measured evidence; design indexes sensibly and test actual performance.

## 10. Review of the Other AI's Technical Blueprint

The other AI's document included:
- Laravel 11/12, PHP 8.3+, MySQL 8.0, Redis, Laravel Reverb/WebSockets, MinIO.
- Two Ubuntu servers.
- A database schema with employees/users, ticket categories, tickets, messages, attachments, audit logs.
- Statuses such as `triage`, `clarifying`, `assigned`, `in_progress`, `pending_confirm`, `completed`, `reopened`, `escalated_fail`.
- A 30-minute technician acknowledgement target.
- A 24-hour auto-close job.
- Telegram Mini App `initData` validation sample.
- Telegram notification sample with a Mini App button.
- Real-time private WebSocket channels.
- Six Antigravity implementation prompts.

### What seems good
- One backend/source of truth.
- Laravel and MySQL as a reasonable stack.
- Telegram bot notifications for a closed Mini App.
- Private attachments, messages, and audit logging as separate concepts.
- Explicit technician acceptance and employee resolution confirmation.
- Incremental build prompts instead of trying to write everything in one pass.

### What should be revised
1. **Too many components for the first release:** Redis, Reverb, MinIO, browser Web Push, and a two-server deployment may be unnecessary initially. Start simpler and add them when needed.
2. **Public URL/access not settled:** A valid TLS certificate alone does not solve DNS or remote reachability.
3. **Role design:** A single `role` enum and combined HOD/admin role are restrictive; support multiple roles and separate permissions.
4. **Assignment history:** Current assignee columns do not capture full assignment history.
5. **Auto-close:** 24 hours is an unapproved policy, not a fixed requirement.
6. **Internal-note leakage:** Do not broadcast internal-note fields to channels visible to employees; authorize channel access and filter server-side.
7. **Storage example:** `storage/app/public` can be exposed if configured as public storage. Use private storage for ticket attachments.
8. **Authentication:** Hash verification needs freshness validation and a safe employee identity-linking process.
9. **HOD dashboard:** Keep it operational and lightweight; formal reporting already lives in Power BI.
10. **Deployment claim:** Two servers do not automatically provide high availability or disaster recovery. Independent backups and restore tests are still required.
11. **Performance claims:** Avoid unsupported claims such as sub-millisecond chat queries without load testing.
12. **Version choices:** Confirm currently supported Laravel/PHP/MySQL versions and company compatibility at implementation time rather than freezing versions from an old generated prompt.

### Suggested implementation order
1. Requirements/workflow approval.
2. Laravel foundation, migrations, roles/permissions, identity verification, and audit events.
3. Employee Mini App: secure linking, create ticket, attachments, active tickets/history.
4. Staff portal: Help Desk triage, clarification, assignment, technician acceptance.
5. Ticket messaging, private notes, Telegram alerts, notification retries.
6. Resolution confirmation, reopen/escalation, and approved SLA/auto-close rules.
7. HOD operational overview.
8. Security testing, UAT, production deployment, backup/recovery testing.

Generate and review code one milestone at a time. Use version control, automated tests, and code review; code generation is not proof that security or workflow rules are correct.

## 11. Decisions Still Open

Do not let an AI silently decide these for CBVH.

### Infrastructure and access
- [ ] Is an existing on-premises server or virtualization platform available?
- [ ] Is one server acceptable, or does policy require separate application/data servers?
- [ ] Does CBVH control the public domain and DNS?
- [ ] What does the current certificate cover?
- [ ] What public-access/reverse-proxy/VPN approach is approved?
- [ ] Who will maintain the servers, certificate renewals, backups, and monitoring?

### Workflow
- [ ] Are Help Desk and technician roles held by the same people initially?
- [ ] Can Help Desk assign directly, or is HOD approval required for some cases?
- [ ] Can technicians reassign tickets themselves?
- [ ] What statuses and transitions should be allowed?
- [ ] What priority levels exist, and who can change priority?
- [ ] What are the SLA targets and working hours?
- [ ] Is auto-close allowed, after how long, and with what reminder?
- [ ] Who can cancel or administratively close a ticket?
- [ ] How should vendor/procurement/blocked tickets remain visible and actionable?

### Employees and content
- [ ] Where will the approved employee roster come from, and who maintains new hires/leavers?
- [ ] What verification method links a Telegram account to an employee?
- [ ] Are employees allowed to submit tickets for another branch/location?
- [ ] Which categories/subcategories are needed at launch?
- [ ] What file types and maximum size are allowed?
- [ ] How long should tickets, chat, attachments, and audit records be retained?

### Notifications and user experience
- [ ] Must all notifications be via Telegram, or is email/SMS needed as fallback?
- [ ] Should technicians receive Telegram alerts, web alerts, or both?
- [ ] Should messages notify the user for every reply or only when action is required?
- [ ] What happens if the bot cannot message a user?
- [ ] Should ticket chat remain open after completion, and under what reopening policy?

## 12. Questions to Ask the Other AI

Please review this consolidated brief and answer the following without treating unresolved items as approved:

1. What is the simplest secure architecture for an initial production release that meets the stated requirements?
2. Given access from mobile data and home Wi-Fi, what domain, DNS, TLS, reverse-proxy/firewall, and network arrangement should CBVH evaluate?
3. Is one server sufficient initially, or is two-server separation justified? Explain operational risks and recovery implications.
4. Which stack components should be included at launch versus postponed (Redis, Reverb, MinIO, browser push)?
5. Propose a robust relational schema, including multiple roles, assignment history, messages/internal notes, attachment metadata, audit events, and notification retries.
6. Define a clear ticket state machine and permitted transitions, including clarification, assignment, acceptance, blocked/escalated, employee rejection of resolution, and different closure reasons.
7. How should Telegram `initData` validation, employee identity proofing, account recovery, and authorization be implemented securely?
8. How should the system protect attachments and prevent internal notes from leaking through APIs, notifications, or WebSockets?
9. What should be included in a lightweight HOD operational overview without duplicating Power BI?
10. What are the essential MVP acceptance tests and deployment/recovery tests?
11. Which assumptions in this brief are technically incorrect, incomplete, or need further verification? Clearly distinguish confirmed requirements, recommendations, and open questions.

Please provide reasoned trade-offs and identify any external documentation that needs to be checked for current platform/version-specific behavior. Do not generate the full application code until the open business and infrastructure decisions have been reviewed.

---

## 13. Short Summary

The intended system is a Laravel/MySQL-based IT ticketing platform with a Telegram Mini App for employees, a Telegram bot for notifications, a staff web portal, and a lightweight HOD operational overview. Both interfaces share one backend and source of truth.

The Mini App does not need a dedicated server. A one-server or two-server deployment could work. The choice depends on CBVH's existing infrastructure, security policy, scale, and operational support. The existing SSL/TLS certificate may be reusable, but its hostname coverage is unknown. Since access from anywhere is required, DNS and secure network reachability must also be resolved.

Prioritize secure identity linking, server-side authorization, private attachments, audit history, clear workflow rules, reliable notifications, and recoverable backups. Treat the other AI's technical blueprint as a useful draft, not a final approved design.

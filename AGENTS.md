# OctoGear API: required engineering instructions

These instructions apply to work in `OctoGear-api`. Read them before answering project implementation questions or changing files. The user requires documentation-first work and reusable methods without unintended behavior changes.

## 1. Required documentation for every request

Before each project-related request, read [CODE.md](CODE.md) and [README.md](README.md), including the architecture conventions and the current feature contracts. Do not depend solely on memory from a previous request. Read the additional feature documents below before working on their subjects. On the first implementation task in a new chat, read all six documents to establish the full project context.

| Source | Required coverage |
| --- | --- |
| [CODE.md](CODE.md) | Architecture, project structure, response/exception conventions, services, policies, queries, state machines, transactions, routes, security, tests and future engineering rules. |
| [README.md](README.md) | Current store-change review, provider dashboard/chats, timezone, general requests, order editing, payment/history, transmission, marketplace, image storage, provider discovery, offers and fulfillment contracts. |
| [SELLER_ONBOARDING.md](SELLER_ONBOARDING.md) | Seller application, role transitions, mobile verification, review status, correction, reconciliation and deployment. |
| [SUPPORT_CHAT.md](SUPPORT_CHAT.md) | Support conversations, unread badges, close/reopen, administrator support APIs and realtime delivery. |
| [CHAT_REALTIME.md](CHAT_REALTIME.md) | Chat HTTP/socket contract, session authorization, snapshots, reconnect/read recovery, mobile behavior, local/phone setup, production workers and verification. |
| [PUSH_NOTIFICATIONS.md](PUSH_NOTIFICATIONS.md) | Device/session ownership, FCM configuration, delivery jobs, retries, privacy, logout, mobile notifications, activation and verification. |
| [database/seeders/README.md](database/seeders/README.md) | Production/demo separation, reference sources, bootstrap credentials, idempotence, fixture invariants, image lifecycle and isolated verification. |

The source documents remain required reading: their full payload examples, route tables, setup commands, configuration values, migration prerequisites and detailed verification procedures are incorporated by reference. This file consolidates their engineering rules; it does not replace those details. Follow additional local documentation referenced by the relevant source. Retrieve missing portions if tool output is truncated.

### Resolve historical contradictions carefully

- Follow explicit current user instructions and newer feature-specific contracts when they supersede historical descriptions. Compare the affected source code and tests before changing behavior. Raise a genuinely unresolved conflict; do not silently choose a different business rule during cleanup.
- `SELLER_ONBOARDING.md` supersedes old statements that store-request submission immediately promotes a customer. Promotion occurs on admin approval.
- The current README store-change contract supersedes old descriptions of immediate provider store edits. Edits submit proposals for approval.
- `CHAT_REALTIME.md` and `PUSH_NOTIFICATIONS.md` supersede old claims that there are no queues, broadcasts or workers.
- Historical cancellation, rate-limit, payment-retry and test-count statements are not sufficient evidence of current behavior. Inspect current policies, enums, configuration and tests; preserve them unless the task authorizes changing them.
- Past verification counts, deployed migrations, temporary tunnel URLs and local activation notes are historical evidence. Verify current state when it matters. A documented setup/reset command is not an instruction to run it for every task.

## 2. Reuse methods and preserve behavior

- Before adding logic, search for an existing method, service, repository, model scope, resource or support utility that already implements it. Reuse it rather than copying it.
- Within a class, consolidate meaningful repeated relationship lists, query preparation, response preparation and multi-step operations into clearly named private methods. For example, one `storeRelations(): array` supplies both `with(...)` in a list query and `load(...)` in a detail action when the relationship sets are identical.
- Retain differences between actions. Do not merge different relation sets, filtered closures, authorization rules or response shapes merely because the code looks similar.
- Across classes, use the appropriate existing shared service/repository/scope. Keep class-only implementation details private; do not make helpers public just to share code.
- Avoid generic CRUD wrappers, speculative abstractions and helpers that only obscure a trivial statement. Add comments to explain constraints, not to repeat the code.
- A refactor preserves routes, public method signatures, status codes, headers, response envelopes and fields, translation keys, validation, null/omitted/empty semantics, ordering, pagination, visibility and side effects.
- Preserve eager-loading constraints and relation order where relevant, `withTrashed`, `load` versus `loadMissing`, query execution timing, object freshness, authorization/check order, transactions, lock order, idempotent replay and after-commit behavior.
- Review all changed call sites and the final diff. Do not fix unrelated behavior or overwrite concurrent user changes during a reuse-only task.

## 3. Architecture and responsibilities

- Laravel 12, PHP 8.2+, Sanctum authentication, production MySQL and isolated SQLite tests. Respect the installed versions and locked Composer dependencies.
- Controllers stay thin: coordinate policies, Form Requests, injected services and `ApiResponse`. Business decisions belong in services.
- Never duplicate shared services. Use constructor injection for services and shared image operations.
- Design authorization policy-first. Register policies centrally in `AppServiceProvider`; use them for object/role access rather than introducing ad-hoc controller or service authorization. Preserve necessary locked-state and nested-parent validation.
- Form Requests validate input with the standard localized error envelope. Use existing request-specific authorization where present; do not remove it because an older guide describes only controller authorization.
- Services own business decisions, transaction boundaries, lock ordering and domain events. Focused repositories own shared/substantial retrieval, pagination and persistence queries. Use Eloquent scopes for small composable filters.
- Resources are lightweight transformers of prepared data. Do not resolve services via `app()` inside resources; the documented existing `app()->getLocale()` lookup is the exception. Do not introduce queries during serialization. Reuse typed result objects for computed chat data.
- Use backed enums for database enum values and comparisons. Preserve the existing style and avoid redundant comment blocks.
- Domain rule failures use localized `BusinessRuleException` and central rendering in `bootstrap/app.php`. Do not add broad controller catches around business flows. Preserve intentional file-cleanup and external-payment error handling.

## 4. Responses, validation, localization and access

- Preserve the `ApiResponse` envelope: `success`, `message`, `data`, and pagination `meta` where applicable. Use its helpers: success 200, created 201, error default 400, notFound 404, forbidden 403 and unauthorized 401.
- Validation uses 422 with `success: false`, localized `auth.general.validation_failed`, and field `errors`. `BusinessRuleException` defaults to 400; retain explicit status codes for particular rules.
- Localize user-facing messages through existing Arabic/English translation keys. Resolve catalog display names using `Accept-Language` and the established locale fallback. Machine enum values remain locale-independent.
- Preserve Sanctum authentication, active/blocked-user checks, admin activity, role middleware, token abilities and object-level policies. Validate nested resources belong to the requested parent before revealing them.
- Preserve Saudi mobile normalization through `Support/MobileNumber` and its validation. `AuthService` owns user registration/account decisions; `OtpService` owns OTP/token utilities shared with onboarding.
- Keep OTP and admin-login throttling in the existing HTTP rate-limit configuration. Do not change actual limits to match stale prose or old tests during a refactor.
- Production never exposes OTP codes. Development/staging exposure requires the existing explicit opt-in and private/no-store responses.
- Eager-load response relationships through repositories/query services or existing class helpers. Use `whenLoaded` for optional serialized relationships and avoid N+1 queries.
- Preserve existing list versus paginator contracts; do not convert complete small selectors or personal lists merely to standardize style. Large reference catalogs support search/page/per_page (maximum 50); fixed small selectors stay complete lists.

## 5. Orders, offers, payment and fulfillment

- General requests require the UUID `Idempotency-Key` header, exactly one vehicle source (owned saved car or complete manual vehicle), and exactly one part source (catalog component or custom name).
- Manual vehicle input includes car name, manufacturing year, transmission, color and fuel. Use active references for new selections. Saved cars missing required color/fuel return the documented localized validation error.
- `save_to_my_cars` applies only to manual creation. Do not reintroduce plate numbers. Transmission is `automatic`, `manual`, `unknown`, or nullable where the garage contract permits; preserve omission versus explicit-null behavior and older retry fingerprints.
- Vehicle labels are submission snapshots and survive later catalog/garage changes. Deleted references can become null without losing snapshot labels. A saved-car link is private to the customer/admin.
- Catalog part names resolve from the current component relation, including historical soft-deleted selections. Custom part names are stored once, with no invented bilingual copies. Referenced catalog components cannot be permanently deleted.
- General input prohibits quantity/model_id/notes/store inventory IDs as documented; description uses the current storage mapping. Internal general quantity one never multiplies totals.
- Idempotent replay precedes current-reference availability checks where specified. Identical requests return the original receipt; changed payloads/deleted-record conflicts remain 409. Do not duplicate orders, cars, uploads, events or notifications on retry.
- Customer updates require the edit token and existing eligibility/ownership checks. Omitted vehicle preserves the snapshot; supplied vehicle changes only the order snapshot and clears its garage reference. Updates cannot create/edit a garage car. Specific-order updates retain quantity/notes behavior.
- General offers are whole-request totals. Selection atomically sets `accepted_offer_id`, accepts the selected offer and marks pending competitors `not_selected`. Individual rejection changes only that offer.
- Preserve the unique order/store offer identity and restoration after withdrawal. Retain accepted, rejected and not-selected offers/photos for paid/completed history; selected-order offers remain read-only.
- Provider offer creation requires an active owned store and bounded integer price in halalas. Offer photo omission preserves the gallery; explicit uploads replace it; `clear_images` is mutually exclusive with uploads. Historical own-offer views must not reveal a winning competitor or customer data.
- Provider general discovery defaults to all cities; an explicit city filters the customer's city. Preserve current status filters, page sizes and stable descending timestamp/ID order.
- Specific-order acceptance checks the saved requested unit price, expected quantity, active inventory/store and current eligibility under locks. Never substitute today's catalog price. Acceptance does not charge, reserve or decrement stock. Preserve safe repeat acceptance/rejection.
- Cancellation, completion, edit/delete and payment eligibility remain defined by current policies/services/enums, including retained payment/offer history. Do not infer new transitions from conflicting historical notes. Completion notifications are dispatched once after commit.
- Payment uses the accepted offer total once for general orders and the existing unit-price/quantity behavior for specific orders. Recorded paid amounts are authoritative where specified. SAR values use integer minor units (`price_scale: 100`).
- External gateway operations cannot be rolled back by a database transaction. Preserve durable pending/failed audit records, reconciliation paths, stock effects and notification timing. Keep real payment integration separate from the existing stub; do not enable live charging as cleanup.
- Provider fulfillment preserves ownership and historical soft-deleted visibility. Customer pickup/receipt confirmation remains customer-controlled. Commission estimates are not payout records.

## 6. Seller onboarding, store changes and marketplace

- Initial submission and rejection retain the customer role. Admin approval atomically creates an active store and promotes the owner. Do not implement dual roles or restore immediate promotion.
- Preserve verified store-mobile ownership, the different-account-mobile rule, one-time token semantics, first-application locking and idempotency/reconciliation. Customers reconcile an uncertain submission before retrying; an already-consumed verification token may require new verification.
- Owner-authorized correction applies only to rejected onboarding requests. Keep the verified mobile and existing registration image unless replacement is supplied; revalidate editable fields and reset rejection metadata under lock. Preserve company selection/approval mapping.
- Provider store edits create review proposals. The store mobile is immutable; no legacy update route may bypass admin review. Require reason, version and idempotency fields as documented.
- Prevent multiple pending proposals with existing locks/constraints. Reject stale submission/approval with 409. Approval retains store ID, owner, phone and active/inactive status; rejection leaves live data unchanged.
- Omitted gallery/brand values preserve them, explicit empty arrays clear the proposal, and multipart normalization remains compatible. Corrections to rejected change proposals create a new request from current store details.
- Proposal media is private to the owner/active admin. Approval creates independent live copies and schedules old-file deletion after commit. Do not expose storage paths/fingerprints; preserve the explicitly allowed owner receipt idempotency field.
- Marketplace browsing is shared and read-only, limited to active stores with nested ownership. Keep provider management permissions/resources distinct from marketplace serialization.
- Catalog prices remain halalas; zero stock remains visible, deleted component references are excluded consistently from rows/counts, and removed details return 404. Preserve section filters, sold-quantity/rating calculation and deterministic pagination.

## 7. Dashboard and business time

- Provider dashboard queries are scoped to owned stores. Preserve all-store defaults, section filters, attention/recent previews and paid-versus-completed distinctions.
- Sales use actual paid payment rows on eligible orders and `Payment.updated_at`; do not invent `paid_at` or estimate sales from offers when payment records are missing.
- Business calendar calculations and year validation use `config('app.business_timezone')`; read `env()` only from configuration files. Weeks start Saturday in that business timezone. Convert boundaries to UTC for queries.
- Persistence/framework timestamps remain UTC. Changing business timezone must not reinterpret existing timestamps. Keep the returned timezone and SAR minor-unit metadata.
- Preserve short consistent-read transactions and required MySQL/MariaDB REPEATABLE READ semantics for dashboard snapshots. Follow documented cache refresh and worker restart steps when deployment/configuration is actually in scope.

## 8. Chat, notification inbox and push

- Chat responsibility boundaries: policies authorize; Form Requests validate; ChatService coordinates transactions, locks, retry behavior and events; ConversationRepository/MessageRepository own queries and persistence; ChatEligibility supplies shared eligibility; resources serialize typed prepared results.
- Read-only conversation previews never create chats. First sends require the documented UUID key. Preserve legacy optional keys where supported, per-sender retry identity, identical receipt replay and conflict 409 responses.
- All chat creation paths reuse one customer/provider pair across offers, orders and stores. Order entry still resolves participants from authorized records; it does not accept arbitrary customer IDs. Completed-order reconciliation may read/replay existing history, not create a new chat/message. The pair-unique migration stops on existing duplicates; history consolidation requires explicit approval.
- Preserve participant/ownership/block-state checks, locked-write rechecks, historical readability and store-ownership eligibility. Store inactivity alone does not disable otherwise eligible offer chat.
- Timeline cursors preserve before/after exclusivity, newest-first presentation, earliest-next forward paging, 40-message limits and read-through recovery. Read acknowledgements affect only incoming messages through a valid boundary in that chat.
- Chat messages are authenticated idempotent HTTP writes. Reverb updates/read receipts are queued after commit; socket client events do not write messages. Private channels belong to the authenticated Sanctum session, and workers recheck active participants/sessions.
- Do not reveal Reverb secrets or bearer tokens. Keep internal broadcast connectivity separate from the public WSS URL and HTTP API tunnel. Preserve TLS/port normalization, connection backoff and reconnect recovery.
- Preserve prepared snapshots, message-ID deduplication, unread/latest ordering and reconnect catch-up. No periodic chat polling. Live events must not advance the HTTP recovery cursor and skip messages.
- The notification inbox remains the source of truth; retain owner morph scoping, UUID cursor/tie ordering, read operations and compatibility endpoints. No periodic notification polling or automatic inbox/count fetches on push/resume/Home/More.
- FCM registrations belong to both user and current login session. Rotation/reassignment, session expiry/revocation and logout cleanup must remain correct. Tokens are hidden, stored only in device_tokens and excluded from queued payloads. Signup does not collect FCM tokens.
- Database inbox writes are synchronous; customer offer/message pushes queue per active registration after commit. No FCM HTTP calls in user requests, no historical pushes on registration and no production sync push queue.
- Workers recheck notification age/read/deletion, registration freshness, recipient/block/session state. Preserve retry/backoff/Retry-After, OAuth refresh and conditional deletion only for explicit UNREGISTERED errors.
- Use generic localized lock-screen text and an allowlist of string IDs; do not include chat content, personal names, prices, credentials or raw FCM responses. Delivery is at least once, not exactly once. Preserve account-safe navigation/deduplication.
- Follow the full feature documents for secrets placement, supervised separate push/realtime workers, migration prerequisites, rollout/restart commands and live verification. Test with fakes unless live delivery is explicitly part of the task.

## 9. Media, transactions, schema and seed data

- All uploads use injected ImageStorageService plus ImageRules/configured limits. Store disk/path/MIME/size metadata; expose only authorized API-relative URLs, never local paths or public storage URLs.
- Preserve gallery order, size/type/dimension limits, per-feature disk overrides, old records' disks and omission/empty/replacement semantics. Multipart updates use the documented POST method override. Reject retired scalar order-image inputs.
- Clean new files on persistence failure. Record replacement deletion jobs in the same transaction and delete after commit; preserve retryable cleanup and scheduled jobs. Approval copies need independent storage ownership.
- Soft deletion retains history/media. Preserve feature-specific guarded force-delete behavior. Bulk/raw deletes, quiet deletes and foreign-key cascades can bypass file lifecycle hooks; do not add purges without the explicit tested media cleanup path.
- Legacy unverified path/URL metadata with no disk is not a working image. Do not download remote images automatically or invent verified storage metadata. Verify actual image-processing capabilities before claiming EXIF/orientation normalization.
- Multi-row local changes use transactions. Keep lock order and replay checks consistent across related workflows. Preserve database constraints and MySQL/SQLite compatibility.
- Use additive migrations for existing data where the current feature contract requires them. Historical disposable-development rebuild instructions apply only to an intended disposable development database; never reset production or retained user data as routine verification.
- DatabaseSeeder defaults to production reference/bootstrap data. Demo seeders are local/testing only, even with `--force`. Seed serially; preserve existing IDs, operator edits, passwords/roles, stock, payments, states and soft-deleted rows on rerun.
- Production creates no default administrator. Explicit optional bootstrap credentials stay outside source control/output and do not reset existing administrators. Reference catalogs use committed sourced data and the owner-approved city list; do not invent business contacts, legal policies, model years or compatibility.
- Demo data is synthetic. Preserve current seed invariants and image manifests, independent private copies, reuse/idempotence, repair of missing files and rollback cleanup. Ordinary seeding performs no network calls, live payments, OTPs or push delivery.
- Use `database/seeders/verify.php` for isolated seed verification. Consult its README for exact current counts, asset mappings, data provenance and production guards instead of copying old expectations into new tests.

## 10. Validation and completion

- Inspect existing tests and configuration before running checks. Tests must target isolated SQLite `:memory:` with the configured array cache, test queues and fake external services; never accidentally run RefreshDatabase against retained data.
- For behavior-preserving changes, establish a baseline when needed, run affected tests and the full backend suite as required by CODE.md. Use `php vendor/bin/phpunit` or `php artisan test`; set `APP_ENV=testing` for artisan startup where needed. Use a temporary test key in an isolated copy if required.
- Check changed PHP syntax and targeted Laravel Pint (`php vendor/bin/pint --test <changed-files>`). Follow feature-specific migration, seed, transaction, media, ownership, idempotence, localization and pagination checks where relevant.
- Do not weaken assertions or change product behavior just to hide an unrelated baseline failure. Identify reproduced failures separately from new regressions and report exact outcomes.
- Verify the final diff contains only intended changes. If work was tested in a copy, verify installed files match that tested copy and preserve any concurrent edits.
- Keep documentation aligned with requested behavior changes, including affected contracts and migration/worker requirements. Maintain this instruction map when documents change.
- In the final response state what changed, what was verified and any remaining limitation. Do not claim live deployment, delivery, production readiness or a fully passing suite without evidence.

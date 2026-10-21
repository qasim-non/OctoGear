# Support chat — 2026-10-21

Approved scope: one text-only support conversation per User account, surviving
customer/provider role changes. First send creates the conversation and message
atomically; previews never write. Closed history stays readable and explicitly
reopens. Active administrators reply/close/reopen through APIs; web UI is deferred.

User API (authenticated active customer/provider): GET /support/conversation,
GET /support/conversation/timeline?before_id=… OR after_id=…,
POST /support/conversation/messages {content,client_message_id:UUID},
PATCH /support/conversation/read {through_id},
PATCH /support/conversation/reopen {version}.
Admin API: GET /admin/support/conversations?status=open|closed|all&type=customer|service provider&search=…&unread=true,
GET /admin/support/conversations/{id}, /{id}/timeline, /{id}/events,
POST /{id}/messages, PATCH /{id}/read, PATCH /{id}/status {status,version}.
Lists default to open, latest activity then ID descending, 20 rows (max 50).
Messages are 1–2000 characters, cursor pages at most 40. Same actor/UUID/content
replays return the original receipt, including after closure; changed content is 409.
Version checks prevent stale closure/reopening; read receipts never reorder lists.
Support-side read position is shared by the support team, not a separate inbox per admin.

Data: support_conversations has unique user_id, status, version, last_message_id,
last_activity_at, user/support read positions, closed_at/closed_by and reopened_at,
plus Laravel created_at/updated_at. Separate messages and status audit events retain
history. Sender identities come only from authentication. No delete/history merge.

User events reuse the existing private-chat.sessions.{tokenId} connection with
event support.updated. Payload contains kind, current conversation snapshot and
optional message. Monotonic version guards snapshots; clients merge messages by ID.
Admin GET /admin/support/realtime and POST /admin/support/realtime/auth provide
private-support.admin.sessions.{tokenId}; authorization is exact current session.
Revoked/expired/blocked sessions are excluded at delivery. Reuse realtime workers;
HTTP remains the only write transport. Reconnect catches up using HTTP cursors.

Support replies create database notifications and queued generic Android push.
Payload uses support_conversation_id/support_message_id, never message text.
Flutter loads the support summary once per authenticated session, updates badges
from versioned socket snapshots, and reconciles on reconnect/entry. Zero is hidden.
Only visible incoming messages are acknowledged; closed unread replies remain visible
in the badge so closing cannot hide an unseen answer. No polling or automatic send retry.

Flutter: headset button replaces authenticated header language buttons, plus a More
entry; language stays on sign-in/account registration and in More settings. Shared
support page supports welcome, open, closed/reopen, offline/retry, older messages,
Arabic/English, RTL and large text. Routes and push taps remain account-authorized.

Validation: isolated SQLite API tests, Flutter controller/widget/transport tests,
full suites, Pint, flutter analyze, custom_lint. Apply only the additive support
migration to retained databases; do not run pending unrelated chat-merge migrations.
Deploy API/migration before Flutter and restart existing realtime/push workers.
No web UI files or real user messages are part of verification.

## Dashboard implementation in the next phase

Add a Chats page defaulting to status=open, newest activity first. Offer search
by conversation ID/account name/mobile, account role, status and unread filters.
Read the selected conversation and its cursor-paginated messages; acknowledge only
the displayed incoming boundary. Team unread position is shared across admins.
Reply using a UUID retained for uncertain retries. A close/reopen action submits
the latest version; a 409 requires refreshing the snapshot before another explicit
action. Never auto-close after sending a reply. Closed items leave the default list
and remain available under Closed/All; a user's explicit reopen returns them.

Subscribe through the administrator realtime endpoints using the current admin's
Bearer token. The channel belongs to that exact token. Reuse one connection, apply
snapshot versions, deduplicate messages, and catch up via after_id on reconnect.
Client socket events never send messages or change status. No web source files
were changed in this phase.

## Response example and client reconciliation

All routes retain the normal success/message/data envelope. A conversation data
object includes id, status (open/closed), version, unread_count,
user_read_through_id, support_read_through_id, last_message, created_at, updated_at,
last_activity_at, closed_at and reopened_at (UTC timestamps). Admin snapshots also
include user {id,name,mobile,type} and closed_by. User snapshots omit admin identity.
Messages contain id, sender_kind (user/admin), content, client_message_id, created_at.
Send returns {conversation,message}; timeline returns {conversation,messages,has_more}.
A new account's preview returns data:null. Neither GET nor Start creates records.

Flutter's support push callback may refresh the support summary as an event-based
fallback. It does not introduce notification list/count polling. Reading support
history does not mark unrelated notification inbox entries as read.

Local rollout on 2026-10-21 applied only
2026_10_21_180000_create_support_chat_tables.php. Existing pending marketplace-chat
consolidation migrations were not run. No seed/reset or real support message was sent.

## Verification results (2026-10-21)

The focused support/realtime/push suite passed: 36 tests, 300 assertions, including
12 support-specific tests. The complete API suite ran 633 tests / 17,218 assertions:
630 passed; the three existing failures remain in two AuthRateLimitTest cases
(expected 429, actual 200) and GeneralOrderDetailsTest's quantity-field assertion.
Support introduced no new failing backend tests. Targeted Pint and diff checks passed.
The additive migration also succeeded against the local database. This does not
claim a live admin-to-device delivery test; automated delivery tests use fakes and
Flutter's socket test uses a local WebSocket server.
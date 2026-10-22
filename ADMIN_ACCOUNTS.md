# Admin accounts and authorization — 2026-10-22

This supersedes earlier documentation that any non-blocked admin could access
every admin endpoint. Existing role values and passwords are preserved. Inactive,
blocked, archived and pending-password-setup accounts cannot access the protected
admin API, private media or support broadcasts.

Read `../OctoGear-admin/docs/ADMIN_ACCOUNTS_STEP.md` for the complete role matrix,
payloads, UI behavior, review order and rollout. Both projects keep Arabic/English.

## Roles

`Support/AdminPermissions.php` is the single permission matrix. AdminPolicy uses it;
every business admin route declares permission middleware. Catalog/payment/support
policies reuse it, media checks the corresponding read permission, and support
delivery reloads eligible roles/sessions. Permissions come from the current database
role/status, never from client input or a snapshot embedded in a token.

- admin: all implemented business and staff access.
- manager: dashboard/financial summary, user/store management, application reviews,
  order/payment reads, support and catalog CRUD. No staff management/cancel/refund.
- employee: customer/provider, store/document and order/quoted-price reads; support.
  Payment records and the financial dashboard are excluded.
- hr: read-only staff directory, role previews and security history.
- developer: vehicle company/model-name reads and CRUD only.

All active, configured roles retain their own profile/language/password and logout.
Profile adds `permissions` and `password_changed_at`. AdminOrderResource omits payment
without payments.view. Quoted order prices remain part of orders.view. A regression
test enumerates admin routes to detect an accidentally missing permission middleware.
Mobile user guards and business policies remain unchanged.

## Account API

GET/POST admin/accounts lists/creates accounts. GET admin/accounts/roles describes
roles. PATCH/DELETE admin/accounts/{admin} edits/archives. POST its reset-password
child replaces the temporary password. GET its events child paginates security history.
Lists/events have 25 rows; account filters combine search (name/email/mobile/ID),
role, status and page. Explicit resources never expose credentials or hashes.

Create accepts name, email, mobile, assigned_role, preferred_language, password and
the operator's current_password. Edit accepts account fields/status plus version and
current_password; password is prohibited. Reset accepts password/version/current_password;
archive accepts version/current_password. Saudi mobiles and emails are normalized.
The service locks the singleton security row then reloads/authorizes/password-checks
the actor and locks the target. Stale versions return 409. Own access changes are
prohibited; the last active Administrator with completed setup is protected. Email
uniqueness includes archived rows. Archival soft-deletes, retaining security events.

New/reset passwords expire in 24 hours. Login returns the existing `{token}` for a
configured account, or `{requires_password_change:true,challenge:<64-hex>,expires_in:600}`.
The latter issues no Sanctum token. Challenges contain 256 random bits, are stored
only as SHA-256, expire in ten minutes, and are replaced on another temporary login.
POST auth/admin/password-setup sends challenge/password/password_confirmation. It
locks account then challenge, rechecks status/expirations, rejects the old password,
consumes the challenge once, replaces the password and only then issues a session.

PATCH admin/profile/password requires current_password/password/password_confirmation.
It revokes all sessions and challenges; the browser returns to sign-in. Access edits,
resets and archival likewise revoke target sessions/challenges. Existing passwords
are not reset by migration. New passwords need 15+ characters and at most 72 UTF-8
bytes, avoiding bcrypt truncation without changing the existing hash driver. Browser
generation uses crypto.getRandomValues to produce 16 characters; there is no server
plaintext retrieval endpoint or email/SMS delivery.

AdminCredentialService owns admin credentials/session decisions; AuthService delegates
only adminLogin and preserves mobile OTP/registration. Account service owns access
transactions; repository owns filtered/paginated reads. Security events record
actor/target IDs, UTC timestamps, actions and before/after role/status only. Passwords,
tokens and challenges must never be logged, flashed or included in URLs/notifications.
Password operations have 5/minute IP throttling; login retains its separate limits.

## Deployment and checks

Apply `2026_10_22_180000_add_admin_account_security.php` before serving the updated
backend, then deploy the frontend and restart realtime workers. It adds security
metadata, a lock row, hashed challenges and events without changing role assignments.
Review current roles first: developer accounts now have catalog access only. Do not
automatically promote accounts. Run only this migration path on retained databases,
never a reset or unrelated pending migrations. Roll back application code before
dropping the new fields; migration rollback drops security events/challenges.

Email invitations/recovery, MFA, editable roles, archived-account restoration and
an operator recovery command are outside this slice. A sole Administrator's forgotten
password requires a separate controlled recovery procedure, not a public bypass.

Run AdminAccountSecurityTest plus existing auth/admin/language/catalog/payment/support
tests, then the full isolated SQLite suite, changed-file syntax/Pint and diff checks.
The web guide and PROJECT_DOCUMENTATION.md record actual results. Browser tests mock
runtime configuration and all API traffic; they do not use retained accounts.

### Verification record — 2026-10-22

Final focused run: 63 tests / 808 assertions passed. Changed PHP syntax and Pint
checks passed. Frontend typecheck/build passed; 108 browser tests passed, followed
by 9 admin scenarios on the final pagination-style build. The migration was applied
and confirmed only on the verified local MySQL database at 127.0.0.1, preserving
existing accounts. No remote deployment or seed/reset was performed by this slice.

The earlier full backend run had 637 passing and 7 failing tests out of 644
(17,350 assertions). All failures reproduce with the original committed AuthService:
two AuthRateLimitTest OTP-throttle cases, GeneralOrderDetailsTest quantity exposure,
SellerApplicationTest OTP exposure, and three TestingOtpTest exposure/opt-out cases.
The unchanged OtpService returns codes unconditionally, including production-mode
tests. That existing security defect must be resolved before production sign-off.
This slice preserves customer OTP behavior and does not claim a green full suite.
Review the web PROJECT_DOCUMENTATION.md for limits and deployment sequencing.

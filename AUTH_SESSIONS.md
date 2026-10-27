# Expiring access and rotating refresh — 2026-10-23

Owner policy: access tokens last 10 minutes; a login lasts at most 14 days from
authentication, with no short inactivity logout. Both clients use this policy.
Refreshing never extends the absolute deadline. Server time is authoritative.

Sanctum continues authenticating API requests. AuthSessionService owns issuance,
locked rotation and revocation; AuthSessionRepository owns persistence queries.
Each login has an auth_sessions row; its tokens reference that row. Refresh secrets
have 256 random bits and only SHA-256 hashes are retained. A consumed token's exact
request_id can recover an encrypted response for 60 seconds, provided its successor
has not been consumed. Other reuse revokes the whole login. This bounded receipt
is for a lost response, not a general replay grace period. Cleanup erases receipts.
Lock order is account -> session -> refresh token. Rejection commits revocation.

Existing mobile OTP/register and admin login/password-setup success payloads retain
token and add token_type, expires_in, access_expires_at, refresh_token, session_id,
session_expires_at. Pending admin setup still issues neither token. Refresh uses
POST auth/refresh {refresh_token,request_id:UUID}; POST auth/session/logout accepts
{refresh_token} even after access expiry. Existing authenticated logout remains.

Admin web sends X-Auth-Client:web on login/setup and uses POST auth/admin/refresh
{request_id:UUID}, POST auth/admin/logout {} with credentials. Its refresh secret
is only a Secure HttpOnly host-only cookie scoped to /api/auth/admin. Access remains
in browser memory. Exact Origin validation and the required non-simple header
protect every cookie-setting/consuming endpoint against CSRF, including login.
CORS never uses wildcard credential origins. Native/admin API clients retain body
credentials and cannot use browser-session refresh secrets through mobile routes.

The first access-token ID is the stable channel/push anchor for the login. Rotation
retains that row until session cleanup; its secret still expires normally. Channel
authorization uses the anchor ID; delivery checks login expiry/revocation rather
than the anchor's access expiry. Reuse/logout/password/access changes revoke all
access and refresh credentials in the affected login(s), removing device bindings.
An expired access token alone does not disable background push or a live socket.

Deployment: apply only 2026_10_23_070000_create_auth_sessions.php, then API, web,
Flutter and restart push/realtime workers. No database reset, seeds or history
merge. Existing tokens get an expiry no later than 14 days after migration and
cannot gain refresh credentials; users sign in once with updated clients.
AUTH_ACCESS_MINUTES and AUTH_SESSION_DAYS default to 10 and 14. Set
AUTH_BROWSER_ORIGINS to the exact deployed web origins (comma-separated; no paths),
including explicitly needed Firebase hosting origins. Production requires HTTPS.
AUTH_COOKIE_SECURE defaults true; AUTH_COOKIE_SAME_SITE defaults none for the
current cross-site Firebase/Bluehost topology. For reliable browser privacy-mode
support deploy admin/API under the same site or a same-origin proxy and use lax.
Third-party-cookie blocking cannot be bypassed with CORS or JavaScript storage.
Local HTTP requires AUTH_COOKIE_SECURE=false and AUTH_COOKIE_SAME_SITE=lax.

Run auth-sessions:prune through the scheduler. Do not separately run Sanctum's
generic expired-token pruning command: active login anchors must be retained.
Clients serialize refresh, reuse a request UUID after uncertain transport failure,
retain credentials while offline, and never blindly replay uncertain writes.
Private media and socket authorization also obtain fresh credentials. Login
replacement/logout invalidate pending work so stale refresh cannot resurrect access.

Verification and final rollout status will be recorded after implementation.

# Android customer push notifications

Implemented on 2026-10-08. Android customer offers and incoming chat messages use
Firebase Cloud Messaging HTTP v1. The existing notification inbox remains the
source of truth. Payments, SMS and iOS are outside this change.

## Configuration

1. Install the locked Composer dependencies (`composer install`). Google’s
   official `google/auth` library handles OAuth; no legacy Firebase server key.
2. Run `php artisan migrate` to add session/locale/freshness fields to
   `device_tokens` and create `jobs`/`failed_jobs`. Existing rows are preserved;
   legacy rows without a registered login session are not used for delivery.
3. Enable the FCM HTTP v1 API for `octogear-1d72b`. Grant the server identity the
   Firebase Cloud Messaging API Admin role on that project. Use workload identity
   / Application Default Credentials on a supported host, or store a service
   account JSON file outside source control and the web root with restricted
   filesystem permissions. Never put this key in Flutter, Remote Config or chat.
4. Set the following environment values and reload Laravel configuration:

   ```dotenv
   FCM_ENABLED=true
   FCM_PROJECT_ID=octogear-1d72b
   FCM_CREDENTIALS=C:/Secrets/OctoGear/service-account.json
   FCM_QUEUE_CONNECTION=database
   ```

   The path is an example. Leave `FCM_CREDENTIALS` empty when using ADC. The
   existing Android `google-services.json` is client configuration, not a server
   credential. Set `FCM_ENABLED=false` to suspend sending without losing inbox
   entries. Do not select the `sync` queue connection for push.
5. Run a supervised worker: `php artisan queue:work database --queue=push --tries=4 --timeout=40`.
   Queue retry_after must exceed 40 seconds (the database default is 90). Restart
   workers after deploying/configuring changes. Monitor `queue:failed` and resolve
   credentials/project/payload errors before retrying failed jobs.

## API and ownership

- `POST /api/push/device`: authenticated customer bearer session; body
  `{ "token": "<FCM token>", "platform": "android", "locale": "ar" }`.
  Locale accepts `ar`/`en`; token length is at most 512 characters. The response
  uses the existing success envelope with null data. It never echoes a token.
- `DELETE /api/push/device`: remove only the current login session’s subscription.
- Existing `POST /api/auth/logout` removes the current subscription and revokes
  its Sanctum token atomically. Other signed-in devices are retained.

Requests authorize through DeviceTokenPolicy before Form Request validation.
The service owns transactions; DeviceTokenRepository owns registration and
active-device queries. A login has one current device token. Rotation replaces
it; another login claiming the same installation token transfers its ownership.
Token values are hidden from model serialization and excluded from queued jobs.
FCM tokens are stored only in `device_tokens`. The cleanup migration removes the
legacy `users.device_token` column without changing users, sessions or active push
registrations. Signup no longer reads or stores an FCM token; old clients sending
the retired signup field are accepted, and that extra field is ignored. Device
registration happens through the authenticated push endpoint after login.
Keep `user_id` for direct ownership queries and `personal_access_token_id` for
session cleanup and expiry checks. Delivery verifies that the session belongs to
the same user. Deploy the updated backend code with the cleanup migration before
the new Flutter build. Migration rollback restores an empty nullable legacy
column; discarded legacy token values are not restored.

## Delivery guarantees and limits

The single NotificationSent listener handles the database channel for
NewOfferNotification and NewMessageNotification, for customer recipients only.
It queues one job per active registration after the database transaction commits.
Database inbox creation remains synchronous; no FCM HTTP call runs in a customer
or provider request. Registering a device does not send historical notifications.

Workers recheck the recipient, login ownership/expiry, block status, read state,
registration freshness and notification age. Read/deleted notifications and
notifications older than one day are skipped. Registrations not seen for 60 days
are excluded; reopening/re-registering the app renews them.

Delivery retries use 60/120/300-second backoff and honor FCM Retry-After. A 401
clears cached OAuth credentials for the next attempt. Permanent payload/permission
errors fail the job. Only explicit FCM UNREGISTERED errors delete a registration;
a generic HTTP 400/404 does not. Conditional deletion protects token rotation and
account reassignment. HTTP responses and tokens are excluded from error messages.

FCM data is an allowlist of string IDs: notification_id, recipient_id, type,
order_id/offer_id or conversation_id. Lock-screen text is a generic Arabic/English
offer/message alert; no chat content, prices or personal names are included.
Android uses one high-importance channel, a monochrome small icon, private
lock-screen visibility and the notification UUID as its replacement tag.

Delivery is at least once: a worker/network failure after FCM accepts a message
can cause a retry. Android tags and Flutter event deduplication reduce duplicate
presentation; they do not promise exactly-once transport. Force-stopped apps and
OS notification restrictions can prevent delivery. Already-delivered/in-flight
alerts cannot always be recalled on offline logout; payloads remain generic and
Flutter rejects taps belonging to another account.

## Flutter behavior

Permission is requested once after authenticated customer login. Customers can
open Android notification settings from the inbox. Login, token rotation and
locale changes synchronize registration. Resume rechecks device permission/token
and retries a failed registration without starting an inbox fetch or timer.
Unchanged successful registrations do not issue repeat registration requests.
Logout attempts server removal, clears displayed Android notifications, disables
FCM auto-init, invalidates the installation token and revokes the login before
clearing local session storage. Offline failures do not block local sign-out.

Foreground pushes show a localized in-app banner. Android displays background
notification messages itself; Flutter does not duplicate those notifications or
fetch data in a background handler. Cold-start taps wait for session restoration.
Only known, validated IDs navigate through the app’s typed routes; destination
APIs still enforce ownership and handle unavailable resources.

There is **no notification polling**. Opening the inbox and explicit refresh load
its data. User-selected filters/pagination and read actions remain available.
Home, More, elapsed time, app resume and incoming pushes do not fetch notification
lists or counts. The badge uses the last loaded inbox count plus locally received
pushes; it starts at zero until that session has data, and resynchronizes when the
inbox is opened/refreshed. The legacy unread-count API remains for other clients.

## Verification and activation status

Automated tests cover ownership, multiple devices, rotation, revocation/expiry,
FCM payloads, localization, invalid-token cleanup, retries, account switches,
cold-start handling, malformed targets and absence of automatic inbox requests.
Tests use SQLite in memory, fake HTTP and fake messaging; they do not contact FCM.
Verification: 48 focused Laravel tests passed (265 assertions), including 12 new
push tests. The full Laravel run had 432 passing tests and the same eight existing
order lifecycle, OTP rate-limit and seed-count failures recorded before this work.
Flutter's full run passed 320 tests; the final notification checks additionally
passed the new no-fetch-on-resume and foreground-banner navigation regressions.
Flutter analysis, custom lint, Pint and the Android debug build passed.

The local additive migrations have been applied. Live sending remains disabled
because server credentials were not configured during implementation. After
configuration, use two test accounts and a Google Play-enabled Android device to
verify new offer/message delivery in foreground, background and normally closed
states, tapping destinations, permission denial, rotation and logout. A Firebase
Console test alone does not verify the Laravel queue pipeline.

References: [Firebase Flutter reception](https://firebase.google.com/docs/cloud-messaging/flutter/receive-messages),
[HTTP v1 authentication](https://firebase.google.com/docs/cloud-messaging/send/v1-api),
[token management](https://firebase.google.com/docs/cloud-messaging/manage-tokens).

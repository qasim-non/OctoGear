# Chat: HTTP writes and WebSocket updates

Messages continue through the authenticated HTTP POST endpoints, using
`client_message_id` for safe retries. Laravel validates and authorizes the sender,
saves the message, and queues `BroadcastChatUpdate` **after the database commit**.
The existing notification/FCM flow remains separate.

Flutter receives `chat.updated` events through Laravel Reverb. Messages merge by
server ID, including when the WebSocket echo arrives before the POST response.
Read receipts use the same event with `kind: read`. Every event includes a prepared
conversation snapshot with the latest preview and participant unread counts.
The inbox merges it locally, discarding older snapshots, so receiving a message
or receipt does not trigger another GET. Neither screen periodically polls HTTP.

Opening a screen, manual refresh, loading history and reconnect catch-up use GET.
On reconnect the conversation drains forward timeline pages from its last HTTP
cursor; live events cannot advance that cursor and accidentally skip messages.
Timeline responses include `read_through_id` for the viewer's outgoing messages,
so missed read receipts recover even when no new messages were sent.

## Ownership and authorization

- `GET /api/chat/realtime` returns enabled state, public WebSocket URL/app key and
  the current session's channel. It never returns the Reverb secret.
- `POST /api/chat/realtime/auth` accepts `socket_id` and `channel_name` using the
  current Sanctum bearer token and the normal API response envelope. A Form
  Request and policy authorize the exact `private-chat.sessions.{tokenId}` channel
  before Laravel's Reverb driver signs the subscription.
- Broadcast jobs reload the conversation, message and active participant sessions
  at delivery. Expired/revoked logins and blocked recipients are excluded.
- Session IDs and the Reverb app key are identifiers, not authentication secrets.
  Flutter holds only its normal bearer token and a per-socket subscription signature.
- Client events are disabled: clients cannot publish chat messages over the socket.
- Native sockets omit Origin. The default `REVERB_ALLOWED_ORIGINS=*` supports
  mobile clients; signed private subscriptions enforce access to message data.
  Restrict origins for a browser-only deployment if appropriate. An Origin filter
  alone does not authorize a user.

Controllers coordinate HTTP, policies authorize, Form Requests validate,
`ChatRealtimeService` schedules delivery, and `ChatSessionRepository` owns the
recipient query. Flutter separates transport/data parsing, domain events and
Riverpod presentation state. A shared connection follows the authenticated
session while chat providers are active; it pauses in the background and closes
on logout/disposal. Failed connections retry with backoff. Socket heartbeats are
protocol traffic, not API polling. An offline indicator preserves access to
history and manual refresh; HTTP sending can still work during a socket outage.

The customer application holds the shared connection across tabs to show message
activity outside Chats. Message banners and the new-activity dot are suppressed
while Chats (including an offer conversation) is visible. Visiting that tab clears
the dot without marking all messages read. FCM and socket alerts deduplicate by
the stable message ID; persistent database notifications and background Android
push continue independently. Opening a message alert selects the Chats tab.

Expected HTTP traffic: initial data on entry, user refresh/history loading, and
catch-up after an actual socket reconnect. Sending is one POST; receiving while
reading can cause a PATCH read acknowledgement. Live message/receipt updates do
not refresh the conversation or inbox. Older servers without snapshot payloads
use an event-triggered inbox refresh as a compatibility fallback. Restart queue
workers when deploying this payload change, then rebuild/restart Flutter.

## Local setup

Install locked dependencies with `composer install` and Flutter `flutter pub get`.
Set unique `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` in the ignored
Laravel `.env` (generate the secret with `bin2hex(random_bytes(32))`). Set:

```dotenv
CHAT_REALTIME_ENABLED=true
CHAT_WEBSOCKET_URL=ws://127.0.0.1:8080
CHAT_REALTIME_QUEUE_CONNECTION=database
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=*
```

Apply outstanding migrations normally (`php artisan migrate`); this change adds
no migration and reuses the existing `jobs`/`failed_jobs` tables. Run these as
separate long-running processes alongside the API:

```sh
php artisan reverb:start
php artisan queue:work database --queue=realtime --sleep=1 --tries=4 --timeout=30
```

For this Android emulator's existing localhost API setup:

```sh
adb reverse tcp:8000 tcp:8000
adb reverse tcp:8080 tcp:8080
```

Repeat reverse forwarding after reconnecting/restarting the device. For a phone
over Wi-Fi, configure a device-reachable URL and listener/firewall appropriately.
Rebuild the Flutter app for the new dependency; hot reload alone is insufficient.

### Physical phones using the Firebase API URL and a tunnel

Firebase Remote Config's `api_base_url` selects the HTTP API only. The app obtains
the WebSocket URL separately from `GET /api/chat/realtime`. A tunnel forwarding to
port 8000 does not forward the Reverb service on port 8080. Returning localhost in
`CHAT_WEBSOCKET_URL` makes a physical phone connect to itself, even when login and
HTTP chat requests work through the API tunnel.

For an approved temporary phone test, run a separate tunnel for Reverb:

```powershell
cloudflared tunnel --protocol http2 --url http://127.0.0.1:8080
```

Set `CHAT_WEBSOCKET_URL=wss://<the-new-host>.trycloudflare.com:443` in Laravel's local
`.env`, then run `php artisan config:clear`. Keep `REVERB_HOST=127.0.0.1` for
internal broadcasts. Leave Firebase's API URL and the existing API tunnel alone.
Keep both tunnels, Reverb and the realtime queue worker running. Reopen the phone
app to obtain the corrected configuration. The WebSocket endpoint is publicly
reachable; subscriptions still require the authenticated session's signed private
channel. Never put the Reverb secret or a user's bearer token in a URL.

Quick tunnel hostnames change when restarted; update the environment value then.
Use a stable WSS hostname and supervised processes for a permanent deployment.

If `/app/<reverb-app-key>` appears in `php artisan serve` logs alongside `/api/*`,
the WebSocket handshake is reaching the HTTP API server instead of Reverb. Do not
copy the API tunnel hostname into `CHAT_WEBSOCKET_URL`: use the hostname from the
separate tunnel forwarding to port 8080, keep `:443`, and clear Laravel's config.
An old tunnel reporting `Unauthorized: Tunnel not found` must be replaced; merely
leaving its process running does not restore that hostname. Verify the replacement
with a WebSocket handshake and signed private subscription before testing the phone.

Flutter suppresses the old disconnected text; a small accessible reconnect icon
appears only after ten continuous seconds without a connection and clears on
recovery. This presentation delay does not send API requests.

Phone connectivity verification (2026-10-09): after explicit approval, a separate
Cloudflare quick tunnel was started for Reverb and the local public URL was changed
to WSS. A connection through the public endpoint passed the protocol handshake,
signed isolated private-channel subscription and ping/pong checks without sending
user messages. Ten focused Flutter tests and targeted analysis passed, including
the reconnect-indicator grace period and recovery. Reopen existing phone builds
to fetch the new URL; the quieter indicator requires an updated build.

The first phone rollout exposed a client validation bug: Dart's `Uri.port` is 0
for a `wss://` URL with no explicit port, so the old parser rejected a valid URL
before opening a socket. The local URL now includes `:443` for compatibility with
already installed builds. Updated Flutter builds accept omitted standard ports,
normalize WSS to 443 and WS to 80, and still reject insecure production URLs and
explicit ports outside 1–65535. The regression test first reproduced this failure.

Follow-up verification: all 27 Flutter chat tests and final targeted analysis
passed. A public-tunnel smoke check using the actual app configuration parser
passed WSS connection, signed isolated subscription and ping/pong. During the
check the old quick tunnel expired after a long host interruption; it was replaced
and the local `.env` updated with the confirmed new hostname and explicit port
443. An existing phone build can consume that compatible URL after reopening.

## Production

Use `wss://chat.your-domain` for `CHAT_WEBSOCKET_URL`, behind a TLS reverse proxy
that forwards WebSocket Upgrade/Connection headers and allows long connections.
Flutter staging and production reject insecure `ws://` URLs. Keep Reverb's
internal HTTP broadcast endpoint private; `REVERB_HOST/PORT/SCHEME` describe the
Laravel-to-Reverb connection and can differ from the public URL.

Supervise both Reverb and a dedicated `realtime` queue worker with restart on
failure. Keep the existing `push` worker running separately. After deploying
code/configuration, refresh configuration/event caches and restart both long-lived
processes (`php artisan reverb:restart`, `php artisan queue:restart`, under your
process supervisor). Monitor worker failures and `php artisan queue:failed`.
After an outage, retry relevant failed jobs; clients deduplicate repeated messages.
Use Reverb's Redis scaling configuration if multiple Reverb servers are required.

The current local `.env` is enabled; production credentials, TLS and process
supervision must be configured on the deployment host. No production deployment
is performed by this repository change.

## Verification

`ChatRealtimeTest` covers authenticated configuration, session channel isolation,
revocation, blocked/expired recipients, idempotent sends and read receipt recovery.
`ChatBroadcastTransactionTest` verifies real commit/rollback queue behavior using
the isolated test database. Flutter chat tests cover direct delivery, duplicates,
out-of-order receipts, forward pagination and missed receipt catch-up. A transport
test uses real sockets to exercise the Pusher handshake, reconnect and pause.

Local verification on 2026-10-09: 64 focused Laravel tests passed (457 assertions),
and Pint passed. The full backend suite passed 447 tests with eight existing
failures in auth throttling, order completion/cancellation and demo seed counts.
Flutter's full suite passed 329 tests; after adding the idle-no-polling check, all
23 chat tests passed. Analysis of the final chat files and custom lint are clean.
The Android x64 debug APK built successfully. A live native socket smoke check
verified a signed private subscription and Laravel-to-Reverb broadcast without
creating user data or sending any user a test message.

References: [Laravel Reverb](https://laravel.com/docs/12.x/reverb),
[Laravel broadcasting](https://laravel.com/docs/12.x/broadcasting),
[dart_pusher_channels](https://pub.dev/packages/dart_pusher_channels).

The subsequent navigation/notification/request-reduction update passed 43 focused
backend tests, with the final delivery/push subset passing 18 tests (100 assertions).
Flutter's full run passed 332 tests with one old URL expectation; all eight order
navigation tests passed after updating that fixture. The final eight realtime
unit tests also pass, including participant snapshot decoding. Alert tests cover
socket/FCM deduplication, own-message echoes, suppression in Chats, tab navigation
and no notification inbox polling. Final targeted analysis, custom lint, Pint and
the Android debug build pass.
The running emulator's open conversation made zero conversation/inbox GETs during
a 45-second idle observation. All 14 notification-inbox widget tests pass after
making message notification taps switch to the Chats branch as well.

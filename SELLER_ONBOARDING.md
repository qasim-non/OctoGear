# Seller registration — 2026-10-10

The customer app needs a complete registration, review-status and correction flow.
Approved account behavior: submission and rejection retain the customer role.
Admin approval atomically creates the active store and promotes its owner to
service provider. The next authenticated GET /profile (startup, app resume, or
explicit status check) opens the existing provider shell. Customer/provider dual
roles are out of scope. The existing provider shell is not the complete inventory dashboard.

Existing authenticated customer endpoints verify a different store mobile and
accept multipart business/store details. Store OTP responses now optionally expose
data.test_otp under the same local/staging opt-in rule as login, with no-store
headers and login-equivalent throttling. Production never returns a code.

GET /seller-application returns the authenticated customer/provider's latest
application, or null. GET /provider/store-requests and its owner-authorized detail remain available.
Resources additionally expose rejection_reason. POST
/customer/seller-application/{storeRequest}/resubmit is customer-only and owner-authorized;
the equivalent provider resubmission route supports existing provider requests:
rejected -> pending, retaining the verified mobile and existing document unless a
replacement image is supplied. Store/business fields are revalidated; the row is
locked and rejection metadata reset. Pending/accepted requests cannot be edited.
The wireframe's vehicle manufacturers are submitted as optional company_ids for
backward compatibility, validated against active cars_companies rows, stored as a JSON array
on store_requests, and copied to store_companies on approval. The additive nullable
migration preserves existing applications; no role backfill modifies existing providers.
Administrative approval creates the active store; it is not performed by the app.

No submission is automatically retried. After an uncertain first submission the
client reads GET /seller-application; after an uncertain correction it reads the same endpoint.
The client must reconcile before another attempt. A failed first upload may consume
the one-time token, so the customer verifies the store number again before retrying.
The server also checks the locked user's customer role to prevent concurrent first
applications. Tests cover OTP exposure, production exclusion, ownership, transitions,
retained documents/mobile, validation, and admin approval after correction.

Verification: the 49 focused onboarding/approval/media/OTP tests pass with 262
assertions. The full API suite reports 457 passed and eight failures outside seller
registration: order completion/cancellation expectations, login rate-limit
expectations (three requests versus the configured six), and demo seed counts.
These existing contracts were not changed as part of seller registration.

Deploy the additive migration and updated API together with the client. The migration
has been applied to the local development database; no remote deployment was performed.

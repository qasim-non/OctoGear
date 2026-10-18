# Replaceable integrations

Implementation scope (2026-10-19): introduce injected SMS/payment contracts and configurable adapters, install the S3 filesystem adapter with a private image disk, and share order amount/commission calculations. Preserve existing OTP exposure, payment/stock transactions, API responses, media authorization and recorded disks. No live delivery, payment activation or file migration is part of this change.

## SMS

`OtpService` generates, hashes, expires and verifies codes for both login and store verification. It delegates delivery to `SmsSender`. `config/sms.php` selects an adapter using `SMS_DRIVER`; the `log` adapter preserves the previous behavior (logs only in local, sends nothing). It is not a production SMS provider.

To connect a provider, implement `SmsSender`, register its class in `sms.drivers`, put its credentials in configuration/environment variables and select it. Do not add vendor HTTP calls to controllers, auth or onboarding services. The adapter must throw on rejected delivery, use bounded HTTP timeouts and avoid logging credentials, mobile numbers or OTPs. Verify the vendor's delivery/error behavior in its sandbox before activation. Unknown driver names fail instead of silently falling back.

## Payments

`PaymentService` owns order eligibility, durable payment records, stock and notifications. `PaymentGateway` owns external charge/refund calls, selected through `config/payments.php` and `PAYMENT_DRIVER`. The existing `stub` adapter remains a simulation. Charge/refund commands contain SAR minor-unit amounts and stable, operation-specific idempotency keys. Successful return means the operation is confirmed; exceptions must never be used to represent an unresolved payment as a definitive rejection.

Adding a live vendor is more than setting a key: implement its adapter, persist gateway/payment references, distinguish uncertain outcomes, implement signed webhooks/reconciliation, test 3DS/tokenization and refund recovery, and complete the client checkout. The current synchronous stub lifecycle is preserved and must not be enabled for real money. Historical payments will need their originating driver/reference recorded before a live vendor switch. The interfaces are the replacement boundary, not a claim that the live workflow is complete.

Provider resources receive prepared amount summaries. `OrderPricingService` owns the shared charge calculation and percentage rounding. Paid payment rows remain authoritative; commission remains an estimate rather than a payout ledger.

## Private pictures on S3

The S3 Flysystem dependency and `images_s3` disk support the existing `ImageStorageService`. Set `IMAGE_DISK=images_s3` and the `AWS_*` settings listed in `.env.example`; leave credentials out of the app and source control. IAM role credentials may replace explicit keys. Feature-specific disk overrides still take precedence.

Use a private bucket with Block Public Access and least-privilege read/write/delete permissions for the configured prefix. `AWS_IMAGE_ACL` defaults to `bucket-owner-full-control`, accepted by AWS buckets using Bucket owner enforced ownership (ACLs disabled); configure the equivalent supported ACL when using another S3-compatible provider. This follows [AWS Object Ownership guidance](https://docs.aws.amazon.com/AmazonS3/latest/userguide/about-object-ownership.html). `AWS_ENDPOINT` and path-style settings support compatible endpoints. This does not make arbitrary non-S3 image services interchangeable without an adapter. The dependency follows [Laravel's S3 driver setup](https://laravel.com/docs/12.x/filesystem#s3-driver-configuration).

The application role needs `s3:GetObject`, `s3:PutObject` and `s3:DeleteObject` on the image prefix, plus appropriately scoped `s3:ListBucket` so missing objects can be distinguished from access failures. Encrypted buckets may also require their KMS permissions.

New uploads use the selected disk. Existing rows keep their recorded disk/path; reads, deletes, rollback cleanup and approval copies honor that metadata. Keep old disks and their files available. Changing the default does not migrate existing files. A migration requires verified copies and metadata updates, and is outside this change. Do not expose raw object URLs or make the bucket public: customer/provider apps continue using authenticated media API routes.

After a disk has stored images, treat its bucket, prefix and endpoint as part of its identity. For a later move to another bucket/provider, define a new named disk and select that name; retain the old disk configuration. Repointing `images_s3` to a different bucket would also redirect all old records referencing that name. Rotating credentials for the same storage location is safe once permissions are verified.

After deployment, rebuild Laravel's configuration cache and restart long-lived workers. Verify upload, authorized read, unauthorized rejection, replacement/copy and cleanup against the actual bucket before rollout. Automated tests use a mocked S3 client, never a live bucket.

## Other boundaries and activation

Push already uses its shared FCM client and configuration; chat already separates HTTP business actions from its configured broadcast transport. Database/cache/queue/mail use Laravel configuration. Switching providers with different protocols still requires an adapter and provider-specific verification. Flutter API/realtime environment configuration and Firebase platform setup remain separate deployment settings.

Flutter upload validation constraints are centralized in `ImageUploadPolicy`; Laravel remains authoritative through `config/images.php`. They are separate runtimes: changing upload policy requires keeping both values aligned, updating explanatory translation text and rebuilding the app. No dynamic upload-policy endpoint is introduced.

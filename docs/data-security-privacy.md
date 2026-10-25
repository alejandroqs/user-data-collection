# Data, security, and privacy

**Answers:** Which sensitive data is processed, where it goes, which source-level controls exist, and which privacy or security capabilities are absent or unverified.

**Read when:** Changing fields, schema, validation, permissions, consent, credentials, data destinations, retention, export, or deletion.

**Canonical sources:** `UDC_Activator`, `UDC_Shortcode`, `UDC_Validation`, `UDC_Backup`, `UDC_Settings`, `UDC_GDrive`, and `UDC_Email_Sync`.

**Update when:** A data category, destination, validation rule, access rule, consent record, credential, retention rule, or deletion path changes.

**Out of scope:** Operational procedures and release packaging; use [Operations](operations.md) and [Release](release.md).

This document describes current source behavior. It is not a legal assessment and does not certify GDPR compliance or any other regulatory status.

## Data categories

`UDC_Activator::activate()` defines the `{prefix}udc_submissions` table with:

- identity and contact: first name, last name, date of birth, address, city, postal code, and phone;
- health-related answers: general health, treatment, blood-thinning medication, allergies, and pregnancy or breastfeeding;
- appointment and service details: appointment date, time, and piercing location;
- consent and operational state: `liability_accepted`, generated `id`, and `created_at`;
- form metadata: `form_type`, `piercing_category`, `form_language`, `form_version`, and `form_evidence`.

The liability field is stored as a boolean/tinyint. The original form and historical rows do not store presented consent text/version/language. The new piercing form stores those alongside the selected care category.

## Explicit health answers and piercing evidence

**Verified from source:** both forms require each health answer to be exactly 0 (No), 1 (Yes), or 2 (Not sure / discuss in person). Missing, empty, array and other values are rejected before insertion. Existing 0/1 records retain their original meaning; this migration does not recover previously omitted answers. Detail views render 2 as a discussion response, not as Yes.

UDC_Piercing_Form::snapshot() captures the rendered consent, information/risk acknowledgement, care acknowledgement, data-processing notice, care text, document version, and interface language. A server HMAC authenticates that hidden payload on submission; all four acknowledgements and an allowlisted category are required. The stored snapshot preserves the text even if translations later change. This HMAC prevents client edits to the rendered text; it is not a client identity check or electronic signature. The insertion timestamp records receipt, not proof that care was read or that professional review occurred. The source copy is French; bundled defaults for the existing locales are described in [Development and quality](development-and-quality.md). Client wording review remains pending.

**Requirement / pending client review:** approve the new consent/care wording, health questions, minor/representative procedure, signature requirements, and the site's privacy notice before publication. No automatic medical eligibility decision or waiver of statutory rights is inferred from acceptance. Existing tattoo wording has been preserved at the user's request.

Care draft reference sources: [APP body piercing aftercare](https://safepiercing.org/aftercare/) and [APP oral piercing aftercare](https://safepiercing.org/oral-aftercare/). The draft is not a clinical protocol or a guarantee of healing.

**Verified from source:** care PDF downloads contain public guidance, category, language, document version and the automatic WordPress home URL; no submitted identity, contact, health or consent data is included. PDFs are generated on demand without adding records or persistent PDF files. Request-specific engine/font artifacts use the WordPress temporary directory and are removed in a finally block. Hosting controls over that directory remain an operator responsibility. The public provider registry must not be used for personal submission exports; those need a separate authorized controller and the stored versioned evidence. The shared renderer can be reused by that future private controller.

## Validation and result review

**Verified:** new submissions reject impossible/future birth dates, dates outside the MySQL DATE range, and birth after the procedure date. Both browser birth-date fields have matching lower/current-date bounds. Text length is checked in Unicode characters rather than UTF-8 bytes, and restore uses the same character limits for identity/contact/location fields. Evidence/metadata byte limits are retained. Existing records are not retroactively edited.

Public result messages now require a short-lived HMAC receipt from the handler. Receipts contain only the service-specific status and expiry; no identity, health, record ID or contact values are placed in the URL. A copied valid receipt can be replayed within its five-minute lifetime; it is UI feedback, not identity or authorization evidence. Nonces, capabilities and the submission budget are retained.

The piercing form no longer exposes website/review destination settings or promotional links. The PDF footer derives its URL from WordPress home_url('/'); legacy option values cannot override it. The shared renderer still escapes and restricts any document provider's website link to HTTP(S).

## Retired approval state

**Verified from source:** version 1.6.0 omits is_confirmed from the schema, submissions, new backups, and administrative views. UDC_Activator::activate() inspects the table after dbDelta and drops only that retired column if present. If inspection or deletion fails, the stored schema version is not advanced, allowing a later load to retry. Existing submission rows and all other fields are retained; prior approval values are discarded. Historical backups keep their original contents, but restore validates and discards the retired boolean before insertion. See [Operations](operations.md) for accepted backup schemas.

**Unknown:** this upgrade has not been executed against a live WordPress database.

## Data destinations

The current flow is:

```text
Public form -> custom database table -> local JSON backup -> optional Google Drive or email attachment
```

`UDC_Backup::create_backup()` serializes all rows to JSON under the uploads directory. `UDC_GDrive::sync_backups()` can upload those files to a configured Drive folder. `UDC_Email_Sync::send_backup()` can attach the newest local JSON file to an email. These destinations create copies outside the primary table and may have different access, retention, and deletion controls.

## Existing controls

**Verified:** the form and administrative AJAX handlers use WordPress nonces. Administrative endpoints check `manage_options`. Submission and restore writes use `$wpdb->insert()`, and many administrative outputs use WordPress escaping functions. Sortable list columns are constrained by an allowlist in `UDC_List_Table`.

These are source-level controls, not evidence of complete security or privacy compliance.

`UDC_Shortcode::handle_submission()` counts attempts only after nonce, scalar structure, health-answer/acknowledgement, required-field, length, date, and time validation, using a filterable transient lifetime of ten minutes per observed `REMOTE_ADDR`. The transient key uses an HMAC of that address; the raw address is not stored or logged. The default budget is five attempts. Each counted attempt resets the transient lifetime; this is an inactivity-based expiry, not a fixed window. Rejected field values do not consume this budget. The transient counter is non-atomic and is not protection against distributed sources. Counter failures fail closed, and an exceeded budget returns HTTP 429.

`UDC_Settings::register_settings()` supplies explicit setting types, defaults, and sanitizers. Email values, Drive folder IDs, service-account JSON structure, and design color tokens are validated before storage. An administrator can clear the configured credentials by submitting an empty or whitespace-only value; while configured, the service-account JSON and private key remain stored in a WordPress option pending an operator decision about secret storage.

## Known limitations

- The public form remains unauthenticated and uses a nonce for request integrity, not authentication. The attempt budget is an abuse-control measure, not proof of identity or a complete anti-spam system.
- Local JSON backups are stored under `wp-content/uploads/udc-backups/`. `UDC_Backup::secure_directory()` writes an `.htaccess` rule and `index.php`, but `.htaccess` protection depends on the web server and does not by itself establish encryption or controlled access.
- Restore and upload paths are bounded to 10 MiB and 10,000 rows, use an allowlisted schema and field validation, reject mixed current/legacy schemas, and use a transactional additive import when the table is InnoDB. Duplicate positive IDs remain skipped.
- Design values are restricted to the supported color-token grammar before their escaped inline CSS output is generated.
- `udc_gdrive_json` stores the service-account JSON and `UDC_GDrive::get_token()` reads its `client_email` and `private_key` from that option. The current plugin does not provide a separate secret-management or encryption-at-rest layer.
- A source search of the current plugin found no `wp_privacy_*` exporter or eraser registrations. Individual WordPress privacy export and erasure workflows are therefore not established by this plugin.
- `UDC_Settings::ajax_delete_all_data()` deletes rows from the local table, removes local JSON files, and deletes selected plugin options. It does not delete Google Drive files, email attachments, or copies retained by external systems. It also does not constitute a documented individual-subject erasure process.
- The source does not define a submission-retention policy. Local and external rotation behavior is operational behavior, not a complete privacy-retention policy.

## Requirements for future changes

Treat health answers, identity, consent, backups, email attachments, Drive copies, and credentials as sensitive. Document any new destination, column, deletion path, validation rule, or access rule here and in [Operations](operations.md) where operational behavior changes. Keep legal conclusions separate from implementation evidence.

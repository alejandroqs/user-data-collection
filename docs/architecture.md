# Architecture

**Answers:** What runs, when it runs, and how the plugin components exchange data.

**Read when:** Changing components, hooks, lifecycle, entry points, administrative actions, or cross-component flows.

**Canonical sources:** `user-data-collection.php`, hook registrations, and PHP files under `includes/`.

**Update when:** A class, responsibility, hook, lifecycle step, dependency, or data flow changes.

**Out of scope:** Field-level privacy controls and operational runbooks; use [Data, security, and privacy](data-security-privacy.md) and [Operations](operations.md).

## Evidence status

The component and lifecycle descriptions below are **Verified** from `user-data-collection.php`, the ten `includes/class-udc-*.php` files, and `includes/udc-validation.php`. Structural tools are discovery aids; active source is authoritative when coverage is incomplete or stale.

## Entrypoint and lifecycle

`user-data-collection.php` defines plugin constants, manually requires sixteen classes and the document-provider interface, registers activation and deactivation callbacks, and attaches `udc_init_plugin()` to `plugins_loaded`. Fifteen class files are stored in `includes/class-udc-*.php`; `UDC_Validation` is stored in `includes/udc-validation.php`.

On activation, `UDC_Activator::activate()` calls `dbDelta`, migrates the retired approval column, stores `UDC_DB_VERSION` only if that inspection/migration succeeds, and then calls the three scheduler methods. See [Data, security, and privacy](data-security-privacy.md) for the migration contract. On later loads, `udc_init_plugin()` calls the activator when the stored database version differs. It then instantiates `UDC_i18n`, `UDC_Shortcode`, `UDC_Piercing_Form`, `UDC_Backup`, `UDC_GDrive`, and `UDC_Email_Sync`. In an administrative request it also instantiates `UDC_Admin` and `UDC_Settings`.

Deactivation calls `UDC_Backup::clear_cron()`, `UDC_GDrive::clear_cron()`, and `UDC_Email_Sync::clear_cron()`. Each clearing loop stops if WordPress reports an unscheduling failure, so complete removal is not guaranteed. There is no uninstall flow in the current source.

## Components

| Component | Verified responsibility |
| --- | --- |
| `UDC_Activator` | Creates or updates `{prefix}udc_submissions` with `dbDelta`, stores the schema version, and requests scheduled events. |
| `UDC_Shortcode` | Renders `[udc_tattoo_form]` (legacy alias `[udc_contact_form]`), registers public and authenticated `admin_post` handlers, applies the nonce-valid attempt budget, validates explicit 0/1/2 health responses, and inserts rows through shared `save_submission()`. The endpoint fixes the service type on the server. |
| `UDC_Piercing_Form` | Extends the shared submission handler with `[udc_piercing_form]`, French source copy, categorized care downloads, signed rendered-text evidence and Polylang strings. |
| `UDC_Document` / `UDC_Document_Provider` | Plain-text document contract and public-content provider interface. |
| `UDC_Piercing_Care_Document` | Builds a care document from the same translated text as the form snapshot. |
| `UDC_Tattoo_Care_Document` | Owns translated tattoo aftercare shared between the original form and the reusable PDF provider. |
| `UDC_Pdf_Renderer` | Owns the A4 print template, isolated PDF engine and temporary-artifact cleanup. |
| `UDC_Document_Download` | Registers public providers, validates requests, restores locale context and streams complete PDF responses. |
| `UDC_Validation` | Shared static date, datetime, time, and length validators used by the shortcode handler and backup restore. |
| `UDC_List_Table` | Extends `WP_List_Table`; displays filtered, paginated submissions with an allowlist for sortable columns and row actions. |
| `UDC_Admin` | Creates the Waivers menu (Descargos in Spanish) with Tattoos, Piercings, Backups and Settings views; renders read-only list/detail views. Both service lists display their shortcode. The list retains filters, sortable data columns, pagination, and the ID detail link; ID is the first and responsive primary column. |
| `UDC_Backup` | Creates local JSON snapshots, rotates local files, restores missing rows, accepts uploaded JSON, and exposes administrative AJAX actions. |
| `UDC_Settings` | Registers integration and design options and exposes the global Delete All Data action. |
| `UDC_GDrive` | Creates JWT credentials from the configured service-account JSON, uploads local JSON files, lists Drive files, and rotates the configured folder. |
| `UDC_Email_Sync` | Sends the newest local JSON file through `wp_mail` and registers the monthly scheduler. |
| `UDC_i18n` | Loads the text domain and optionally registers and resolves Polylang strings. |

## Hooks and flows

| Hook or registration | Source symbol | Flow |
| --- | --- | --- |
| `register_activation_hook` | `user-data-collection.php` | Calls `UDC_Activator::activate()`. |
| `plugins_loaded` | `udc_init_plugin()` | Updates the schema when needed and instantiates services. |
| `admin_menu` | `UDC_Admin::add_admin_menu()` | Adds the original submissions screen, separate Piercings screen, Backups, and Settings. It constructs additional `UDC_Backup` and `UDC_Settings` objects for submenu callbacks. |
| `admin_post_udc_submit_form` and `admin_post_nopriv_udc_submit_form` | `UDC_Shortcode` | Receives the frontend form and writes a submission. |
| `udc_daily_backup_action` | `UDC_Backup` | Creates a local JSON snapshot. |
| `udc_weekly_gdrive_sync_action` | `UDC_GDrive` | Attempts a Drive synchronization when enabled. |
| `udc_monthly_email_sync_action` | `UDC_Email_Sync` | Attempts an email backup when enabled. |
| `cron_schedules` | `UDC_Activator`, `UDC_GDrive`, `UDC_Email_Sync` | The activator registers the Drive and email callbacks before scheduling; those two service classes define the custom intervals. |
| `admin_init` | `UDC_Settings` | Registers typed options with defaults and sanitization callbacks. |
| `init` | `UDC_i18n` | Registers Polylang strings when Polylang is available. |

The public form flows into the custom table. Administrative list/detail views read that table. Appointment approval and its AJAX handlers were removed in 1.6.0. The table can then flow into local JSON, Google Drive, and email attachments. See [Data, security, and privacy](data-security-privacy.md) and [Operations](operations.md).

## Form separation (1.8.0)

**Verified from source:** the original shortcode and all its pre-existing strings are preserved. Health checkboxes are replaced by required dropdowns; each form instance now has unique HTML IDs while retaining request field names. New original-form submissions use form_type=tattoo; pre-upgrade rows and historical imports use legacy. UDC_List_Table filters rows, totals and upcoming/past counts by section. UDC_Admin applies the same service boundary to direct detail requests. The new submenu slug is udc-piercings; the original remains udc-submissions.

UDC_Piercing_Form registers authenticated/public admin_post_udc_submit_piercing handlers, authenticated/public admin_post_udc_piercing_care downloads and its own nonce action. The existing download action delegates to the shared PDF controller. Care sections use native details/summary elements without JavaScript. Styles are enqueued through WordPress and scoped to the new form.

## Reusable public PDF documents

**Verified, tattoo aftercare:** UDC_Shortcode registers `tattoo-care` with the common document registry. Its care block and PDF use UDC_Tattoo_Care_Document::translate() for the same six paragraphs. The form link uses `admin-post.php?action=udc_download_document&document=tattoo-care&language=...`; no category or personal data is needed. The document has its own version 1.0 and automatic WordPress home URL. New Polylang strings use the `UDC Tattoo Care` group and distinct IDs, so old piercing-care overrides from the original form cannot replace them. The legacy dictionary is retained for compatibility, but its old care paragraphs are no longer rendered on the tattoo form. Existing liability wording, personal/health fields, submission handling and stored records are unchanged.

**Verified from source:** UDC_Document_Download also registers authenticated/public `admin_post_udc_download_document` handlers. Its `document` parameter selects a registered provider; it accepts no HTML, filesystem path or submission identifier. `piercing-care` requires an allowlisted category and an installed/Polylang language. The controller resolves Polylang slug to locale, builds all bytes before sending `application/pdf`, attachment and nosniff headers, and restores the previous locale on success or failure. Invalid requests return 400; generation failures return a generic 503 without dependency/path details.

UDC_Piercing_Form::care_data() reuses snapshot translations and restores its explicit translation language in a finally block. The existing care_document() HTML helper remains available for preview tooling; download actions return PDF exclusively. PDFs use home_url('/') automatically and show that URL once, without a promotional label. The public website/review links and their admin settings, registration hook and helper methods have been removed. The admin list retains the shortcode and submission controls. Legacy URL options are ignored; the existing global reset still deletes them for compatibility. No consent text, schema or stored evidence changes are introduced by this module.

To add public documents, implement UDC_Document_Provider::build(array $parameters), return UDC_Document with title, language, safe PDF filename, version label, sections and website metadata, and register the instance with UDC_Document_Download::register('document-key', $provider) during initialization. The URL uses `admin-post.php?action=udc_download_document&document=document-key&language=...` plus provider-specific parameters. Providers must validate those parameters. Sections contain escaped plain text, optional headings and an alert flag; layout and HTTP behavior remain in the common modules.

The renderer lazy-loads the build-generated UDCPdfVendor runtime, embeds DejaVu fonts, disables remote resources, PHP and JavaScript, limits local resources to its runtime, and cleans its request-specific temporary workspace. Dependency isolation also adapts upstream dynamic class/font names; real generation tests cover those adaptations. Runtime/font/license packaging and build instructions are canonical in [Release](release.md) and [Development and quality](development-and-quality.md).

## Result and browser behavior

**Verified from source:** UDC_Shortcode::redirect_result() redirects with HTTP 303 and a five-minute HMAC receipt bound to the result and form type. UDC_Shortcode::result_status() ignores unsigned, altered, expired and cross-form statuses. Old result parameters are removed before redirecting; an absent referer falls back to the site home. The template_redirect callback requests no-cache headers for receipt URLs. Hosting caches that bypass PHP still require separate verification.

The shared wp_enqueue_scripts callback adds submit locking for both forms. It disables submit buttons and exposes aria-busy after a valid browser submit, rejects a second submit event, and restores controls on pageshow (including browser Back). This is browser interaction protection; there is no server-side exactly-once guarantee if scripts are disabled or requests are replayed.

## Dependencies and current implementation characteristics

The plugin depends on WordPress APIs, the WordPress database abstraction, filesystem access to the uploads directory, WP-Cron, `wp_mail`, OpenSSL for JWT signing, and optional Polylang and Google Drive HTTP endpoints. PDF generation uses the isolated bundled Dompdf runtime described above.

**Verified limitation:** `UDC_Backup` and `UDC_Settings` are instantiated during plugin initialization and again from `UDC_Admin::add_admin_menu()`. Their constructors register hooks, so administrative requests can register duplicate effects.

**Verified limitation:** The form, admin actions, and settings pages emit inline CSS or JavaScript. This increases coupling to the rendered page and makes a strict content-security-policy and independent asset testing harder.

**Verified, tattoo introduction:** the original form now starts with the translatable title_tattoo and intro_tattoo entries in UDC_i18n, mirroring the piercing introduction. They state required fields, completion before the session and in-person review of uncertain answers. They are registered through the existing original-form Polylang group. No validation, consent or submission behavior changes accompany this copy addition.

## Unreleased table and optional-field adjustments

**Verified from source:** both list views keep eight columns with ID first and primary. Detail actions move with the primary ID cell so native mobile expansion remains singular. Admin styles are enqueued only on the two service pages; the birth-date heading stays on one line on desktop and the ID column accommodates its detail action. Care category and exact body location are shown in piercing details, alongside the existing health choices and accepted-text evidence; category is not added to the index.

UDC_Shortcode::field_label() normalizes required markers for both forms without rewriting existing consent strings. Address, city and postal code have no required marker or browser required attribute. Shared submission validation accepts blank or omitted address/city/postal values while keeping scalar, character-length, nonce, consent and other required-field checks. The schema is unchanged.

## Tattoo document evidence (1.8.3)

**Verified from source:** UDC_Shortcode::snapshot() captures the rendered tattoo heading, introduction, liability text and all six care paragraphs, with form version, care version and interface language. render_form() renders those captured values and embeds the JSON and its server HMAC. submission_evidence() validates the bounded shape, current form/care versions and signature before save_submission() writes the exact JSON and metadata. The handler also requires the existing liability checkbox.

The canonical shortcode is udc_tattoo_form; udc_contact_form remains an alias. The admin legend and README use the canonical identifier. Routes, nonce actions and service isolation are unchanged.

Both admin detail views show Accepted document. Tattoo details display the stored versions/language and saved text, never a reconstruction using current translations. Missing or inconsistent evidence is marked unavailable; historical records are not relabelled or given fabricated snapshots. The existing form_language/form_version/form_evidence columns are reused without a schema change. UDC_Backup validates tattoo evidence consistency while accepting older tattoo rows with entirely empty evidence metadata.

Version policy: UDC_Shortcode::FORM_VERSION starts at 1.0 and identifies the tattoo consent document. Increment it for future changes to accepted consent wording/structure. UDC_Tattoo_Care_Document::VERSION independently identifies care text. Current form submissions require both current versions; historical detail and restore accept valid older versioned snapshots. Plugin release version 1.8.3 is independent of those document versions.

**Verified, optional city in 1.9.0:** city remains varchar(255) NOT NULL, and optionality is represented as an empty string rather than SQL NULL, matching address/postal code. No CREATE TABLE or ALTER TABLE change is required. Browser and server requirements are relaxed for both services; scalar/character-length limits and evidence validation remain unchanged. Restore accepts an empty city in all supported backup schemas while requiring the city key and string type.

**Verified, tattoo location label in 1.9.1:** UDC_i18n::get_strings()['label_location'] now renders Tattoo location with its translated default in every supported locale. The stored/request field piercing_location is retained for compatibility. The piercing form owns a separate location label and is unaffected by this copy correction.

## Tattoo acknowledgements (1.10.0)

**Verified from source:** UDC_Shortcode::FORM_VERSION is 2.0. The tattoo document contains four separate procedure/truthfulness, risk-information, aftercare and data-processing acknowledgements. All four controls start unchecked and are required in the browser; the handler requires the exact scalar string 1 for each acceptance. The existing liability_accepted column records the procedure acceptance, and the other three are prerequisites to saving the exact signed document in form_evidence, matching the piercing approach. No schema columns are added.

The document snapshot includes the four texts under document, alongside title/intro and care content. Admin details render all four stored texts, and backup validation uses the revised bounded snapshot shape. The previous tattoo document shape is not migrated or accepted as the new format; the maintainer reports clearing the development data. No automatic acceptance, database deletion or historical-format reader was added. Old generic missing-evidence handling remains unchanged.

## Shared public-form styling

**Verified from source:** UDC_Shortcode::form_styles() provides the existing piercing rules under fixed service-specific selectors. Piercing enqueues the shared rules; tattoo renders its scoped variant and a care-panel override. Both now use full-width fields, inherited font, 44px input/button minimum height, visible focus, custom checkboxes, select arrows and date/time color-scheme rules. Tattoo section headings follow the piercing h2/h3 hierarchy. No consent wording or data behavior changes.

**Verified, checkbox styling:** both forms reuse the former tattoo checkbox shape and animated checkmark, with the existing background/border/check color options when design is enabled and visible neutral defaults otherwise. Reduced-motion preferences disable the transition. Native checked/required behavior and keyboard focus remain intact.


## QR posters

Requirement implemented: admin list buttons and the QR Image settings tab provide local A5 portrait PNG generation (1748 x 2480, 300 dpi metadata). QR modules use a white four-module quiet zone; saved colors and direct French destination URLs are shared through UDC_QR_Poster. Assets include qrcode-generator 1.4.4 (MIT). Settings use the Settings API and manage_options; no submission schema changes. Physical printing and phone-camera acceptance remain pending.


QR poster branding uses the transparent logo supplied by the studio, bundled as assets/qr/logo-titanium.png from https://titaniumlausanne.ch/wp-content/uploads/2024/10/Logo-titanium-galeria.png. The renderer awaits image decoding for previews and downloads, preserves aspect ratio and reports the existing translated error if loading fails. Bundling avoids remote Canvas access restrictions.

# Architecture

**Answers:** What runs, when it runs, and how the plugin components exchange data.

**Read when:** Changing components, hooks, lifecycle, entry points, administrative actions, or cross-component flows.

**Canonical sources:** `user-data-collection.php`, hook registrations, and PHP files under `includes/`.

**Update when:** A class, responsibility, hook, lifecycle step, dependency, or data flow changes.

**Out of scope:** Field-level privacy controls and operational runbooks; use [Data, security, and privacy](data-security-privacy.md) and [Operations](operations.md).

## Evidence status

The component and lifecycle descriptions below are **Verified** from `user-data-collection.php`, the sixteen `includes/class-udc-*.php` files, and `includes/udc-validation.php`. Structural tools are discovery aids; active source is authoritative when coverage is incomplete or stale.

## Entrypoint and lifecycle

`user-data-collection.php` defines plugin constants, manually requires eighteen classes and the document-provider interface, registers activation and deactivation callbacks, and attaches `udc_init_plugin()` to `plugins_loaded`. Sixteen class files are stored in `includes/class-udc-*.php`; `UDC_Validation` is stored in `includes/udc-validation.php`; `UDC_Guardian` is stored in `includes/udc-guardian.php`.

On activation, `UDC_Activator::activate()` calls `dbDelta`, migrates the retired approval column, stores `UDC_DB_VERSION` only if that inspection/migration succeeds, and then calls the three scheduler methods. See [Data, security, and privacy](data-security-privacy.md) for the migration contract. On later loads, `udc_init_plugin()` calls the activator when the stored database version differs. It then instantiates `UDC_i18n`, `UDC_Shortcode`, `UDC_Document_Download`, `UDC_Piercing_Form`, `UDC_Backup`, `UDC_GDrive`, and `UDC_Email_Sync`. In an administrative request it also instantiates `UDC_Admin`, `UDC_QR_Poster` and `UDC_Settings`.

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
| `UDC_Guardian` | Resolves the active form language, validates blank authorization attachments, renders the shared minority declaration, and owns the five native media settings and original-file download. |
| `UDC_Validation` | Shared static date, datetime, time, and length validators used by the shortcode handler and backup restore. |
| `UDC_List_Table` | Extends `WP_List_Table`; displays filtered, paginated submissions with an allowlist for sortable columns and row actions. |
| `UDC_Admin` | Creates the Waivers menu (Descargos in Spanish) with Tattoos, Piercings, Backups and Settings views; renders read-only list/detail views. Both service lists display their shortcode. The list retains filters, sortable data columns, pagination, and the ID detail link; ID is the first and responsive primary column. |
| `UDC_Backup` | Creates local JSON snapshots, rotates local files, restores missing rows, accepts uploaded JSON, and exposes administrative AJAX actions. |
| `UDC_QR_Poster` | Supplies admin QR poster defaults, validates settings, loads local rendering assets and renders buttons/previews. |
| `UDC_Settings` | Registers general guardian-document, integration, design and QR options and exposes the global Delete All Data action. |
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

## Table and optional-field adjustments (1.8.2–1.9.0)

**Historical checkpoint:** the optional contact-field behavior below was superseded by field removal in 1.12.0. The eight-column list layout described here gained a ninth minority column in 1.13.0; see [current minority badges](#minority-badges). Current collection, migration and restore behavior is documented in [Data, security, and privacy](data-security-privacy.md#validation-and-result-review) and [Operations](operations.md#local-backup).

**Verified at the 1.8.2–1.9.0 checkpoint:** both list views kept eight columns with ID first and primary. Detail actions move with the primary ID cell so native mobile expansion remains singular. Admin styles are enqueued only on the two service pages; the birth-date heading stays on one line on desktop and the ID column accommodates its detail action. Care category and exact body location are shown in piercing details, alongside the existing health choices and accepted-text evidence; category is not added to the index.

UDC_Shortcode::field_label() normalizes required markers for both forms without rewriting existing consent strings. Address, city and postal code have no required marker or browser required attribute. Shared submission validation accepts blank or omitted address/city/postal values while keeping scalar, character-length, nonce, consent and other required-field checks. The schema is unchanged.

## Tattoo document evidence (1.8.3)

**Historical checkpoint:** this section describes the initial evidence format. The current tattoo format is 2.0; see [Tattoo acknowledgements](#tattoo-acknowledgements-1100) for its replacement and historical-format limits.

**Verified from source:** UDC_Shortcode::snapshot() captures the rendered tattoo heading, introduction, liability text and all six care paragraphs, with form version, care version and interface language. render_form() renders those captured values and embeds the JSON and its server HMAC. submission_evidence() validates the bounded shape, current form/care versions and signature before save_submission() writes the exact JSON and metadata. The handler also requires the existing liability checkbox.

The canonical shortcode is udc_tattoo_form; udc_contact_form remains an alias. The admin legend and README use the canonical identifier. Routes, nonce actions and service isolation are unchanged.

Both admin detail views show Accepted document. Tattoo details display the stored versions/language and saved text, never a reconstruction using current translations. Missing or inconsistent evidence is marked unavailable; historical records are not relabelled or given fabricated snapshots. The existing form_language/form_version/form_evidence columns are reused without a schema change. UDC_Backup validates tattoo evidence consistency while accepting older tattoo rows with entirely empty evidence metadata.

Version policy: UDC_Shortcode::FORM_VERSION starts at 1.0 and identifies the tattoo consent document. Increment it for future changes to accepted consent wording/structure. UDC_Tattoo_Care_Document::VERSION independently identifies care text. Current form submissions require both current versions; historical detail and restore accept valid older versioned snapshots. Plugin release version 1.8.3 is independent of those document versions.

**Historical checkpoint, optional city in 1.9.0:** city remains varchar(255) NOT NULL, and optionality is represented as an empty string rather than SQL NULL, matching address/postal code. No CREATE TABLE or ALTER TABLE change is required. Browser and server requirements are relaxed for both services; scalar/character-length limits and evidence validation remain unchanged. Restore accepts an empty city in all supported backup schemas while requiring the city key and string type.

**Verified, tattoo location label in 1.9.1:** UDC_i18n::get_strings()['label_location'] now renders Tattoo location with its translated default in every supported locale. The stored/request field piercing_location is retained for compatibility. The piercing form owns a separate location label and is unaffected by this copy correction.

## Tattoo acknowledgements (1.10.0)

**Verified from source:** UDC_Shortcode::FORM_VERSION is 2.0. The tattoo document contains four separate procedure/truthfulness, risk-information, aftercare and data-processing acknowledgements. All four controls start unchecked and are required in the browser; the handler requires the exact scalar string 1 for each acceptance. The existing liability_accepted column records the procedure acceptance, and the other three are prerequisites to saving the exact signed document in form_evidence, matching the piercing approach. No schema columns are added.

The document snapshot includes the four texts under document, alongside title/intro and care content. Admin details render all four stored texts, and backup validation uses the revised bounded snapshot shape. The previous tattoo document shape is not migrated or accepted as the new format; the maintainer reports clearing the development data. No automatic acceptance, database deletion or historical-format reader was added. Old generic missing-evidence handling remains unchanged.

## Administrative labels

**Verified from source:** tattoo and piercing list-page titles and H1 headings use the translated `Tattoo Waivers` and `Piercing Waivers` messages (Spanish: Descargos tatuajes / Descargos piercings). The tattoo submit button uses the separately registered `tattoo_submit_btn` string, `Submit my consent`, matching the piercing button's intent. Existing sidebar labels and consent snapshots retain their current contracts.

## Minority badges

**Verified from source, 1.13.0:** both service lists have nine columns, ending with the small translated minority column. `UDC_Guardian::record_is_minor()` prioritizes a saved 0/1 declaration, including the string values returned by the database. A saved zero remains No even if the date would otherwise indicate a minor; a saved one remains Yes even if the person has since turned 18. When no valid saved 0/1 declaration is available, the helper calculates completed calendar years from date of birth on the current WordPress site date. It does not use the appointment date or rewrite records. Invalid/missing/future historical birth dates yield a neutral Not recorded badge rather than inventing an adult status.

`UDC_Guardian::badge()` supplies identical escaped, translated Yes/No text and state classes to `UDC_List_Table::column_default()` and the detail row immediately below date of birth. No is green and Yes is red; text conveys the state independently of color. The derived column is not SQL-sortable. Scoped admin CSS gives it a 6em desktop width, and native WP_List_Table mobile row expansion exposes it alongside the other secondary columns.

## Shared public-form styling

**Verified from source:** UDC_Shortcode::form_styles() provides the existing piercing rules under fixed service-specific selectors. Piercing enqueues the shared rules; tattoo renders its scoped variant and a care-panel override. With design disabled, both use local Roboto, black 18px base text, a transparent form container, rounded 48px controls, and visible focus through assets/forms/default.css. Custom design retains the existing color options and 44px base controls. Tattoo section headings follow the piercing h2/h3 hierarchy. See [Data, security, and privacy](data-security-privacy.md) for current validation and schema behavior.

**Verified, checkbox styling:** both forms reuse the former tattoo checkbox shape and animated checkmark, with the existing background/border/check color options when design is enabled and visible neutral defaults otherwise. Reduced-motion preferences disable the transition. Native checked/required behavior and keyboard focus remain intact.

**Verified from source:** shared rules leave 24px below the minority block in both forms and 16px above a paragraph immediately following the piercing care disclosures. These margins apply with custom design enabled or disabled. Nonempty minority errors also explicitly receive 24px bottom margin, including when the guardian notice is hidden.


## QR posters

**Verified from source:** `UDC_Admin::render_list_page()` adds a Download QR button for each service. `UDC_Settings` registers a separate `udc_qr_settings` group and QR Image tab. `UDC_QR_Poster::enqueue()` restricts assets/configuration to administrators with `manage_options` on the plugin list/settings pages. The existing Settings API save flow protects changes; no new public download endpoint or database columns are added.

`UDC_QR_Poster::defaults()` defines shared background/text/module colors (#550000, #ffffff, #000000) and two direct French page URLs:

- Tattoo: https://titaniumlausanne.ch/fr/formulaire-de-rendez-vous-pour-un-tatouage/
- Piercing: https://titaniumlausanne.ch/fr/formulaire-de-rendez-vous-pour-un-piercing/

Colors accept six-digit hexadecimal values. URLs accept HTTP(S), at most 1,000 bytes, without embedded credentials. Blank values restore defaults; invalid values produce a settings error and preserve the previous value. Previews and downloads use saved settings; the global reset deletes these options.

`assets/qr/poster.js` composes a 1748 × 2480 portrait PNG with 300 dpi pHYs metadata, French text and a fixed white QR panel. It uses the locally bundled qrcode-generator 1.4.4 with error correction M, integer module sizing and a four-module quiet zone. The invitation block is centered between logo and panel using measured text bounds. Georgia/serif uses the browser's available fonts, so glyph metrics can vary by platform. A contrast ratio below 4.5 against white triggers an advisory; this is not a QR readability certification.

Canvas generation runs in the administrator's browser without a QR service. The encoded URL is direct and contains no submission data. Changing settings does not change printed posters. Download failures use the translated error and re-enable the button. See [QR validation](development-and-quality.md#qr-poster-validation) and [pending acceptance](backlog.md#qr-01--qr-posters).


QR poster branding uses the transparent logo supplied by the studio, bundled as assets/qr/logo-titanium.png from https://titaniumlausanne.ch/wp-content/uploads/2024/10/Logo-titanium-galeria.png. The renderer awaits image decoding for previews and downloads, preserves aspect ratio and reports the existing translated error if loading fails. Bundling avoids remote Canvas access restrictions.

## Guardian authorization

**Verified from source:** `UDC_Guardian` is shared by both forms. General settings contain one attachment ID for each of fr/de/it/en/es. Settings API handles saves with existing administrative capability checks; the media modal loads only on the General screen. A valid attachment requires a matching allowlisted extension/MIME and a non-trash attachment post. The active Polylang locale/slug or WordPress locale chooses the language; there is no cross-language document substitution. Missing documents show translated contact instructions and do not independently block submission.

The minority notice states that the minor must bring a completed paper authorization signed by their legal guardian. `assets/forms/minor.js` enhances the initially visible no-JavaScript notice, validates before submit locking, and keeps errors connected to the checkbox. `save_submission()` repeats the age/declaration check and returns a service-bound signed `minor_error` receipt. Existing consent snapshots retain their original contracts.

Public authenticated/anonymous `admin_post_udc_guardian_download` handlers serve only the configured language attachment's readable local original, with attachment and nosniff headers. No path or arbitrary URL is accepted. Offloaded media without a readable local original returns 404; no remote-fetch proxy is introduced.

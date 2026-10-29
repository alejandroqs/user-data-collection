# Pending work

**Answers:** What remains unresolved, where its evidence lives, and what would establish completion.

**Read when:** Resuming project work, selecting a task, or recording a newly found limitation or verification result.

**Update when:** A task is added, its evidence changes, a decision is made, or completion is demonstrated.

## Maintenance rules

**Requirement:** keep stable IDs and distinguish Open, In progress, Blocked, and Done. Every item needs evidence, a next action, and a completion criterion. Record executed checks and their result when closing an item. A source fix alone does not close a runtime verification item. Link technical explanations to their canonical document rather than copying them here. These items are proposed work, not authorization to change product behavior, access production, or run destructive checks.

Initial triage: 2026-10-24. Items under Open items are **Open**; no owner or deadline has been assigned. Review the recovery and deletion items before relying on those operations; choose implementation order according to the intended environment and maintainer decisions.

## Open items

| ID | Evidence and canonical context | Next action and completion criterion |
| --- | --- | --- |
| FORM-NONCE-01 | **Open, investigation 2026-10-29:** user reports intermittent `Security check failed.` affecting two testers. Source checks show nonce rejection precedes field validation, initial pages lack plugin-specific cache exclusion, and the current form script does not renew tokens. Ten fresh anonymous production probes passed nonce verification, including Autoptimize bypass and all five linked tattoo-page languages; no real submission was stored. See [Data, security, and privacy](data-security-privacy.md#public-form-token-caching-investigation-2026-10-29). | Identify failed-page age/session/cache context, then validate targeted cache exclusions or another evidence-based correction. Close only after affected-browser retesting and an aged/restored-page check with nonce protection retained. Current evidence does not identify Autoptimize as the cause; production settings were not changed. |
| BACKUP-01 | **Inference:** legacy import to a current-schema snapshot could produce a backup that restore rejects before 1.8.0. Typed legacy normalization now passes isolated round-trip validation; live migration/filesystem/database proof remains pending. Source: UDC_Backup::validate_backup_rows(), validate_backup_row(), create_backup(). See [Operations](operations.md). | Contact fields were retired in 1.12.0; current restore intentionally discards them. The 1.12.0 native checkpoint verified restoration from a 1.11.1 snapshot. Complete a synthetic historical zip_city import -> new snapshot -> restore round trip, preserving all remaining supported data while discarded contact fields stay absent and malformed/mixed schemas remain rejected. |
| DELETE-01 | **Verified:** UDC_Settings::ajax_delete_all_data() returns success without checking local file and database deletion results. See [Operations](operations.md). | Define partial-failure behavior and implement result checks. Close after isolated failure tests prove a failed deletion cannot be reported as complete; preserve capability and nonce checks. |
| ENV-01 | **Verified:** PHP 7.4+ is now declared and the PDF module was exercised in isolated WordPress 7.1.3; a versioned full WordPress environment, supported WordPress range and general WordPress integration CI gate remain absent. See [Development and quality](development-and-quality.md). | Define the supported WordPress range and a reproducible isolated environment. Close after documented setup and automated checks execute for submission validation, permissions, schema activation/update, and backup restore using synthetic data. |
| OPS-01 | **Unknown:** effective backup HTTP protection, cron execution/unscheduling, Drive behavior, and mail delivery in the intended environment. See [Operations](operations.md) and [Data, security, and privacy](data-security-privacy.md). | Record separate results for each boundary in an authorized isolated environment. Include denied HTTP access to a synthetic backup, cron failure cases, Drive ownership/rotation, and delivery to a controlled mailbox. Close only when results and remaining limitations are recorded. |
| ARCH-01 | **Verified:** initialization and UDC_Admin::add_admin_menu() instantiate additional UDC_Backup and UDC_Settings objects whose constructors register hooks. See [Architecture](architecture.md). | Decide how to reuse service instances or avoid repeated registration. Close after an executed admin-request check demonstrates one intended callback per relevant hook and functioning submenu pages. |
| PRIV-01 | **Verified:** retention, individual export/erasure and separate credential storage remain unresolved capabilities or policy decisions. Tattoo and piercing text/version/language evidence is implemented; client approval is still pending. See [Data, security, and privacy](data-security-privacy.md). | Have the maintainer/operator define requirements and ownership, including external copies. Split approved implementation work into explicit tasks; close this decision item when decisions and resulting tasks are recorded, not by asserting compliance. |
| I18N-01 | **Verified:** 2026-10-25 adds a versioned catalog builder/checker, synchronizes POT/PO metadata with 1.8.0, and validates new-form defaults in all nine existing locale catalogs and compiled MO files. See [Development and quality](development-and-quality.md). | Verify actual WordPress catalog loading and locale changes, including the optional Polylang override path. The isolated catalog/PHP checks do not establish production loading or complete extraction of every unrelated plugin string. |
| META-01 | **Verified:** plugin/schema, npm and translation versions are synchronized for the current release (see Release); package metadata declares ISC. Intended licensing/distribution policy is **Unknown**. See [Release](release.md). | Obtain maintainer decisions for licensing; version alignment is implemented for this release. Close after the selected policy is documented and applicable metadata agrees; do not infer license terms or bump versions during a documentation review. |
| FORM-01 | **Verified:** 1.8.0 adds required health dropdowns and a separate piercing form/admin section. New copy and care are initial French drafts in UDC_Piercing_Form. See [Data, security, and privacy](data-security-privacy.md). | Review text, categories, languages, privacy notice, minors/signatures and privacy links with the studio. Execute real WordPress migration, both submission flows, direct detail isolation, care downloads and a backup/restore round trip. Close only after content approval and recorded runtime results. |

## Completed source and isolated checks

| ID | Status and evidence | Remaining boundary |
| --- | --- | --- |
| FORM-REVIEW-01 | **Done (source and isolated validation):** fixed forged result messages, duplicate form IDs, invalid birth dates, byte/character length mismatch, empty/invalid link destinations, translation fallback, keyboard focus and malformed admin IDs; added browser duplicate-submit protection. Executed: 141 PHP assertions, 12 browser checkpoints, PHP lint and diff whitespace checks. See [Development and quality](development-and-quality.md) for commands and limits. | Real WordPress/Polylang/database and client content approval remain open under FORM-01 and ENV-01. |
| FORM-TRANSLATIONS-01 | **Done (catalog generation and isolated validation):** 64 required message IDs in each of nine PO/MO catalogs, updated POT, corrected locale headers and 1,538 existing nonempty translations preserved. Nine catalog checks, nine PHP locale checks and 141 form regressions passed. See [Development and quality](development-and-quality.md). | Real WordPress/Polylang loading remains I18N-01; client wording approval remains FORM-01. |
| FORM-COLOR-01 | **Done (local source and isolated preview):** improved closed-select text/fill contrast, matching select arrows and date/time icons for dark backgrounds. Existing form wording preserved. PHP syntax, 141 form checks and targeted Edge contrast/mobile checks passed. See [Development and quality](development-and-quality.md). | Publish an updated ZIP and verify against the actual site theme. |
| FORM-PDF-01 | **Done (implementation and isolated/native checks):** reusable public-document providers, shared PDF renderer and controller replace care HTML downloads with PDF while retaining existing links and text. Executed: 45 category/locale PDFs plus a second multipage provider parsed independently, translated errors, locale restoration/failure checks, Edge downloads, catalog/form regressions, ZIP manifest/lint/integrity and native same-version WordPress replacement with data/settings preservation. See [Architecture](architecture.md), [Development and quality](development-and-quality.md) and [Release](release.md). | Publish an authorized new release and verify production PHP extensions, temporary-directory permissions and real Polylang behavior; content approval remains FORM-01. |
| FORM-LINKS-01 | **Done (source and isolated checks):** removed website/review calls to action, admin inputs, setting registration and related helpers. Care PDF footer uses WordPress home_url('/') automatically; legacy settings cannot override it. Executed: PHP form regressions, all nine catalog checks, Edge download/layout checks, 46 independently parsed PDFs, PHP lint and ZIP checks. Existing consent copy and stored records are unchanged. See [Architecture](architecture.md). | Production theme/Polylang verification and publication remain pending. |
| ADMIN-NAMES-01 | **Done (1.8.1 source and isolated checks):** Waivers/Descargos menu, explicit Tattoos/Tatuajes first view and a shortcode legend on both service lists. Added two labels in all nine catalogs and synchronized release metadata. Executed: nine catalog/PHP locale checks, nine isolated admin renderings, 141 form regressions, PHP syntax and ZIP gates. See [Release](release.md). | Production admin/Polylang verification and publication remain pending. |
| TATTOO-CARE-01 | **Done (source and isolated checks, 2026-10-26):** user authorized replacing the original form's piercing aftercare with tattoo-specific instructions. A shared translated provider supplies the form and PDF. Executed: nine catalogs/PHP locales, 143 form checks, 55 parsed PDFs, Edge downloads, visual PDF review, PHP lint and ZIP gates. Consent text and records remain unchanged. See [Development and quality](development-and-quality.md) and [Data, security, and privacy](data-security-privacy.md). | Studio approval of dressing/aftercare instructions and production/Polylang verification remain pending; no release published. |

## FORM-INTRO-01 - Tattoo introduction

**Done (source and isolated checks):** added the tattoo consent heading and initial guidance matching the piercing form, translated in all nine catalogs. Executed catalog/PHP locale validation, 143 form regressions and PHP lint. Production theme/Polylang verification remains pending.

## FORM-ADMIN-FIELDS-01 - Optional fields and detail layout

**Done (source, isolated and native-table checks):** ID first, nonwrapping birth-date heading, optional address/postal code, consistent required markers and nine-locale introduction updates. Selected care category is translated in details; body location and health choices remain visible there without adding index columns. Executed: 169 PHP checks, nine catalogs/locales, Edge forms, native WordPress replacement and twelve native table viewport checks. See Development and quality, Architecture and Operations. Production/Polylang verification and publication remain pending.

## TATTOO-EVIDENCE-01 - Accepted document and shortcode

**Done (1.8.3 source and isolated checks):** new tattoo submissions capture signed accepted text, document/care versions and language. Detail and restore preserve historical snapshots and explicitly report missing evidence. Canonical shortcode is udc_tattoo_form with the old identifier retained as an alias. Translated version labels cover all nine catalogs. See Architecture, Data/security/privacy and Development and quality for gates and evidence boundaries. Production acceptance and publication remain pending.

## CITY-OPTIONAL-01 - Optional city (1.9.0)

**Done (source, isolated and native SQL checks):** city is optional in both forms, shared server validation and backup restore. Empty/missing values are stored as empty strings in the unchanged varchar(255) NOT NULL column. Required markers are removed in every translated form. Executed: 200 PHP assertions, nine catalogs/locales, Edge forms and native WordPress/MySQL upgrade/submission/restore for both services with blank and omitted city. See Development and quality and Release. Production acceptance and publication remain pending.

## TATTOO-LOCATION-01 - Corrected tattoo label (1.9.1)

**Done (source and isolated checks):** corrected the inherited piercing-location label in the tattoo form and added defaults in all nine catalogs. Version metadata is 1.9.1. Nine catalog/PHP locale checks, 200 form regressions and PHP syntax checks passed. Request/database fields and piercing copy are preserved. Publication and production Polylang checks remain pending.

## Adding and closing items

**Requirement:** add only source-backed findings, explicit requirements, or clearly labelled hypotheses. Include a path and symbol for source findings, the relevant canonical document, and a testable completion criterion. Do not store personal data, credentials, production backup contents, or unverified success claims.

Move completed items to a Closed items section with the completion date, change reference when available, and actual validation evidence. Keep unresolved subchecks open or create linked follow-up items. Record Blocked only with the specific dependency that prevents progress.

## TATTOO-CONSENT-02 — Four explicit tattoo acknowledgements

**Implemented:** separate procedure/truthfulness, risk, care and data-processing acceptances; server validation, document version 2.0, exact stored text in admin, translations and plugin minor version 1.10.0. The maintainer reports clearing the development database and explicitly excludes historical-record work. No automatic acceptance or destructive cleanup was implemented. Studio review of wording and actual pre-procedure information remains separate from technical verification. See Architecture and Data, security, and privacy for the implementation contract.

**Verification:** 229 synthetic PHP checks, nine catalog/PHP locale checks, Edge form checks and local 351-file ZIP/lint/hash/integrity gates passed. Native installation and production wording acceptance remain unverified.

## FORM-STYLES-01 — Match tattoo styles to piercing

**Implemented and verified locally:** shared scoped CSS for both forms; tattoo care background remains configurable and defaults to rgba(0, 0, 0, 0.15) when design is disabled or the value is unspecified/invalid. Settings registration, display fallback and clearing behavior agree. PHP regressions, Edge responsive/focus checks, computed-style comparison and option checks pass. No version bump or remote publication requested for this adjustment.

**Verified, checkbox styling:** both forms reuse the former tattoo checkbox shape and animated checkmark, with the existing background/border/check color options when design is enabled and visible neutral defaults otherwise. Reduced-motion preferences disable the transition. Native checked/required behavior and keyboard focus remain intact.


## QR-01 — QR posters

**Done (implementation and local checks):** configurable posters, bundled logo and balanced invitation spacing. See [Architecture](architecture.md#qr-posters) for behavior and [Development and quality](development-and-quality.md#qr-poster-validation) for exact checks and limitations.

**Open acceptance:** verify real WordPress saving/reloading of all five options, list buttons, preview/logo asset loading, failure messages, print at A5 and scan with actual phone cameras. Close acceptance only after recording those results; local browser fixtures and decoder checks do not establish them.

## FORM-GUARDIAN-01 — Reduced contact data and guardian authorization

**Implemented:** remove address/city/postal inputs, columns and historical database values; discard retired fields on supported historical restore; persist nullable minority declarations; enforce under-18 declarations on the submission date; show translated signed-paper instructions; provide five language-specific media selectors; use a local Roboto/mobile default design. See Architecture, Data/security/privacy, Operations and Release for canonical behavior.

**Verified locally:** 298 synthetic PHP assertions, 114 required strings in each of nine catalog/PHP locale checks, Edge responsive/form checks and the local ZIP lint/hash/integrity gate (362 files). Isolated WordPress 7.1.4/MySQL 8.4.3 upgrades from v1.11.1 remove the columns, preserve other data/settings and NULL history, normalize/restore an old backup and validate per-language native media options. Native Edge checks also pass the General screen, media modal, Settings API save, anonymous minor validation, original-file downloads and both submissions; SQL verifies exactly one new minority-declared record per service.

**Pending:** studio review of the paper template/translations, production theme/Polylang checks, and inventory/disposal of existing local/Drive/email/hosting copies containing retired fields. No production data, settings or remote release was changed.

**Done, spacing follow-up:** shared styles add 24px below the minority block in both forms and 16px above the paragraph following the final piercing care disclosure. PHP syntax and fixture generation passed. Eight Edge fixture combinations (390/1280px, default/custom design, open/closed disclosures) confirmed the gaps and no horizontal overflow. Production theme verification remains pending; no version bump or publication was performed.

**Done, wording follow-up:** tattoo/piercing admin page titles and H1 headings now identify their waivers; the tattoo button says Submit my consent. All five languages and nine regional catalogs are updated (117 required messages per locale). PHP lint, catalog/MO fallback validation, nine synthetic admin renderings, nine synthetic tattoo-button renderings, the underlying form fixture suite and diff whitespace checks passed. Live WordPress/Polylang overrides remain unverified for this wording update. The preceding spacing changes are preserved; no version bump or publication was performed.

**Done, patch 1.12.1:** the piercing-waiver heading explicitly refers to piercings in all five languages, using natural plural wording (Spanish: Descargos piercings). Version metadata and form asset versions advance to 1.12.1. All nine catalogs and synthetic admin/button renderings pass. The preceding spacing and wording changes remain included; no commit, tag, push or remote publication was performed by the agent.

## DOCS-FORMS-1121 — Current versus historical form documentation

**Done, documentation only:** corrected the user README's retired-field description and added General-template setup; routed current tasks to canonical documents and marked the approved implementation plan as historical scope. Historical optional-field sections now identify their replacement. Package contents and the current build command reflect 1.12.1. Validation records distinguish the earlier native 1.12.0 checkpoint from local patch checks; no full native patch rerun or production result is implied.

**Verified, executed:** 127 local file/anchor links pass across the root README, AGENTS and eight canonical/supporting documents. Active source/npm metadata and the saved 362-file ZIP agree on 1.12.1; the ZIP contains the documented form/font assets and passes archive integrity inspection. All 74 tracked/nonignored non-Markdown files were preserved byte-for-byte during this reconciliation; existing pending code/catalog changes remain intact. Diff whitespace checks pass. No PHP/browser/native database/external-service suite was rerun for this documentation-only change.

## ADMIN-MINORITY-01 — Minority badges

**Done (local implementation and verification), 1.13.0:** ninth/final minority column on tattoo/piercing lists and matching badge beneath date of birth in details; saved declaration takes priority, otherwise age is calculated on the current site date. Badge text and header are translated. The existing block margin is retained and nonempty minority errors explicitly get 24px bottom margin. See [Architecture](architecture.md#minority-badges) for the canonical display contract.

**Verified:** 347 PHP assertions, nine translated header/Yes/No renderings, all nine 118-message catalogs, PHP lint and the local 362-file ZIP gate pass. Isolated WordPress 7.1.4/MySQL 8.4.3 upgrade from v1.12.0 and native Edge checks pass: both nine-column lists, red/green badges, mobile row expansion at 390px, desktop at 1280px, badge placement below date of birth, minority-error spacing, both anonymous submissions and SQL persistence. Previous pending local code/documentation changes remain included; no production state, commit, tag or remote release was changed.

**Remaining acceptance:** actual production theme and Polylang overrides. Full PDF generation and external integrations are outside this display-only verification; no schema or consent snapshot changes were made.

## DOCS-ADMIN-1130 — Minority documentation follow-up

**Done, documentation only:** added a task-index route for minority display/validation/acceptance; marked the earlier eight-column table description as historical; linked the old native-test checkpoint to the current 1.13.0 command; centralized display semantics in Architecture. ADMIN-MINORITY-01 is locally complete while production theme/Polylang acceptance remains open.

**Verification boundary:** source, metadata and saved-log inspection confirm the existing local implementation record; this review does not rerun application, database, browser or external-service suites. Existing pending code and catalog changes are preserved.

**Verified, executed in this review:** 137 local Markdown file/anchor links pass; active version metadata and the saved ZIP agree on 1.13.0 and archive integrity passes. All 75 tracked/nonignored non-Markdown files remain byte-identical. Diff whitespace checks pass.

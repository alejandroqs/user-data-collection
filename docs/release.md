# Release

**Answers:** How versions, tags, package contents, and release gates relate.

**Read when:** Changing version metadata, database schema version, tag conventions, translation packaging, ZIP contents, or the release workflow.

**Canonical sources:** `user-data-collection.php`, package metadata, translation metadata, Git tags, and `.github/workflows/release.yml`.

**Update when:** A version source, tag convention, package allowlist, release permission, or validation gate changes.

**Out of scope:** Runtime integration success and development environment setup; use [Operations](operations.md) and [Development and quality](development-and-quality.md).

## Current version metadata

The active plugin header and `UDC_DB_VERSION` in `user-data-collection.php` both report `1.13.1`. `package.json`, `package-lock.json` and POT/PO/MO project metadata also report `1.13.1`. Plugin, schema marker, npm and translation versions remain synchronized with the release gate. The catalog build/check procedure is in [Development and quality](development-and-quality.md). Active code is authoritative for the plugin and schema version.

The local tags include `v1.5.0`, earlier `v1.x` tags, and the historical unprefixed tags `1.4.2`, `1.4.1`, and `1.4.0`. The `vX.Y.Z` convention matches the workflow's `v*` trigger; the historical unprefixed tags do not. This document does not claim whether a remote release was created for any tag.

## Upgrade to 1.9.1

**Verified from source:** patch release correcting the tattoo body-location label to Tattoo location, with translations in all nine supported catalogs. The separate piercing label, request field and database column names remain unchanged. Plugin/schema marker, assets, npm and gettext metadata agree on 1.9.1. No schema, document-version or consent-text changes are introduced; no tag or remote release was created by this update.

## Upgrade to 1.9.0

**Verified from source:** minor version update for optional city in tattoo and piercing forms, shared submission validation and backup restoration. City is stored as an empty string when omitted; the existing varchar(255) NOT NULL column supports this without a schema alteration. Plugin/schema marker, asset, npm and gettext metadata agree on 1.9.0. Form/care document versions are unchanged. No Git commit, tag or remote release was created by this update.

**Verified, local 1.9.0 gates:** 200 PHP assertions, nine catalog/PHP locale checks, Edge forms, package lint/hash/ZIP integrity and native WordPress/MySQL upgrade, empty-city submission and SQL restore checks passed. No remote workflow was triggered.

## Upgrade to 1.8.3

**Verified from source:** tattoo accepted-document snapshots with independent form/care versions and language, historical text in admin details and backup/restore consistency. The canonical shortcode is udc_tattoo_form; udc_contact_form remains an alias. Existing table columns are reused and no historical snapshots are invented. Plugin/schema marker, asset, npm and translation metadata are synchronized at 1.8.3. No Git commit, tag or remote publication was created for this update.

**Verified, local release gates:** 196 PHP assertions, nine catalogs/PHP locales, Edge forms and a native WordPress 1.8.2→1.8.3 package/submission/SQL-restore/detail check passed. The final local ZIP is validated before publication; the remote workflow has not run for this version.

## Upgrade to 1.8.2

**Verified from source:** patch version for ID-first admin tables, desktop birth-date heading layout, optional address/postal code in both services and backup restore, consistent required markers, translated introductions and explicit care category in details. Plugin/schema marker, asset versions, npm and gettext metadata are synchronized. No database columns or care-document versions change. Release tag v1.8.2 and remote publication have not been created by this version update.

## Upgrade to 1.8.1

**Verified from source:** patch release for administrative naming and shortcode legends. Waivers (Descargos in Spanish) is the main menu; the explicit first submenu and original list title are Tattoos. Existing admin slugs, capability checks, service filters and consent wording are retained. Tattoo aftercare and its PDF are described in Architecture. Both lists display their own shortcode. The two new labels have defaults in all nine PO/MO catalogs. No database columns or consent document versions change; the existing idempotent activation path runs when the synchronized schema marker advances.

**Verified, executed locally:** all nine catalog/PHP locale checks, nine isolated admin rendering checks, 141 form regressions, PHP syntax, package manifest/hash checks and ZIP integrity. The implementation is committed as f84363b. The maintainer authorized the release checkpoint and annotated v1.8.1 tag on 2026-10-26. Release automation must independently confirm ZIP generation after the tag push. Production WordPress/Polylang verification remains separate.

## Upgrade to 1.8.0

**Verified from source:** plugin/schema versions advance together for typed forms and piercing evidence. dbDelta adds the five metadata/evidence columns, defaulting historical rows to legacy. The activator verifies that those columns exist before recording the new version. It retains the retired approval migration for older installations. No history is relabelled as tattoo and no existing consent text is rewritten. The release package includes UDC_Piercing_Form and the class-file gate now expects ten class files.

**Unknown:** live WordPress/database migration. The maintainer has authorized the v1.8.0 tag publication to trigger the ZIP workflow; CI/release success must be checked separately.

## Upgrade to 1.6.0

**Verified from source:** the schema version and plugin header advance together for removal of appointment approval. Existing installations run the activator on the next plugin load when their stored schema version differs. New installs omit the column; upgrades explicitly drop is_confirmed because [dbDelta](https://developer.wordpress.org/reference/functions/dbdelta/) does not remove omitted columns. The migration preserves submission rows but intentionally discards prior approval values. It records the new schema version only after inspection and any required drop succeed. Old backups are normalized as described in [Operations](operations.md). No tag, remote release, deployment, or live database migration was performed.

## License metadata

**Verified:** package.json and package-lock.json declare ISC. The root README previously described the software as private, but that wording did not establish intended distribution rights. **Unknown:** the intended license and distribution policy. A maintainer decision is pending as META-01 in [Pending work](backlog.md); this documentation review does not change licensing.

## Workflow and package contents

`.github/workflows/release.yml` installs both Composer lockfiles, audits runtime dependencies, builds the isolated PDF runtime and runs form/PDF regressions. scripts/build-package.py assembles only the plugin entrypoint, direct includes/*.php, direct translation catalogs, direct files in assets/qr, assets/forms and assets/fonts, and manifest-verified runtime/pdf assets. It requires sixteen class files, the PDF loader, Unicode fonts and dependency license; it rejects runtime path/hash mismatches and symlinks, lints every packaged PHP, checks tag/header/schema equality and verifies the ZIP. Every entry has non-executable permissions and the stable user-data-collection root. No tests, build tools, unscoped vendor dependencies, credentials or development documents are packaged. The workflow attaches the ZIP to a GitHub release with contents:write and commit-pinned release actions.

The direct assets/qr, assets/forms and assets/fonts directories are included without a per-file hash manifest; review their contents before packaging. Form assets include the local Roboto font and its OFL license. The generated PDF runtime retains third-party source and original licenses. Composer/PHP-Scoper/Python are build tools, not production requirements. Build commands and runtime compatibility are canonical in [Development and quality](development-and-quality.md#pdf-build-and-validation). Release publication requires a separate tag push and a successful workflow run; neither is established by the package configuration.

**Historical PDF checkpoint (1.8.0 package):** the PDF ZIP passed manifest/hash checks, PHP lint and archive integrity (350 files, approximately 4.4 MB). In an isolated WordPress 7.1.3 / PHP 8.3.28 / MySQL 8.4.3 installation, the native Plugin_Upgrader detected the existing v1.8.0 folder, replaced it after the overwrite choice, and retained the synthetic record (including NULL evidence) and design settings. The installed package generated a real PDF, rejected an invalid category and restored the locale, while another unprefixed Dompdf class was present. This used a temporary extension of the existing upgrade harness; no production site, email or external integration was exercised. Remote workflow status was not established by that local checkpoint.

## Maintainer checklist

Before a release, a maintainer should verify the following against the active source and intended release policy:

1. Decide which version is authoritative and synchronize the plugin header, `UDC_DB_VERSION`, package metadata, and translation metadata as appropriate.
2. If the database schema changes, update `UDC_DB_VERSION` and the `CREATE TABLE` statement together.
3. Use a `vX.Y.Z` tag if the existing workflow trigger is retained.
4. Review the workflow package allowlist and confirm that the intended PHP and translation files are included.
5. Run any newly defined tests, static analysis, and packaging checks; do not infer their result from the workflow's existence.
6. Record runtime, backup, external-integration, and migration verification separately from documentation review.

Changing the tag convention or adding release gates requires a separate change to the workflow or project configuration; this document does not make that change.

**Verified, tattoo PDF addition (2026-10-26):** the package includes UDC_Tattoo_Care_Document and the class-inventory gate now expects fifteen files. This follows the local 1.8.1 changes without another version increment, schema change, Git tag or publication.

## 1.8.1 release checkpoint (2026-10-26)

**Verified before tagging:** plugin/schema marker, npm and gettext metadata agree on 1.8.1; the implementation includes the admin labels, both shortcode legends, tattoo introduction and translated tattoo-care PDF. Prior isolated gates passed with 143 form checks and 55 parsed PDFs. The tag-triggered workflow installs locked dependencies, audits the runtime, rebuilds isolated PDF assets, runs the regressions and validates the final ZIP before publishing it. This checkpoint does not claim production theme/Polylang or clinical wording approval.

## Upgrade to 1.10.0

**Verified from source:** minor release introducing four separate tattoo acknowledgements and document version 2.0, translated into all nine locale catalogs. The existing table and evidence columns are reused; no CREATE TABLE change is needed. Plugin/schema marker, npm, assets and gettext metadata are synchronized to 1.10.0. No old-format migration is included because the maintainer cleared the development data. No commit, tag or push is performed by this change. The target annotated release tag is v1.10.0.

## Upgrade to 1.10.1

**Verified from source:** patch release for shared tattoo/piercing styles, configurable tattoo-care background defaulting to rgba(0, 0, 0, 0.15), and the former tattoo custom checkbox design on both forms. Plugin/schema marker, npm, asset and catalog metadata are synchronized. No database structure or consent-document version change is required. Target tag: v1.10.1; no commit, tag or push performed by this update.


## QR assets

See [Architecture](architecture.md#qr-posters) for behavior and [Development and quality](development-and-quality.md#qr-poster-validation) for local validation. QR assets and their MIT encoder license ship in the ZIP; the studio logo retains its separate ownership.


## Upgrade to 1.11.0

Requirement implemented: minor release introducing downloadable QR posters for both services and the QR Image settings tab. Plugin, schema marker, npm, assets and translation metadata are synchronized to 1.11.0. No database structure or consent-document version change. Target tag: v1.11.0. Commit, tag and push are left to the maintainer.


## Upgrade to 1.11.1

Patch release: QR posters use the supplied transparent studio logo and center the invitation text between the logo and QR panel. Plugin, schema marker, npm, asset and catalog metadata are synchronized to 1.11.1. No database structure or consent-document change. Target tag: v1.11.1. Commit, tag and push remain unexecuted.

## Upgrade to 1.12.0

**Verified from source:** removes address/city/postal columns and historical database values, adds the nullable minority declaration, shared translated guardian notice and five language-specific native media settings. Default forms use local Roboto and accessible mobile spacing. `scripts/build-package.py` includes the new PHP helper and explicitly allowlists assets/forms and assets/fonts, including the Roboto OFL license. Form/care snapshot versions are unchanged because their accepted texts/contracts are unchanged.

**Requirement:** review the destructive column migration before installing on a real site and separately address existing backup copies. No Git commit, tag, push or remote publication is part of this update. See [Development and quality](development-and-quality.md) for executed gates.

## Patch 1.12.1

**Verified from source:** plural piercing-waiver headings in all supported languages, including Descargos piercings in Spanish. This patch also includes the preceding form-spacing adjustments, explicit waiver page titles and tattoo consent-button wording. Header/schema marker, npm metadata, form asset versions and catalog metadata are synchronized. No database columns or consent snapshot contracts change in this patch. Local build validation does not create or push a Git tag.

**Local 1.12.1 status:** the patch includes the previously pending spacing and wording changes. Catalog/rendering and ZIP checks are recorded in [Development and quality](development-and-quality.md#patch-1121-validation-boundary). No full native 1.12.1 rerun or production acceptance is established by those checks. A local package does not establish a pushed tag or successful GitHub release; check the tag-triggered workflow separately after publication.

## Minor release 1.13.0

**Verified from source:** adds the translated minority badge as the ninth/final list column and below date of birth in both detail views. Saved 0/1 declarations take priority; missing history derives age without changing stored values. The existing 24px minority-block spacing remains, with explicit spacing below nonempty errors. Plugin/schema marker, npm, relevant asset versions and all nine catalog headers are synchronized to 1.13.0. This release adds no database columns and does not change accepted consent/care snapshots. It includes the preceding pending 1.12.1 copy and spacing changes.

Local checks and their boundaries are recorded in [Development and quality](development-and-quality.md#minority-badges-in-1130). Build output remains tmp/user-data-collection.zip. The annotated publication tag is v1.13.0; the agent has not committed, tagged or pushed it.


## Patch release 1.13.1

**Verified from source:** the administrative submenu now places Piercings first, followed by Tattoos, Backups and Settings, using the native submenu position argument. Existing translated labels, page slugs, callbacks and capabilities are retained.

**Verified from source:** fixes white text on form buttons/download buttons while leaving ordinary links theme-customizable; unifies translated authenticated success/error notices beneath both form headings; adds a shared server-side honeypot with compatibility for absent fields in old cached/open forms. See [Architecture](architecture.md) and [antispam controls](data-security-privacy.md#public-form-honeypot).

Plugin/header, schema marker, npm, asset cache keys and translation metadata are synchronized to 1.13.1. The schema marker follows this repository's synchronized release convention; table structure and accepted-document versions are unchanged. The existing idempotent activator runs when the stored marker differs.

Local behavior checks before the metadata bump are recorded in [Development and quality](development-and-quality.md#public-form-honeypot-and-feedback). The 1.13.1 package and metadata checks are recorded there separately. Target annotated tag: v1.13.1. No commit, tag, push, GitHub workflow or production deployment has been performed by the agent.

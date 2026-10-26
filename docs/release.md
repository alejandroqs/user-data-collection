# Release

**Answers:** How versions, tags, package contents, and release gates relate.

**Read when:** Changing version metadata, database schema version, tag conventions, translation packaging, ZIP contents, or the release workflow.

**Canonical sources:** `user-data-collection.php`, package metadata, translation metadata, Git tags, and `.github/workflows/release.yml`.

**Update when:** A version source, tag convention, package allowlist, release permission, or validation gate changes.

**Out of scope:** Runtime integration success and development environment setup; use [Operations](operations.md) and [Development and quality](development-and-quality.md).

## Current version metadata

The active plugin header and `UDC_DB_VERSION` in `user-data-collection.php` both report `1.8.1`. `package.json`, `package-lock.json` and POT/PO/MO project metadata also report `1.8.1`. Plugin, schema marker, npm and translation versions remain synchronized with the release gate. The catalog build/check procedure is in [Development and quality](development-and-quality.md). Active code is authoritative for the plugin and schema version.

The local tags include `v1.5.0`, earlier `v1.x` tags, and the historical unprefixed tags `1.4.2`, `1.4.1`, and `1.4.0`. The current `v1.5.0` convention matches the workflow's `v*` trigger; the historical unprefixed tags do not. This document does not claim whether a remote release was created for any tag.

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

`.github/workflows/release.yml` installs both Composer lockfiles, audits runtime dependencies, builds the isolated PDF runtime and runs form/PDF regressions. scripts/build-package.py assembles only the plugin entrypoint, direct includes/*.php, direct translation catalogs and manifest-verified runtime/pdf assets. It requires fifteen class files, the PDF loader, Unicode fonts and dependency license; it rejects runtime path/hash mismatches and symlinks, lints every packaged PHP, checks tag/header/schema equality and verifies the ZIP. Every entry has non-executable permissions and the stable user-data-collection root. No tests, build tools, unscoped vendor dependencies, credentials or development documents are packaged. The workflow attaches the ZIP to a GitHub release with contents:write and commit-pinned release actions.

The generated runtime retains third-party source and original licenses. Composer/PHP-Scoper/Python are build tools, not production requirements. Build commands and runtime compatibility are canonical in [Development and quality](development-and-quality.md#pdf-build-and-validation). This PDF change does not alter database schema or version metadata, create a new tag or publish a release; existing tags remain unchanged.

**Verified, executed locally:** the PDF ZIP passed manifest/hash checks, PHP lint and archive integrity (350 files, approximately 4.4 MB). In an isolated WordPress 7.1.3 / PHP 8.3.28 / MySQL 8.4.3 installation, the native Plugin_Upgrader detected the existing v1.8.0 folder, replaced it after the overwrite choice, and retained the synthetic record (including NULL evidence) and design settings. The installed package generated a real PDF, rejected an invalid category and restored the locale, while another unprefixed Dompdf class was present. This used a temporary extension of the existing upgrade harness; no production site, email or external integration was exercised. The updated GitHub Actions workflow has not run remotely.

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

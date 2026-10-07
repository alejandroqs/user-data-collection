# Release

**Answers:** How versions, tags, package contents, and release gates relate.

**Read when:** Changing version metadata, database schema version, tag conventions, translation packaging, ZIP contents, or the release workflow.

**Canonical sources:** `user-data-collection.php`, package metadata, translation metadata, Git tags, and `.github/workflows/release.yml`.

**Update when:** A version source, tag convention, package allowlist, release permission, or validation gate changes.

**Out of scope:** Runtime integration success and development environment setup; use [Operations](operations.md) and [Development and quality](development-and-quality.md).

## Current version metadata

The active plugin header and `UDC_DB_VERSION` in `user-data-collection.php` both report `1.5.0`. `package.json` and `package-lock.json` report `1.0.0`. The POT metadata reports `1.3.0`. These are separate metadata sources and their synchronization policy is not documented in the source. Active code is authoritative for the plugin and schema version.

The local tags include `v1.5.0`, earlier `v1.x` tags, and the historical unprefixed tags `1.4.2`, `1.4.1`, and `1.4.0`. The current `v1.5.0` convention matches the workflow's `v*` trigger; the historical unprefixed tags do not. This document does not claim whether a remote release was created for any tag.

## Workflow and package contents

`.github/workflows/release.yml` creates an isolated temporary package directory and explicitly copies the plugin entrypoint, PHP files directly under `includes/`, and `.po`, `.mo`, and `.pot` files directly under `languages/`. It creates `user-data-collection.zip`, captures its manifest, permits only the expected root directories, entrypoint, direct PHP include files, and direct translation files, and rejects every other archive entry. The workflow requires all ten `includes/class-udc-*.php` files; `includes/udc-validation.php` is included by the broader PHP copy and manifest allowlist. It validates PHP syntax, checks tag/header and `UDC_DB_VERSION`/header version equality, rejects executable package files, and attaches the ZIP to a GitHub release. It grants `contents: write` and uses commit-pinned release actions. The workflow itself is the canonical source for the current action commits.

## Maintainer checklist

Before a release, a maintainer should verify the following against the active source and intended release policy:

1. Decide which version is authoritative and synchronize the plugin header, `UDC_DB_VERSION`, package metadata, and translation metadata as appropriate.
2. If the database schema changes, update `UDC_DB_VERSION` and the `CREATE TABLE` statement together.
3. Use a `vX.Y.Z` tag if the existing workflow trigger is retained.
4. Review the workflow package allowlist and confirm that the intended PHP and translation files are included.
5. Run any newly defined tests, static analysis, and packaging checks; do not infer their result from the workflow's existence.
6. Record runtime, backup, external-integration, and migration verification separately from documentation review.

Changing the tag convention or adding release gates requires a separate change to the workflow or project configuration; this document does not make that change.

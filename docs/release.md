# Release

## Current version metadata

The active plugin header and `UDC_DB_VERSION` in `user-data-collection.php` both report `1.4.1`. `package.json` and `package-lock.json` report `1.0.0`. The POT metadata reports `1.3.0`. These are separate metadata sources and their synchronization policy is not documented in the source.

The local Git tags most recently inspected are `1.4.1`, `1.4.0`, and earlier `v1.x` tags. The release workflow is triggered only by tags matching `v*`; the two newest unprefixed tags do not match that trigger. This document does not claim whether a remote release was created for any tag.

## Workflow and package contents

`.github/workflows/release.yml` creates an isolated temporary package directory and explicitly copies the plugin entrypoint, PHP files directly under `includes/`, and `.po`, `.mo`, and `.pot` files directly under `languages/`. It creates `user-data-collection.zip`, captures its manifest, permits only the expected root directories, entrypoint, direct PHP include files, and direct translation files, and rejects every other archive entry. The workflow requires all ten `includes/class-udc-*.php` files, validates PHP syntax, checks tag/header and `UDC_DB_VERSION`/header version equality, rejects executable package files, and attaches the ZIP to a GitHub release. It grants only `contents: write`, pins `actions/checkout` to commit `34e114876b0b11c390a56381ad16ebd13914f8d5` (`v4.3.1`) and `softprops/action-gh-release` to commit `5be0e66d93ac7ed76da52eca8bb058f665c3a5fe` (`v2.4.2`).

## Maintainer checklist

Before a release, a maintainer should verify the following against the active source and intended release policy:

1. Decide which version is authoritative and synchronize the plugin header, `UDC_DB_VERSION`, package metadata, and translation metadata as appropriate.
2. If the database schema changes, update `UDC_DB_VERSION` and the `CREATE TABLE` statement together.
3. Use a `vX.Y.Z` tag if the existing workflow trigger is retained.
4. Review the workflow package allowlist and confirm that the intended PHP and translation files are included.
5. Run any newly defined tests, static analysis, and packaging checks; do not infer their result from the workflow's existence.
6. Record runtime, backup, external-integration, and migration verification separately from documentation review.

Changing the tag convention or adding release gates requires a separate change to the workflow or project configuration; this document does not make that change.

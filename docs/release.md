# Release

## Current version metadata

The active plugin header and `UDC_DB_VERSION` in `user-data-collection.php` both report `1.4.1`. `package.json` and `package-lock.json` report `1.0.0`. The POT metadata reports `1.3.0`. These are separate metadata sources and their synchronization policy is not documented in the source.

The local Git tags most recently inspected are `1.4.1`, `1.4.0`, and earlier `v1.x` tags. The release workflow is triggered only by tags matching `v*`; the two newest unprefixed tags do not match that trigger. This document does not claim whether a remote release was created for any tag.

## Workflow and package contents

`.github/workflows/release.yml` checks out the repository, creates a `user-data-collection` directory, and uses `rsync` to exclude:

- `.git/`, `.github/`, `.agents/`, and `node_modules/`;
- `package*.json`, `skills-lock.json`, and `.gitignore`;
- all `*.md` files;
- the destination directory itself.

It then creates `user-data-collection.zip` and attaches it to a GitHub release. The workflow contains no lint, test, static-analysis, version-preflight, or package-content verification step. The workflow was not changed by this documentation task.

## Maintainer checklist

Before a release, a maintainer should verify the following against the active source and intended release policy:

1. Decide which version is authoritative and synchronize the plugin header, `UDC_DB_VERSION`, package metadata, and translation metadata as appropriate.
2. If the database schema changes, update `UDC_DB_VERSION` and the `CREATE TABLE` statement together.
3. Use a `vX.Y.Z` tag if the existing workflow trigger is retained.
4. Review the actual ZIP exclusions and confirm that the intended plugin files are included.
5. Run any newly defined tests, static analysis, and packaging checks; do not infer their result from the workflow's existence.
6. Record runtime, backup, external-integration, and migration verification separately from documentation review.

Changing the tag convention or adding release gates requires a separate change to the workflow or project configuration; this document does not make that change.

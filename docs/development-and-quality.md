# Development and quality

**Answers:** Which development environment, discovery tools, translation process, and quality gates are actually available or absent.

**Read when:** Changing development commands, dependencies, agent tooling, translations, tests, linting, static analysis, or reproducibility requirements.

**Canonical sources:** Package and lock files, repository inventory, translation catalogs, project skills, and configured workflows.

**Update when:** A dependency, command, environment file, skill, translation process, test, lint, analysis, or CI gate changes.

**Out of scope:** Runtime architecture and release packaging; use [Architecture](architecture.md) and [Release](release.md).

## Known environment

The supplied project skills describe Windows and PowerShell as the local agent environment. That is local tooling context, not a universal runtime requirement for the WordPress plugin.

The repository does not declare minimum PHP or WordPress versions. It does not include Composer, PHPUnit, PHPCS, PHPStan, a general automated test framework, or a `.wp-env.json`. `package.json` has no dependencies and its `test` script exits with the message that no test is specified. The package metadata is not a plugin quality gate.

`@wordpress/env` is not declared or pinned in the current package files, and the repository provides no versioned `wp-env` configuration. A reproducible local WordPress environment is therefore **Unknown**, not a verified checkout property. Example `npx wp-env` commands in [Operations](operations.md) are explicitly unverified.

## Translations

The repository contains `.po` catalogs and compiled `.mo` files under `languages/`. The POT metadata reports `Project-Id-Version: User Data Collection 1.3.0`, while the plugin header reports version `1.6.0`. No versioned command or documented process establishes how POT, PO, and MO files are regenerated and synchronized.

`UDC_i18n` loads the text domain and optionally registers strings with Polylang. Translation catalog freshness and runtime loading were not executed or verified.

## Skills and discovery

The `env`, `wordpress-pro`, and `php-pro` skills are guidance, not installed project gates. Their generic recommendations must not be reported as current project compliance.

`.agents/skills/codebase-memory-project/SKILL.md` provides repository-specific discovery guidance. It recommends the codebase-memory graph for structural discovery, direct source verification, and an explicit fallback to `rg` and direct reads when the graph is unavailable, stale, incomplete, or unsuitable for exact configuration and documentation searches. Its tracked or untracked state is a Git fact to inspect at task start, not a stable property to document here.

## Focused regression checks

**Verified:** run `php tests/confirmation-removal.php` from the repository root. The standalone PHP checks exercise activation/migration failure handling, current and historical backup normalization, restore inserts, submission inserts, list filtering/sorting/pagination contracts, endpoint registration, and detail HTML. WordPress functions and the database are doubles; this does not execute dbDelta, SQL, the real WP_List_Table renderer, or browser interactions. The fixture upgrade file under `tests/fixtures/` only records schema requests.

Executed on 2026-10-24 with PHP 8.3.28: 26 checks passed, and PHP syntax checks passed for the plugin entrypoint and all ten included PHP files. Docker was unavailable, so real database migration and WordPress browser acceptance remain unexecuted. The existing BACKUP-01 legacy postal-code round-trip issue remains open.

## Isolated browser preview

**Verified:** Chromium checks on 2026-10-24 rendered the active plugin list/detail code with synthetic data, a locally cached WordPress WP_List_Table, and its native CSS and common.js (with jQuery and hoverIntent). The preview used doubles for WordPress context/functions and database reads. List, empty-list, and detail layouts passed at 320, 390, 782, and 1280 pixels without horizontal overflow. Rows had eight cells, one expansion control each, and no approval elements; the empty state spanned all eight columns. Native mobile expansion exposed the detail link without JavaScript page errors. Desktop, mobile, and expanded mobile screenshots were inspected. The surname is the first/primary column, matching WordPress responsive selectors.

This temporary preview is not a complete WordPress browser acceptance test, a deployed-site check, or evidence that live SQL migration ran. Its generated HTML and screenshots used only synthetic records and were kept outside the repository.

## Quality status

| Gate or check | Configured checkout status | What may be claimed |
| --- | --- | --- |
| PHP syntax | The release workflow runs `php -l` over packaged PHP files. | The gate is configured; a particular run passes only when its execution result is inspected. |
| Package manifest and version checks | The release workflow checks package paths, executable files, tag/header equality, and `UDC_DB_VERSION`/header equality. | The gates are configured; release success is not established by the workflow file. |
| `git diff --check` | Available through Git but not configured as a repository workflow gate. | Report only the result of the current executed command. |
| `php tests/confirmation-removal.php` | Focused standalone checks with WordPress/database doubles; not currently a CI gate. | A particular execution proves only these isolated checks. |
| `npm test` | Intentionally failing placeholder. | Not a test or quality gate. |
| Functional WordPress, database, browser, cron, mail, Drive, or web-server checks | No configured gate exists in the checkout. | **Unknown** until executed in an authorized environment. |
| PHPUnit, PHPCS, PHPStan, or WPCS | No configured gate exists in the checkout. | Do not claim conformance or a passing result. |

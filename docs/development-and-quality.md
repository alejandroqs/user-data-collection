# Development and quality

**Answers:** Which development environment, discovery tools, translation process, and quality gates are actually available or absent.

**Read when:** Changing development commands, dependencies, agent tooling, translations, tests, linting, static analysis, or reproducibility requirements.

**Canonical sources:** Package and lock files, repository inventory, translation catalogs, project skills, and configured workflows.

**Update when:** A dependency, command, environment file, skill, translation process, test, lint, analysis, or CI gate changes.

**Out of scope:** Runtime architecture and release packaging; use [Architecture](architecture.md) and [Release](release.md).

## Known environment

The supplied project skills describe Windows and PowerShell as the local agent environment. That is local tooling context, not a universal runtime requirement for the WordPress plugin.

The repository does not declare minimum PHP or WordPress versions. It does not include Composer, PHPUnit, PHPCS, PHPStan, automated tests, or a `.wp-env.json`. `package.json` has no dependencies and its `test` script exits with the message that no test is specified. The package metadata is not a plugin quality gate.

`@wordpress/env` is not declared or pinned in the current package files, and the repository provides no versioned `wp-env` configuration. A reproducible local WordPress environment is therefore **Unknown**, not a verified checkout property. Example `npx wp-env` commands in [Operations](operations.md) are explicitly unverified.

## Translations

The repository contains `.po` catalogs and compiled `.mo` files under `languages/`. The POT metadata reports `Project-Id-Version: User Data Collection 1.3.0`, while the plugin header reports version `1.5.0`. No versioned command or documented process establishes how POT, PO, and MO files are regenerated and synchronized.

`UDC_i18n` loads the text domain and optionally registers strings with Polylang. Translation catalog freshness and runtime loading were not executed or verified.

## Skills and discovery

The `env`, `wordpress-pro`, and `php-pro` skills are guidance, not installed project gates. Their generic recommendations must not be reported as current project compliance.

`.agents/skills/codebase-memory-project/SKILL.md` provides repository-specific discovery guidance. It recommends the codebase-memory graph for structural discovery, direct source verification, and an explicit fallback to `rg` and direct reads when the graph is unavailable, stale, incomplete, or unsuitable for exact configuration and documentation searches. Its tracked or untracked state is a Git fact to inspect at task start, not a stable property to document here.

## Quality status

| Gate or check | Configured checkout status | What may be claimed |
| --- | --- | --- |
| PHP syntax | The release workflow runs `php -l` over packaged PHP files. | The gate is configured; a particular run passes only when its execution result is inspected. |
| Package manifest and version checks | The release workflow checks package paths, executable files, tag/header equality, and `UDC_DB_VERSION`/header equality. | The gates are configured; release success is not established by the workflow file. |
| `git diff --check` | Available through Git but not configured as a repository workflow gate. | Report only the result of the current executed command. |
| `npm test` | Intentionally failing placeholder. | Not a test or quality gate. |
| Functional WordPress, database, browser, cron, mail, Drive, or web-server checks | No configured gate exists in the checkout. | **Unknown** until executed in an authorized environment. |
| PHPUnit, PHPCS, PHPStan, or WPCS | No configured gate exists in the checkout. | Do not claim conformance or a passing result. |

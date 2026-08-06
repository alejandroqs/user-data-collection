# Development and quality

## Known environment

The supplied project skills describe Windows and PowerShell as the local agent environment. That is local tooling context, not a universal runtime requirement for the WordPress plugin.

The repository does not declare minimum PHP or WordPress versions. It does not include Composer, PHPUnit, PHPCS, PHPStan, automated tests, or a `.wp-env.json`. `package.json` has no dependencies and its `test` script exits with the message that no test is specified. The package metadata is not a plugin quality gate.

The README documents `npx wp-env` commands, but `@wordpress/env` is not declared or pinned in the current package files. A reproducible local WordPress environment is therefore **Unknown**, not a verified checkout property.

## Translations

The repository contains `.po` catalogs and compiled `.mo` files under `languages/`. The POT metadata reports `Project-Id-Version: User Data Collection 1.3.0`, while the plugin header reports version `1.4.1`. The `make-mo` command described in the README compiles MO files from existing translation sources; it is not evidence of a complete POT/PO update process.

`UDC_i18n` loads the text domain and optionally registers strings with Polylang. Translation catalog freshness and runtime loading were not executed or verified.

## Skills and discovery

The `env`, `wordpress-pro`, and `php-pro` skills are guidance, not installed project gates. Their generic recommendations must not be reported as current project compliance.

`.agents/skills/codebase-memory-project/SKILL.md` is present as an untracked local skill and must be preserved. It recommends the codebase-memory graph for structural discovery, direct source verification, and an explicit fallback to `rg` and direct reads when the graph is unavailable, stale, incomplete, or unsuitable for exact configuration and documentation searches.

## Quality status

**Verified:** PHP syntax validation and `git diff --check` are available and were executed for this remediation. The release workflow now runs PHP syntax validation and package-manifest checks in CI. No functional WordPress, database, browser, cron, mail, Drive, Apache, Nginx, IIS, production, PHPUnit, PHPCS, PHPStan, or WPCS gate exists in the checkout. The existing `npm test` script remains an intentionally failing placeholder and is not a quality gate.

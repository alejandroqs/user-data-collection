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

**Verified, generated and checked on 2026-10-25:** bundled catalogs cover the new piercing form, the four explicit health-answer strings and eight new administration strings (64 required message IDs per locale). Existing variants are de_CH, de_DE, en_GB, es_ES, fr, fr_CH, fr_FR, it_CH and it_IT. Every PO has a matching compiled MO and the language header matches its filename. POT and PO project metadata now match plugin version 1.8.0. Two identical pre-existing POT duplicates were normalized; 1,538 pre-existing nonempty translations were compared with Git HEAD and preserved exactly.

The source copy for the new form remains French. French defaults use that copy; other languages have translated defaults. New Swiss German entries use ss instead of sharp s. The care document's version label is now translatable as well. Existing tattoo strings and their translations were not rewritten.

Reproducible tooling requires Python 3, PHP and GNU gettext msgfmt on PATH; no Python package installation is needed:

```powershell
py -3 scripts/update-translations.py --write
py -3 scripts/update-translations.py
```

The write command fills missing entries, updates catalog metadata and compiles MO files with msgfmt --check --check-format. The default command only validates. scripts/form-translations.json supplies the initial defaults; existing nonempty PO translations take priority and remain the editable catalogs. The script reads active string arrays through PHP without starting WordPress. It fails on unsupported context/plural PO syntax rather than performing a lossy rewrite.

Validation checks required-string coverage, nonempty/non-fuzzy entries, metadata, all translated PO/MO pairs, and the active PHP gettext fallback, health options, evidence snapshot and care document using the compiled catalogs. Executed: nine catalog checks and nine PHP locale checks passed; the existing 141 form regressions also passed. PHP helper functions are doubles, so actual WordPress text-domain loading and Polylang override behavior remain unverified. Polylang translations configured in WordPress can still override the bundled defaults. Client approval of the wording remains FORM-01.

## Skills and discovery

The `env`, `wordpress-pro`, and `php-pro` skills are guidance, not installed project gates. Their generic recommendations must not be reported as current project compliance.

`.agents/skills/codebase-memory-project/SKILL.md` provides repository-specific discovery guidance. It recommends the codebase-memory graph for structural discovery, direct source verification, and an explicit fallback to `rg` and direct reads when the graph is unavailable, stale, incomplete, or unsuitable for exact configuration and documentation searches. Its tracked or untracked state is a Git fact to inspect at task start, not a stable property to document here.

## Focused regression checks

**Verified:** run `php tests/confirmation-removal.php` from the repository root. The standalone PHP checks exercise activation/migration failure handling, current and historical backup normalization, restore inserts, submission inserts, list filtering/sorting/pagination contracts, endpoint registration, and detail HTML. WordPress functions and the database are doubles; this does not execute dbDelta, SQL, the real WP_List_Table renderer, or browser interactions. The fixture upgrade file under `tests/fixtures/` only records schema requests.

Executed on 2026-10-24 with PHP 8.3.28: 26 checks passed, and PHP syntax checks passed for the plugin entrypoint and all ten included PHP files. Docker was unavailable, so real database migration and WordPress browser acceptance remain unexecuted. The existing BACKUP-01 legacy postal-code round-trip issue remains open.

## Isolated browser preview

**Verified:** Chromium checks on 2026-10-24 rendered the active plugin list/detail code with synthetic data, a locally cached WordPress WP_List_Table, and its native CSS and common.js (with jQuery and hoverIntent). The preview used doubles for WordPress context/functions and database reads. List, empty-list, and detail layouts passed at 320, 390, 782, and 1280 pixels without horizontal overflow. Rows had eight cells, one expansion control each, and no approval elements; the empty state spanned all eight columns. Native mobile expansion exposed the detail link without JavaScript page errors. Desktop, mobile, and expanded mobile screenshots were inspected. The surname is the first/primary column, matching WordPress responsive selectors.

This temporary preview is not a complete WordPress browser acceptance test, a deployed-site check, or evidence that live SQL migration ran. Its generated HTML and screenshots used only synthetic records and were kept outside the repository.

## Form checks for 1.8.0

**Verified, executed 2026-10-24:** php tests/forms.php passes 90 checks (including the 26 earlier approval-removal checks) with PHP 8.3.28. Coverage includes unchanged original text, explicit 0/1/2 health answers, missing/invalid fields, distinct shortcode/nonce actions, signed piercing evidence, service-controlled inserts, typed backup normalization/restoration, submenu permissions, and direct-detail isolation. These use WordPress/database doubles; no real SQL or dbDelta executes.

For an isolated visual fixture, run php tests/forms.php --preview, then node tests/forms-browser.cjs with Playwright resolvable in the environment and Edge installed. Playwright is optional local tooling, not a new project dependency. The harness starts and stops a loopback-only PHP fixture server on port 18761; tests/fixtures/forms-router.php uses synthetic WordPress/database functions and is excluded from release packaging.

**Verified, executed:** Edge/Chromium checks pass at 320, 390, 782 and 1280 pixels, including no horizontal overflow, associated labels, explicit dropdown values, keyboard-operated care disclosures, and browser validation of a completed synthetic piercing form. The synthetic PHP endpoint returns attachment HTML and nosniff headers, rejects invalid category/language, and the browser downloads a care document. The standalone document also renders at 320 pixels. Screenshots use fictitious content and are generated under the system temporary directory.

**Unknown / unexecuted:** complete WordPress acceptance, live schema migration, real backup-file round trips, and translation behavior with a real Polylang installation. Docker Desktop's engine was unavailable. French piercing source copy, legal/medical wording, and minors/signature procedures await studio review as FORM-01.

## Form bug review

**Verified, executed 2026-10-24:** php tests/forms-review.php runs the earlier 90 checks plus targeted review regressions. Cases cover forged/expired/cross-form result receipts, database insert failure, safe redirects, repeated shortcodes including nonce IDs, future/impossible birth dates, accented names at the database character limit and restore compatibility, invalid stored links, gettext/Polylang fallback, and malformed administrative IDs. Exact current totals are recorded in the completed backlog item.

Generate the browser fixtures with php tests/forms-review.php --preview, then run node tests/forms-browser.cjs with Playwright resolvable. The extended isolated browser checks cover both forms' complete synthetic POST bodies, duplicate-submit prevention, Back restoration, requested-language downloads with a Polylang/gettext double, styled repeated original forms, visible keyboard focus, and birth-date bounds. PHP syntax and git diff --check are separate gates. Screenshots were inspected; this does not establish live WordPress, real Polylang or real database behavior.

The Polylang locale/slug and explicit translation contracts were checked against its [official function reference](https://polylang.pro/documentation/support/developers/function-reference/). Existing original-form strings remain protected by the baseline hash test. No content changes to that form were made during this review.

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

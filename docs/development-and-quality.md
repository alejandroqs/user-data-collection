# Development and quality

**Answers:** Which development environment, discovery tools, translation process, and quality gates are actually available or absent.

**Read when:** Changing development commands, dependencies, agent tooling, translations, tests, linting, static analysis, or reproducibility requirements.

**Canonical sources:** Package and lock files, repository inventory, translation catalogs, project skills, and configured workflows.

**Update when:** A dependency, command, environment file, skill, translation process, test, lint, analysis, or CI gate changes.

**Out of scope:** Runtime architecture and release packaging; use [Architecture](architecture.md) and [Release](release.md).

## Known environment

The supplied project skills describe Windows and PowerShell as the local agent environment. That is local tooling context, not a universal runtime requirement for the WordPress plugin.

The plugin header and PDF runtime require PHP 7.4+. The repository does not declare a minimum WordPress version. Composer lockfiles now define PDF runtime and isolated build-tool dependencies; PHPUnit, PHPCS, PHPStan, a general automated test framework and `.wp-env.json` remain absent. `package.json` has no dependencies and its `test` script exits with the message that no test is specified. The npm metadata is not a plugin quality gate.

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

**Verified, executed:** Edge/Chromium checks pass at 320, 390, 782 and 1280 pixels, including no horizontal overflow, associated labels, explicit dropdown values, keyboard-operated care disclosures, and browser validation of a completed synthetic piercing form. The synthetic PHP endpoint now returns attachment PDF and nosniff headers, rejects invalid category/language or unknown provider, and the browser downloads a care document through the preserved links. The HTML preview helper also renders at 320 pixels. Screenshots use fictitious content and are generated under the system temporary directory.

## PDF build and validation

**Verified from configuration:** root composer.json/composer.lock lock Dompdf and its runtime dependencies; tools/pdf-build/composer.json/composer.lock lock PHP-Scoper and development tools separately. Build tools require PHP 8.2+; the generated runtime targets PHP 7.4+ with DOM and mbstring. Production hosting needs a writable WordPress temporary directory, not Composer or Python. The namespace isolation rules in tools/pdf-build/scoper.inc.php include explicit patches for upstream dynamic class names and font-type lookup. Do not remove those adaptations without executing real rendering tests.

From the repository root (PowerShell; Python 3.9+ and PHP/Composer on PATH):

```powershell
composer install --no-dev --no-interaction --prefer-dist
composer --working-dir=tools/pdf-build install --no-dev --no-interaction --prefer-dist
php scripts/build-pdf-runtime.php
python -m pip install -r tests/requirements-pdf.txt
python tests/pdf-documents.py
php tests/forms-review.php
python scripts/update-translations.py
python scripts/build-package.py --tag v1.10.1
```

The build script regenerates only runtime/pdf, a Git-ignored build output containing isolated dependency source, Unicode fonts, original licenses, a relative plugin-owned autoloader and a SHA-256 manifest. vendor and tools/pdf-build/vendor are ignored as well. Source checkouts must build the runtime before using PDF downloads. The package command defaults to tmp/user-data-collection.zip. tmp contains synthetic test artifacts and is excluded from Git and the release ZIP. Dependency licenses are preserved; the unresolved project license remains META-01.

**Verified, executed:** PHP 8.3.28 generated all five categories in all nine bundled locales. pypdf 6.10.0 parsed 46 PDFs (45 care documents plus a second provider), verifying complete text, titles, embedded Unicode fonts, A4 dimensions, page numbering and multipage output. Provider failures and invalid parameters restore the locale; explicit Polylang translation and gettext fallback are tested with doubles. Spanish, German and an interior multipage rendering were inspected using Poppler. Edge checked both document endpoints, PDF MIME/attachment/nosniff headers, invalid inputs, and the downloaded filename, alongside the form regressions. All nine catalog/PHP locale checks and the 141 existing PHP form assertions passed. Composer audit reported no runtime advisories at execution; composer validate reports the existing unresolved project-license metadata.

**Evidence boundary:** synthetic tests do not establish real Polylang behavior, production hosting compatibility or client content approval. Native WordPress/package results are recorded in [Release](release.md); production checks remain pending.

The PDF module adds two translated download-error messages to all nine catalogs (61 required IDs per locale after removal of the five active website/review strings). The builder preserves every existing nonempty translation; tattoo and piercing care/consent copy are unchanged. The two new messages use the normal gettext fallback.

**Verified after link removal:** form regressions and PDF integration tests now assert that old review settings cannot restore promotional links and old website settings cannot override the WordPress home URL. All care PDF footers contain that URL exactly once. Removed UI strings remain as inactive catalog entries to preserve existing translations; they are no longer registered by the piercing form or required by its default-string manifest. No URL setting is added to another admin tab because the document URL is automatic.

**Unknown / unexecuted:** complete WordPress acceptance, live schema migration, real backup-file round trips, and translation behavior with a real Polylang installation. Docker Desktop's engine was unavailable. French piercing source copy, legal/medical wording, and minors/signature procedures await studio review as FORM-01.

## Form bug review

**Verified, executed 2026-10-24:** php tests/forms-review.php runs the earlier 90 checks plus targeted review regressions. Cases cover forged/expired/cross-form result receipts, database insert failure, safe redirects, repeated shortcodes including nonce IDs, future/impossible birth dates, accented names at the database character limit and restore compatibility, invalid stored links, gettext/Polylang fallback, and malformed administrative IDs. Exact current totals are recorded in the completed backlog item.

Generate the browser fixtures with php tests/forms-review.php --preview, then run node tests/forms-browser.cjs with Playwright resolvable. The extended isolated browser checks cover both forms' complete synthetic POST bodies, duplicate-submit prevention, Back restoration, requested-language downloads with a Polylang/gettext double, styled repeated original forms, visible keyboard focus, and birth-date bounds. PHP syntax and git diff --check are separate gates. Screenshots were inspected; this does not establish live WordPress, real Polylang or real database behavior.

The Polylang locale/slug and explicit translation contracts were checked against its [official function reference](https://polylang.pro/documentation/support/developers/function-reference/). Existing original-form strings remain protected by the baseline hash test. No content changes to that form were made during this review.

## Dark-background control contrast

**Verified, executed 2026-10-25:** the piercing form now applies the configured input text color to closed selects despite competing black theme rules, uses a matching SVG chevron and honors the existing light-icon setting through native date/time color-scheme. Popup options keep a readable light surface and system Highlight/HighlightText selection colors. The original form receives the same text-fill protection for selects without changing its copy.

An isolated Edge preview used a #550000 background, #ead8c3 configured text and a simulated theme rule forcing black select text. Computed text/text-fill, custom arrow and dark native control scheme passed; the inspected screenshot shows light calendar/clock icons. A 390px viewport had no horizontal overflow. Existing 141 synthetic form checks and PHP syntax checks passed. This has not been deployed to the live site or added to the published v1.8.0 ZIP.

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

## Admin naming in 1.8.1

**Verified, executed:** Waivers and Tattoos are now required admin message IDs in the default-string manifest (63 required IDs per locale). All nine catalogs and compiled MO files were rebuilt with version 1.8.1 and passed validation while preserving existing nonempty translations. An isolated PHP rendering check loaded each compiled catalog and checked the menu label, first submenu, tattoo view heading and both shortcode legends. It used WordPress/list-table doubles; real WordPress menu rendering and Polylang remain unverified for this change. The 141 existing form regressions and PHP syntax checks also passed.

## Tattoo aftercare PDF (2026-10-26)

**Verified, executed:** the default-string manifest now includes tattoo_care (71 required message IDs per locale, sharing the existing Version ID). All nine PO/MO catalogs and the PHP fallback checks pass for the new tattoo text, button and title. Existing nonempty translations remain preserved. The new provider uses distinct Polylang names; tests cover explicit overrides, locale mapping/restoration and gettext fallback with doubles.

`php tests/forms-review.php --preview` now passes 143 assertions, including preservation of the legacy string dictionary/consent and the tattoo-specific care block/download link. `python tests/pdf-documents.py` generates and independently parses 55 PDFs: nine tattoo documents, 45 piercing documents and a multipage test provider. It checks all section text, requested-language filenames, fonts, A4, metadata, numbering and the automatic website URL. The tattoo PDFs use exactly the six paragraphs rendered on the form. Spanish and German samples were rendered with Poppler and visually inspected. Edge downloaded both services' PDFs from their forms and retained the responsive/duplicate-submit checks. The ZIP gate passes with fifteen class files and 351 total archive entries.

These checks use the real PDF engine and compiled catalogs with synthetic WordPress functions. Real Polylang and production acceptance, and studio approval of tattoo-aftercare wording, remain pending. Clinical reference sources and scope are canonical in [Data, security, and privacy](data-security-privacy.md).

**Verified, tattoo introduction:** tattoo_intro adds two required message IDs (73 current required IDs per locale). All nine PO/MO variants and the PHP fallback checks pass, including the tattoo title and intro. The 143 form regressions pass; the preservation check excludes these two authorized new entries while continuing to protect the legacy dictionary.

## Unreleased optional fields and admin tables

**Verified, executed:** 169 PHP assertions cover both handlers with blank/omitted address and postal code, length/array rejection, required city, backup normalization/restore, ID-first primary-column contracts and translated category/body-location details. All nine catalog/PHP locale checks pass with 74 required IDs, including updated asterisk wording and the new Care category label. Edge form checks pass at 320, 390, 782 and 1280px; required labels end with an asterisk and optional controls do not, and completed forms remain valid with blank address/postal code.

An isolated WordPress 7.1.3 / PHP 8.3.28 / MySQL 8.4.3 installation accepted the same-version ZIP replacement and preserved synthetic data/settings. Its native WP_List_Table HTML, Spanish plugin catalog, core CSS, core common.js and assets were then rendered in Edge for both services at 320, 390, 782, 1024, 1280 and 1600px. Checks verified ID first, a single mobile expansion control, working native expansion, no horizontal overflow and a single-line desktop birth-date heading. Desktop/mobile screenshots were inspected; the final CSS also prevents the ID detail action from wrapping. This uses a temporary extension of the upgrade harness, with a NULL-field preservation assertion corrected only in that temporary copy. No production data or credentials were used.

These results do not prove real Polylang settings, production theme behavior or a real backup-file/database restore round trip. The field-validation/restore assertions use doubles; the native SQL test covers installation/update and table rendering. No release or version bump is part of this adjustment.

## Tattoo accepted-document evidence in 1.8.3

**Verified, executed:** 196 PHP assertions cover signed tattoo documents, missing/array/edited payloads, cross-service rejection, current-version submission gates, immutable historical rendering, separate form/care versions, inconsistent metadata and historical rows without evidence. All nine catalogs and PHP locale checks pass with 77 required IDs, including the new metadata labels and tattoo snapshots in every locale. Edge verifies that both completed forms post their document data, and the original responsive/download/duplicate-submit checks remain passing.

An isolated native WordPress 7.1.3 / PHP 8.3.28 / MySQL 8.4.3 run upgraded v1.8.2 to the local v1.8.3 ZIP, retained previous data/settings, registered the canonical shortcode and alias, rendered signed fields, submitted a real tattoo form with blank optional fields, checked the exact stored JSON, restored a cloned tattoo record through the real InnoDB transaction path, and rendered the native historical/current admin details. A tampered document was rejected without another row. This reused a temporary extension of the upgrade harness, with its NULL-field preservation assertion corrected only in the temporary copy. The normal UI tests still use doubles.

The schema is unchanged; existing metadata columns carry the snapshot. Real Polylang and production behavior remain separate acceptance boundaries. Structural graph tooling was unavailable (transport closed), so affected code and callers were verified by direct source reads, targeted text searches and executed tests. PDF tests and package gates remain part of the release workflow. No remote publication is performed by these checks.

## Optional city in 1.9.0

**Verified, executed:** 200 PHP assertions cover blank/omitted address/city/postal code in both handlers, backup normalization/restore, malformed types and maximum lengths. All nine catalog/PHP locale checks pass with synchronized 1.9.0 metadata. Edge checks at 320, 390, 782 and 1280px confirm that city is optional, has no required marker, and completed forms remain valid with all three optional contact fields empty.

An isolated WordPress 7.1.3 / PHP 8.3.28 / MySQL 8.4.3 run upgraded the HEAD baseline (1.8.3) to the local 1.9.0 ZIP and preserved synthetic data/settings. It verified the existing city varchar(255) NOT NULL column, then exercised tattoo and piercing submissions with city blank and omitted. All four cases stored an empty string with their exact document evidence, and real SQL restore preserved that value. No schema alteration was needed. The temporary harness used get_row() to distinguish an empty database string because wpdb::get_var() normalizes it to null; its existing NULL-field preservation check was also corrected only in the temporary copy. This does not establish production hosting or real Polylang behavior.

## Tattoo location label in 1.9.1

**Verified, executed:** tattoo_labels adds the corrected label_location source and defaults for en_GB, es_ES, fr/fr_FR/fr_CH, de_DE/de_CH and it_IT/it_CH (78 required IDs per locale). PO/MO compilation and all nine PHP fallback checks pass. The 200 form regressions pass. The legacy-copy hash invariant now excludes only the explicitly authorized location-label change, with its remaining baseline derived from the previous committed source. No input/column rename is part of this wording correction.

## Tattoo acknowledgements in 1.10.0

**Verified, executed:** 229 PHP assertions pass with WordPress/database doubles, including missing/invalid acknowledgement rejection, exact evidence persistence, backup normalization/restore and all four stored texts in admin details. All nine PO/MO/POT and PHP locale checks pass with 84 required strings. Edge checks at 320, 390, 782 and 1280px pass; tattoo has four required unchecked controls and submits all four explicit values with its signed document. PDF downloads and duplicate-submit protection remain functional in the synthetic server/browser harness. The local v1.10.0 ZIP passes the 351-file allowlist, PHP lint, runtime hashes and ZIP integrity checks.

**Unknown / not executed:** native WordPress/MySQL installation or deployment for this change, production Polylang overrides, studio/legal approval, and remote CI. The graph tools returned Transport closed, so investigation used direct source and tests; no graph-completeness claim is made. No historical-record migration was added.

## Shared tattoo/piercing styles

**Verified, executed:** the 229 PHP regressions and existing Edge checks pass after the styling change. An additional local Edge check compared computed field, select-arrow, button and checkbox styles across both forms; they match in the default fixture. The default care-panel background computes to rgba(0, 0, 0, 0.15), and its screenshot was inspected. Synthetic option checks verify missing, empty, invalid and disabled care-background cases, valid custom color preservation, and clearing the setting to its default. Production theme overrides remain unverified.

# Documentation index

This directory is the canonical map for maintainers and agents. The root [README](../README.md) is the user-facing product and installation guide. `AGENTS.md` contains working rules and links here rather than duplicating technical architecture.

## Epistemic labels

- **Verified:** directly supported by active source, configuration, metadata, or an executed command.
- **Inference:** a reasoned interpretation that is not directly asserted by the source.
- **Unknown:** not established by the available checkout or by an executed runtime check.
- **Requirement:** a rule or desired state that is not evidence that the current implementation satisfies it.

## Canonical documents

| Topic | Canonical document | Primary evidence |
| --- | --- | --- |
| Components, lifecycle, hooks, and data flows | [Architecture](architecture.md) | Entrypoint and PHP files under `includes/` |
| Data categories, controls, privacy, consent, and deletion | [Data, security, and privacy](data-security-privacy.md) | `UDC_Activator`, `UDC_Shortcode`, `UDC_Backup`, `UDC_Settings` |
| Backup, restore, cron, Drive, email, and recovery | [Operations](operations.md) | `UDC_Backup`, `UDC_GDrive`, `UDC_Email_Sync`, `UDC_Settings` |
| Environment, translations, tests, and quality gates | [Development and quality](development-and-quality.md) | Package files, catalogs, skills, and repository inventory |
| Versioning, tags, workflow, and ZIP contents | [Release](release.md) | Plugin header, package metadata, Git, and workflow |

## Read by task

Use the smallest applicable reading set. Follow links from those documents only when the task crosses an additional boundary.

| Task | Minimum reading set |
| --- | --- |
| Change the form, shortcode, or submission validation | [Architecture](architecture.md) and [Data, security, and privacy](data-security-privacy.md) |
| Change the database schema or migration behavior | [Data, security, and privacy](data-security-privacy.md) and [Release](release.md) |
| Change administrative screens, AJAX actions, or permissions | [Architecture](architecture.md) and [Data, security, and privacy](data-security-privacy.md) |
| Change backup, restore, retention, or deletion behavior | [Operations](operations.md) and [Data, security, and privacy](data-security-privacy.md) |
| Change cron, Google Drive, or email behavior | [Operations](operations.md) |
| Change the environment, tools, quality gates, or translations | [Development and quality](development-and-quality.md) |
| Change versions, tags, package contents, or the release workflow | [Release](release.md) |
| Investigate without changing code | Read the document for the affected area; do not read every document by default. |

## Change-to-document map

| Changed concern | Documentation that must be reviewed |
| --- | --- |
| Class responsibility, hook, lifecycle, dependency, or data flow | [Architecture](architecture.md) |
| Column, data category, validation, access, consent, or deletion path | [Data, security, and privacy](data-security-privacy.md) |
| Cron, backup, restore, retention, Drive, email, or recovery | [Operations](operations.md) |
| Dependency, command, environment, analysis gate, or translation process | [Development and quality](development-and-quality.md) |
| Version, tag convention, package allowlist, or release gate | [Release](release.md) |
| Stable cross-cutting instruction for agents | Root [AGENTS.md](../AGENTS.md) |

## Freshness contract

Each material fact has one canonical explanation. Other documents link to it instead of copying it. Update the affected document when the schema, hooks, lifecycle, integrations, commands, translations, versions, or release packaging changes.

Documentation must distinguish current behavior from requirements, known limitations, proposals, and unknown runtime conditions. Commands not executed in the current checkout must be marked **No verification** or **Unknown**. Code and configuration outrank Markdown when they disagree. Do not add claims of legal compliance, security, performance, reliability, or WPCS conformance without a defined gate and current evidence.

## Documentation validation

For documentation-only changes:

1. Compare versions, class inventories, hooks, commands, and package behavior with their canonical code or configuration source.
2. Resolve every relative Markdown link.
3. Search the changed text for unsupported claims such as `secure`, `GDPR compliant`, `high performance`, `maximum reliability`, or `WPCS compliant`.
4. Confirm that commands either exist in the checkout or are explicitly marked **No verification**.
5. Check that a fact has one canonical explanation and that other documents link to it.
6. Review the complete diff and run `git diff --check`.

Inspection of source or configuration is not proof that a runtime, integration, test, build, or release passed. Record any validation not performed and the reason; do not use a “last updated” date as a substitute for evidence.

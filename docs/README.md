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
| Components, lifecycle, hooks, and data flows | [Architecture](architecture.md) | Entrypoint and `includes/class-udc-*.php` |
| Data categories, controls, privacy, consent, and deletion | [Data, security, and privacy](data-security-privacy.md) | `UDC_Activator`, `UDC_Shortcode`, `UDC_Backup`, `UDC_Settings` |
| Backup, restore, cron, Drive, email, and recovery | [Operations](operations.md) | `UDC_Backup`, `UDC_GDrive`, `UDC_Email_Sync`, `UDC_Settings` |
| Environment, translations, tests, and quality gates | [Development and quality](development-and-quality.md) | Package files, catalogs, skills, and repository inventory |
| Versioning, tags, workflow, and ZIP contents | [Release](release.md) | Plugin header, package metadata, Git, and workflow |

## Freshness contract

Each material fact has one canonical explanation. Other documents link to it instead of copying it. Update the affected document when the schema, hooks, lifecycle, integrations, commands, translations, versions, or release packaging changes.

Documentation must distinguish current behavior from requirements, known limitations, proposals, and unknown runtime conditions. Commands not executed in the current checkout must be marked **No verification** or **Unknown**. Code and configuration outrank Markdown when they disagree. Do not add claims of legal compliance, security, performance, reliability, or WPCS conformance without a defined gate and current evidence.

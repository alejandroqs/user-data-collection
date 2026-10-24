# Pending work

**Answers:** What remains unresolved, where its evidence lives, and what would establish completion.

**Read when:** Resuming project work, selecting a task, or recording a newly found limitation or verification result.

**Update when:** A task is added, its evidence changes, a decision is made, or completion is demonstrated.

## Maintenance rules

**Requirement:** keep stable IDs and distinguish Open, In progress, Blocked, and Done. Every item needs evidence, a next action, and a completion criterion. Record executed checks and their result when closing an item. A source fix alone does not close a runtime verification item. Link technical explanations to their canonical document rather than copying them here. These items are proposed work, not authorization to change product behavior, access production, or run destructive checks.

Initial triage: 2026-10-24. All items below are **Open**; no owner or deadline has been assigned. Review the recovery and deletion items before relying on those operations; choose implementation order according to the intended environment and maintainer decisions.

## Open items

| ID | Evidence and canonical context | Next action and completion criterion |
| --- | --- | --- |
| BACKUP-01 | **Inference:** legacy import to a current-schema snapshot may produce a backup that restore rejects. Source: UDC_Backup::validate_backup_rows(), validate_backup_row(), create_backup(). See [Operations](operations.md). | Reproduce with synthetic data; decide the postal-code compatibility policy. Close after an executed legacy import -> new snapshot -> restore round trip preserves the supported data, while malformed and mixed schemas remain rejected. |
| DELETE-01 | **Verified:** UDC_Settings::ajax_delete_all_data() returns success without checking local file and database deletion results. See [Operations](operations.md). | Define partial-failure behavior and implement result checks. Close after isolated failure tests prove a failed deletion cannot be reported as complete; preserve capability and nonce checks. |
| ENV-01 | **Verified:** no versioned WordPress environment, supported PHP/WordPress version declaration, or WordPress integration test gate is provided. Focused checks with doubles are documented in Development and quality. See [Development and quality](development-and-quality.md). | Choose supported versions and a reproducible isolated environment. Close after documented setup and automated checks execute for submission validation, permissions, schema activation/update, and backup restore using synthetic data. |
| OPS-01 | **Unknown:** effective backup HTTP protection, cron execution/unscheduling, Drive behavior, and mail delivery in the intended environment. See [Operations](operations.md) and [Data, security, and privacy](data-security-privacy.md). | Record separate results for each boundary in an authorized isolated environment. Include denied HTTP access to a synthetic backup, cron failure cases, Drive ownership/rotation, and delivery to a controlled mailbox. Close only when results and remaining limitations are recorded. |
| ARCH-01 | **Verified:** initialization and UDC_Admin::add_admin_menu() instantiate additional UDC_Backup and UDC_Settings objects whose constructors register hooks. See [Architecture](architecture.md). | Decide how to reuse service instances or avoid repeated registration. Close after an executed admin-request check demonstrates one intended callback per relevant hook and functioning submenu pages. |
| PRIV-01 | **Verified:** retention, individual export/erasure, consent version/language storage, and separate credential storage are unresolved capabilities or policy decisions. See [Data, security, and privacy](data-security-privacy.md). | Have the maintainer/operator define requirements and ownership, including external copies. Split approved implementation work into explicit tasks; close this decision item when decisions and resulting tasks are recorded, not by asserting compliance. |
| I18N-01 | **Verified:** POT metadata is 1.3.0 versus plugin 1.6.0; no versioned regeneration process is provided. Catalog completeness and loading remain **Unknown**. See [Development and quality](development-and-quality.md). | Define and run an extraction/compilation process; review PO changes and MO output. Close after catalog coverage and runtime loading, including the optional Polylang path, are checked and recorded. |
| META-01 | **Verified:** plugin/schema, npm, and POT metadata differ; package metadata declares ISC. Intended licensing/distribution policy is **Unknown**. See [Release](release.md). | Obtain maintainer decisions for licensing and which version sources should align. Close after the selected policy is documented and applicable metadata agrees; do not infer license terms or bump versions during a documentation review. |

## Adding and closing items

**Requirement:** add only source-backed findings, explicit requirements, or clearly labelled hypotheses. Include a path and symbol for source findings, the relevant canonical document, and a testable completion criterion. Do not store personal data, credentials, production backup contents, or unverified success claims.

Move completed items to a Closed items section with the completion date, change reference when available, and actual validation evidence. Keep unresolved subchecks open or create linked follow-up items. Record Blocked only with the specific dependency that prevents progress.

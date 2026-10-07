# User Data Collection - Agent Instructions

## Evidence and source hierarchy

Use this order when making technical claims:

1. Active code, database schema, and registered hooks.
2. Configuration, scripts, workflows, and lockfiles.
3. Git history and tags.
4. Documentation.
5. Comments and inference.

Label material statements as **Verified**, **Inference**, **Unknown**, or **Requirement**. Do not use line numbers; cite paths and symbols instead. Do not claim that a test, build, browser flow, cron job, email, Drive operation, or runtime behavior passed unless it was actually executed.

## Codebase discovery

This repository uses `codebase-memory-mcp` as its primary source of structural code intelligence.

For questions involving architecture, symbols, dependencies, callers, callees, routes, cross-service relationships, execution paths, or change impact:

1. Prefer the `codebase-memory-mcp` graph tools before broad grep, glob, or file-by-file exploration.
2. Use direct source inspection to verify exact implementation behavior before editing or making high-confidence conclusions.
3. Do not treat an empty graph result as proof that code, callers, references, or dependencies do not exist.
4. Account for index freshness, pagination, excluded files, unsupported formats, and coverage gaps before making negative or exhaustive claims.
5. Use `rg`, glob, direct reads, and ordinary file search for exact text, configuration, documentation, generated files, unsupported files, or gaps not adequately represented by the graph.
6. Do not invoke destructive or persistent MCP operations unless the task explicitly requires them.

For non-trivial codebase investigation, dependency tracing, change-impact analysis, dead-code analysis, or exhaustive structural claims, use `.agents/skills/codebase-memory-project/SKILL.md`. If the MCP server is unavailable or incomplete, state the limitation and use direct source inspection as the fallback.

## Git and scope preservation

- Inspect `git status --short --branch` and relevant diffs before editing.
- Preserve unrelated modifications and untracked files. Do not reset, revert, stage, commit, push, tag, or change remote state unless explicitly requested.
- Keep changes within the user-approved scope. Report any path that could not be edited because it falls outside that scope.
- Treat generated files and local skills as user-owned unless the request explicitly includes them.

## Language and implementation rules

- All code, variable names, function names, and inline comments must be in English.
- Use official WordPress APIs and hooks. Use a custom database table created with `dbDelta`; do not introduce Custom Post Types.
- Use `$wpdb` APIs for database operations and preserve capability checks, nonces, input sanitization, and output escaping.
- Every database schema change requires an atomic update to `UDC_DB_VERSION` in `user-data-collection.php` and the `CREATE TABLE` schema in `includes/class-udc-activator.php`.
- Treat identity, contact, health, consent, local backups, external copies, email attachments, and service-account credentials as sensitive. Do not print or copy real secrets or personal data during debugging.
- Use proportional server-side validation; browser `required` attributes are not server validation.
- Do not describe the plugin as GDPR compliant, secure, high-performance, maximum-reliability, WPCS compliant, or legally certified without a named gate and current evidence.

## Canonical documentation

Keep one primary explanation for each topic. Start with the [documentation index](docs/README.md) and its task-routing table; read only the documents required for the current task. The canonical areas are [Architecture](docs/architecture.md), [Data, security, and privacy](docs/data-security-privacy.md), [Operations](docs/operations.md), [Development and quality](docs/development-and-quality.md), and [Release](docs/release.md).

Do not duplicate detailed architecture or audit tables in `AGENTS.md`. Update the affected canonical document when code, schema, hooks, integrations, commands, translations, versions, or release packaging changes.

---
name: codebase-memory-project
description: Investigate this repository using codebase-memory-mcp. Use for architecture exploration, locating unfamiliar code, tracing callers or dependencies, understanding execution paths, analyzing change impact, identifying dead code, investigating cross-service relationships, or making negative or exhaustive structural claims before editing code. Do not use for simple edits in already-known files or searches limited to exact text, documentation, or configuration.
---

# Codebase Memory Investigation

Use `codebase-memory-mcp` as the primary structural code-intelligence source for this repository.

The graph accelerates discovery but is not automatically authoritative or exhaustive. Verify implementation-sensitive conclusions against source code and account for index freshness, pagination, exclusions, unsupported files, and coverage gaps.

## Investigation level

Choose the lowest level that can answer the task safely.

### Scout

Use for fast, provisional discovery when the user needs orientation rather than a definitive conclusion.

Typical scope:

- approximately 3–4 focused MCP calls;
- identify likely symbols, files, modules, routes, or components;
- provide provisional findings;
- explicitly label unresolved uncertainty.

Scout findings must not be used to claim:

- that code does not exist;
- that a symbol has no callers;
- that an implementation is unused;
- that all affected files have been identified;
- that the result is exhaustive.

### Verify — default

Use for normal debugging, implementation planning, code changes, dependency analysis, or architectural questions.

Requirements:

1. Gather task-directed graph evidence.
2. Inspect the exact source implementations supporting the conclusion.
3. Check index status or freshness when uncertain.
4. Check relevant coverage when the capability is available.
5. Inspect skipped, excluded, unsupported, or coverage-flagged files directly when relevant.
6. Complete relevant pagination before treating a result set as complete.
7. State material limitations that remain.

### Auditor

Use when the user explicitly requests an audit, exhaustive analysis, dead-code determination, complete blast radius, complete caller inventory, or another high-confidence negative or comprehensive claim.

Requirements:

1. Define a bounded scope before searching.
2. Confirm the correct indexed project.
3. Confirm the current index generation or freshness.
4. Retrieve all relevant result pages.
5. Check broader inbound, outbound, type, route, import, test, and cross-service relationships.
6. Run coverage checks for all important evidence paths when available.
7. Inspect skipped, excluded, unsupported, generated, or ambiguous files directly.
8. Search exact source text when it can reveal references absent from the graph.
9. Report unresolved limitations explicitly.

A clean coverage result means only that no recorded gap was found. It is not proof that the index is complete.

## Tool selection

Choose tools according to the question rather than following a rigid universal sequence.

### Project and index state

- Use `list_projects` to identify indexed projects when project selection is uncertain.
- Use `index_status` when index state or freshness is uncertain.
- Use `index_repository` only when the relevant repository is not indexed or the index genuinely needs refreshing.
- Do not repeatedly re-index a repository whose index is current and automatically synchronized.
- Never use `delete_project` unless explicitly requested.

### Repository orientation

Use `get_architecture` when:

- entering an unfamiliar repository or subsystem;
- identifying packages, routes, entry points, layers, clusters, hotspots, or architectural boundaries;
- determining where a feature is likely implemented.

Do not call it automatically when the relevant subsystem and symbols are already known.

### Structural symbol search

Use `search_graph` to locate:

- functions;
- methods;
- classes;
- interfaces;
- types;
- modules;
- packages;
- files;
- routes;
- resources.

Search by structural properties, name patterns, labels, paths, or degree filters.

Respect pagination. An initial page is not a complete inventory.

### Conceptual search

Use `semantic_query` when available and the user describes behavior or intent without knowing the symbol name.

Use results as candidates, not final proof. Refine promising results with structural search and source inspection.

### Call paths and dependencies

Use `trace_path` for:

- inbound callers;
- outbound callees;
- execution paths;
- dependency chains;
- transitive impact.

When the exact qualified symbol is unknown, locate it with `search_graph` first.

Do not infer that a symbol has no callers from one empty or incomplete trace without verifying freshness, scope, pagination, coverage, and relevant source text.

### Source implementation

Use `get_code_snippet` for a known qualified function, method, or symbol.

Discover the qualified name with `search_graph` rather than guessing it.

Use direct file reads when:

- surrounding module context is needed;
- the relevant code is not represented as a graph symbol;
- exact line-level behavior matters;
- coverage is uncertain;
- the file format is unsupported or excluded.

### Exact textual search

Use `search_code` for exact textual searches within indexed project files, including:

- error messages;
- string literals;
- SQL fragments;
- configuration keys;
- UI copy;
- comments;
- constant names.

Use grep, glob, or ordinary file search instead when:

- the files are not indexed;
- configuration, documentation, assets, generated output, migrations, or unsupported formats are involved;
- the graph-backed search is insufficient;
- a precise repository-wide literal search is required.

### Complex graph questions

Use `query_graph` only when higher-level tools cannot answer the relationship or aggregate question clearly.

Before writing an unfamiliar Cypher-like query:

1. Call `get_graph_schema`.
2. Confirm relevant node labels, edge types, and properties.
3. Add an explicit bounded scope.
4. Include pagination or a justified limit.
5. Treat negative results cautiously.

### Current changes and blast radius

Use `detect_changes` when evaluating uncommitted Git changes or reviewing the impact of an implementation.

Use it to identify:

- changed symbols;
- callers and dependants;
- route or service effects;
- likely blast radius;
- risk classifications.

Verify surprising, high-risk, or incomplete results directly against source files, tests, and the Git diff.

Do not claim a change is safe solely because the detected blast radius is small.

### Architectural decisions

Use `manage_adr` to read relevant Architecture Decision Records when they can explain a design constraint.

Do not create, update, or delete ADRs unless the user explicitly requests an architectural decision to be recorded or changed.

### Runtime traces

Use `ingest_traces` only when runtime traces are available and the task requires validating dynamic HTTP or cross-service relationships.

Do not fabricate runtime evidence from static graph information.

## Negative and exhaustive claims

Before making claims such as:

- “this function is unused”;
- “there are no callers”;
- “this is the only implementation”;
- “no other route reaches this code”;
- “these are all affected files”;
- “nothing else depends on this type”;

perform all relevant checks:

1. Confirm the correct project.
2. Confirm index status and freshness.
3. Complete all relevant pagination.
4. Check coverage for the relevant files or paths when available.
5. Inspect skipped, excluded, generated, or unsupported files directly.
6. Search exact symbol names or literals in source when appropriate.
7. Check tests, registrations, reflection, dependency injection, configuration, and dynamically resolved references when applicable.
8. State any remaining limitation.

Absence from the graph is evidence, not proof of absence.

## Before editing unfamiliar code

Before making a non-trivial modification:

1. Locate the relevant symbols and files.
2. Identify the entry point or initiating route.
3. Trace important inbound callers.
4. Trace important outbound dependencies.
5. Inspect the exact implementations to be changed.
6. Identify related types, interfaces, registrations, tests, consumers, and cross-service links.
7. Note likely side effects and unresolved uncertainties.
8. Form a bounded implementation plan.

Do not perform broad file-by-file exploration when the graph has already identified a narrow relevant scope.

## After editing

After a non-trivial change:

1. Review the actual Git diff.
2. Run `detect_changes` when available.
3. Investigate unexpected or high-risk impact findings.
4. Run relevant tests.
5. Run applicable type checking, linting, and build commands.
6. Reinspect affected interfaces, routes, registrations, and consumers when the change alters a contract.
7. Report validation performed and any validation that could not be completed.

Do not claim the implementation is correct or safe solely from graph analysis.

## Fallback behavior

Fall back to ordinary repository tools when:

- the MCP server is unavailable;
- the repository is not indexed;
- indexing fails;
- relevant files are excluded or unsupported;
- results appear stale, inconsistent, incomplete, or ambiguous;
- exact textual or line-level inspection is more appropriate.

When falling back because of an MCP limitation, mention the limitation in the investigation summary instead of silently presenting the fallback result as graph-verified.

## Investigation output

For a substantial investigation, summarize:

1. Investigation level used: Scout, Verify, or Auditor.
2. Relevant architecture or subsystem.
3. Main symbols and files identified.
4. Important inbound and outbound relationships.
5. Exact source behavior verified.
6. Likely impact or implementation scope.
7. Coverage or index limitations.
8. Recommended next action.
---
name: env
description: Core knowledge about the local development environment, OS, terminal, and wp-env configuration to ensure correct commands and system interactions.
---

# Local Environment Knowledge (Windows 11 + wp-env)

This skill provides crucial context about the user's development machine. Whenever making system-level changes, running scripts, or interacting with the database, ADHERE strictly to the constraints outlined here.

## Operating System & Terminal
- **OS**: Windows 11.
- **Terminal Shell**: PowerShell.
- **Rule**: ALL terminal commands suggested or executed must be natively compatible with **PowerShell** syntax (e.g., using `&&` requires newer PS versions, otherwise prefer separate commands or `;` depending on context). Do NOT use Linux-only bash commands (like `rm -rf`, use `Remove-Item -Recurse -Force` or use cross-platform node scripts) unless executing *inside* the `wp-env` container.

## Local Server Environment (`wp-env`)
- **Technology**: The project relies on `@wordpress/env` (`wp-env`) which uses Docker Desktop (via WSL2 backend).
- **Execution Rule**: To run WP-CLI commands, you must use the container wrapper: `npx wp-env run cli wp <command>`.
- **Containers**: The architecture handles the WordPress core, the MySQL/MariaDB database, and mapping this plugin automatically.

## Database
- **Engine**: MariaDB (specifically `11.4.9-MariaDB` as per the container info) acting as a drop-in replacing MySQL. Both syntaxes are highly compatible, but any advanced native SQL written manually in the code (e.g., `dbDelta`) should target standard MySQL/MariaDB schema rules. 
- **WordPress Wrapper**: Always interact with the database using the `$wpdb` global object. 

## Best Practices for this Environment
1. **Paths**: Be careful with directory separators. The host machine uses `\` (Windows), while inside the docker container `wp-env` uses `/` (Linux).
2. **Line Endings**: Git in Windows usually handles `CRLF`. Ensure generated files are saved with standard line endings or ignore LF/CRLF warnings if they don't break functionality.
3. **Execution**: Avoid raw `curl` commands if `Invoke-WebRequest` is needed in PowerShell, or ideally, perform HTTP tests using Node scripts or WordPress's `wp_remote_*` inside the app.

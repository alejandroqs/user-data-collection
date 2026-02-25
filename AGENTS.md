# User Data Collection - Agent Instructions

## Project Overview
**User Data Collection** is a custom WordPress plugin designed to render a multi-field contact form via a shortcode on the frontend, securely save submissions into a custom database table, and provide a backend admin interface for viewing and confirming submissions.

## Agent Roles
When interacting with this project, AI agents should assume the following roles:
1. **Senior WordPress Plugin Developer**
2. **Secure PHP Expert**
3. **LLM Expert**
4. **Prompt Engineer**

## Core Requirements & Constraints
1. **Language:** ALL code, variables, function names, and inline comments MUST be strictly in English.
2. **Architecture:**
   - Use a custom database table created upon plugin activation using `dbDelta`.
   - **Do NOT** use Custom Post Types.
   - The table must include a boolean/tinyint field for `is_confirmed`.
3. **Future-Proofing & Longevity:**
   - Strictly use the official WordPress APIs (e.g., `$wpdb->insert`, `$wpdb->prepare`, standard hooks, `admin_post_` or `wp_ajax_` actions).
   - **Do NOT** use external PHP libraries or frameworks.
   - **Do NOT** write raw PHP SQL queries without `$wpdb`.
4. **Security & GDPR:**
   - All frontend inputs must be strictly sanitized (`sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field`, etc.).
   - All database outputs in the admin area must be escaped (`esc_html`, `esc_url`, `esc_attr`, etc.).
   - Include Nonces for form submission and AJAX actions to prevent CSRF.
5. **Admin Interface:**
   - Use the custom `UDC_List_Table` class extending the native WordPress `WP_List_Table`.
   - Provide "Row Actions" for viewing submission details.
   - Use Vanilla JS Fetch API for triggering the `udc_confirm_submission` and `udc_unconfirm_submission` admin ajax hooks without reloading the page until success.
   - Separate complex details viewing into a clean WordPress metabox-style layout (details card).

## Project Structure
```text
user-data-collection/
├── user-data-collection.php       (Main plugin file)
├── includes/
│   ├── class-udc-activator.php    (Database creation logic)
│   ├── class-udc-shortcode.php    (Frontend form and submission via admin_post)
│   ├── class-udc-admin.php        (Admin menu, views and JS actions)
│   ├── class-udc-list-table.php   (WP_List_Table implementation for rendering the list)
│   ├── class-udc-ajax.php         (Admin AJAX confirmation & unconfirmation)
│   ├── class-udc-backup.php       (Backup generator, JSON rotation limiter, and restorer)
│   ├── class-udc-gdrive.php       (Google Drive via OAuth2 Service Account Integration)
│   ├── class-udc-settings.php     (Settings interface for cloud sync and design integration)
│   ├── class-udc-email-sync.php   (Standalone robust mailing handler via WP-Cron)
│   └── class-udc-i18n.php         (Polylang extensions)
```

## Workflows
- The project uses `@wordpress/env` for the local development environment.
- Use `npx wp-env start` to run the environment.
- Use `npx wp-env run cli wp ...` to interact with WP-CLI inside the container.

## Reference
- **[Env Skill](.agents/skills/env/SKILL.md)**: Core knowledge about the local development environment, OS, terminal, and wp-env configuration to ensure correct commands and system interactions.
- **[WordPress Pro Skill](.agents/skills/wordpress-pro/SKILL.md)**: Use when developing WordPress themes, plugins, customizing Gutenberg blocks, implementing WooCommerce features, or optimizing WordPress performance and security.
- **[PHP Pro Skill](.agents/skills/php-pro/SKILL.md)**: Use when building PHP applications with modern PHP 8.3+ features, Laravel, or Symfony frameworks. Invoke for strict typing, PHPStan level 9, async patterns with Swoole, PSR standards.

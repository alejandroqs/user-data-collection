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
   - Use a clean approach for the admin area.
   - Provide a basic tabular view of the submissions with an action link/button to mark a submission as "Confirmed" via AJAX.

## Project Structure
```text
user-data-collection/
├── user-data-collection.php       (Main plugin file)
├── includes/
│   ├── class-udc-activator.php    (Database creation logic)
│   ├── class-udc-shortcode.php    (Frontend form and submission via admin_post)
│   ├── class-udc-admin.php        (Admin menu and display)
│   └── class-udc-ajax.php         (Admin AJAX confirmation)
```

## Workflows
- The project uses `@wordpress/env` for the local development environment.
- Use `npx wp-env start` to run the environment.
- Use `npx wp-env run cli wp ...` to interact with WP-CLI inside the container.

## Reference
- **[WordPress Pro Skill](.agents/skills/wordpress-pro/SKILL.md)**: Use when developing WordPress themes, plugins, customizing Gutenberg blocks, implementing WooCommerce features, or optimizing WordPress performance and security.
- **[PHP Pro Skill](.agents/skills/php-pro/SKILL.md)**: Use when building PHP applications with modern PHP 8.3+ features, Laravel, or Symfony frameworks. Invoke for strict typing, PHPStan level 9, async patterns with Swoole, PSR standards.

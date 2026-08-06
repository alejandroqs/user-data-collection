# User Data Collection for WordPress

User Data Collection renders a multi-field consent form through the `[udc_contact_form]` shortcode and stores submissions in a custom database table. The administrative interface provides a sortable submission list, detail views, confirmation actions, backup tools, optional Google Drive synchronization, optional email backups, and frontend color settings.

The form processes sensitive personal information, including identity and contact details, date of birth, appointment information, health-related answers, and liability acceptance. Configure access, storage, retention, and external destinations according to the site's own privacy and security requirements. This plugin documentation is not legal advice or a legal certification.

## Installation

1. Copy the `user-data-collection` directory into `wp-content/plugins/`.
2. Activate the plugin from the WordPress Plugins screen.
3. Activation creates or updates the custom `{prefix}udc_submissions` table through `dbDelta`.

## Frontend use

Add the shortcode below to a page, post, or widget:

```text
[udc_contact_form]
```

The form collects personal data, health questionnaire answers, appointment details, piercing location, and a required liability acceptance checkbox. The submission is sent through WordPress's `admin-post.php` endpoint and, when accepted, is saved to the custom table.

## Administration

Users with the `manage_options` capability can open the **Submissions** menu to:

- review upcoming and past appointments;
- open submission details;
- confirm or unconfirm submissions through AJAX actions;
- create, restore, and upload local JSON backups;
- configure optional Google Drive, email backup, and frontend design settings.

## Optional integrations

- **Local JSON backups:** The plugin writes database snapshots under the WordPress uploads directory and rotates local JSON files according to the current implementation. See [Operations](docs/operations.md).
- **Google Drive:** A service-account integration can upload local JSON files to a configured folder. This sends sensitive data to an external service and requires separate credential and access management.
- **Email:** The latest local JSON file can be sent as an email attachment to a configured address. Email storage and retention are outside this plugin.

These integrations are optional and depend on WordPress runtime behavior, server configuration, and the availability of the external service. WP-Cron events are not guaranteed to run at their scheduled time merely because they are registered.

## Database schema changes

When the table schema changes, update both the `CREATE TABLE` statement in `includes/class-udc-activator.php` and the `UDC_DB_VERSION` constant in `user-data-collection.php`. The plugin compares the stored version during `plugins_loaded` and invokes the activator when the version differs.

## Documentation

- [Documentation index](docs/README.md)
- [Data, security, and privacy](docs/data-security-privacy.md)
- [Operations](docs/operations.md)

The technical documentation distinguishes verified behavior, inference, unknowns, and requirements. It does not claim GDPR compliance, security certification, performance certification, or WordPress Coding Standards compliance without a defined and executed gate.

## License

The repository README describes this as private software. The package metadata currently declares the ISC license; see [Release](docs/release.md) for the version and packaging discrepancies that require an explicit maintainer decision.

# User Data Collection for WordPress

User Data Collection renders separate consent forms through `[udc_contact_form]` and `[udc_piercing_form]` and stores submissions in a custom database table. The administrative interface provides a sortable submission list, detail views, backup tools, optional Google Drive synchronization, optional email backups, and frontend color settings.

The form processes sensitive personal information, including identity and contact details, date of birth, appointment information, health-related answers, and liability acceptance. Configure access, storage, retention, and external destinations according to the site's own privacy and security requirements. This plugin documentation is not legal advice or a legal certification.

## Installation

1. Install the built `user-data-collection.zip` (it includes the PDF library and fonts), or copy its `user-data-collection` directory into `wp-content/plugins/`.
2. Activate the plugin from the WordPress Plugins screen.
3. Activation creates or updates the custom `{prefix}udc_submissions` table through `dbDelta`.

## Frontend use

Add the shortcode below to a page, post, or widget:

```text
[udc_contact_form]
```

The existing form keeps its original copy. Its five health questions now require an explicit Yes, No, or Not sure / discuss in person answer.

For the new piercing form, use:

```text
[udc_piercing_form]
```

The piercing form has French source copy and bundled default translations for the existing Spanish, British English, French, German and Italian locale variants, registers its own Polylang strings, offers five care categories with PDF downloads, and records the accepted consent/care text and interface language. It uses one care category per submission; multiple perforations in that category can be described in the location field. Review its content with the studio before publication.

PDF downloads require PHP 7.4+, DOM, mbstring and a writable temporary directory. Source checkouts need the [PDF build steps](docs/development-and-quality.md#pdf-build-and-validation); Composer is not needed on the production host when installing the built ZIP.

The existing form collects personal data, health questionnaire answers, appointment details, piercing location, and a required liability acceptance checkbox. The submission is sent through WordPress's `admin-post.php` endpoint and, when accepted, is saved to the custom table.

## Administration

Users with the `manage_options` capability can open the **Submissions** menu to:

- review upcoming and past appointments;
- open submission details;
- open the separate **Piercings** submenu and copy its shortcode;
- create, restore, and upload local JSON backups;
- configure optional Google Drive, email backup, and frontend design settings.

The piercing form has no promotional website or review links. Care PDFs use the WordPress home URL automatically, without an editable URL field. Existing records remain in the original section without being retrospectively classified as tattoos.

Both forms reject future birth dates and use character limits that preserve accented names. Result messages require a short-lived handler receipt. With JavaScript enabled, repeated browser submits are blocked while an earlier submission is pending; controls are restored when returning with the Back button.

## Optional integrations

- **Local JSON backups:** The plugin writes database snapshots under the WordPress uploads directory and rotates local JSON files according to the current implementation. See [Operations](docs/operations.md).
- **Public submission controls:** The public handler applies server-side validation and a bounded attempt budget. See [Data, security, and privacy](docs/data-security-privacy.md) for the exact controls and their limitations.
- **Restore:** Local and uploaded JSON restores are validated and additive. See [Operations](docs/operations.md) for the current limits, legacy compatibility, and failure behavior.
- **Google Drive:** A service-account integration can upload local JSON files to a configured folder. This sends sensitive data to an external service and requires separate credential and access management.
- **Email:** The latest local JSON file can be sent as an email attachment to a configured address. Email storage and retention are outside this plugin.

These integrations are optional and depend on WordPress runtime behavior, server configuration, and the availability of the external service. WP-Cron events are not guaranteed to run at their scheduled time merely because they are registered.

## Documentation

- [Documentation index](docs/README.md)
- [Data, security, and privacy](docs/data-security-privacy.md)
- [Operations](docs/operations.md)

Maintainers and coding agents should start with the [documentation index](docs/README.md), which routes common tasks to the minimum required technical context. Schema-change and release rules are maintained in [AGENTS.md](AGENTS.md) and [Release](docs/release.md).

The technical documentation distinguishes verified behavior, inference, unknowns, and requirements. It does not claim GDPR compliance, security certification, performance certification, or WordPress Coding Standards compliance without a defined and executed gate.

## License

**Verified:** the package metadata declares ISC. **Unknown:** the intended licensing and distribution policy; see [Release](docs/release.md) for the discrepancy and pending maintainer decision.

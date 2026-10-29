# User Data Collection for WordPress

User Data Collection renders separate consent forms through `[udc_tattoo_form]` and `[udc_piercing_form]` and stores submissions in a custom database table. The administrative interface provides a sortable submission list, detail views, backup tools, optional Google Drive synchronization, optional email backups, and frontend color settings.

The form processes sensitive personal information, including identity and contact details, date of birth, appointment information, health-related answers, and liability acceptance. Configure access, storage, retention, and external destinations according to the site's own privacy and security requirements. This plugin documentation is not legal advice or a legal certification.

## Installation

1. Install the built `user-data-collection.zip` (it includes the PDF library and fonts), or copy its `user-data-collection` directory into `wp-content/plugins/`.
2. Activate the plugin from the WordPress Plugins screen.
3. Activation creates or updates the custom `{prefix}udc_submissions` table through `dbDelta`.

## Frontend use

Add the shortcode below to a page, post, or widget:

```text
[udc_tattoo_form]
```

The previous `[udc_contact_form]` shortcode remains a compatibility alias. New tattoo submissions retain the exact accepted text, language, form version and care version for the administrative detail view. Older records without a saved document are explicitly marked unavailable.

The tattoo form requires four separate acknowledgements covering procedure/truthfulness, risk information, aftercare and data processing. Its five health questions require an explicit Yes, No, or Not sure / discuss in person answer. Its care section now provides tattoo-specific instructions and a translated PDF download using the same text and the automatic site URL.

For the new piercing form, use:

```text
[udc_piercing_form]
```

The piercing form has French source copy and bundled default translations for the existing Spanish, British English, French, German and Italian locale variants, registers its own Polylang strings, offers five care categories with PDF downloads, and records the accepted consent/care text and interface language. It uses one care category per submission; multiple perforations in that category can be described in the location field. Review its content with the studio before publication.

PDF downloads require PHP 7.4+, DOM, mbstring and a writable temporary directory. Source checkouts need the [PDF build steps](docs/development-and-quality.md#pdf-build-and-validation); Composer is not needed on the production host when installing the built ZIP.

Both forms mark required fields and answers with an asterisk (*). Address, city and postal code are no longer collected. See [data migration and retired fields](docs/data-security-privacy.md#validation-and-result-review) before upgrading an older installation.

The tattoo form collects personal data, health questionnaire answers, appointment details, tattoo location, and the four required acknowledgements. The submission is sent through WordPress's `admin-post.php` endpoint and, when accepted, is saved to the custom table.

## Guardian authorization

Under **Waivers → Settings → General**, select a blank authorization template for each of French, German, Italian, English and Spanish, then save changes. The selectors use the WordPress media library and accept PDF, JPG/JPEG, PNG, WebP and AVIF. Both service forms share these settings; the form language selects its document. Supply templates approved by the studio.

Visitors under 18 on the submission date must check the minority declaration. The displayed notice instructs them to attend with the paper authorization completed and signed by their legal guardian. The form does not upload a signed authorization. If the corresponding template is missing, the form displays contact instructions rather than substituting another language or independently blocking submission. See [guardian architecture](docs/architecture.md#guardian-authorization) for language resolution and the local-file download limitation.

With custom design disabled, both forms use a mobile layout with local Roboto, black text and a transparent container. Enable custom design in Settings to apply the configured colors. The tattoo submit button uses the translated **Submit my consent** label, matching the piercing form's intent.

## Administration

Users with the `manage_options` capability can open the **Waivers** menu (**Descargos** in Spanish) to:

- review upcoming and past appointments;
- open submission details;
- read the final **Is a minor** column (**Es menor** in Spanish), with green No / red Yes badges; details show the same badge below date of birth. See [minority display rules](docs/architecture.md#minority-badges) for stored declarations versus calculated age;
- open **Tattoos** or **Piercings** and copy the corresponding shortcode shown above each list; their page headings are **Tattoo waivers** and **Waivers for piercings** (**Descargos tatuajes** / **Descargos piercings** in Spanish);
- create, restore, and upload local JSON backups;
- configure the five language-specific guardian authorization templates under **Settings → General**;
- configure optional Google Drive, email backup, and frontend design settings;
- download a French A5 QR poster from either service list and configure its colors and destination URLs in **Settings → QR Image**.

The piercing form has no promotional website or review links. Care PDFs use the WordPress home URL automatically, without an editable URL field. Existing records remain in the original section without being retrospectively classified as tattoos.

Both forms reject future birth dates and use character limits that preserve accented names. Result messages require a short-lived handler receipt. With JavaScript enabled, repeated browser submits are blocked while an earlier submission is pending; controls are restored when returning with the Back button.

## QR posters

Save the settings before downloading. Posters include the bundled studio logo and always use French invitation text. Changing a destination URL requires downloading and printing a new poster; already printed QR codes keep their original URL. See [QR poster architecture](docs/architecture.md#qr-posters) for defaults and implementation, and [QR validation](docs/development-and-quality.md#qr-poster-validation) for verification limits.

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

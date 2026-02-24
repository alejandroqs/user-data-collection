# User Data Collection & Consents for WordPress

A robust, high-performance, and GDPR-compliant WordPress plugin engineered to handle custom user data collection, securely gathering sensitive demographic data, health questionnaires, and legal piercing/tattoo consent forms directly into a custom database table.

## 🚀 Features

* **Custom Database Architecture:** Uses `dbDelta` upon activation to construct a highly performant and secure standalone table (`wp_udc_submissions`) instead of cluttering the default `wp_posts` table with Custom Post Types.
* **Security First:** Rigorous backend data sanitization (`sanitize_text_field`, `wp_unslash`) and output escaping (`esc_html`, `esc_attr`). Integrates WordPress Nonces to prevent Cross-Site Request Forgery (CSRF).
* **GDPR Compliance Support:** By isolating data in a custom table, bulk exports, deletions, and privacy management become inherently easier and structurally sound.
* **Modern Admin Interface:** Provides a clean, native-feeling WordPress backend table to view all submissions. Supports instantaneous, vanilla Javascript AJAX integration to mark submissions as "Confirmed" without page reloads.
* **Fully Internationalized (i18n):** Seamlessly integrates with the **Polylang** string translation API (`pll_register_string`), falling back to standard WordPress translation functions (`__()`) when needed. Supports instant translation of all form labels, checkboxes, and terms based on the current active locale (e.g., FR, DE, IT, EN).
* **Performant Form Processing:** Submission handling uses the `admin_post_*` API, completely avoiding generic frontend POST targets that can be exploited or cause cache misses.

## 📦 Installation

1. Copy the `user-data-collection` folder into your `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Upon activation, the plugin automatically creates the necessary `wp_udc_submissions` custom table.

## 💻 Usage

### 1. The Frontend Shortcode
Insert the following shortcode into any Page, Post, or Widget to render the multi-part consent form:

```text
[udc_contact_form]
```

The rendered shortcode includes two distinct logical documents:
1. **Health Questionnaire:** Captures demographic details and boolean responses regarding health conditions (medications, allergies, pregnancy, etc.) along with a mandatory Liability Acceptance clause.
2. **Disclaimer & Piercing Care:** Captures appointment time/location details, displays required post-procedure care instructions, and mandates a final consent confirmation.

### 2. Managing Submissions
* Navigate to the **Submissions** menu located in the left sidebar of the WordPress Admin dashboard.
* Here you can view a tabular list of all submitted forms containing Name, Date of Birth, Appointment Details, and Status.
* Click the **Confirm** button on any pending row to mark the consent form as reviewed. This operates via a secure AJAX call.

### 3. Translating the Form (Polylang)
If you operate a multi-language website, this plugin is natively ready for Polylang.
1. Install and activate Polylang.
2. Navigate to **Languages > String Translations** in the WordPress admin.
3. Filter by the `"UDC Contact Form"` group.
4. Provide translations for the labels, questions, and legal clauses for your configured languages (French, German, Italian, etc.).
5. The form will automatically switch languages based on the active URL locale (e.g., website.com/fr/).

## 🛠 Architecture & Development

This project was built strictly adhering to established WordPress Coding Standards, leveraging `wp-env` for local dockerized development.

### File Structure
* `user-data-collection.php` - Plugin header, constants, and initialization.
* `includes/class-udc-activator.php` - Database schema creation.
* `includes/class-udc-shortcode.php` - Frontend rendering and POST handling API.
* `includes/class-udc-admin.php` - Backend interface rendering and inline scripts.
* `includes/class-udc-ajax.php` - Secure backend endpoint for the "Confirm" action.
* `includes/class-udc-i18n.php` - Translation hooks and Polylang dynamic string registration.

### Local Development (`wp-env`)
To run the WordPress environment locally for testing:
```bash
# Start the local environment
npx wp-env start

# Interact with WP-CLI inside the container
npx wp-env run cli wp plugin status
```

## 📜 License
This built-for-purpose plugin is private software. Do not redistribute without authorization.

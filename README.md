# User Data Collection & Consents for WordPress

A robust, high-performance, and GDPR-compliant WordPress plugin engineered to handle custom user data collection, securely gathering sensitive demographic data, health questionnaires, and legal piercing/tattoo consent forms directly into a custom database table.

## 🚀 Features

* **Custom Database Architecture:** Uses `dbDelta` upon activation to construct a highly performant and secure standalone table (`wp_udc_submissions`) instead of cluttering the default `wp_posts` table with Custom Post Types.
* **Security First:** Rigorous backend data sanitization (`sanitize_text_field`, `wp_unslash`) and output escaping (`esc_html`, `esc_attr`). Integrates WordPress Nonces to prevent Cross-Site Request Forgery (CSRF).
* **GDPR Compliance Support:** By isolating data in a custom table, bulk exports, deletions, and privacy management become inherently easier and structurally sound.
* **Modern Admin Interface (`WP_List_Table`):** Provides a robust, native WordPress backend table implementing sorting, pagination, and filter views for "Upcoming" and "Past" appointments.
* **Instant AJAX Actions:** Vanilla Javascript AJAX integration allows you to mark submissions as "Confirmed" or "Unconfirmed" seamlessly without page reloads.
* **Comprehensive Details View:** A dedicated, custom-designed inspector screen displays the breakdown of all demographic, health data, and legal acceptance clauses.
* **Fully Internationalized (i18n):** The backend is fully translated using standard WordPress `__()` and `.po`/`.mo` files. The frontend dynamically ties into Polylang's string translation API for ultimate locale flexibility (FR, DE, IT, EN, ES, etc).
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
* Here you can view a powerful, sortable list of all submitted forms containing Name, Date of Birth, Appointment Details, and Status.
* **Views:** Use the top tabs to filter between "Upcoming" (sorted closest to today) and "Past" appointments.
* **Actions:** Use the "Confirm" or "Unconfirm" buttons in the action column.
* **Details:** Click "View Details" under the Last Name column to inspect the full scope of health and liability responses for an individual submission.

### 3. Translating the Form
If you operate a multi-language website, this plugin provides two translation pipelines:
1. **Backend Admin:** Standard `.po/.mo` files located in the `/languages/` folder. Re-compile using `npx wp-env run cli wp i18n make-mo <path>`.
2. **Frontend (Polylang):**
   * Navigate to **Languages > String Translations** in the WordPress admin.
   * Filter by the `"UDC Contact Form"` group.
   * Provide translations for the labels, questions, and legal clauses.
   * The form will automatically switch languages based on the active URL locale (e.g., website.com/fr/).

## 🛠 Architecture & Development

This project was built strictly adhering to established WordPress Coding Standards, leveraging `wp-env` for local dockerized development.

### File Structure
* `user-data-collection.php` - Plugin header, constants, and initialization.
* `includes/class-udc-activator.php` - Database schema creation.
* `includes/class-udc-shortcode.php` - Frontend rendering and POST handling API.
* `includes/class-udc-admin.php` - Backend interface rendering and inline scripts.
* `includes/class-udc-list-table.php` - Native implementation of `WP_List_Table` for admin data management.
* `includes/class-udc-ajax.php` - Secure backend endpoints for "Confirm/Unconfirm" actions.
* `includes/class-udc-i18n.php` - Translation hooks and Polylang dynamic string registration.
* `languages/` - Contains standard WordPress `.po` and compiled `.mo` translation catalogs.

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

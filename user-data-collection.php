<?php
/**
 * Plugin Name: User Data Collection
 * Plugin URI:  https://alejandroqs.github.io
 * Description: A secure, high-performance plugin to collect user submissions via shortcode and manage them in a custom database table. GDPR compliant.
 * Version:     1.2.0
 * Author:      Alejandro Quesada
 * Author URI:  https://alejandroqs.github.io
 * Text Domain: user-data-collection
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define('UDC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UDC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UDC_DB_VERSION', '1.3.0');

// Include required dependencies
require_once UDC_PLUGIN_DIR . 'includes/class-udc-i18n.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-activator.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-shortcode.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-list-table.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-admin.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-ajax.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-backup.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-settings.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-gdrive.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-email-sync.php';

// Register Activation Hook
register_activation_hook(__FILE__, ['UDC_Activator', 'activate']);

// Register Deactivation Hook
function udc_on_deactivate()
{
    UDC_Backup::clear_cron();
    UDC_GDrive::clear_cron();
    UDC_Email_Sync::clear_cron();
}
register_deactivation_hook(__FILE__, 'udc_on_deactivate');

// Initialize components
add_action('plugins_loaded', 'udc_init_plugin');
function udc_init_plugin()
{
    // Auto-update database schema if version changed
    if (get_option('udc_db_version') !== UDC_DB_VERSION) {
        UDC_Activator::activate();
    }

    new UDC_i18n();
    new UDC_Shortcode();

    // Core background services (Required outside is_admin for WP-Cron)
    new UDC_Backup();
    new UDC_GDrive();
    new UDC_Email_Sync();

    if (is_admin()) {
        new UDC_Admin();
        new UDC_Ajax();
        new UDC_Settings();
    }
}

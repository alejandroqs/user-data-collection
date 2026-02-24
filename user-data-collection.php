<?php
/**
 * Plugin Name: User Data Collection
 * Plugin URI:  https://alejandroqs.github.io
 * Description: A secure, high-performance plugin to collect user submissions via shortcode and manage them in a custom database table. GDPR compliant.
 * Version:     1.0.0
 * Author:      Alejandro Quesada
 * Text Domain: user-data-collection
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define('UDC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UDC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UDC_DB_VERSION', '1.0.0');

// Include required dependencies
require_once UDC_PLUGIN_DIR . 'includes/class-udc-i18n.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-activator.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-shortcode.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-admin.php';
require_once UDC_PLUGIN_DIR . 'includes/class-udc-ajax.php';

// Register Activation Hook
register_activation_hook(__FILE__, ['UDC_Activator', 'activate']);

// Initialize components
add_action('plugins_loaded', 'udc_init_plugin');
function udc_init_plugin()
{
    new UDC_i18n();
    new UDC_Shortcode();

    if (is_admin()) {
        new UDC_Admin();
        new UDC_Ajax();
    }
}

<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Ajax
{

    public function __construct()
    {
        add_action('wp_ajax_udc_confirm_submission', [$this, 'confirm_submission']);
    }

    public function confirm_submission()
    {
        // 1. Ensure user has capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }

        // 2. Validate input and ID
        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;

        if ($submission_id <= 0) {
            wp_send_json_error('Invalid submission ID.');
        }

        // 3. Verify Nonce securely
        if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'udc_confirm_nonce_' . $submission_id)) {
            wp_send_json_error('Security token invalid.');
        }

        // 4. Update the DB
        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';

        $updated = $wpdb->update(
            $table_name,
            ['is_confirmed' => 1], // What to update
            ['id' => $submission_id], // Where clause
            ['%d'], // Format for update field
            ['%d']  // Format for where field
        );

        if ($updated !== false) {
            wp_send_json_success('Submission confirmed successfully.');
        } else {
            wp_send_json_error('Database update failed.');
        }
    }
}

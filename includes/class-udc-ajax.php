<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Ajax
{

    public function __construct()
    {
        add_action('wp_ajax_udc_confirm_submission', [$this, 'confirm_submission']);
        add_action('wp_ajax_udc_unconfirm_submission', [$this, 'unconfirm_submission']);
    }

    public function confirm_submission()
    {
        // 1. Ensure user has capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'user-data-collection'));
        }

        // 2. Validate input and ID
        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;

        if ($submission_id <= 0) {
            wp_send_json_error(__('Invalid submission ID.', 'user-data-collection'));
        }

        // 3. Verify Nonce securely
        if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'udc_confirm_nonce_' . $submission_id)) {
            wp_send_json_error(__('Security token invalid.', 'user-data-collection'));
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
            wp_send_json_success(__('Submission confirmed successfully.', 'user-data-collection'));
        } else {
            wp_send_json_error(__('Database update failed.', 'user-data-collection'));
        }
    }

    public function unconfirm_submission()
    {
        // 1. Ensure user has capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'user-data-collection'));
        }

        // 2. Validate input and ID
        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;

        if ($submission_id <= 0) {
            wp_send_json_error(__('Invalid submission ID.', 'user-data-collection'));
        }

        // 3. Verify Nonce securely (We can use the same confirmation nonce, or a new unconfirm nonce. Let's use unconfirm_nonce_)
        if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'udc_unconfirm_nonce_' . $submission_id)) {
            wp_send_json_error(__('Security token invalid.', 'user-data-collection'));
        }

        // 4. Update the DB
        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';

        $updated = $wpdb->update(
            $table_name,
            ['is_confirmed' => 0], // Revert to pending
            ['id' => $submission_id],
            ['%d'],
            ['%d']
        );

        if ($updated !== false) {
            wp_send_json_success(__('Submission unconfirmed successfully.', 'user-data-collection'));
        } else {
            wp_send_json_error(__('Database update failed.', 'user-data-collection'));
        }
    }
}

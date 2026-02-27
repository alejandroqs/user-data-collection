<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Email_Sync
{
    public function __construct()
    {
        add_action('wp_ajax_udc_manual_email_backup', [$this, 'ajax_manual_email_backup']);
        add_action('udc_monthly_email_sync_action', [$this, 'send_backup']);

        add_filter('cron_schedules', [$this, 'add_cron_schedule']);
    }

    public function add_cron_schedule($schedules)
    {
        if (!isset($schedules['udc_monthly'])) {
            $schedules['udc_monthly'] = [
                'interval' => 2592000, // 30 days
                'display' => __('Once Monthly', 'user-data-collection')
            ];
        }
        return $schedules;
    }

    public static function schedule_cron()
    {
        if (!wp_next_scheduled('udc_monthly_email_sync_action')) {
            wp_schedule_event(time(), 'udc_monthly', 'udc_monthly_email_sync_action');
        }
    }

    public static function clear_cron()
    {
        $timestamp = wp_next_scheduled('udc_monthly_email_sync_action');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'udc_monthly_email_sync_action');
        }
    }

    public function send_backup()
    {
        $is_enabled = get_option('udc_email_backup_enabled', '0');
        if (!$is_enabled) {
            return false; // Silently abort if disabled
        }

        $email_address = get_option('udc_email_address', '');
        if (empty($email_address) || !is_email($email_address)) {
            return new WP_Error('invalid_email', __('Invalid email address configured for backups.', 'user-data-collection'));
        }

        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/udc-backups/';
        $local_files = glob($backup_dir . '*.json');

        if (empty($local_files)) {
            return new WP_Error('no_backups', __('No local backups found to send.', 'user-data-collection'));
        }

        // Sort dynamically newest first
        usort($local_files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $latest_backup = $local_files[0];

        $subject = __('Automated Data Backup - User Data Collection', 'user-data-collection');

        $message = __('Hello,', 'user-data-collection') . "\n\n";
        $message .= __('Attached is the most recent backup of your database submissions from the User Data Collection system.', 'user-data-collection') . "\n\n";
        $message .= sprintf(__('Date of backup: %s', 'user-data-collection'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($latest_backup))) . "\n";
        $message .= sprintf(__('File size: %s', 'user-data-collection'), size_format(filesize($latest_backup))) . "\n\n";
        $message .= __('This is an automated message. Please store this file in a secure location.', 'user-data-collection') . "\n";

        $attachments = is_array(func_get_args()) && !empty(func_get_args()) ? [] : [$latest_backup];

        $sender_email = get_option('udc_email_sender_address', '');
        $sender_name = get_option('udc_email_sender_name', '');

        // Temporarily override mail from features
        $mail_from_filter = function ($original_email_address) use ($sender_email) {
            return !empty($sender_email) ? $sender_email : $original_email_address;
        };
        $mail_from_name_filter = function ($original_email_from) use ($sender_name) {
            return !empty($sender_name) ? $sender_name : $original_email_from;
        };

        if (!empty($sender_email)) {
            add_filter('wp_mail_from', $mail_from_filter);
        }
        if (!empty($sender_name)) {
            add_filter('wp_mail_from_name', $mail_from_name_filter);
        }

        $sent = wp_mail($email_address, $subject, $message, '', $attachments);

        if (!empty($sender_email)) {
            remove_filter('wp_mail_from', $mail_from_filter);
        }
        if (!empty($sender_name)) {
            remove_filter('wp_mail_from_name', $mail_from_name_filter);
        }

        if ($sent) {
            return true;
        } else {
            return new WP_Error('mail_failed', __('Failed to send email. Check your server mail configuration.', 'user-data-collection'));
        }
    }

    public function ajax_manual_email_backup()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer('udc_email_nonce', 'security', false)) {
            wp_send_json_error(__('Permission denied or invalid security token.', 'user-data-collection'));
        }

        $result = $this->send_backup();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } elseif ($result === true) {
            wp_send_json_success(['message' => __('Backup emailed successfully to the configured address.', 'user-data-collection')]);
        } else {
            wp_send_json_error(__('Email backups are currently disabled in settings.', 'user-data-collection'));
        }
    }
}

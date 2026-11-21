<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Email_Sync
{
    public static function send_with_sender($recipient, $subject, $message, $attachments, $sender_email, $sender_name)
    {
        $sender_email = is_scalar($sender_email) && ('' === $sender_email || is_email($sender_email)) ? (string) $sender_email : '';
        $sender_name = is_scalar($sender_name) ? sanitize_text_field((string) $sender_name) : '';
        if ('mail' === self::mail_transport()) {
            return self::send_with_php_mailer($recipient, $subject, $message, $attachments, $sender_email, $sender_name);
        }

        $email_filter = function ($original) use ($sender_email) { return '' !== $sender_email ? $sender_email : $original; };
        $name_filter = function ($original) use ($sender_name) { return '' !== $sender_name ? $sender_name : $original; };
        if ('' !== $sender_email) { add_filter('wp_mail_from', $email_filter); }
        if ('' !== $sender_name) { add_filter('wp_mail_from_name', $name_filter); }
        try { return wp_mail($recipient, $subject, $message, '', $attachments); }
        finally {
            if ('' !== $sender_email) { remove_filter('wp_mail_from', $email_filter); }
            if ('' !== $sender_name) { remove_filter('wp_mail_from_name', $name_filter); }
        }
    }

    public static function mail_transport()
    {
        $transport = get_option('udc_mail_transport', 'mail');
        return is_string($transport) && in_array($transport, ['mail', 'wordpress'], true) ? $transport : 'mail';
    }

    private static function send_with_php_mailer($recipient, $subject, $message, $attachments, $sender_email, $sender_name)
    {
        if (!is_string($recipient) || !is_email($recipient) || !is_string($subject) || !is_string($message) || !is_array($attachments)) {
            return false;
        }

        try {
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $phpmailer_path = ABSPATH . WPINC . '/PHPMailer/';
                require_once $phpmailer_path . 'Exception.php';
                require_once $phpmailer_path . 'PHPMailer.php';
            }

            $site_host = parse_url(network_home_url(), PHP_URL_HOST);
            if (is_string($site_host) && 0 === strpos($site_host, 'www.')) {
                $site_host = substr($site_host, 4);
            }
            $default_from_email = sanitize_email('wordpress@' . (is_string($site_host) ? $site_host : 'localhost'));
            $filtered_from_email = apply_filters('wp_mail_from', $default_from_email);
            $from_email = is_email($filtered_from_email) ? $filtered_from_email : $default_from_email;
            $filtered_from_name = apply_filters('wp_mail_from_name', 'WordPress');
            $from_name = is_scalar($filtered_from_name) ? sanitize_text_field((string) $filtered_from_name) : 'WordPress';
            if ('' !== $sender_email) { $from_email = $sender_email; }
            if ('' !== $sender_name) { $from_name = $sender_name; }

            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isMail();
            $mailer->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
            $mailer->isHTML(false);
            $mailer->setFrom($from_email, $from_name, false);
            $mailer->addAddress($recipient);
            $mailer->Subject = $subject;
            $mailer->Body = $message;

            foreach ($attachments as $attachment) {
                if (!is_string($attachment) || '' === $attachment || !$mailer->addAttachment($attachment)) {
                    return false;
                }
            }

            return $mailer->send();
        } catch (Throwable $error) {
            return false;
        }
    }

    public static function diagnostic_settings()
    {
        $missing = new stdClass();
        $enabled = get_option('udc_diagnostic_email_enabled', 0);
        $recipient = get_option('udc_diagnostic_email_address', $missing);
        $sender_email = get_option('udc_diagnostic_email_sender_address', $missing);
        $sender_name = get_option('udc_diagnostic_email_sender_name', $missing);

        if ($missing === $recipient) {
            $recipient = get_option('udc_email_address', get_option('admin_email'));
        }
        if ($missing === $sender_email) {
            $sender_email = get_option('udc_email_sender_address', '');
        }
        if ($missing === $sender_name) {
            $sender_name = get_option('udc_email_sender_name', '');
        }

        return [
            'enabled' => (int) (bool) $enabled,
            'recipient' => is_scalar($recipient) ? (string) $recipient : '',
            'sender_email' => is_scalar($sender_email) ? (string) $sender_email : '',
            'sender_name' => is_scalar($sender_name) ? (string) $sender_name : '',
        ];
    }

    public static function send_diagnostic($event, $form_type)
    {
        $settings = self::diagnostic_settings();
        if (empty($settings['enabled']) || !is_string($settings['recipient']) || !is_email($settings['recipient'])) { return false; }
        $claim = 'udc_diagnostic_mail_throttle';
        if (false !== get_transient($claim)) { return false; }
        if (!set_transient($claim, 1, 15 * MINUTE_IN_SECONDS)) {
            UDC_Shortcode::log_diagnostic('diagnostic_throttle_write_failed', $form_type);
            return false;
        }
        $descriptions = [
            'insert_failed' => __('A form submission could not be saved because the database insert failed.', 'user-data-collection'),
            'submission_limit_failure' => __('The form submission limit could not be checked.', 'user-data-collection'),
        ];
        $description = isset($descriptions[$event]) ? $descriptions[$event] : __('A submission diagnostic event occurred.', 'user-data-collection');
        $body_template = __('Description: %1$s\nForm type: %2$s\nEvent code: %3$s', 'user-data-collection');
        $form_label = 'piercing' === $form_type ? __('Piercing', 'user-data-collection') : __('Tattoo', 'user-data-collection');
        $body = sprintf(str_replace('\\n', "\n", $body_template), $description, $form_label, UDC_Shortcode::diagnostic_code($event));
        try {
            $sent = self::send_with_sender($settings['recipient'], __('User Data Collection submission diagnostic', 'user-data-collection'), $body, [], $settings['sender_email'], $settings['sender_name']);
        } catch (Throwable $error) {
            UDC_Shortcode::log_diagnostic('diagnostic_mail_exception', $form_type);
            return false;
        }
        if (!$sent) { UDC_Shortcode::log_diagnostic('diagnostic_mail_failed', $form_type); }
        return (bool) $sent;
    }
    public function __construct()
    {
        add_action('wp_ajax_udc_manual_email_backup', [$this, 'ajax_manual_email_backup']);
        add_action('udc_monthly_email_sync_action', [$this, 'send_backup']);

        if (false === has_filter('cron_schedules', ['UDC_Email_Sync', 'add_cron_schedule'])) {
            add_filter('cron_schedules', ['UDC_Email_Sync', 'add_cron_schedule']);
        }
    }

    public static function add_cron_schedule($schedules)
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
            return false !== wp_schedule_event(time(), 'udc_monthly', 'udc_monthly_email_sync_action');
        }
        return true;
    }

    public static function clear_cron()
    {
        while ($timestamp = wp_next_scheduled('udc_monthly_email_sync_action')) {
            if (!wp_unschedule_event($timestamp, 'udc_monthly_email_sync_action')) { break; }
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

        $attachments = [$latest_backup];

        $sender_email = get_option('udc_email_sender_address', '');
        $sender_name = get_option('udc_email_sender_name', '');
        $sent = self::send_with_sender($email_address, $subject, $message, $attachments, $sender_email, $sender_name);

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

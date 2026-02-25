<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_GDrive
{
    public function __construct()
    {
        add_action('wp_ajax_udc_manual_gdrive_sync', [$this, 'ajax_manual_sync']);
        add_action('udc_weekly_gdrive_sync_action', [$this, 'sync_backups']);

        add_filter('cron_schedules', [$this, 'add_cron_schedule']);
    }

    public function add_cron_schedule($schedules)
    {
        $schedules['udc_weekly'] = [
            'interval' => 604800, // 7 days
            'display' => __('Once Weekly', 'user-data-collection')
        ];
        return $schedules;
    }

    public static function schedule_cron()
    {
        if (!wp_next_scheduled('udc_weekly_gdrive_sync_action')) {
            wp_schedule_event(time(), 'udc_weekly', 'udc_weekly_gdrive_sync_action');
        }
    }

    public static function clear_cron()
    {
        $timestamp = wp_next_scheduled('udc_weekly_gdrive_sync_action');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'udc_weekly_gdrive_sync_action');
        }
    }

    private function log_status($error, $message)
    {
        update_option('udc_gdrive_last_status', [
            'error' => $error,
            'message' => $message,
            'time' => current_time('mysql')
        ]);
        return !$error;
    }

    private function get_token()
    {
        $json_opt = get_option('udc_gdrive_json', '');
        if (empty($json_opt)) {
            $this->log_status(true, __('Google Drive JSON settings are empty.', 'user-data-collection'));
            return false;
        }

        $creds = json_decode($json_opt, true);
        if (!$creds || empty($creds['client_email']) || empty($creds['private_key'])) {
            $this->log_status(true, __('Invalid JSON credentials. Missing client_email or private_key.', 'user-data-collection'));
            return false;
        }

        $header = wp_json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $claim = wp_json_encode([
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => time() + 3600,
            'iat' => time()
        ]);

        $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64_claim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));

        $signature = '';
        if (!openssl_sign($base64_header . '.' . $base64_claim, $signature, $creds['private_key'], 'sha256WithRSAEncryption')) {
            $this->log_status(true, __('Error signing JWT. Ensure OpenSSL is configured correctly in server.', 'user-data-collection'));
            return false;
        }
        $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $jwt = $base64_header . '.' . $base64_claim . '.' . $base64_signature;

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            $this->log_status(true, __('Network error requesting access token.', 'user-data-collection'));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['access_token'])) {
            $this->log_status(true, __('Google refused access token. Check credentials and scopes.', 'user-data-collection') . ' (' . ($data['error_description'] ?? 'Unknown Error') . ')');
            return false;
        }

        return $data['access_token'];
    }

    public function sync_backups()
    {
        $is_enabled = get_option('udc_gdrive_sync_enabled', '0');
        if (!$is_enabled) {
            return false;
        }

        $folder_id = get_option('udc_gdrive_folder', '');
        if (empty($folder_id)) {
            return $this->log_status(true, __('Google Drive Folder ID is missing.', 'user-data-collection'));
        }

        $token = $this->get_token();
        if (!$token) {
            return false; // Error already logged in get_token
        }

        // Get Cloud Files
        $query = "'" . sanitize_text_field($folder_id) . "' in parents and trashed=false";
        $list_url = 'https://www.googleapis.com/drive/v3/files?q=' . urlencode($query) . '&fields=files(id,name,createdTime)&orderBy=createdTime asc';

        $list_response = wp_remote_get($list_url, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 20
        ]);

        if (is_wp_error($list_response)) {
            return $this->log_status(true, __('Failed to list Cloud files.', 'user-data-collection'));
        }

        $list_body = json_decode(wp_remote_retrieve_body($list_response), true);
        if (isset($list_body['error'])) {
            // Handle 403 or Storage Full here
            $err_msg = $list_body['error']['message'] ?? 'API Error';
            return $this->log_status(true, sprintf(__('Drive API Error: %s', 'user-data-collection'), $err_msg));
        }

        $cloud_files = $list_body['files'] ?? [];
        $cloud_names = array_column($cloud_files, 'name');

        // Check Local Files
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/udc-backups/';
        $local_files = glob($backup_dir . '*.json');

        if ($local_files === false) {
            $local_files = [];
        }

        $uploaded_count = 0;

        foreach ($local_files as $filepath) {
            $filename = basename($filepath);
            if (!in_array($filename, $cloud_names)) {
                // Upload missing file
                $uploaded = $this->upload_file($filepath, $folder_id, $token);
                if ($uploaded) {
                    $uploaded_count++;
                } else {
                    return false; // Error already logged in upload_file
                }
            }
        }

        // Refetch cloud files if we uploaded new ones to calculate rotation
        if ($uploaded_count > 0) {
            $list_response = wp_remote_get($list_url, [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'timeout' => 20
            ]);
            $list_body = json_decode(wp_remote_retrieve_body($list_response), true);
            $cloud_files = $list_body['files'] ?? [];
        }

        // Rotation
        $max_cloud_files = 5;
        $total_cloud_files = count($cloud_files);

        $deleted_count = 0;
        if ($total_cloud_files > $max_cloud_files) {
            $files_to_delete = array_slice($cloud_files, 0, $total_cloud_files - $max_cloud_files);
            foreach ($files_to_delete as $gfile) {
                $delete_url = 'https://www.googleapis.com/drive/v3/files/' . $gfile['id'];
                $del_res = wp_remote_request($delete_url, [
                    'method' => 'DELETE',
                    'headers' => ['Authorization' => 'Bearer ' . $token]
                ]);
                if (!is_wp_error($del_res)) {
                    $deleted_count++;
                }
            }
        }

        if ($uploaded_count === 0 && $deleted_count === 0) {
            return $this->log_status(false, __('Sync successful. Cloud is already up to date with local files.', 'user-data-collection'));
        }

        return $this->log_status(false, sprintf(__('Sync successful. Uploaded %d files, removed %d old cloud records.', 'user-data-collection'), $uploaded_count, $deleted_count));
    }

    private function upload_file($filepath, $folder_id, $token)
    {
        $boundary = wp_generate_password(24, false);
        $metadata = wp_json_encode([
            'name' => basename($filepath),
            'parents' => [$folder_id]
        ]);
        $file_contents = file_get_contents($filepath);

        if ($file_contents === false) {
            $this->log_status(true, __('Failed to read local backup for upload.', 'user-data-collection'));
            return false;
        }

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= $metadata . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/json\r\n\r\n";
        $body .= $file_contents . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'multipart/related; boundary=' . $boundary,
                'Content-Length' => strlen($body)
            ],
            'body' => $body,
            'timeout' => 120 // GDrive upload could be slower
        ]);

        if (is_wp_error($response)) {
            $this->log_status(true, __('Network error uploading to Drive.', 'user-data-collection'));
            return false;
        }

        $res_code = wp_remote_retrieve_response_code($response);
        $res_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($res_code !== 200) {
            $err_msg = $res_body['error']['message'] ?? 'Unknown Upload Error';
            $this->log_status(true, sprintf(__('Drive API Upload Error: %s', 'user-data-collection'), $err_msg));
            return false;
        }

        return true;
    }

    public function ajax_manual_sync()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer('udc_sync_nonce', 'security', false)) {
            wp_send_json_error(__('Permission denied or invalid security token.', 'user-data-collection'));
        }

        // Check if sync is enabled before allowing manual
        $is_enabled = get_option('udc_gdrive_sync_enabled', '0');
        if (!$is_enabled) {
            wp_send_json_error(__('Cloud Sync must be enabled and saved in settings first.', 'user-data-collection'));
        }

        $result = $this->sync_backups();
        $status = get_option('udc_gdrive_last_status');

        if ($result && isset($status['message'])) {
            wp_send_json_success(['message' => $status['message']]);
        } else {
            wp_send_json_error($status['message'] ?? __('Failed to sync.', 'user-data-collection'));
        }
    }
}

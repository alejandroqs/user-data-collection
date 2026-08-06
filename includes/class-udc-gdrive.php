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

        if (false === has_filter('cron_schedules', ['UDC_GDrive', 'add_cron_schedule'])) {
            add_filter('cron_schedules', ['UDC_GDrive', 'add_cron_schedule']);
        }
    }

    public static function add_cron_schedule($schedules)
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
            return false !== wp_schedule_event(time(), 'udc_weekly', 'udc_weekly_gdrive_sync_action');
        }
        return true;
    }

    public static function clear_cron()
    {
        while ($timestamp = wp_next_scheduled('udc_weekly_gdrive_sync_action')) {
            if (!wp_unschedule_event($timestamp, 'udc_weekly_gdrive_sync_action')) { break; }
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

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300) {
            $this->log_status(true, __('Network error requesting access token.', 'user-data-collection'));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['access_token']) || !is_string($data['access_token']) || '' === $data['access_token'] || strlen($data['access_token']) > 8192 || preg_match('/[\x00-\x1F\x7F]/', $data['access_token'])) {
            $this->log_status(true, __('Google refused access token. Check credentials and scopes.', 'user-data-collection'));
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
        if (!$this->is_valid_drive_id($folder_id)) {
            return $this->log_status(true, __('Google Drive Folder ID is invalid.', 'user-data-collection'));
        }

        $token = $this->get_token();
        if (!$token) {
            return false; // Error already logged in get_token
        }

        $cloud_listing = $this->list_owned_files($folder_id, $token);
        if (is_wp_error($cloud_listing) || !isset($cloud_listing['owned'], $cloud_listing['legacy_names']) || !is_array($cloud_listing['owned']) || !is_array($cloud_listing['legacy_names'])) {
            return $this->log_status(true, __('Failed to list Cloud files.', 'user-data-collection'));
        }
        $owned_files = $cloud_listing['owned'];
        $legacy_names = $cloud_listing['legacy_names'];
        $cloud_names = array_merge(array_column($owned_files, 'name'), $legacy_names);

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
            $cloud_listing = $this->list_owned_files($folder_id, $token);
            if (is_wp_error($cloud_listing) || !isset($cloud_listing['owned'], $cloud_listing['legacy_names']) || !is_array($cloud_listing['owned']) || !is_array($cloud_listing['legacy_names'])) {
                return $this->log_status(true, __('Failed to refresh Cloud files.', 'user-data-collection'));
            }
            $owned_files = $cloud_listing['owned'];
        }

        // Rotation
        $max_cloud_files = 5;
        $total_cloud_files = count($owned_files);

        $deleted_count = 0;
        if ($total_cloud_files > $max_cloud_files) {
            $files_to_delete = array_slice($owned_files, 0, $total_cloud_files - $max_cloud_files);
            foreach ($files_to_delete as $gfile) {
                if (!isset($gfile['id']) || !$this->is_valid_drive_id($gfile['id'])) {
                    return $this->log_status(true, __('Failed to remove an old Cloud backup.', 'user-data-collection'));
                }
                $delete_url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($gfile['id']);
                $del_res = wp_remote_request($delete_url, [
                    'method' => 'DELETE',
                    'headers' => ['Authorization' => 'Bearer ' . $token]
                ]);
                $status = is_wp_error($del_res) ? 0 : wp_remote_retrieve_response_code($del_res);
                if ($status >= 200 && $status < 300) {
                    $deleted_count++;
                } else {
                    return $this->log_status(true, __('Failed to remove an old Cloud backup.', 'user-data-collection'));
                }
            }
        }

        if ($uploaded_count === 0 && $deleted_count === 0) {
            return $this->log_status(false, __('Sync successful. Cloud is already up to date with local files.', 'user-data-collection'));
        }

        return $this->log_status(false, sprintf(__('Sync successful. Uploaded %d files, removed %d old cloud records.', 'user-data-collection'), $uploaded_count, $deleted_count));
    }

    private function list_owned_files($folder_id, $token)
    {
        $query = "'" . $folder_id . "' in parents and trashed=false";
        $page_token = '';
        $owned_files = [];
        $legacy_names = [];
        for ($page = 0; $page < 10; $page++) {
            $url = 'https://www.googleapis.com/drive/v3/files?q=' . rawurlencode($query) . '&fields=files(id,name,mimeType,appProperties,createdTime),nextPageToken&orderBy=createdTime asc';
            if ($page_token) { $url .= '&pageToken=' . rawurlencode($page_token); }
            $response = wp_remote_get($url, ['headers' => ['Authorization' => 'Bearer ' . $token], 'timeout' => 20]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300) { return new WP_Error('drive_list_error', 'Drive list failed.'); }
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data) || !isset($data['files']) || !is_array($data['files'])) { return new WP_Error('drive_list_error', 'Drive list failed.'); }
            if (count($data['files']) > 0 && array_keys($data['files']) !== range(0, count($data['files']) - 1)) { return new WP_Error('drive_list_error', 'Drive list failed.'); }
            foreach ($data['files'] as $file) {
                if (!is_array($file)) { return new WP_Error('drive_list_error', 'Drive list failed.'); }
                foreach (['id', 'name', 'mimeType', 'createdTime'] as $field) {
                    if (!array_key_exists($field, $file) || !is_string($file[$field])) { return new WP_Error('drive_list_error', 'Drive list failed.'); }
                }
                if (!$this->is_valid_drive_id($file['id']) || !$this->is_valid_drive_name($file['name']) || !$this->is_valid_mime_type($file['mimeType']) || '' === $file['createdTime'] || preg_match('/[\x00-\x1F\x7F]/', $file['createdTime'])) {
                    return new WP_Error('drive_list_error', 'Drive list failed.');
                }
                $app_properties = array_key_exists('appProperties', $file) ? $file['appProperties'] : [];
                if (!is_array($app_properties) || !$this->is_valid_app_properties($app_properties)) { return new WP_Error('drive_list_error', 'Drive list failed.'); }

                $owned = isset($app_properties['udc_backup']) && '1' === $app_properties['udc_backup'];
                if ($owned) {
                    $owned_files[] = $file;
                } elseif ($this->is_legacy_backup($file['name'], $file['mimeType'])) {
                    $legacy_names[] = $file['name'];
                }
            }
            if (!array_key_exists('nextPageToken', $data)) { return ['owned' => $owned_files, 'legacy_names' => $legacy_names]; }
            if (!is_string($data['nextPageToken']) || strlen($data['nextPageToken']) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $data['nextPageToken'])) { return new WP_Error('drive_list_error', 'Drive list failed.'); }
            $page_token = $data['nextPageToken'];
            if ('' === $page_token) { return ['owned' => $owned_files, 'legacy_names' => $legacy_names]; }
        }
        return new WP_Error('drive_list_error', 'Drive list failed.');
    }

    private function upload_file($filepath, $folder_id, $token)
    {
        if (!is_string($filepath) || !$this->is_valid_drive_id($folder_id) || !is_readable($filepath)) {
            $this->log_status(true, __('Failed to read local backup for upload.', 'user-data-collection'));
            return false;
        }

        $boundary = wp_generate_password(24, false);
        $metadata = wp_json_encode([
            'name' => basename($filepath),
            'parents' => [$folder_id],
            'mimeType' => 'application/json',
            'appProperties' => ['udc_backup' => '1']
        ]);
        $file_contents = file_get_contents($filepath);

        if ($file_contents === false || false === $metadata) {
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

        if ($res_code < 200 || $res_code >= 300 || !is_array($res_body) || !isset($res_body['id']) || !$this->is_valid_drive_id($res_body['id'])) {
            $this->log_status(true, __('Drive upload failed.', 'user-data-collection'));
            return false;
        }

        return true;
    }

    private function is_valid_drive_id($value)
    {
        return is_string($value) && (bool) preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $value);
    }

    private function is_valid_drive_name($value)
    {
        return is_string($value) && strlen($value) >= 1 && strlen($value) <= 255 && !preg_match('/[\x00-\x1F\x7F]/', $value);
    }

    private function is_valid_mime_type($value)
    {
        return is_string($value) && (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9!#$&^_.+-]{0,126}\/[A-Za-z0-9][A-Za-z0-9!#$&^_.+-]{0,126}$/D', $value);
    }

    private function is_valid_app_properties($properties)
    {
        foreach ($properties as $key => $value) {
            if (!is_string($key) || !is_string($value) || strlen($key) > 124 || strlen($value) > 124) {
                return false;
            }
        }
        return true;
    }

    private function is_legacy_backup($name, $mime_type)
    {
        return (bool) preg_match('/^backup_\d{8}_\d{6}\.json$/D', $name) && $this->is_json_mime_type($mime_type);
    }

    private function is_json_mime_type($value)
    {
        return in_array($value, ['application/json', 'text/json'], true) || (bool) preg_match('/^application\/[A-Za-z0-9][A-Za-z0-9!#$&^_.+-]{0,126}\+json$/D', $value);
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

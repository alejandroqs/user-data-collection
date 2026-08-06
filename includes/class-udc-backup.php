<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Backup
{
    private static $backup_dir = '';

    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        self::$backup_dir = $upload_dir['basedir'] . '/udc-backups/';

        // Register AJAX actions for manual backups and restore
        add_action('wp_ajax_udc_create_backup', [$this, 'ajax_create_backup']);
        add_action('wp_ajax_udc_restore_backup', [$this, 'ajax_restore_backup']);
        add_action('wp_ajax_udc_upload_backup', [$this, 'ajax_upload_backup']);

        // Register cron hook
        add_action('udc_daily_backup_action', [$this, 'create_backup']);
    }

    public static function schedule_cron()
    {
        if (!wp_next_scheduled('udc_daily_backup_action')) {
            return false !== wp_schedule_event(time(), 'daily', 'udc_daily_backup_action');
        }
        return true;
    }

    public static function clear_cron()
    {
        while ($timestamp = wp_next_scheduled('udc_daily_backup_action')) {
            if (!wp_unschedule_event($timestamp, 'udc_daily_backup_action')) { break; }
        }
    }

    private function secure_directory()
    {
        if (!is_dir(self::$backup_dir)) {
            if (!wp_mkdir_p(self::$backup_dir)) {
                return new WP_Error('directory_error', __('Backup storage is unavailable.', 'user-data-collection'));
            }
        }

        $htaccess_file = self::$backup_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $rules = "Order deny,allow\nDeny from all\n";
            if (false === file_put_contents($htaccess_file, $rules, LOCK_EX)) {
                return new WP_Error('protection_error', __('Backup storage is unavailable.', 'user-data-collection'));
            }
            $this->restrict_file($htaccess_file);
        }

        $index_file = self::$backup_dir . 'index.php';
        if (!file_exists($index_file)) {
            if (false === file_put_contents($index_file, "<?php\n// Silence is golden.\n", LOCK_EX)) {
                return new WP_Error('protection_error', __('Backup storage is unavailable.', 'user-data-collection'));
            }
            $this->restrict_file($index_file);
        }
        return true;
    }

    public function create_backup()
    {
        $directory = $this->secure_directory();
        if (is_wp_error($directory)) {
            return $directory;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';

        // Fetch all data using the same explicit field order accepted by restore.
        $columns = implode(', ', array_map(function ($column) {
            return '`' . $column . '`';
        }, $this->backup_columns()));
        $results = $wpdb->get_results("SELECT $columns FROM $table_name LIMIT 10001", ARRAY_A);
        if (!is_array($results) || count($results) > 10000) {
            return new WP_Error('backup_limit', __('Backup could not be created because the dataset is too large.', 'user-data-collection'));
        }

        $json_data = wp_json_encode($results);
        if (false === $json_data) {
            return new WP_Error('backup_error', __('Backup could not be created.', 'user-data-collection'));
        }
        if (strlen($json_data) > 10 * 1024 * 1024) {
            return new WP_Error('backup_limit', __('Backup could not be created because the encoded data is too large.', 'user-data-collection'));
        }

        $filename = 'backup_' . current_time('Ymd_His') . '.json';
        $filepath = self::$backup_dir . $filename;

        $temporary = tempnam(self::$backup_dir, '.udc-backup-');
        $written = false === $temporary ? false : file_put_contents($temporary, $json_data, LOCK_EX);
        if (false === $temporary || false === $written || strlen($json_data) !== $written) {
            if (false !== $temporary) {
                unlink($temporary);
            }
            return new WP_Error('write_error', __('Backup could not be written.', 'user-data-collection'));
        }
        $this->restrict_file($temporary);
        if (function_exists('fsync')) {
            $handle = fopen($temporary, 'rb');
            if (false === $handle || !fsync($handle)) {
                if (is_resource($handle)) { fclose($handle); }
                unlink($temporary);
                return new WP_Error('write_error', __('Backup could not be written.', 'user-data-collection'));
            }
            fclose($handle);
        }
        if (!rename($temporary, $filepath)) {
            unlink($temporary);
            return new WP_Error('write_error', __('Backup could not be written.', 'user-data-collection'));
        }

        $rotation = $this->rotate_backups();
        if (is_wp_error($rotation)) {
            return $rotation;
        }

        return $filename;
    }

    private function rotate_backups()
    {
        if (!file_exists(self::$backup_dir)) {
            return new WP_Error('directory_error', __('Backup storage is unavailable.', 'user-data-collection'));
        }

        // Get all JSON files sorted by modification time (oldest first)
        $files = glob(self::$backup_dir . '*.json');
        if ($files === false) {
            return new WP_Error('rotation_error', __('Older backups could not be rotated.', 'user-data-collection'));
        }

        usort($files, function ($a, $b) {
                return (int) filemtime($a) - (int) filemtime($b);
        });

        // If we have more than 5, delete the oldest ones
        $max_backups = 5;
        $total_files = count($files);

        if ($total_files > $max_backups) {
            $files_to_delete = array_slice($files, 0, $total_files - $max_backups);
            foreach ($files_to_delete as $file) {
                if (!unlink($file)) {
                    return new WP_Error('rotation_error', __('Older backups could not be rotated.', 'user-data-collection'));
                }
            }
        }
        return true;
    }

    private function restrict_file($path)
    {
        if (function_exists('chmod')) {
            chmod($path, 0600);
        }
    }

    public function restore_backup($filename)
    {
        if (!is_scalar($filename)) {
            return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
        }
        $filepath = self::$backup_dir . basename($filename);

        $size = file_exists($filepath) ? filesize($filepath) : false;
        if (!file_exists($filepath) || pathinfo($filepath, PATHINFO_EXTENSION) !== 'json' || false === $size || $size < 1 || $size > 10 * 1024 * 1024 || !is_readable($filepath)) {
            return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
        }

        $json_data = file_get_contents($filepath);
        return false === $json_data ? new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection')) : $this->decode_backup_data($json_data);
    }

    private function insert_backup_data($data)
    {
        $normalized = $this->validate_backup_rows($data);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';
        $added_count = 0;
        $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table_name));
        if (!is_string($engine) || 'innodb' !== strtolower($engine) || false === $wpdb->query('START TRANSACTION')) {
            return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
        }

        foreach ($normalized as $safe_row) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_name WHERE id = %d", $safe_row['id']));
            if (false === $exists) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
            }
            if (!$exists) {
                if (false === $wpdb->insert($table_name, $safe_row, $this->backup_formats())) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
                }
                $added_count++;
            }
        }
        if (false === $wpdb->query('COMMIT')) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
        }
        return $added_count;
    }

    private function decode_backup_data($json_data)
    {
        if ('' === $json_data) {
            return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
        }
        $data = json_decode($json_data, true, 8);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
        }
        return $this->insert_backup_data($data);
    }

    private function validate_backup_rows($data)
    {
        $data_keys = is_array($data) ? array_keys($data) : [];
        if (!is_array($data) || (count($data) > 0 && $data_keys !== range(0, count($data) - 1)) || count($data) > 10000) {
            return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
        }
        $current = $this->backup_columns();
        $legacy = array_values(array_diff($current, ['city','zip']));
        $legacy[] = 'zip_city';
        $mode = null;
        $normalized = [];
        foreach ($data as $row) {
            if (!is_array($row) || array_keys($row) === range(0, count($row) - 1)) {
                return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
            }
            foreach ($row as $value) {
                if (!is_scalar($value) && null !== $value) {
                    return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
                }
            }
            $keys = array_keys($row);
            sort($keys);
            $current_keys = $current; sort($current_keys);
            $legacy_keys = $legacy; sort($legacy_keys);
            $row_mode = $keys === $current_keys ? 'current' : ($keys === $legacy_keys ? 'legacy' : false);
            if (false === $row_mode || (null !== $mode && $mode !== $row_mode)) {
                return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
            }
            $mode = $row_mode;
            if ('legacy' === $mode) {
                $row['city'] = (string) $row['zip_city'];
                $row['zip'] = '';
                unset($row['zip_city']);
            }
            $validated = $this->validate_backup_row($row, 'legacy' === $mode);
            if (is_wp_error($validated)) {
                return $validated;
            }
            $normalized[] = $validated;
        }
        return $normalized;
    }

    private function validate_backup_row($row, $legacy_schema = false)
    {
        $lengths = ['last_name'=>255,'first_name'=>255,'address'=>255,'city'=>255,'zip'=>50,'phone'=>50,'piercing_location'=>255];
        if (!is_array($row)) {
            return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
        }
        foreach ($this->backup_columns() as $field) {
            if (!array_key_exists($field, $row)) {
                return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
            }
        }
        if (!isset($row['id']) || filter_var($row['id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false || !$this->valid_date($row['dob']) || !$this->valid_date($row['appointment_date']) || false === $this->normalize_time($row['appointment_time']) || !$this->valid_datetime($row['created_at'])) {
            return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
        }
        foreach ($lengths as $field => $length) {
            $empty_zip_allowed = $legacy_schema && 'zip' === $field && '' === $row[$field];
            if (!is_string($row[$field]) || ('' === $row[$field] && !$empty_zip_allowed) || strlen($row[$field]) > $length) {
                return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
            }
        }
        foreach (['health_good','health_treatment','health_blood_thinners','health_allergies','health_pregnant','liability_accepted','is_confirmed'] as $field) {
            if (!in_array($row[$field], [0, 1, '0', '1'], true)) {
                return new WP_Error('restore_error', __('Backup could not be restored.', 'user-data-collection'));
            }
            $row[$field] = (int) $row[$field];
        }
        $row['id'] = (int) $row['id'];
        $row['appointment_time'] = $this->normalize_time($row['appointment_time']);
        $ordered = [];
        foreach ($this->backup_columns() as $field) {
            $ordered[$field] = $row[$field];
        }
        return $ordered;
    }

    private function valid_date($value)
    {
        return UDC_Validation::valid_date($value);
    }

    private function valid_datetime($value)
    {
        return UDC_Validation::valid_datetime($value);
    }

    private function normalize_time($value)
    {
        return UDC_Validation::normalize_time($value);
    }

    private function backup_columns()
    {
        return ['id','last_name','first_name','dob','address','city','zip','phone','health_good','health_treatment','health_blood_thinners','health_allergies','health_pregnant','liability_accepted','appointment_date','appointment_time','piercing_location','is_confirmed','created_at'];
    }

    private function backup_formats()
    {
        return ['%d','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%d','%d','%s','%s','%s','%d','%s'];
    }

    public function ajax_create_backup()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer('udc_backup_nonce', 'security', false)) {
            wp_send_json_error(__('Permission denied or invalid security token.', 'user-data-collection'));
        }

        $result = $this->create_backup();
        if (!is_wp_error($result) && false !== $result) {
            wp_send_json_success(['message' => __('Backup created successfully.', 'user-data-collection')]);
        } else {
            wp_send_json_error(__('Failed to create backup.', 'user-data-collection'));
        }
    }

    public function ajax_restore_backup()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer('udc_backup_nonce', 'security', false)) {
            wp_send_json_error(__('Permission denied or invalid security token.', 'user-data-collection'));
        }

        $filename = isset($_POST['filename']) && is_scalar($_POST['filename']) ? sanitize_text_field(wp_unslash((string) $_POST['filename'])) : '';
        if (empty($filename)) {
            wp_send_json_error(__('Invalid filename.', 'user-data-collection'));
        }

        $result = $this->restore_backup($filename);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(['message' => sprintf(__('Backup processed. %d missing submissions were added.', 'user-data-collection'), $result)]);
    }

    public function ajax_upload_backup()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer('udc_upload_nonce', 'security', false)) {
            wp_send_json_error(__('Permission denied or invalid security token.', 'user-data-collection'));
        }

        if (!isset($_FILES['backup_file']) || !is_array($_FILES['backup_file']) || empty($_FILES['backup_file']['tmp_name'])) {
            wp_send_json_error(__('No file uploaded.', 'user-data-collection'));
        }

        $file = $_FILES['backup_file'];

        if (!isset($file['error'], $file['tmp_name'], $file['name'], $file['size']) || !is_scalar($file['error']) || !is_scalar($file['tmp_name']) || !is_scalar($file['name']) || !is_scalar($file['size']) || (int) $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || !is_readable($file['tmp_name']) || (int) $file['size'] < 1 || (int) $file['size'] > 10 * 1024 * 1024) {
            wp_send_json_error(__('Error uploading file.', 'user-data-collection'));
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'json') {
            wp_send_json_error(__('Only JSON files are allowed.', 'user-data-collection'));
        }

        $actual_size = filesize($file['tmp_name']);
        if (false === $actual_size || $actual_size < 1 || $actual_size > 10 * 1024 * 1024) {
            wp_send_json_error(__('Could not read uploaded file.', 'user-data-collection'));
        }
        $json_data = file_get_contents($file['tmp_name']);
        if (false === $json_data) {
            wp_send_json_error(__('Could not parse uploaded JSON data.', 'user-data-collection'));
        }
        $added_count = $this->decode_backup_data($json_data);
        if (is_wp_error($added_count)) {
            wp_send_json_error(__('Could not restore the backup.', 'user-data-collection'));
        }

        wp_send_json_success(['message' => sprintf(__('Uploaded backup processed. %d missing submissions were added.', 'user-data-collection'), $added_count)]);
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $directory = $this->secure_directory();
        if (is_wp_error($directory)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Backup storage is unavailable.', 'user-data-collection') . '</p></div>';
            return;
        }
        $files = glob(self::$backup_dir . '*.json');
        if ($files === false) {
            $files = [];
        }

        // Sort dynamically newest first for display
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('Data Backups', 'user-data-collection'); ?>
            </h1>
            <button id="udc-manual-backup" class="page-title-action"
                data-nonce="<?php echo esc_attr(wp_create_nonce('udc_backup_nonce')); ?>">
                <?php esc_html_e('Create Manual Backup', 'user-data-collection'); ?>
            </button>
            <div style="display:inline-block; margin-left: 10px;">
                <input type="file" id="udc-upload-backup-file" accept=".json" style="display:none;" />
                <button type="button" id="udc-upload-backup-btn" class="page-title-action"
                    data-nonce="<?php echo esc_attr(wp_create_nonce('udc_upload_nonce')); ?>">
                    <?php esc_html_e('Upload Backup JSON', 'user-data-collection'); ?>
                </button>
            </div>
            <p>
                <?php esc_html_e('The system automatically creates a daily backup via WP-Cron. It stores a maximum of 5 recent backups securely in the local filesystem.', 'user-data-collection'); ?>
            </p>

            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th class="manage-column">
                            <?php esc_html_e('Filename', 'user-data-collection'); ?>
                        </th>
                        <th class="manage-column">
                            <?php esc_html_e('Date', 'user-data-collection'); ?>
                        </th>
                        <th class="manage-column">
                            <?php esc_html_e('Size', 'user-data-collection'); ?>
                        </th>
                        <th class="manage-column column-action">
                            <?php esc_html_e('Action', 'user-data-collection'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php if (empty($files)): ?>
                        <tr>
                            <td colspan="4">
                                <?php esc_html_e('No backups available found.', 'user-data-collection'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($files as $file):
                            $filename = basename($file);
                            $date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($file));
                            $size = size_format(filesize($file));
                            ?>
                            <tr>
                                <td><strong>
                                        <?php echo esc_html($filename); ?>
                                    </strong></td>
                                <td>
                                    <?php echo esc_html($date); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($size); ?>
                                </td>
                                <td>
                                    <button class="button button-primary udc-restore-btn"
                                        data-filename="<?php echo esc_attr($filename); ?>"
                                        data-nonce="<?php echo esc_attr(wp_create_nonce('udc_backup_nonce')); ?>">
                                        <?php esc_html_e('Restore', 'user-data-collection'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const backupBtn = document.getElementById('udc-manual-backup');
                if (backupBtn) {
                    backupBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        const btn = this;
                        const nonce = btn.getAttribute('data-nonce');

                        btn.disabled = true;
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<?php echo esc_js(__('Creating...', 'user-data-collection')); ?>';

                        const formData = new FormData();
                        formData.append('action', 'udc_create_backup');
                        formData.append('security', nonce);

                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert(data.data.message);
                                    window.location.reload();
                                } else {
                                    alert(data.data || '<?php echo esc_js(__('An error occurred.', 'user-data-collection')); ?>');
                                    btn.disabled = false;
                                    btn.innerHTML = originalText;
                                }
                            })
                            .catch(error => {
                                alert('<?php echo esc_js(__('Network Error', 'user-data-collection')); ?>');
                                btn.disabled = false;
                                btn.innerHTML = originalText;
                            });
                    });
                }

                const uploadBtn = document.getElementById('udc-upload-backup-btn');
                const fileInput = document.getElementById('udc-upload-backup-file');

                if (uploadBtn && fileInput) {
                    uploadBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        fileInput.click();
                    });

                    fileInput.addEventListener('change', function (e) {
                        const file = e.target.files[0];
                        if (!file) return;

                        const nonce = uploadBtn.getAttribute('data-nonce');
                        uploadBtn.disabled = true;
                        const originalText = uploadBtn.innerHTML;
                        uploadBtn.innerHTML = '<?php echo esc_js(__('Uploading...', 'user-data-collection')); ?>';

                        const formData = new FormData();
                        formData.append('action', 'udc_upload_backup');
                        formData.append('security', nonce);
                        formData.append('backup_file', file);

                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert(data.data.message);
                                    window.location.reload();
                                } else {
                                    alert(data.data || '<?php echo esc_js(__('An error occurred.', 'user-data-collection')); ?>');
                                    uploadBtn.disabled = false;
                                    uploadBtn.innerHTML = originalText;
                                    fileInput.value = '';
                                }
                            })
                            .catch(error => {
                                alert('<?php echo esc_js(__('Network Error', 'user-data-collection')); ?>');
                                uploadBtn.disabled = false;
                                uploadBtn.innerHTML = originalText;
                                fileInput.value = '';
                            });
                    });
                }

                document.body.addEventListener('click', function (e) {
                    const btn = e.target.closest('.udc-restore-btn');
                    if (btn) {
                        e.preventDefault();
                        if (!confirm('<?php echo esc_js(__('Are you sure you want to process this backup? Only missing submissions will be added. Existing data will not be overwritten or deleted.', 'user-data-collection')); ?>')) {
                            return;
                        }

                        const filename = btn.getAttribute('data-filename');
                        const nonce = btn.getAttribute('data-nonce');

                        btn.disabled = true;
                        const oldText = btn.innerHTML;
                        btn.innerHTML = '<?php echo esc_js(__('Restoring...', 'user-data-collection')); ?>';

                        const formData = new FormData();
                        formData.append('action', 'udc_restore_backup');
                        formData.append('filename', filename);
                        formData.append('security', nonce);

                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert(data.data.message);
                                    window.location.reload();
                                } else {
                                    alert(data.data || '<?php echo esc_js(__('An error occurred.', 'user-data-collection')); ?>');
                                    btn.disabled = false;
                                    btn.innerHTML = oldText;
                                }
                            })
                            .catch(error => {
                                alert('<?php echo esc_js(__('Network Error', 'user-data-collection')); ?>');
                                btn.disabled = false;
                                btn.innerHTML = oldText;
                            });
                    }
                });
            });
        </script>
        <?php
    }
}

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
            wp_schedule_event(time(), 'daily', 'udc_daily_backup_action');
        }
    }

    public static function clear_cron()
    {
        $timestamp = wp_next_scheduled('udc_daily_backup_action');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'udc_daily_backup_action');
        }
    }

    private function secure_directory()
    {
        if (!file_exists(self::$backup_dir)) {
            wp_mkdir_p(self::$backup_dir);
        }

        $htaccess_file = self::$backup_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $rules = "Order deny,allow\nDeny from all\n";
            file_put_contents($htaccess_file, $rules);
        }

        $index_file = self::$backup_dir . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
    }

    public function create_backup()
    {
        $this->secure_directory();

        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';

        // Fetch all data
        $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

        $json_data = wp_json_encode($results);
        if ($json_data === false) {
            return false;
        }

        $filename = 'backup_' . current_time('Ymd_His') . '.json';
        $filepath = self::$backup_dir . $filename;

        file_put_contents($filepath, $json_data);

        $this->rotate_backups();

        return $filename;
    }

    private function rotate_backups()
    {
        if (!file_exists(self::$backup_dir)) {
            return;
        }

        // Get all JSON files sorted by modification time (oldest first)
        $files = glob(self::$backup_dir . '*.json');
        if ($files === false) {
            return;
        }

        usort($files, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // If we have more than 5, delete the oldest ones
        $max_backups = 5;
        $total_files = count($files);

        if ($total_files > $max_backups) {
            $files_to_delete = array_slice($files, 0, $total_files - $max_backups);
            foreach ($files_to_delete as $file) {
                @unlink($file);
            }
        }
    }

    public function restore_backup($filename)
    {
        $filepath = self::$backup_dir . basename($filename);

        if (!file_exists($filepath) || pathinfo($filepath, PATHINFO_EXTENSION) !== 'json') {
            return new WP_Error('not_found', __('Backup file not found or invalid.', 'user-data-collection'));
        }

        $json_data = file_get_contents($filepath);
        if (!$json_data) {
            return new WP_Error('read_error', __('Could not read backup file.', 'user-data-collection'));
        }

        $data = json_decode($json_data, true);
        if ($data === null) {
            return new WP_Error('parse_error', __('Could not parse backup JSON data.', 'user-data-collection'));
        }

        return $this->insert_backup_data($data);
    }

    private function insert_backup_data($data)
    {
        if (empty($data) || !is_array($data)) {
            return 0;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';

        $added_count = 0;

        foreach ($data as $row) {
            if (!isset($row['id'])) {
                continue;
            }

            // Check if the submission already exists
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $table_name WHERE id = %d", $row['id']));

            if (!$exists) {
                // Ensure arrays are allowed in insert
                $wpdb->insert($table_name, $row);
                $added_count++;
            }
        }

        return $added_count;
    }

    public function ajax_create_backup()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer('udc_backup_nonce', 'security', false)) {
            wp_send_json_error(__('Permission denied or invalid security token.', 'user-data-collection'));
        }

        $result = $this->create_backup();
        if ($result) {
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

        $filename = isset($_POST['filename']) ? sanitize_text_field(wp_unslash($_POST['filename'])) : '';
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

        if (empty($_FILES['backup_file']['tmp_name'])) {
            wp_send_json_error(__('No file uploaded.', 'user-data-collection'));
        }

        $file = $_FILES['backup_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('Error uploading file.', 'user-data-collection'));
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'json') {
            wp_send_json_error(__('Only JSON files are allowed.', 'user-data-collection'));
        }

        $json_data = file_get_contents($file['tmp_name']);
        if (!$json_data) {
            wp_send_json_error(__('Could not read uploaded file.', 'user-data-collection'));
        }

        $data = json_decode($json_data, true);
        if ($data === null) {
            wp_send_json_error(__('Could not parse uploaded JSON data.', 'user-data-collection'));
        }

        $added_count = $this->insert_backup_data($data);

        wp_send_json_success(['message' => sprintf(__('Uploaded backup processed. %d missing submissions were added.', 'user-data-collection'), $added_count)]);
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->secure_directory();
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

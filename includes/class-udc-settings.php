<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Settings
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings()
    {
        register_setting('udc_settings_group', 'udc_gdrive_json');
        register_setting('udc_settings_group', 'udc_gdrive_folder');
        register_setting('udc_settings_group', 'udc_gdrive_sync_enabled');
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get options
        $gdrive_json = get_option('udc_gdrive_json', '');
        $gdrive_folder = get_option('udc_gdrive_folder', '');
        $sync_enabled = get_option('udc_gdrive_sync_enabled', '0');
        $last_sync_status = get_option('udc_gdrive_last_status', []);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Settings', 'user-data-collection'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active"><?php esc_html_e('Google Drive Integration', 'user-data-collection'); ?></a>
            </h2>

            <?php if (!empty($last_sync_status)): ?>
                <?php 
                    $is_error = isset($last_sync_status['error']) && $last_sync_status['error'] === true; 
                    $class = $is_error ? 'notice-error' : 'notice-success';
                ?>
                <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
                    <p><strong><?php esc_html_e('Last Sync Status:', 'user-data-collection'); ?></strong> <?php echo esc_html($last_sync_status['message']); ?> <em>(<?php echo esc_html($last_sync_status['time']); ?>)</em></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('udc_settings_group'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Cloud Sync', 'user-data-collection'); ?></th>
                        <td>
                            <input type="checkbox" name="udc_gdrive_sync_enabled" value="1" <?php checked(1, $sync_enabled, true); ?> />
                            <p class="description"><?php esc_html_e('If enabled, a weekly task will synchronize your local backups to Google Drive.', 'user-data-collection'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Google Drive Folder ID', 'user-data-collection'); ?></th>
                        <td>
                            <input type="text" name="udc_gdrive_folder" value="<?php echo esc_attr($gdrive_folder); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('The 33-character ID found in your Google Drive folder URL.', 'user-data-collection'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Service Account JSON Key', 'user-data-collection'); ?></th>
                        <td>
                            <textarea name="udc_gdrive_json" rows="8" class="large-text code" placeholder='{"type": "service_account", ...}'><?php echo esc_textarea($gdrive_json); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Create a Service Account in Google Cloud, generate a JSON key, and paste its contents here. Make sure to share the Google Drive Folder with the Service Account email (Editor role).', 'user-data-collection'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>
            <h3><?php esc_html_e('Manual Sync', 'user-data-collection'); ?></h3>
            <p><?php esc_html_e('You can trigger the synchronization manually to immediately upload missing backups and enforce the 5-file retention limit.', 'user-data-collection'); ?></p>
            <button id="udc-manual-sync" class="button button-secondary" data-nonce="<?php echo esc_attr(wp_create_nonce('udc_sync_nonce')); ?>">
                <?php esc_html_e('Test Connection & Sync Now', 'user-data-collection'); ?>
            </button>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const syncBtn = document.getElementById('udc-manual-sync');
                if (syncBtn) {
                    syncBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const btn = this;
                        const originalText = btn.innerHTML;
                        btn.disabled = true;
                        btn.innerHTML = '<?php echo esc_js(__('Synchronizing...', 'user-data-collection')); ?>';

                        const formData = new FormData();
                        formData.append('action', 'udc_manual_gdrive_sync');
                        formData.append('security', btn.getAttribute('data-nonce'));

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
            });
        </script>
        <?php
    }
}

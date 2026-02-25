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
        // GDrive Settings
        register_setting('udc_gdrive_settings', 'udc_gdrive_json');
        register_setting('udc_gdrive_settings', 'udc_gdrive_folder');
        register_setting('udc_gdrive_settings', 'udc_gdrive_sync_enabled');

        // Email Settings
        register_setting('udc_email_settings', 'udc_email_backup_enabled');
        register_setting('udc_email_settings', 'udc_email_address');

        // Design Settings
        register_setting('udc_design_settings', 'udc_design_enabled');
        register_setting('udc_design_settings', 'udc_design_input_bg');
        register_setting('udc_design_settings', 'udc_design_input_border');
        register_setting('udc_design_settings', 'udc_design_input_text');
        register_setting('udc_design_settings', 'udc_design_care_bg');
        register_setting('udc_design_settings', 'udc_design_care_border');
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'gdrive';

        // GDrive options
        $gdrive_json = get_option('udc_gdrive_json', '');
        $gdrive_folder = get_option('udc_gdrive_folder', '');
        $sync_enabled = get_option('udc_gdrive_sync_enabled', '0');
        $last_sync_status = get_option('udc_gdrive_last_status', []);

        // Email options
        $email_enabled = get_option('udc_email_backup_enabled', '0');
        $email_address = get_option('udc_email_address', get_option('admin_email'));

        // Design options
        $design_enabled = get_option('udc_design_enabled', '0');
        $input_bg = get_option('udc_design_input_bg', 'transparent');
        $input_border = get_option('udc_design_input_border', 'rgba(255, 255, 255, 0.5)');
        $input_text = get_option('udc_design_input_text', '#ffffff');
        $care_bg = get_option('udc_design_care_bg', 'rgba(255, 255, 255, 0.05)');
        $care_border = get_option('udc_design_care_border', '#ffffff');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Settings', 'user-data-collection'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=udc-settings&tab=gdrive" class="nav-tab <?php echo $active_tab == 'gdrive' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Google Drive Integration', 'user-data-collection'); ?></a>
                <a href="?page=udc-settings&tab=email" class="nav-tab <?php echo $active_tab == 'email' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Email Backups', 'user-data-collection'); ?></a>
                <a href="?page=udc-settings&tab=design" class="nav-tab <?php echo $active_tab == 'design' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Design Customization', 'user-data-collection'); ?></a>
            </h2>

            <?php if ($active_tab == 'gdrive'): ?>
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
                    <?php settings_fields('udc_gdrive_settings'); ?>
                    
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

            <?php elseif ($active_tab == 'email'): ?>
                <form method="post" action="options.php">
                    <?php settings_fields('udc_email_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Email Backups', 'user-data-collection'); ?></th>
                            <td>
                                <input type="checkbox" name="udc_email_backup_enabled" value="1" <?php checked(1, $email_enabled, true); ?> />
                                <p class="description"><?php esc_html_e('If enabled, a monthly task will send the most recent backup file to the configured email address.', 'user-data-collection'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Email Address', 'user-data-collection'); ?></th>
                            <td>
                                <input type="email" name="udc_email_address" value="<?php echo esc_attr($email_address); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('The email address where the automatic backups should be sent.', 'user-data-collection'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>

                <hr>
                <h3><?php esc_html_e('Send Manual Backup', 'user-data-collection'); ?></h3>
                <p><?php esc_html_e('You can trigger this action to immediately email the most recent local backup to the configured address.', 'user-data-collection'); ?></p>
                <button id="udc-manual-email" class="button button-secondary" data-nonce="<?php echo esc_attr(wp_create_nonce('udc_email_nonce')); ?>">
                    <?php esc_html_e('Send Backup Now', 'user-data-collection'); ?>
                </button>

            <?php elseif ($active_tab == 'design'): ?>
                <form method="post" action="options.php">
                    <?php settings_fields('udc_design_settings'); ?>
                    
                    <p><?php esc_html_e('Customize the frontend form colors to match your dark/red theme seamlessly.', 'user-data-collection'); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Custom Design', 'user-data-collection'); ?></th>
                            <td>
                                <input type="checkbox" name="udc_design_enabled" value="1" <?php checked(1, $design_enabled, true); ?> />
                                <p class="description"><?php esc_html_e('Check to override default browser styles with the custom colors below.', 'user-data-collection'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Input Background Color', 'user-data-collection'); ?></th>
                            <td>
                                <input type="text" name="udc_design_input_bg" value="<?php echo esc_attr($input_bg); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('E.g. transparent, #ffffff, rgba(0,0,0,0.5)', 'user-data-collection'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Input Border Color', 'user-data-collection'); ?></th>
                            <td>
                                <input type="text" name="udc_design_input_border" value="<?php echo esc_attr($input_border); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Input Text Color', 'user-data-collection'); ?></th>
                            <td>
                                <input type="text" name="udc_design_input_text" value="<?php echo esc_attr($input_text); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Care Instructions Background', 'user-data-collection'); ?></th>
                            <td>
                                <input type="text" name="udc_design_care_bg" value="<?php echo esc_attr($care_bg); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Care Instructions Border', 'user-data-collection'); ?></th>
                            <td>
                                <input type="text" name="udc_design_care_border" value="<?php echo esc_attr($care_border); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>

            <?php endif; ?>
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

                const emailBtn = document.getElementById('udc-manual-email');
                if (emailBtn) {
                    emailBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const btn = this;
                        const originalText = btn.innerHTML;
                        btn.disabled = true;
                        btn.innerHTML = '<?php echo esc_js(__('Sending...', 'user-data-collection')); ?>';

                        const formData = new FormData();
                        formData.append('action', 'udc_manual_email_backup');
                        formData.append('security', btn.getAttribute('data-nonce'));

                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.data.message);
                            } else {
                                alert(data.data || '<?php echo esc_js(__('An error occurred.', 'user-data-collection')); ?>');
                            }
                            btn.disabled = false;
                            btn.innerHTML = originalText;
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

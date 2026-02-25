<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Admin
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('Submissions', 'user-data-collection'),
            __('Submissions', 'user-data-collection'),
            'manage_options',
            'udc-submissions',
            [$this, 'render_admin_page'],
            'dashicons-feedback',
            30
        );

        add_submenu_page(
            'udc-submissions',
            __('Backups', 'user-data-collection'),
            __('Backups', 'user-data-collection'),
            'manage_options',
            'udc-backups',
            [new UDC_Backup(), 'render_admin_page']
        );

        add_submenu_page(
            'udc-submissions',
            __('Settings', 'user-data-collection'),
            __('Settings', 'user-data-collection'),
            'manage_options',
            'udc-settings',
            [new UDC_Settings(), 'render_admin_page']
        );
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';

        if ($action === 'view' && isset($_GET['id'])) {
            $this->render_details_page(intval($_GET['id']));
        } else {
            $this->render_list_page();
        }
    }

    private function render_list_page()
    {
        $list_table = new UDC_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('User Submissions', 'user-data-collection'); ?></h1>

            <form id="udc-filter" method="get">
                <input type="hidden" name="page"
                    value="<?php echo isset($_REQUEST['page']) ? esc_attr($_REQUEST['page']) : 'udc-submissions'; ?>" />
                <?php $list_table->views(); ?>
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php $this->render_ajax_script(); ?>
    <?php
    }

    private function render_details_page($id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';
        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

        if (!$submission) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Submission not found.', 'user-data-collection') . '</p></div></div>';
            return;
        }

        $back_url = add_query_arg(['page' => 'udc-submissions'], admin_url('admin.php'));
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Submission Details', 'user-data-collection'); ?>
                #<?php echo esc_html($submission->id); ?></h1>
            <a href="<?php echo esc_url($back_url); ?>"
                class="page-title-action"><?php esc_html_e('Back to List', 'user-data-collection'); ?></a>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Personal Information', 'user-data-collection'); ?></span>
                            </h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th><?php esc_html_e('First Name', 'user-data-collection'); ?></th>
                                        <td><?php echo esc_html($submission->first_name); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e('Last Name', 'user-data-collection'); ?></th>
                                        <td><?php echo esc_html($submission->last_name); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e('Date of Birth', 'user-data-collection'); ?></th>
                                        <td><?php echo esc_html($submission->dob); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e('Address', 'user-data-collection'); ?></th>
                                        <td><?php echo esc_html($submission->address); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e('Zip Code, City', 'user-data-collection'); ?></th>
                                        <td><?php echo esc_html($submission->zip_city); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e('Phone', 'user-data-collection'); ?></th>
                                        <td><?php echo esc_html($submission->phone); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Health Questionnaire', 'user-data-collection'); ?></span>
                            </h2>
                            <div class="inside">
                                <ul>
                                    <li><strong><?php esc_html_e('Good Health:', 'user-data-collection'); ?></strong>
                                        <?php echo $submission->health_good ? __('Yes', 'user-data-collection') : __('No', 'user-data-collection'); ?>
                                    </li>
                                    <li><strong><?php esc_html_e('Undergoing Treatment:', 'user-data-collection'); ?></strong>
                                        <?php echo $submission->health_treatment ? __('Yes', 'user-data-collection') : __('No', 'user-data-collection'); ?>
                                    </li>
                                    <li><strong><?php esc_html_e('Blood Thinners/Sintrom:', 'user-data-collection'); ?></strong>
                                        <?php echo $submission->health_blood_thinners ? __('Yes', 'user-data-collection') : __('No', 'user-data-collection'); ?>
                                    </li>
                                    <li><strong><?php esc_html_e('Allergies:', 'user-data-collection'); ?></strong>
                                        <?php echo $submission->health_allergies ? __('Yes', 'user-data-collection') : __('No', 'user-data-collection'); ?>
                                    </li>
                                    <li><strong><?php esc_html_e('Pregnant/Breastfeeding:', 'user-data-collection'); ?></strong>
                                        <?php echo $submission->health_pregnant ? __('Yes', 'user-data-collection') : __('No', 'user-data-collection'); ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div id="postbox-container-1" class="postbox-container">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Appointment Info', 'user-data-collection'); ?></span></h2>
                            <div class="inside">
                                <p><strong><?php esc_html_e('Date:', 'user-data-collection'); ?></strong>
                                    <?php echo esc_html($submission->appointment_date); ?></p>
                                <p><strong><?php esc_html_e('Time:', 'user-data-collection'); ?></strong>
                                    <?php echo esc_html($submission->appointment_time); ?></p>
                                <p><strong><?php esc_html_e('Location:', 'user-data-collection'); ?></strong>
                                    <?php echo esc_html($submission->piercing_location); ?></p>
                                <p><strong><?php esc_html_e('Submitted:', 'user-data-collection'); ?></strong>
                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->created_at))); ?>
                                </p>
                                <p><strong><?php esc_html_e('Status:', 'user-data-collection'); ?></strong>
                                    <?php if ($submission->is_confirmed): ?>
                                        <span
                                            style="color: green; font-weight: bold;"><?php esc_html_e('Confirmed', 'user-data-collection'); ?></span>
                                    <?php else: ?>
                                        <span
                                            style="color: orange; font-weight: bold;"><?php esc_html_e('Pending', 'user-data-collection'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="bottom-actions"
                                style="padding: 10px; background: #f9f9f9; border-top: 1px solid #dfdfdf; text-align: right;">
                                <?php if (!$submission->is_confirmed): ?>
                                    <button class="button button-primary udc-confirm-btn"
                                        data-id="<?php echo esc_attr($submission->id); ?>"
                                        data-nonce="<?php echo esc_attr(wp_create_nonce('udc_confirm_nonce_' . $submission->id)); ?>">
                                        <?php esc_html_e('Confirm Appointment', 'user-data-collection'); ?>
                                    </button>
                                <?php else: ?>
                                    <button class="button button-secondary udc-unconfirm-btn"
                                        data-id="<?php echo esc_attr($submission->id); ?>"
                                        data-nonce="<?php echo esc_attr(wp_create_nonce('udc_unconfirm_nonce_' . $submission->id)); ?>">
                                        <?php esc_html_e('Unconfirm Appointment', 'user-data-collection'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Liability Notice', 'user-data-collection'); ?></span></h2>
                            <div class="inside">
                                <p>
                                    <?php if ($submission->liability_accepted): ?>
                                        <span style="color: green;">&#10004;</span>
                                        <?php esc_html_e('Terms & conditions and liability waiver accepted by the user.', 'user-data-collection'); ?>
                                    <?php else: ?>
                                        <span style="color: red;">&#10008;</span>
                                        <?php esc_html_e('Not accepted.', 'user-data-collection'); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php $this->render_ajax_script(); ?>
    <?php
    }

    private function render_ajax_script()
    {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.body.addEventListener('click', function (e) {
                    const confirmBtn = e.target.closest('.udc-confirm-btn');
                    const unconfirmBtn = e.target.closest('.udc-unconfirm-btn');

                    if (confirmBtn) {
                        e.preventDefault();
                        handleAjaxAction(confirmBtn, 'udc_confirm_submission', '<?php echo esc_js(__('Confirming...', 'user-data-collection')); ?>', '<?php echo esc_js(__('Confirm', 'user-data-collection')); ?>');
                    } else if (unconfirmBtn) {
                        e.preventDefault();
                        handleAjaxAction(unconfirmBtn, 'udc_unconfirm_submission', '<?php echo esc_js(__('Unconfirming...', 'user-data-collection')); ?>', '<?php echo esc_js(__('Unconfirm', 'user-data-collection')); ?>');
                    }
                });

                function handleAjaxAction(btn, action, loadingText, originalText) {
                    const id = btn.getAttribute('data-id');
                    const nonce = btn.getAttribute('data-nonce');

                    btn.disabled = true;
                    const oldHtml = btn.innerHTML;
                    btn.innerHTML = loadingText;

                    const formData = new FormData();
                    formData.append('action', action);
                    formData.append('submission_id', id);
                    formData.append('security', nonce);

                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.location.reload();
                            } else {
                                alert(data.data || '<?php echo esc_js(__('An error occurred.', 'user-data-collection')); ?>');
                                btn.disabled = false;
                                btn.innerHTML = oldHtml;
                            }
                        })
                        .catch(error => {
                            alert('<?php echo esc_js(__('Network Error', 'user-data-collection')); ?>');
                            btn.disabled = false;
                            btn.innerHTML = oldHtml;
                        });
                }
            });
        </script>
        <?php
    }
}

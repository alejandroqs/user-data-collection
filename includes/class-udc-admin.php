<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Admin
{
    private $is_piercing = false;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('Tattoos', 'user-data-collection'),
            __('Waivers', 'user-data-collection'),
            'manage_options',
            'udc-submissions',
            [$this, 'render_admin_page'],
            'dashicons-feedback',
            30
        );

        add_submenu_page('udc-submissions', __('Tattoos', 'user-data-collection'), __('Tattoos', 'user-data-collection'), 'manage_options', 'udc-submissions', [$this, 'render_admin_page']);
        add_submenu_page('udc-submissions', __('Piercings', 'user-data-collection'), __('Piercings', 'user-data-collection'), 'manage_options', 'udc-piercings', [$this, 'render_piercing_page']);

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

    public function render_piercing_page()
    {
        $this->is_piercing = true;
        try {
            $this->render_admin_page();
        } finally {
            $this->is_piercing = false;
        }
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $action = isset($_GET['action']) && is_string($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';

        if ($action === 'view') {
            $id = isset($_GET['id']) && is_string($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
            if (false === $id) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Submission not found.', 'user-data-collection') . '</p></div>';
                return;
            }
            $this->render_details_page($id);
        } else {
            $this->render_list_page();
        }
    }

    private function render_list_page()
    {
        $list_table = new UDC_List_Table($this->is_piercing);
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__($this->is_piercing ? 'Piercing Submissions' : 'Tattoos', 'user-data-collection'); ?></h1>

            <p><code><?php echo esc_html($this->is_piercing ? '[udc_piercing_form]' : '[udc_contact_form]'); ?></code></p>
            <form id="udc-filter" method="get">
                <input type="hidden" name="page"
                    value="<?php echo $this->is_piercing ? 'udc-piercings' : 'udc-submissions'; ?>" />
                <?php $list_table->views(); ?>
                <?php $list_table->display(); ?>
            </form>
        </div>
    <?php
    }

    private function render_details_page($id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';
        $type_filter = $this->is_piercing ? "form_type = 'piercing'" : "form_type IN ('legacy', 'tattoo')";
        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND $type_filter", $id));

        if (!$submission) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Submission not found.', 'user-data-collection') . '</p></div></div>';
            return;
        }

        $back_url = add_query_arg(['page' => $this->is_piercing ? 'udc-piercings' : 'udc-submissions'], admin_url('admin.php'));
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Submission Details', 'user-data-collection'); ?>
                #<?php echo esc_html($submission->id); ?></h1>
            <a href="<?php echo esc_url($back_url); ?>"
                class="page-title-action"><?php esc_html_e('Back to List', 'user-data-collection'); ?></a>

            <?php if ($this->is_piercing): ?>
                <div class="postbox"><div class="inside">
                    <h2><?php esc_html_e('Accepted document', 'user-data-collection'); ?></h2>
                    <p><?php echo esc_html(($submission->piercing_category ?? '') . ' | ' . ($submission->form_language ?? '') . ' | ' . ($submission->form_version ?? '')); ?></p>
                    <?php $evidence = UDC_Piercing_Form::valid_evidence($submission->form_evidence ?? ''); ?>
                    <?php if ($evidence): ?>
                        <?php foreach ($evidence['consent'] as $text): ?><p><?php echo esc_html($text); ?></p><?php endforeach; ?>
                        <?php foreach (['base', $submission->piercing_category, 'alert'] as $key): ?><p><?php echo esc_html($evidence['care'][$key] ?? ''); ?></p><?php endforeach; ?>
                    <?php else: ?><p><?php esc_html_e('Document evidence is unavailable.', 'user-data-collection'); ?></p><?php endif; ?>
                </div></div>
            <?php endif; ?>
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
                                        <th><?php esc_html_e('City', 'user-data-collection'); ?></th>
                                        <td><?php echo esc_html($submission->city); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e('Zip Code', 'user-data-collection'); ?></th>
                                        <td><?php echo esc_html($submission->zip); ?></td>
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
                                        <?php echo esc_html(UDC_Shortcode::health_answer($submission->health_good)); ?>
                                    </li>
                                    <li><strong><?php esc_html_e('Undergoing Treatment:', 'user-data-collection'); ?></strong>
                                        <?php echo esc_html(UDC_Shortcode::health_answer($submission->health_treatment)); ?>
                                    </li>
                                    <li><strong><?php esc_html_e('Blood Thinners/Sintrom:', 'user-data-collection'); ?></strong>
                                        <?php echo esc_html(UDC_Shortcode::health_answer($submission->health_blood_thinners)); ?>
                                    </li>
                                    <li><strong><?php esc_html_e('Allergies:', 'user-data-collection'); ?></strong>
                                        <?php echo esc_html(UDC_Shortcode::health_answer($submission->health_allergies)); ?>
                                    </li>
                                    <li><strong><?php esc_html_e('Pregnant/Breastfeeding:', 'user-data-collection'); ?></strong>
                                        <?php echo esc_html(UDC_Shortcode::health_answer($submission->health_pregnant)); ?>
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
                            </div>
                        </div>
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e('Liability Notice', 'user-data-collection'); ?></span></h2>
                            <div class="inside">
                                <p>
                                    <?php if ($submission->liability_accepted): ?>
                                        <span style="color: green;">&#10004;</span>
                                        <?php echo esc_html__($this->is_piercing ? 'Piercing consent accepted by the user.' : 'Terms & conditions and liability waiver accepted by the user.', 'user-data-collection'); ?>
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
    <?php
    }
}

<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Shortcode
{

    public function __construct()
    {
        add_shortcode('udc_contact_form', [$this, 'render_form']);

        // Handle form submission (logged in and logged out users)
        add_action('admin_post_udc_submit_form', [$this, 'handle_submission']);
        add_action('admin_post_nopriv_udc_submit_form', [$this, 'handle_submission']);
    }

    public function render_form()
    {
        ob_start();

        // Check for success or error messages (set via transients or URL parameters)
        if (isset($_GET['udc_status']) && $_GET['udc_status'] === 'success') {
            echo '<p style="color: green;">' . esc_html(UDC_i18n::translate('msg_success')) . '</p>';
        } elseif (isset($_GET['udc_status']) && $_GET['udc_status'] === 'error') {
            echo '<p style="color: red;">' . esc_html(UDC_i18n::translate('msg_error')) . '</p>';
        }
        ?>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST" class="udc-form">
            <?php wp_nonce_field('udc_form_action', 'udc_form_nonce'); ?>
            <input type="hidden" name="action" value="udc_submit_form">

            <h2><?php echo esc_html(UDC_i18n::translate('title_personal_info')); ?></h2>

            <p>
                <label for="udc_last_name"><?php echo esc_html(UDC_i18n::translate('label_last_name')); ?></label><br>
                <input type="text" id="udc_last_name" name="udc_last_name" required>
            </p>
            <p>
                <label for="udc_first_name"><?php echo esc_html(UDC_i18n::translate('label_first_name')); ?></label><br>
                <input type="text" id="udc_first_name" name="udc_first_name" required>
            </p>
            <p>
                <label for="udc_dob"><?php echo esc_html(UDC_i18n::translate('label_dob')); ?></label><br>
                <input type="date" id="udc_dob" name="udc_dob" required>
            </p>
            <p>
                <label for="udc_address"><?php echo esc_html(UDC_i18n::translate('label_address')); ?></label><br>
                <input type="text" id="udc_address" name="udc_address" required>
            </p>
            <p>
                <label for="udc_zip_city"><?php echo esc_html(UDC_i18n::translate('label_city')); ?></label><br>
                <input type="text" id="udc_zip_city" name="udc_zip_city" required>
            </p>
            <p>
                <label for="udc_phone"><?php echo esc_html(UDC_i18n::translate('label_phone')); ?></label><br>
                <input type="tel" id="udc_phone" name="udc_phone" required>
            </p>

            <h2><?php echo esc_html(UDC_i18n::translate('title_health')); ?></h2>
            <p>
                <label>
                    <input type="checkbox" name="udc_health_good" value="1">
                    <?php echo esc_html(UDC_i18n::translate('health_good')); ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="udc_health_treatment" value="1">
                    <?php echo esc_html(UDC_i18n::translate('health_treatment')); ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="udc_health_blood_thinners" value="1">
                    <?php echo esc_html(UDC_i18n::translate('health_blood')); ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="udc_health_allergies" value="1">
                    <?php echo esc_html(UDC_i18n::translate('health_allergies')); ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="udc_health_pregnant" value="1">
                    <?php echo esc_html(UDC_i18n::translate('health_pregnant')); ?>
                </label>
            </p>

            <h2><?php echo esc_html(UDC_i18n::translate('title_appointment_info')); ?></h2>
            <p>
                <label for="udc_appointment_date"><?php echo esc_html(UDC_i18n::translate('label_appt_date')); ?></label><br>
                <input type="date" id="udc_appointment_date" name="udc_appointment_date" required>
            </p>
            <p>
                <label for="udc_appointment_time"><?php echo esc_html(UDC_i18n::translate('label_appt_time')); ?></label><br>
                <input type="time" id="udc_appointment_time" name="udc_appointment_time" required>
            </p>
            <p>
                <label for="udc_piercing_location"><?php echo esc_html(UDC_i18n::translate('label_location')); ?></label><br>
                <input type="text" id="udc_piercing_location" name="udc_piercing_location" required>
            </p>

            <h2><?php echo esc_html(UDC_i18n::translate('subtitle_care')); ?></h2>
            <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #ccc; margin-bottom: 15px;">
                <ul>
                    <?php
                    $care_keys = ['care_1', 'care_2', 'care_3', 'care_4', 'care_5', 'care_6'];
                    foreach ($care_keys as $key) {
                        $care_text = UDC_i18n::translate($key);
                        // Make text before colon bold
                        $parts = explode(':', $care_text, 2);
                        if (count($parts) === 2) {
                            echo '<li><strong>' . esc_html($parts[0]) . ':</strong>' . esc_html($parts[1]) . '</li>';
                        } else {
                            echo '<li>' . esc_html($care_text) . '</li>';
                        }
                    }
                    ?>
                </ul>
            </div>

            <h2><?php echo esc_html(UDC_i18n::translate('title_liability')); ?></h2>
            <p>
                <label>
                    <input type="checkbox" name="udc_liability_accepted" value="1" required>
                    <?php echo esc_html(UDC_i18n::translate('liability_text')); ?>
                </label>
            </p>

            <p>
                <button type="submit"><?php echo esc_html(UDC_i18n::translate('submit_btn')); ?></button>
            </p>
        </form>

        <?php
        return ob_get_clean();
    }

    public function handle_submission()
    {
        // 1. Verify Nonce
        if (!isset($_POST['udc_form_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['udc_form_nonce'])), 'udc_form_action')) {
            wp_die('Security check failed.', 'Error', ['response' => 403]);
        }

        // 2. Extract and Sanitize Inputs
        $last_name = isset($_POST['udc_last_name']) ? sanitize_text_field(wp_unslash($_POST['udc_last_name'])) : '';
        $first_name = isset($_POST['udc_first_name']) ? sanitize_text_field(wp_unslash($_POST['udc_first_name'])) : '';
        $dob = isset($_POST['udc_dob']) ? sanitize_text_field(wp_unslash($_POST['udc_dob'])) : '';
        $address = isset($_POST['udc_address']) ? sanitize_text_field(wp_unslash($_POST['udc_address'])) : '';
        $zip_city = isset($_POST['udc_zip_city']) ? sanitize_text_field(wp_unslash($_POST['udc_zip_city'])) : '';
        $phone = isset($_POST['udc_phone']) ? sanitize_text_field(wp_unslash($_POST['udc_phone'])) : '';

        $health_good = isset($_POST['udc_health_good']) ? 1 : 0;
        $health_treatment = isset($_POST['udc_health_treatment']) ? 1 : 0;
        $health_blood_thinners = isset($_POST['udc_health_blood_thinners']) ? 1 : 0;
        $health_allergies = isset($_POST['udc_health_allergies']) ? 1 : 0;
        $health_pregnant = isset($_POST['udc_health_pregnant']) ? 1 : 0;

        $liability_accepted = isset($_POST['udc_liability_accepted']) ? 1 : 0;

        $appointment_date = isset($_POST['udc_appointment_date']) ? sanitize_text_field(wp_unslash($_POST['udc_appointment_date'])) : '';
        $appointment_time = isset($_POST['udc_appointment_time']) ? sanitize_text_field(wp_unslash($_POST['udc_appointment_time'])) : '';
        $piercing_location = isset($_POST['udc_piercing_location']) ? sanitize_text_field(wp_unslash($_POST['udc_piercing_location'])) : '';

        // 3. Validation
        if (empty($last_name) || empty($first_name) || empty($liability_accepted)) {
            $redirect_url = add_query_arg('udc_status', 'error', wp_get_referer());
            wp_safe_redirect($redirect_url);
            exit;
        }

        // 4. Save to Custom Table
        global $wpdb;
        $table_name = $wpdb->prefix . 'udc_submissions';

        $inserted = $wpdb->insert(
            $table_name,
            [
                'last_name' => $last_name,
                'first_name' => $first_name,
                'dob' => $dob,
                'address' => $address,
                'zip_city' => $zip_city,
                'phone' => $phone,
                'health_good' => $health_good,
                'health_treatment' => $health_treatment,
                'health_blood_thinners' => $health_blood_thinners,
                'health_allergies' => $health_allergies,
                'health_pregnant' => $health_pregnant,
                'liability_accepted' => $liability_accepted,
                'appointment_date' => $appointment_date,
                'appointment_time' => $appointment_time,
                'piercing_location' => $piercing_location,
                'is_confirmed' => 0
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%d'
            ]
        );

        if (false === $inserted) {
            error_log('UDC Plugin Insert Error: ' . $wpdb->last_error);
        }

        // 5. Redirect based on result
        $status = $inserted ? 'success' : 'error';
        $redirect_url = add_query_arg('udc_status', $status, wp_get_referer());
        wp_safe_redirect($redirect_url);
        exit;
    }
}

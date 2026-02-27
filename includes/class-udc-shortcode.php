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
            echo '<div style="background-color: #b2c7b7; border: 1px solid #7d9482; color: #155724; padding: 15px 20px; margin: 20px 0 30px 0; border-radius: 4px; font-family: system-ui, -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; font-size: 16px; font-weight: 500;">&#10004; ' . esc_html(UDC_i18n::translate('msg_success')) . '</div>';
        } elseif (isset($_GET['udc_status']) && $_GET['udc_status'] === 'error') {
            echo '<div style="background-color: #d2b6b9; border: 1px solid #a18185; color: #721c24; padding: 15px 20px; margin: 20px 0 30px 0; border-radius: 4px; font-family: system-ui, -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; font-size: 16px; font-weight: 500;">&#9888; ' . esc_html(UDC_i18n::translate('msg_error')) . '</div>';
        }

        $design_enabled = get_option('udc_design_enabled', '0');
        if ($design_enabled) {
            $input_bg = esc_html(get_option('udc_design_input_bg', 'transparent'));
            $input_border = esc_html(get_option('udc_design_input_border', 'rgba(255, 255, 255, 0.5)'));
            $input_text = esc_html(get_option('udc_design_input_text', '#ffffff'));
            $care_bg = esc_html(get_option('udc_design_care_bg', 'rgba(255, 255, 255, 0.05)'));
            $care_border = esc_html(get_option('udc_design_care_border', '#ffffff'));

            $cb_bg = esc_html(get_option('udc_design_cb_bg', 'transparent'));
            $cb_border = esc_html(get_option('udc_design_cb_border', 'rgba(255, 255, 255, 0.5)'));
            $cb_check = esc_html(get_option('udc_design_cb_check', '#ffffff'));
            $invert_icons = get_option('udc_design_invert_icons', '1');
            ?>
            <style>
                .udc-form input[type="text"],
                .udc-form input[type="date"],
                .udc-form input[type="time"],
                .udc-form input[type="tel"] {
                    background:
                        <?php echo $input_bg; ?>
                    ;
                    border: 1px solid
                        <?php echo $input_border; ?>
                    ;
                    color:
                        <?php echo $input_text; ?>
                    ;
                    padding: 8px 12px;
                    width: 100%;
                    max-width: 400px;
                    box-sizing: border-box;
                    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
                }

                .udc-form input[type="text"]:focus,
                .udc-form input[type="date"]:focus,
                .udc-form input[type="time"]:focus,
                .udc-form input[type="tel"]:focus {
                    border-color:
                        <?php echo $input_text; ?>
                    ;
                    outline: none;
                }

                .udc-form .udc-care-instructions {
                    background:
                        <?php echo $care_bg; ?>
                    ;
                    padding: 15px;
                    border-left: 4px solid
                        <?php echo $care_border; ?>
                    ;
                    margin-bottom: 15px;
                    color:
                        <?php echo $input_text; ?>
                    ;
                }

                .udc-form .udc-care-instructions ul {
                    margin: 0;
                    padding-left: 20px;
                }

                .udc-form button[type="submit"] {
                    background: transparent;
                    border: 2px solid
                        <?php echo $input_border; ?>
                    ;
                    color:
                        <?php echo $input_text; ?>
                    ;
                    padding: 10px 20px;
                    cursor: pointer;
                    text-transform: uppercase;
                    font-weight: bold;
                }

                .udc-form button[type="submit"]:hover {
                    background:
                        <?php echo $input_text; ?>
                    ;
                    color: #000;
                }

                /* Custom Checkboxes */
                .udc-form input[type="checkbox"] {
                    appearance: none;
                    -webkit-appearance: none;
                    background-color:
                        <?php echo $cb_bg; ?>
                    ;
                    margin: 0 8px 0 0;
                    font: inherit;
                    color: currentColor;
                    width: 1.15em;
                    height: 1.15em;
                    border: 1px solid
                        <?php echo $cb_border; ?>
                    ;
                    border-radius: 0.15em;
                    transform: translateY(-0.075em);
                    display: inline-grid;
                    place-content: center;
                    cursor: pointer;
                }

                .udc-form input[type="checkbox"]::before {
                    content: "";
                    width: 0.65em;
                    height: 0.65em;
                    transform: scale(0);
                    transition: 120ms transform ease-in-out;
                    box-shadow: inset 1em 1em
                        <?php echo $cb_check; ?>
                    ;
                    transform-origin: bottom left;
                    clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
                }

                .udc-form input[type="checkbox"]:checked::before {
                    transform: scale(1);
                }

                <?php if ($invert_icons): ?>
                    /* Invert browser native icons */
                    .udc-form input[type="date"],
                    .udc-form input[type="time"] {
                        color-scheme: light;
                    }

                    .udc-form input[type="date"]::-webkit-calendar-picker-indicator,
                    .udc-form input[type="time"]::-webkit-calendar-picker-indicator {
                        filter: invert(1);
                    }

                <?php endif; ?>
            </style>
            <?php
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
            <div class="udc-care-instructions" <?php if (!$design_enabled)
                echo 'style="background: #f9f9f9; padding: 15px; border-left: 4px solid #ccc; margin-bottom: 15px;"'; ?>>
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

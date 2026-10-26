<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_Shortcode
{
    const FORM_VERSION = '2.0';

    public function __construct()
    {
        UDC_Document_Download::register('tattoo-care', new UDC_Tattoo_Care_Document());
        add_action('init', ['UDC_Tattoo_Care_Document', 'register_strings']);
        add_shortcode('udc_tattoo_form', [$this, 'render_form']);
        add_shortcode('udc_contact_form', [$this, 'render_form']); // Compatibility for existing pages.
        add_action('template_redirect', [$this, 'prevent_result_cache']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_form_behavior']);

        // Handle form submission (logged in and logged out users)
        add_action('admin_post_udc_submit_form', [$this, 'handle_submission']);
        add_action('admin_post_nopriv_udc_submit_form', [$this, 'handle_submission']);
    }

    /** Capture exactly the document text rendered for this tattoo form. */
    public static function snapshot()
    {
        $care = ['version' => UDC_Tattoo_Care_Document::VERSION, 'title' => UDC_Tattoo_Care_Document::translate('title')];
        foreach (range(1, 6) as $number) { $care['care_' . $number] = UDC_Tattoo_Care_Document::translate('care_' . $number); }
        return [
            'version' => self::FORM_VERSION,
            'language' => UDC_Tattoo_Care_Document::language(),
            'document' => [
                'title' => UDC_i18n::translate('title_tattoo'),
                'intro' => UDC_i18n::translate('intro_tattoo'),
                'liability_text' => UDC_i18n::translate('tattoo_consent_procedure'),
                'risk_text' => UDC_i18n::translate('tattoo_consent_risks'),
                'care_ack' => UDC_i18n::translate('tattoo_consent_care'),
                'privacy_text' => UDC_i18n::translate('tattoo_consent_privacy'),
            ],
            'care' => $care,
        ];
    }

    public static function valid_evidence($json)
    {
        if (!is_string($json) || strlen($json) > 32000) { return false; }
        $data = json_decode($json, true, 5);
        if (!is_array($data) || array_keys($data) !== ['version', 'language', 'document', 'care'] ||
            !is_string($data['version']) || !preg_match('/^[0-9]+\.[0-9]+$/D', $data['version']) ||
            !is_string($data['language']) || !preg_match('/^[A-Za-z0-9_@-]{1,32}$/D', $data['language'])) { return false; }
        foreach (['document' => ['title', 'intro', 'liability_text', 'risk_text', 'care_ack', 'privacy_text'], 'care' => ['version', 'title', 'care_1', 'care_2', 'care_3', 'care_4', 'care_5', 'care_6']] as $group => $keys) {
            if (!is_array($data[$group]) || array_keys($data[$group]) !== $keys) { return false; }
            foreach ($data[$group] as $text) {
                if (!is_string($text) || '' === trim($text) || strlen($text) > 6000) { return false; }
            }
        }
        if (!preg_match('/^[0-9]+\.[0-9]+$/D', $data['care']['version'])) { return false; }
        return $data;
    }

    public static function submission_evidence()
    {
        foreach (['risk_text', 'care_ack', 'privacy_text'] as $key) {
            if (!isset($_POST['udc_' . $key]) || !is_string($_POST['udc_' . $key]) || '1' !== wp_unslash($_POST['udc_' . $key])) {
                return new WP_Error('invalid_consent', 'Invalid tattoo consent.');
            }
        }
        $json = isset($_POST['udc_evidence']) && is_string($_POST['udc_evidence']) ? wp_unslash($_POST['udc_evidence']) : '';
        $signature = isset($_POST['udc_evidence_signature']) && is_string($_POST['udc_evidence_signature']) ? wp_unslash($_POST['udc_evidence_signature']) : '';
        $data = self::valid_evidence($json);
        if (!$data || self::FORM_VERSION !== $data['version'] || UDC_Tattoo_Care_Document::VERSION !== $data['care']['version'] ||
            !hash_equals(hash_hmac('sha256', $json, wp_salt('nonce')), $signature)) {
            return new WP_Error('invalid_evidence', 'Invalid tattoo form version or evidence.');
        }
        return ['form_type' => 'tattoo', 'piercing_category' => '', 'form_language' => $data['language'], 'form_version' => $data['version'], 'form_evidence' => $json];
    }

    /** Keep the two public forms visually consistent with the same scoped rules. */
    protected static function form_styles($piercing = false)
    {
        $enabled = get_option('udc_design_enabled', '0');
        $text = $enabled ? UDC_Settings::get_valid_color('udc_design_input_text', '#ffffff') : '#222222';
        $background = $enabled ? UDC_Settings::get_valid_color('udc_design_input_bg', 'transparent') : '#ffffff';
        $border = $enabled ? UDC_Settings::get_valid_color('udc_design_input_border', 'rgba(255,255,255,0.5)') : '#767676';
        $select_text = $enabled ? $text : 'inherit';
        $arrow_color = $enabled ? $text : '#767676';
        $arrow = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path d="m4 6 4 4 4-4" fill="none" stroke="' . esc_attr($arrow_color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>');
        $scheme = $enabled && get_option('udc_design_invert_icons', '1') ? 'dark' : 'light';
        $control_css = 'form.udc-piercing-form select[name]{appearance:none;-webkit-appearance:none;color:' . $select_text . '!important;-webkit-text-fill-color:' . $select_text . '!important;background-image:url("' . $arrow . '");background-repeat:no-repeat;background-position:right 12px center;background-size:16px 16px;padding-right:40px}';
        $control_css .= 'form.udc-piercing-form select[name] option{background:#fff;color:#111!important;-webkit-text-fill-color:#111!important}';
        $control_css .= 'form.udc-piercing-form select[name] option:checked{background:Highlight;color:HighlightText!important;-webkit-text-fill-color:HighlightText!important}';
        $control_css .= '.udc-piercing-form input[type="date"],.udc-piercing-form input[type="time"]{color-scheme:' . $scheme . '}';
        $css = '.udc-piercing-form{max-width:760px;overflow-wrap:anywhere}.udc-piercing-form input:not([type=checkbox]):not([type=hidden]),.udc-piercing-form select{display:block;width:100%;max-width:100%;min-width:0;box-sizing:border-box;min-height:44px;padding:10px;font:inherit;background:' . $background . ';color:' . $text . ';border:1px solid ' . $border . '}.udc-piercing-form select option{background:#fff;color:#111}.udc-piercing-form :focus-visible{outline:2px solid currentColor;outline-offset:3px}.udc-piercing-form details{padding:12px 0;border-bottom:1px solid currentColor}.udc-piercing-form summary{cursor:pointer;min-height:32px}.udc-piercing-form .udc-resources{display:flex;flex-wrap:wrap;gap:16px}.udc-piercing-form button{min-height:44px;padding:10px 18px;font:inherit;cursor:pointer}.udc-piercing-form input[type=checkbox]{width:1.1em;height:1.1em;vertical-align:middle}' . $control_css;
        $checkbox_background = $enabled ? UDC_Settings::get_valid_color('udc_design_cb_bg', 'transparent') : 'transparent';
        $checkbox_border = $enabled ? UDC_Settings::get_valid_color('udc_design_cb_border', 'rgba(255, 255, 255, 0.5)') : '#767676';
        $checkbox_check = $enabled ? UDC_Settings::get_valid_color('udc_design_cb_check', '#ffffff') : '#222222';
        $css .= '.udc-piercing-form input[type=checkbox]{appearance:none;-webkit-appearance:none;background-color:' . $checkbox_background . ';margin:0 8px 0 0;font:inherit;color:currentColor;width:1.15em;height:1.15em;box-sizing:border-box;border:1px solid ' . $checkbox_border . ';border-radius:0.15em;transform:translateY(-0.075em);display:inline-grid;place-content:center;cursor:pointer}';
        $css .= '.udc-piercing-form input[type=checkbox]::before{content:"";width:0.65em;height:0.65em;transform:scale(0);transition:120ms transform ease-in-out;box-shadow:inset 1em 1em ' . $checkbox_check . ';transform-origin:bottom left;clip-path:polygon(14% 44%,0 65%,50% 100%,100% 16%,80% 0%,43% 62%)}';
        $css .= '.udc-piercing-form input[type=checkbox]:checked::before{transform:scale(1)}';
        $css .= '@media(prefers-reduced-motion:reduce){.udc-piercing-form input[type=checkbox]::before{transition:none}}';
        return $piercing ? $css : str_replace('.udc-piercing-form', '.udc-form', $css);
    }

    public function render_form()
    {
        $prefix = wp_unique_id('udc-tattoo-');
        $status = self::result_status();
        $document = self::snapshot();
        $json = wp_json_encode($document);
        ob_start();

        // Only display a result authenticated by the submission handler.
        if ('success' === $status) {
            echo '<div style="background-color: #b2c7b7; border: 1px solid #7d9482; color: #155724; padding: 15px 20px; margin: 20px 0 30px 0; border-radius: 4px; font-family: system-ui, -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; font-size: 16px; font-weight: 500;">&#10004; ' . esc_html(UDC_i18n::translate('msg_success')) . '</div>';
        } elseif ('error' === $status) {
            echo '<div style="background-color: #d2b6b9; border: 1px solid #a18185; color: #721c24; padding: 15px 20px; margin: 20px 0 30px 0; border-radius: 4px; font-family: system-ui, -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; font-size: 16px; font-weight: 500;">&#9888; ' . esc_html(UDC_i18n::translate('msg_error')) . '</div>';
        }

        $design_enabled = get_option('udc_design_enabled', '0');
        $care_bg = $design_enabled ? UDC_Settings::get_valid_color('udc_design_care_bg', 'rgba(0, 0, 0, 0.15)') : 'rgba(0, 0, 0, 0.15)';
        $care_border = $design_enabled ? UDC_Settings::get_valid_color('udc_design_care_border', '#ffffff') : '#ccc';
        ?>
        <style>
            <?php echo self::form_styles(); // Colors are validated; selectors are fixed internally. ?>
            .udc-form .udc-care-instructions {background:<?php echo esc_html($care_bg); ?>;padding:15px;border-left:4px solid <?php echo esc_html($care_border); ?>;margin-bottom:15px}
            .udc-form .udc-care-instructions ul {margin:0;padding-left:20px}
        </style>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST" class="udc-form">
            <h2><?php echo esc_html($document['document']['title']); ?></h2>
            <p><?php echo esc_html($document['document']['intro']); ?></p>
            <?php self::render_nonce('udc_form_action', $prefix); ?>
            <input type="hidden" name="action" value="udc_submit_form">
            <input type="hidden" name="udc_evidence" value="<?php echo esc_attr($json); ?>">
            <input type="hidden" name="udc_evidence_signature" value="<?php echo esc_attr(hash_hmac('sha256', $json, wp_salt('nonce'))); ?>">

            <h3><?php echo esc_html(UDC_i18n::translate('title_personal_info')); ?></h3>

            <p>
                <label for="<?php echo esc_attr($prefix); ?>last_name"><?php echo esc_html(self::field_label(UDC_i18n::translate('label_last_name'))); ?></label><br>
                <input type="text" id="<?php echo esc_attr($prefix); ?>last_name" name="udc_last_name" maxlength="255" required>
            </p>
            <p>
                <label for="<?php echo esc_attr($prefix); ?>first_name"><?php echo esc_html(self::field_label(UDC_i18n::translate('label_first_name'))); ?></label><br>
                <input type="text" id="<?php echo esc_attr($prefix); ?>first_name" name="udc_first_name" maxlength="255" required>
            </p>
            <p>
                <label for="<?php echo esc_attr($prefix); ?>dob"><?php echo esc_html(self::field_label(UDC_i18n::translate('label_dob'))); ?></label><br>
                <input type="date" id="<?php echo esc_attr($prefix); ?>dob" name="udc_dob" min="1000-01-01" max="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
            </p>
            <p>
                <label for="<?php echo esc_attr($prefix); ?>address"><?php echo esc_html(self::field_label(UDC_i18n::translate('label_address'), false)); ?></label><br>
                <input type="text" id="<?php echo esc_attr($prefix); ?>address" name="udc_address" maxlength="255">
            </p>
            <p>
                <label for="<?php echo esc_attr($prefix); ?>city"><?php echo esc_html(self::field_label(UDC_i18n::translate('label_city'), false)); ?></label><br>
                <input type="text" id="<?php echo esc_attr($prefix); ?>city" name="udc_city" maxlength="255">
            </p>
            <p>
                <label for="<?php echo esc_attr($prefix); ?>zip"><?php echo esc_html(self::field_label(UDC_i18n::translate('label_zip'), false)); ?></label><br>
                <input type="text" id="<?php echo esc_attr($prefix); ?>zip" name="udc_zip" maxlength="50">
            </p>
            <p>
                <label for="<?php echo esc_attr($prefix); ?>phone"><?php echo esc_html(self::field_label(UDC_i18n::translate('label_phone'))); ?></label><br>
                <input type="tel" id="<?php echo esc_attr($prefix); ?>phone" name="udc_phone" maxlength="50" required>
            </p>

            <h3><?php echo esc_html(UDC_i18n::translate('title_health')); ?></h3>
            <?php self::render_health_fields(['UDC_i18n', 'translate'], $prefix); ?>

            <h3><?php echo esc_html(UDC_i18n::translate('title_appointment_info')); ?></h3>
            <p>
                <label for="<?php echo esc_attr($prefix); ?>appointment_date"><?php echo esc_html(self::field_label(UDC_i18n::translate('label_appt_date'))); ?></label><br>
                <input type="date" id="<?php echo esc_attr($prefix); ?>appointment_date" name="udc_appointment_date" required>
            </p>
            <p>
                <label for="<?php echo esc_attr($prefix); ?>appointment_time"><?php echo esc_html(self::field_label(UDC_i18n::translate('label_appt_time'))); ?></label><br>
                <input type="time" id="<?php echo esc_attr($prefix); ?>appointment_time" name="udc_appointment_time" required>
            </p>
            <p>
                <label for="<?php echo esc_attr($prefix); ?>piercing_location"><?php echo esc_html(self::field_label(UDC_i18n::translate('label_location'))); ?></label><br>
                <input type="text" id="<?php echo esc_attr($prefix); ?>piercing_location" name="udc_piercing_location" maxlength="255" required>
            </p>

            <h3><?php echo esc_html($document['care']['title']); ?></h3>
            <div class="udc-care-instructions">
                <ul>
                    <?php
                    $care_keys = ['care_1', 'care_2', 'care_3', 'care_4', 'care_5', 'care_6'];
                    foreach ($care_keys as $key) {
                        $care_text = $document['care'][$key];
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
                <p><a href="<?php echo esc_url(add_query_arg(['action' => 'udc_download_document', 'document' => 'tattoo-care', 'language' => UDC_Tattoo_Care_Document::language()], admin_url('admin-post.php'))); ?>"><?php echo esc_html(UDC_Tattoo_Care_Document::translate('download')); ?></a></p>
            </div>

            <h3><?php echo esc_html(UDC_i18n::translate('tattoo_consent_title')); ?></h3>
            <?php foreach (['liability_text', 'risk_text', 'care_ack', 'privacy_text'] as $key): $field = 'liability_text' === $key ? 'liability_accepted' : $key; ?>
                <p><label><input type="checkbox" name="udc_<?php echo esc_attr($field); ?>" value="1" required> <?php echo esc_html(self::field_label($document['document'][$key])); ?></label></p>
            <?php endforeach; ?>
            <?php if (get_privacy_policy_url()): ?>
                <p><a href="<?php echo esc_url(get_privacy_policy_url()); ?>"><?php echo esc_html(UDC_i18n::translate('tattoo_privacy_link')); ?></a></p>
            <?php endif; ?>

            <p>
                <button type="submit"><?php echo esc_html(UDC_i18n::translate('submit_btn')); ?></button>
            </p>
        </form>

        <?php
        return ob_get_clean();
    }

    public function prevent_result_cache()
    {
        if (isset($_GET['udc_result'])) { nocache_headers(); }
    }

    public function enqueue_form_behavior()
    {
        wp_register_script('udc-forms', false, [], '1.10.1', true);
        wp_enqueue_script('udc-forms');
        wp_add_inline_script('udc-forms', <<<'JS'
document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('.udc-form, .udc-piercing-form') || event.defaultPrevented) return;
    if (form.dataset.udcSubmitting === '1') { event.preventDefault(); return; }
    form.dataset.udcSubmitting = '1';
    form.setAttribute('aria-busy', 'true');
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) { button.disabled = true; });
});
window.addEventListener('pageshow', function () {
    document.querySelectorAll('form[data-udc-submitting="1"]').forEach(function (form) {
        delete form.dataset.udcSubmitting;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) { button.disabled = false; });
    });
});
JS
        );
    }

    public static function render_nonce($action, $prefix)
    {
        $html = wp_nonce_field($action, 'udc_form_nonce', true, false);
        echo str_replace(
            ['id="udc_form_nonce"', 'id="_wp_http_referer"'],
            ['id="' . esc_attr($prefix . 'nonce') . '"', 'id="' . esc_attr($prefix . 'referer') . '"'],
            $html
        );
    }

    public static function result_status($is_piercing = false)
    {
        $key = $is_piercing ? 'udc_piercing_status' : 'udc_status';
        $status = isset($_GET[$key]) && is_string($_GET[$key]) ? wp_unslash($_GET[$key]) : '';
        $receipt = isset($_GET['udc_result']) && is_string($_GET['udc_result']) ? wp_unslash($_GET['udc_result']) : '';
        if (!in_array($status, ['success', 'error'], true) || !preg_match('/^([0-9]{10})\.([a-f0-9]{64})$/D', $receipt, $parts)) {
            return '';
        }
        $expires = (int) $parts[1];
        if ($expires < time() || $expires > time() + 5 * MINUTE_IN_SECONDS) {
            return '';
        }
        $signature = hash_hmac('sha256', $key . '|' . $status . '|' . $expires, wp_salt('auth'));
        return hash_equals($signature, $parts[2]) ? $status : '';
    }

    private function redirect_result($status, $is_piercing)
    {
        $key = $is_piercing ? 'udc_piercing_status' : 'udc_status';
        $expires = time() + 5 * MINUTE_IN_SECONDS;
        $signature = hash_hmac('sha256', $key . '|' . $status . '|' . $expires, wp_salt('auth'));
        $referer = wp_get_referer();
        $url = is_string($referer) && '' !== $referer ? $referer : home_url('/');
        $url = remove_query_arg(['udc_status', 'udc_piercing_status', 'udc_result'], $url);
        wp_safe_redirect(add_query_arg([$key => $status, 'udc_result' => $expires . '.' . $signature], $url), 303);
        exit;
    }

    public static function health_fields()
    {
        return ['health_good' => 'health_good', 'health_treatment' => 'health_treatment', 'health_blood_thinners' => 'health_blood', 'health_allergies' => 'health_allergies', 'health_pregnant' => 'health_pregnant'];
    }

    public static function render_health_fields($translate, $prefix = 'udc_')
    {
        foreach (self::health_fields() as $field => $label) {
            echo '<p><label for="' . esc_attr($prefix . $field) . '">' . esc_html(self::field_label($translate($label))) . '</label><br>';
            echo '<select id="' . esc_attr($prefix . $field) . '" name="udc_' . esc_attr($field) . '" required>';
            foreach (['' => 'answer_choose', '1' => 'answer_yes', '0' => 'answer_no', '2' => 'answer_discuss'] as $value => $key) {
                echo '<option value="' . esc_attr($value) . '">' . esc_html($translate($key)) . '</option>';
            }
            echo '</select></p>';
        }
    }

    public static function health_answer($value)
    {
        return UDC_i18n::translate('1' === (string) $value ? 'answer_yes' : ('0' === (string) $value ? 'answer_no' : 'answer_discuss'));
    }

    /** Normalize the visible required marker independently of existing translated wording. */
    public static function field_label($text, $required = true)
    {
        return rtrim(trim($text), "* \t\n\r\0\x0B") . ($required ? ' *' : '');
    }

    public function handle_submission()
    {
        $this->save_submission(false);
    }

    protected function save_submission($is_piercing)
    {
        $nonce_action = $is_piercing ? 'udc_piercing_action' : 'udc_form_action';
        if (!isset($_POST['udc_form_nonce']) || !is_scalar($_POST['udc_form_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['udc_form_nonce'])), $nonce_action)) {
            wp_die('Security check failed.', 'Error', ['response' => 403]);
        }

        $fields = ['last_name', 'first_name', 'dob', 'address', 'city', 'zip', 'phone', 'appointment_date', 'appointment_time', 'piercing_location', 'liability_accepted', 'health_good', 'health_treatment', 'health_blood_thinners', 'health_allergies', 'health_pregnant'];
        foreach ($fields as $field) {
            $key = 'udc_' . $field;
            if (array_key_exists($key, $_POST) && !is_scalar($_POST[$key])) {
                $this->redirect_error($is_piercing);
            }
        }

        $last_name = $this->sanitize_request_value('udc_last_name');
        $first_name = $this->sanitize_request_value('udc_first_name');
        $dob = $this->sanitize_request_value('udc_dob');
        $address = $this->sanitize_request_value('udc_address');
        $city = $this->sanitize_request_value('udc_city');
        $zip = $this->sanitize_request_value('udc_zip');
        $phone = $this->sanitize_request_value('udc_phone');
        $appointment_date = $this->sanitize_request_value('udc_appointment_date');
        $appointment_time = $this->sanitize_request_value('udc_appointment_time');
        $piercing_location = $this->sanitize_request_value('udc_piercing_location');

        if (!array_key_exists('udc_liability_accepted', $_POST) || !is_string($_POST['udc_liability_accepted']) || '1' !== wp_unslash($_POST['udc_liability_accepted'])) {
            $this->redirect_error($is_piercing);
        }
        $liability_accepted = 1;

        $health_values = [];
        foreach (array_keys(self::health_fields()) as $field) {
            $key = 'udc_' . $field;
            if (!isset($_POST[$key]) || !is_string($_POST[$key]) || !in_array(wp_unslash($_POST[$key]), ['0', '1', '2'], true)) {
                $this->redirect_error($is_piercing);
            }
            $health_values[$field] = (int) wp_unslash($_POST[$key]);
        }
        $health_good = $health_values['health_good'];
        $health_treatment = $health_values['health_treatment'];
        $health_blood_thinners = $health_values['health_blood_thinners'];
        $health_allergies = $health_values['health_allergies'];
        $health_pregnant = $health_values['health_pregnant'];

        $appointment_time = $this->normalize_time($appointment_time);
        $required = [$last_name, $first_name, $dob, $phone, $appointment_date, $appointment_time, $piercing_location, $liability_accepted];
        if (in_array('', $required, true) || !$this->valid_date($dob) || !$this->valid_date($appointment_date) || $dob < '1000-01-01' || $appointment_date < '1000-01-01' || $dob > current_time('Y-m-d') || $dob > $appointment_date || false === $appointment_time || !$this->within_length($last_name, 255) || !$this->within_length($first_name, 255) || !$this->within_length($address, 255) || !$this->within_length($city, 255) || !$this->within_length($zip, 50) || !$this->within_length($phone, 50) || !$this->within_length($piercing_location, 255)) {
            $this->redirect_error($is_piercing);
        }

        $extra = $is_piercing ? UDC_Piercing_Form::submission_evidence() : self::submission_evidence();
        if (is_wp_error($extra)) { $this->redirect_error($is_piercing); }

        $attempt = $this->consume_submission_attempt();
        if (0 === $attempt) {
            wp_die(esc_html($is_piercing ? UDC_Piercing_Form::translate('msg_error') : UDC_i18n::translate('msg_error')), 'Error', ['response' => 429]);
        }
        if (false === $attempt) {
            $this->redirect_error($is_piercing);
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
                'city' => $city,
                'zip' => $zip,
                'phone' => $phone,
                'health_good' => $health_good,
                'health_treatment' => $health_treatment,
                'health_blood_thinners' => $health_blood_thinners,
                'health_allergies' => $health_allergies,
                'health_pregnant' => $health_pregnant,
                'liability_accepted' => $liability_accepted,
                'appointment_date' => $appointment_date,
                'appointment_time' => $appointment_time,
                'piercing_location' => $piercing_location
            ] + $extra,
            [
                '%s',
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
                '%s', '%s', '%s', '%s', '%s', '%s'
            ]
        );

        if (false === $inserted) {
            error_log('UDC_SUBMISSION_INSERT_FAILED');
        }

        // 5. Redirect based on result
        $status = $inserted ? 'success' : 'error';
        $this->redirect_result($status, $is_piercing);
    }

    private function sanitize_request_value($key)
    {
        return isset($_POST[$key]) && is_scalar($_POST[$key]) ? sanitize_text_field(wp_unslash((string) $_POST[$key])) : '';
    }

    private function redirect_error($is_piercing = false)
    {
        $this->redirect_result('error', $is_piercing);
    }

    private function within_length($value, $limit)
    {
        return UDC_Validation::within_length($value, $limit);
    }

    private function valid_date($value)
    {
        return UDC_Validation::valid_date($value);
    }

    private function normalize_time($value)
    {
        return UDC_Validation::normalize_time($value);
    }

    private function consume_submission_attempt()
    {
        $limit = (int) apply_filters('udc_submission_rate_limit', 5);
        $window = (int) apply_filters('udc_submission_rate_window', 10 * MINUTE_IN_SECONDS);
        $address = isset($_SERVER['REMOTE_ADDR']) && is_scalar($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        if ($limit < 1 || $window < 1 || '' === $address) {
            return false;
        }

        $key = 'udc_submission_' . hash_hmac('sha256', $address, wp_salt('auth'));
        $state = get_transient($key);
        if (false !== $state && (!is_array($state) || !isset($state['count']) || !is_int($state['count']))) {
            return false;
        }
        $count = false === $state ? 0 : $state['count'];
        if ($count >= $limit) {
            return 0;
        }
        $new_state = ['count' => $count + 1];
        if (!set_transient($key, $new_state, $window) || get_transient($key) !== $new_state) {
            return false;
        }
        return 1;
    }
}

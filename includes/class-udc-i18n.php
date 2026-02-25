<?php
if (!defined('ABSPATH')) {
    exit;
}

class UDC_i18n
{
    public function __construct()
    {
        add_action('init', [$this, 'register_strings']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }

    public function load_textdomain()
    {
        load_plugin_textdomain(
            'user-data-collection',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    public function register_strings()
    {
        if (!function_exists('pll_register_string')) {
            return;
        }

        $strings = self::get_strings();

        foreach ($strings as $key => $string) {
            $multiline = (strlen($string) > 100) ? true : false;
            // Register string with Polylang
            pll_register_string('udc_' . $key, $string, 'UDC Contact Form', $multiline);
        }
    }

    public static function translate($string_key)
    {
        $strings = self::get_strings();

        $default_string = isset($strings[$string_key]) ? $strings[$string_key] : $string_key;

        // If Polylang function is available, use it to get the translated string
        if (function_exists('pll__')) {
            return pll__($default_string);
        }

        // Fallback to standard WordPress translation
        return __($default_string, 'user-data-collection');
    }

    public static function get_strings()
    {
        return [
            'msg_success' => 'Thank you! Your request has been received.',
            'msg_error' => 'There was an error processing your request. Please try again.',

            'title_personal_info' => 'PERSONAL DATA',
            'label_last_name' => 'Last Name *',
            'label_first_name' => 'First Name *',
            'label_dob' => 'Date of Birth *',
            'label_address' => 'Address *',
            'label_city' => 'Zip Code, City *',
            'label_phone' => 'Phone *',

            'title_health' => 'HEALTH QUESTIONNAIRE',
            'health_good' => 'I am currently in good health.',
            'health_treatment' => 'I am undergoing medical or dental treatment.',
            'health_blood' => 'I regularly take blood-thinning medications, such as Sintrom or aspirin (or suffer from frequent blistering).',
            'health_allergies' => 'I am prone to allergies, latex, or others.',
            'health_pregnant' => 'I am pregnant or breastfeeding.',

            'title_liability' => 'Liability Clause',
            'liability_text' => 'I declare I am aware that minors cannot get a tattoo/piercing without the presence of a parent. I accept that Titanium Shop is subject to the strictest hygiene rules: sterile single-use needles and sterilization equipment meeting standards and approved by EYECO.ch. I assume full responsibility for the hygiene and care of my piercing/tattoo outside the shop; therefore, I release the studio from any liability for any consequences following the procedure (infection, rejection, allergy, etc.). I waive all legal or criminal actions. I acknowledge that if I deliberately conceal information regarding my age or nature that endangers my health or that of the staff, legal action could be taken against me. *',

            'title_appointment_info' => 'APPOINTMENT DETAILS',
            'label_appt_date' => 'Date of appointment *',
            'label_appt_time' => 'Time *',
            'label_location' => 'Piercing location *',

            'subtitle_care' => 'PIERCING CARE INSTRUCTIONS',
            'care_1' => 'Regular cleaning: Always wash your hands thoroughly before touching the piercing.',
            'care_2' => 'Avoid irritating products: Do not use alcohol or hydrogen peroxide. These products are too aggressive and can delay healing. Do not use scented soaps; use mild, unscented soaps.',
            'care_3' => 'Minimal handling: Avoid touching the piercing. Do not rotate the jewelry except to clean it. Wear loose clothing so as not to irritate the area.',
            'care_4' => 'Daily hygiene: Avoid tight clothing for body piercings (navel, nipple). Wear cotton clothing to let the area breathe.',
            'care_5' => 'Monitoring for signs of infection: Redness, pain, heat, yellow or green discharge. If you notice these signs, consult a healthcare professional. An infection must be treated quickly.',
            'care_6' => 'Healing time: Healing time varies depending on the location of the piercing. Earlobe piercings usually take 6 to 8 weeks, while cartilage, nose, or navel piercings can take several months.',

            'submit_btn' => 'Submit'
        ];
    }
}

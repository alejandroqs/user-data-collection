<?php
if (!defined('ABSPATH')) { exit; }

/** Shared guardian authorization copy, media validation and settings. */
final class UDC_Guardian
{
    public static function languages()
    {
        return ['fr' => 'French', 'de' => 'German', 'it' => 'Italian', 'en' => 'English', 'es' => 'Spanish'];
    }

    public static function get_strings()
    {
        return [
            'minor' => 'I am under 18 years old',
            'notice' => 'Minors must come to the appointment with the paper authorization completed and signed by their legal guardian.',
            'download' => 'Download the legal guardian authorization template',
            'missing' => 'The authorization template is not available in this language. Contact the studio before your appointment to obtain it.',
            'error' => 'Your date of birth indicates that you are under 18, but you have not checked "I am under 18 years old". Check the box or correct your date of birth.',
            'general' => 'General',
            'title' => 'Legal guardian authorization',
            'description' => 'Select one blank authorization template per language, shared by the tattoo and piercing forms. Minors must bring the printed document completed and signed by their legal guardian. Accepted formats: PDF, JPG, JPEG, PNG, WebP and AVIF.',
            'select' => 'Select or change document',
            'remove' => 'Remove selection',
            'empty' => 'No document selected.',
            'invalid' => 'The document was not saved. Select a valid PDF, JPG, JPEG, PNG, WebP or AVIF attachment.',
            'declared' => 'Declared under 18',
            'is_minor' => 'Is a minor',
            'unknown' => 'Not recorded',
            'design_help' => 'The default design is optimized for white pages and mobile devices. Enable custom design to use the configured colors instead.',
        ] + self::languages();
    }

    public static function translate($key)
    {
        $strings = self::get_strings();
        $text = $strings[$key] ?? $key;
        if (function_exists('pll__')) {
            $translated = pll__($text);
            if ($translated !== $text) { return $translated; }
        }
        return __($text, 'user-data-collection');
    }

    /** Prefer an explicit saved declaration, including zero; derive age only for missing history. */
    public static function record_is_minor($record)
    {
        if (isset($record->minor_declared) && in_array($record->minor_declared, [0, 1, '0', '1'], true)) {
            return 1 === (int) $record->minor_declared;
        }
        $dob = isset($record->dob) && is_string($record->dob) ? $record->dob : '';
        $today = current_time('Y-m-d');
        if (!UDC_Validation::valid_date($dob) || $dob < '1000-01-01' || $dob > $today) { return null; }
        return UDC_Validation::is_minor($dob, $today);
    }

    public static function badge($record)
    {
        $minor = self::record_is_minor($record);
        $state = null === $minor ? 'unknown' : ($minor ? 'yes' : 'no');
        $label = null === $minor ? self::translate('unknown') : UDC_i18n::translate($minor ? 'answer_yes' : 'answer_no');
        return '<span class="udc-minority-badge udc-minority-' . $state . '">' . esc_html($label) . '</span>';
    }

    public static function register_strings()
    {
        if (function_exists('pll_register_string')) {
            foreach (self::get_strings() as $key => $text) {
                pll_register_string('udc_guardian_' . $key, $text, 'UDC Guardian', strlen($text) > 100);
            }
        }
    }

    public static function language()
    {
        $language = function_exists('pll_current_language') ? pll_current_language('locale') : false;
        $language = $language ?: (function_exists('pll_current_language') ? pll_current_language('slug') : false);
        $language = strtolower(substr(str_replace('-', '_', $language ?: get_locale()), 0, 2));
        return array_key_exists($language, self::languages()) ? $language : '';
    }

    public static function valid_attachment($id)
    {
        if (!is_int($id) || $id < 1 || 'attachment' !== get_post_type($id) || 'trash' === get_post_status($id)) { return false; }
        $url = wp_get_attachment_url($id);
        if (!$url) { return false; }
        $types = ['pdf' => 'application/pdf', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'avif' => 'image/avif'];
        $type = wp_check_filetype((string) parse_url($url, PHP_URL_PATH), $types);
        return !empty($type['type']) && $type['type'] === get_post_mime_type($id);
    }

    public static function register_settings()
    {
        foreach (self::languages() as $language => $label) {
            $option = 'udc_guardian_document_' . $language;
            register_setting('udc_general_settings', $option, ['type' => 'integer', 'default' => 0,
                'sanitize_callback' => function ($value) use ($option) {
                    if ('' === $value || '0' === $value || 0 === $value) { return 0; }
                    $id = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
                    if (false !== $id && self::valid_attachment($id)) { return $id; }
                    add_settings_error($option, 'invalid_guardian_document', self::translate('invalid'), 'error');
                    return (int) get_option($option, 0);
                }]);
        }
    }

    public static function enqueue_admin($hook)
    {
        if (!current_user_can('manage_options') || !isset($_GET['page']) || 'udc-settings' !== $_GET['page'] || (isset($_GET['tab']) && 'general' !== $_GET['tab'])) { return; }
        wp_enqueue_media();
        wp_enqueue_script('udc-guardian-settings', UDC_PLUGIN_URL . 'assets/forms/settings.js', ['media-views'], '1.13.1', true);
    }

    public static function render_settings()
    {
        if (!current_user_can('manage_options')) { return; }
        echo '<form method="post" action="options.php">';
        settings_fields('udc_general_settings');
        echo '<h2>' . esc_html(self::translate('title')) . '</h2><p>' . esc_html(self::translate('description')) . '</p><table class="form-table">';
        foreach (self::languages() as $language => $label) {
            $option = 'udc_guardian_document_' . $language;
            $id = (int) get_option($option, 0);
            $url = self::valid_attachment($id) ? wp_get_attachment_url($id) : '';
            echo '<tr><th scope="row"><label for="' . esc_attr($option) . '">' . esc_html(self::translate($language)) . '</label></th><td class="udc-media-setting">';
            echo '<input type="hidden" id="' . esc_attr($option) . '" name="' . esc_attr($option) . '" value="' . esc_attr($id) . '">';
            echo '<p class="udc-media-name" aria-live="polite">' . esc_html($url ? basename((string) parse_url($url, PHP_URL_PATH)) : self::translate('empty')) . '</p>';
            echo '<button type="button" class="button udc-media-select" data-title="' . esc_attr(self::translate('select')) . '">' . esc_html(self::translate('select')) . '</button> ';
            echo '<button type="button" class="button udc-media-remove" data-empty="' . esc_attr(self::translate('empty')) . '">' . esc_html(self::translate('remove')) . '</button></td></tr>';
        }
        echo '</table>';
        submit_button();
        echo '</form>';
    }

    public static function render($prefix)
    {
        $id = $prefix . 'minor';
        $language = self::language();
        $attachment = $language ? (int) get_option('udc_guardian_document_' . $language, 0) : 0;
        echo '<div class="udc-minor" data-today="' . esc_attr(current_time('Y-m-d')) . '" data-error="' . esc_attr(self::translate('error')) . '">';
        echo '<p><label for="' . esc_attr($id) . '"><input type="checkbox" id="' . esc_attr($id) . '" name="udc_minor_declared" value="1" aria-controls="' . esc_attr($id . '-notice') . '" aria-describedby="' . esc_attr($id . '-error') . '"> ' . esc_html(self::translate('minor')) . '</label></p>';
        echo '<p id="' . esc_attr($id . '-error') . '" class="udc-minor-error" role="alert"></p>';
        // Visible without JavaScript; the script progressively enhances the disclosure.
        echo '<div id="' . esc_attr($id . '-notice') . '" class="udc-minor-notice"><p>' . esc_html(self::translate('notice')) . '</p>';
        if (self::valid_attachment($attachment)) {
            $url = add_query_arg(['action' => 'udc_guardian_download', 'language' => $language], admin_url('admin-post.php'));
            echo '<p><a class="udc-download" href="' . esc_url($url) . '">' . esc_html(self::translate('download')) . '</a></p>';
        } else {
            echo '<p>' . esc_html(self::translate('missing')) . '</p>';
        }
        echo '</div></div>';
    }

    public static function download()
    {
        $language = isset($_GET['language']) && is_string($_GET['language']) ? wp_unslash($_GET['language']) : '';
        $id = array_key_exists($language, self::languages()) ? (int) get_option('udc_guardian_document_' . $language, 0) : 0;
        $file = self::valid_attachment($id) ? get_attached_file($id) : false;
        if (!$file || !is_file($file) || !is_readable($file)) { wp_die(esc_html(self::translate('missing')), '', ['response' => 404]); }
        nocache_headers();
        header('Content-Type: ' . get_post_mime_type($id));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="guardian-authorization-' . $language . '.' . strtolower(pathinfo($file, PATHINFO_EXTENSION)) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

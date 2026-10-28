<?php
if (!defined('ABSPATH')) { exit; }

final class UDC_QR_Poster
{
    public static function defaults()
    {
        return ['udc_qr_background' => '#550000', 'udc_qr_text' => '#ffffff', 'udc_qr_color' => '#000000',
            'udc_qr_tattoo_url' => 'https://titaniumlausanne.ch/fr/formulaire-de-rendez-vous-pour-un-tatouage/',
            'udc_qr_piercing_url' => 'https://titaniumlausanne.ch/fr/formulaire-de-rendez-vous-pour-un-piercing/'];
    }

    public static function sanitize($value, $key, $default)
    {
        if (is_string($value) && trim($value) === '') { return $default; }
        $valid = is_string($value) && (substr($key, -4) === '_url'
            ? strlen($value) <= 1000 && filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) && !parse_url($value, PHP_URL_USER) && !parse_url($value, PHP_URL_PASS)
            : preg_match('/^#[a-fA-F0-9]{6}$/D', $value));
        if ($valid) { return $value; }
        add_settings_error($key, 'invalid_qr', __('Invalid QR image setting.', 'user-data-collection'));
        return get_option($key, $default);
    }

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue()
    {
        $page = $_GET['page'] ?? '';
        if (!current_user_can('manage_options') || !is_string($page) || !in_array($page, ['udc-submissions', 'udc-piercings', 'udc-settings'], true)) { return; }
        wp_enqueue_script('udc-qr-library', UDC_PLUGIN_URL . 'assets/qr/qrcode.js', [], '1.4.4', true);
        wp_enqueue_script('udc-qr-poster', UDC_PLUGIN_URL . 'assets/qr/poster.js', ['udc-qr-library'], UDC_DB_VERSION, true);
        $options = [];
        foreach (self::defaults() as $key => $default) { $options[$key] = get_option($key, $default); }
        $options['contrast'] = __('Save changes before downloading. Use a dark QR color on its white panel.', 'user-data-collection');
        $options['error'] = __('QR image could not be generated.', 'user-data-collection');
        wp_add_inline_script('udc-qr-poster', 'window.udcQR = ' . wp_json_encode($options) . ';', 'before');
    }

    public static function button($type)
    {
        echo '<button type="button" class="page-title-action udc-qr-download" data-service="' . esc_attr($type) . '">' . esc_html__('Download QR', 'user-data-collection') . '</button><span class="udc-qr-status" role="status"></span>';
    }

    public static function settings()
    {
        $labels = ['Background color', 'Text color', 'QR color', 'Tattoo page URL', 'Piercing page URL'];
        echo '<form method="post" action="options.php">';
        settings_fields('udc_qr_settings');
        echo '<table class="form-table">';
        foreach (self::defaults() as $key => $default) {
            $label = array_shift($labels);
            echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html__($label, 'user-data-collection') . '</label></th><td><input class="regular-text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" type="' . (substr($key, -4) === '_url' ? 'url' : 'color') . '" value="' . esc_attr(get_option($key, $default)) . '"></td></tr>';
        }
        echo '</table>';
        submit_button();
        echo '</form><p>' . esc_html__('Save changes before downloading. Use a dark QR color on its white panel.', 'user-data-collection') . '</p>';
        foreach (['tattoo', 'piercing'] as $type) { echo '<canvas class="udc-qr-preview" data-service="' . esc_attr($type) . '" style="width:210px;max-width:100%;height:auto;margin:12px" aria-label="' . esc_attr($type) . '"></canvas>'; }
    }
}

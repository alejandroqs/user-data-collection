<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Piercing-specific copy, resources and evidence; shared submission validation lives in UDC_Shortcode. */
class UDC_Piercing_Form extends UDC_Shortcode
{
    const FORM_VERSION = '1.0';
    private static $download_language = '';

    public function __construct()
    {
        add_shortcode('udc_piercing_form', [$this, 'render_form']);
        add_action('admin_post_udc_submit_piercing', [$this, 'handle_submission']);
        add_action('admin_post_nopriv_udc_submit_piercing', [$this, 'handle_submission']);
        add_action('admin_post_udc_piercing_care', [$this, 'download_care']);
        add_action('admin_post_nopriv_udc_piercing_care', [$this, 'download_care']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'register_strings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    public static function get_strings()
    {
        return [
            'title' => 'Consentement pour un piercing',
            'intro' => 'Tous les champs et toutes les réponses sont obligatoires. Complétez ce formulaire avant la séance. Le professionnel vérifiera vos réponses avec vous avant le geste. Une réponse incertaine doit être discutée sur place.',
            'title_personal_info' => 'DONNÉES PERSONNELLES',
            'label_last_name' => 'Nom *', 'label_first_name' => 'Prénom *',
            'label_dob' => 'Date de naissance *', 'label_address' => 'Adresse *',
            'label_city' => 'Ville *', 'label_zip' => 'Code postal *', 'label_phone' => 'Téléphone *',
            'title_health' => 'QUESTIONNAIRE DE SANTÉ',
            'health_good' => 'Je suis actuellement en bonne santé.',
            'health_treatment' => 'Je suis un traitement médical ou dentaire.',
            'health_blood' => 'Je prends un traitement qui fluidifie le sang.',
            'health_allergies' => 'Je présente des allergies connues, notamment aux métaux ou au latex.',
            'health_pregnant' => 'Je suis enceinte ou j’allaite.',
            'answer_choose' => 'Choisissez une réponse', 'answer_yes' => 'Oui', 'answer_no' => 'Non',
            'answer_discuss' => 'Je ne sais pas / à discuter sur place',
            'title_appointment_info' => 'DÉTAILS DU PIERCING',
            'label_appt_date' => 'Date de la séance *', 'label_appt_time' => 'Heure *',
            'category' => 'Catégorie de soins *',
            'label_location' => 'Emplacement exact et côté (précisez chaque perforation) *',
            'subtitle_care' => 'SOINS APRÈS LE PIERCING',
            'care_intro' => 'Consultez et téléchargez la fiche de la catégorie choisie. Pour plusieurs catégories, remplissez un formulaire par catégorie. Le professionnel précisera les soins et le suivi adaptés à votre situation.',
            'title_liability' => 'INFORMATION ET CONSENTEMENT',
            'liability_text' => 'Je demande et j’autorise la réalisation du piercing indiqué. Je déclare avoir répondu sincèrement et je signalerai toute information à vérifier avec le professionnel avant le geste.',
            'risk_text' => 'J’ai reçu des informations sur le geste, ses risques (infection, allergie, saignement, cicatrice, migration ou rejet ; lésions dentaires ou gingivales pour un piercing oral) et j’ai pu poser mes questions.',
            'care_ack' => 'J’ai lu les soins de la catégorie choisie et je sais comment les télécharger. Je comprends que la cicatrisation varie et qu’un suivi peut être nécessaire.',
            'privacy_text' => 'J’ai pris connaissance du fait que ce formulaire transmet au studio mes données d’identité, de contact et mes réponses de santé pour préparer et documenter la prestation. Je peux contacter le studio pour toute question sur leur traitement.',
            'privacy_link' => 'Consulter la politique de confidentialité',
            'submit_btn' => 'Envoyer mon consentement',
            'msg_success' => 'Merci, votre formulaire a été enregistré. Le professionnel vérifiera les informations avec vous avant la séance.',
            'msg_error' => 'Le formulaire n’a pas pu être enregistré. Vérifiez les champs et les réponses, puis réessayez. Si la page est ancienne, rechargez-la.',
            'download' => 'Télécharger les soins', 'review' => 'Laisser un avis', 'website' => 'Nous découvrir', 'version' => 'Version',
            'lobe' => 'Oreille : lobe', 'cartilage' => 'Oreille : cartilage',
            'face' => 'Visage : nez, septum, sourcil', 'oral' => 'Bouche : langue, lèvre',
            'body' => 'Corps : nombril, téton',
            'care_base' => 'Lavez-vous les mains avant tout contact. Pour les parties externes, utilisez une solution saline stérile à 0,9 % destinée au lavage des plaies, sans additif. Séchez doucement avec une compresse propre à usage unique. Ne tournez pas le bijou et ne forcez pas le retrait des croûtes. Évitez les produits agressifs, le nettoyage excessif, les frottements et l’immersion pendant la cicatrisation. Demandez au professionnel les modalités de suivi et de changement de bijou.',
            'care_lobe' => 'Gardez les taies d’oreiller propres, évitez les accrochages et les pressions sur le bijou. Ne changez pas le bijou sans l’avis du professionnel pendant la cicatrisation.',
            'care_cartilage' => 'Évitez de dormir sur l’oreille percée et les pressions des casques ou écouteurs. Nettoyez les objets en contact avec l’oreille. Le confort en surface ne signifie pas que la cicatrisation est terminée.',
            'care_face' => 'Évitez le maquillage et les cosmétiques sur la zone. Faites attention aux lunettes, aux vêtements et aux serviettes pour ne pas accrocher le bijou.',
            'care_oral' => 'À l’intérieur de la bouche, maintenez une bonne hygiène dentaire et rincez à l’eau après les repas. Si vous utilisez un bain de bouche, choisissez-le sans alcool ni eau oxygénée et évitez les usages excessifs. La solution saline pour plaies est réservée à la partie externe. Ne jouez pas avec le bijou. Faites contrôler sa longueur par le professionnel pour limiter les lésions des dents et des gencives.',
            'care_body' => 'Évitez les vêtements et les activités qui accrochent ou compriment la zone. Adaptez la protection avec le professionnel selon l’emplacement et votre activité.',
            'care_alert' => 'En cas de suspicion d’infection, de douleur ou de gonflement qui s’aggrave, consultez un professionnel de santé. Une difficulté à respirer ou à avaler nécessite une prise en charge urgente. Ne retirez pas vous-même le bijou en cas de suspicion d’infection ; demandez un avis médical.',
        ];
    }

    public function register_strings()
    {
        if (function_exists('pll_register_string')) {
            foreach (self::get_strings() as $key => $value) {
                pll_register_string('udc_piercing_' . $key, $value, 'UDC Piercing Form', strlen($value) > 100);
            }
        }
    }

    public static function translate($key)
    {
        $strings = self::get_strings();
        $text = $strings[$key] ?? $key;
        if ('' !== self::$download_language && function_exists('pll_translate_string')) {
            $translated = pll_translate_string($text, self::$download_language);
            if ($translated !== $text) { return $translated; }
        }
        if ('' === self::$download_language && function_exists('pll__')) {
            $translated = pll__($text);
            if ($translated !== $text) { return $translated; }
        }
        return __($text, 'user-data-collection');
    }

    public static function categories()
    {
        $categories = [];
        foreach (['lobe', 'cartilage', 'face', 'oral', 'body'] as $key) {
            $categories[$key] = self::translate($key);
        }
        return $categories;
    }

    public static function snapshot()
    {
        $consent = [];
        foreach (['liability_text', 'risk_text', 'care_ack', 'privacy_text'] as $key) {
            $consent[$key] = self::translate($key);
        }
        $care = ['base' => self::translate('care_base'), 'alert' => self::translate('care_alert')];
        foreach (self::categories() as $key => $label) {
            $care[$key] = $label . ': ' . self::translate('care_' . $key);
        }
        return ['version' => self::FORM_VERSION, 'language' => self::language(), 'consent' => $consent, 'care' => $care];
    }

    private static function language()
    {
        if ('' !== self::$download_language) { return self::$download_language; }
        $language = function_exists('pll_current_language') ? pll_current_language('slug') : false;
        return $language ?: get_locale();
    }

    public static function valid_evidence($json)
    {
        if (!is_string($json) || strlen($json) > 32000) { return false; }
        $data = json_decode($json, true, 5);
        if (!is_array($data) || array_keys($data) !== ['version', 'language', 'consent', 'care'] ||
            !is_string($data['version']) || !preg_match('/^[0-9]+\.[0-9]+$/D', $data['version']) ||
            !is_string($data['language']) || !preg_match('/^[A-Za-z0-9_@-]{1,32}$/D', $data['language'])) { return false; }
        foreach (['consent' => ['liability_text', 'risk_text', 'care_ack', 'privacy_text'], 'care' => ['base', 'alert', 'lobe', 'cartilage', 'face', 'oral', 'body']] as $group => $keys) {
            if (!is_array($data[$group]) || array_keys($data[$group]) !== $keys) { return false; }
            foreach ($data[$group] as $text) {
                if (!is_string($text) || '' === trim($text) || strlen($text) > 6000) { return false; }
            }
        }
        return $data;
    }

    public static function submission_evidence()
    {
        foreach (['risk_text', 'care_ack', 'privacy_text'] as $key) {
            if (!isset($_POST['udc_' . $key]) || !is_string($_POST['udc_' . $key]) || '1' !== wp_unslash($_POST['udc_' . $key])) {
                return new WP_Error('invalid_consent', 'Invalid piercing consent.');
            }
        }
        $json = isset($_POST['udc_evidence']) && is_string($_POST['udc_evidence']) ? wp_unslash($_POST['udc_evidence']) : '';
        $signature = isset($_POST['udc_evidence_signature']) && is_string($_POST['udc_evidence_signature']) ? wp_unslash($_POST['udc_evidence_signature']) : '';
        $category = isset($_POST['udc_piercing_category']) && is_string($_POST['udc_piercing_category']) ? wp_unslash($_POST['udc_piercing_category']) : '';
        $data = self::valid_evidence($json);
        if (!$data || self::FORM_VERSION !== $data['version'] || !array_key_exists($category, self::categories()) ||
            !hash_equals(hash_hmac('sha256', $json, wp_salt('nonce')), $signature)) {
            return new WP_Error('invalid_evidence', 'Invalid piercing form version or evidence.');
        }
        return ['form_type' => 'piercing', 'piercing_category' => $category, 'form_language' => $data['language'], 'form_version' => $data['version'], 'form_evidence' => $json];
    }

    public function enqueue_styles()
    {
        $enabled = get_option('udc_design_enabled', '0');
        $text = $enabled ? UDC_Settings::get_valid_color('udc_design_input_text', '#ffffff') : '#222222';
        $background = $enabled ? UDC_Settings::get_valid_color('udc_design_input_bg', 'transparent') : '#ffffff';
        $border = $enabled ? UDC_Settings::get_valid_color('udc_design_input_border', 'rgba(255,255,255,0.5)') : '#767676';
        wp_register_style('udc-piercing', false, [], '1.8.0');
        wp_enqueue_style('udc-piercing');
        wp_add_inline_style('udc-piercing', '.udc-piercing-form{max-width:760px;overflow-wrap:anywhere}.udc-piercing-form input:not([type=checkbox]):not([type=hidden]),.udc-piercing-form select{display:block;width:100%;max-width:100%;min-width:0;box-sizing:border-box;min-height:44px;padding:10px;font:inherit;background:' . $background . ';color:' . $text . ';border:1px solid ' . $border . '}.udc-piercing-form select option{background:#fff;color:#111}.udc-piercing-form :focus-visible{outline:2px solid currentColor;outline-offset:3px}.udc-piercing-form details{padding:12px 0;border-bottom:1px solid currentColor}.udc-piercing-form summary{cursor:pointer;min-height:32px}.udc-piercing-form .udc-resources{display:flex;flex-wrap:wrap;gap:16px}.udc-piercing-form button{min-height:44px;padding:10px 18px;font:inherit;cursor:pointer}.udc-piercing-form input[type=checkbox]{width:1.1em;height:1.1em;vertical-align:middle}');
    }

    public function render_form()
    {
        $prefix = wp_unique_id('udc-piercing-');
        $status = self::result_status(true);
        $website_url = self::website_url();
        $review_url = self::sanitize_url(get_option('udc_piercing_review_url', ''));
        $snapshot = self::snapshot();
        $json = wp_json_encode($snapshot);
        ob_start();
        ?>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="udc-piercing-form">
            <h2><?php echo esc_html(self::translate('title')); ?></h2>
            <p><?php echo esc_html(self::translate('intro')); ?></p>
            <?php if ('' !== $status): ?>
                <p role="status"><?php echo esc_html(self::translate('success' === $status ? 'msg_success' : 'msg_error')); ?></p>
            <?php endif; ?>
            <?php self::render_nonce('udc_piercing_action', $prefix); ?>
            <input type="hidden" name="action" value="udc_submit_piercing">
            <input type="hidden" name="udc_evidence" value="<?php echo esc_attr($json); ?>">
            <input type="hidden" name="udc_evidence_signature" value="<?php echo esc_attr(hash_hmac('sha256', $json, wp_salt('nonce'))); ?>">
            <h3><?php echo esc_html(self::translate('title_personal_info')); ?></h3>
            <?php foreach (['last_name' => 'text', 'first_name' => 'text', 'dob' => 'date', 'address' => 'text', 'city' => 'text', 'zip' => 'text', 'phone' => 'tel'] as $field => $type): ?>
                <p><label for="<?php echo esc_attr($prefix . $field); ?>"><?php echo esc_html(self::translate('label_' . $field)); ?></label>
                <input id="<?php echo esc_attr($prefix . $field); ?>" type="<?php echo esc_attr($type); ?>" name="udc_<?php echo esc_attr($field); ?>" maxlength="<?php echo in_array($field, ['zip', 'phone'], true) ? '50' : '255'; ?>" <?php if ('dob' === $field): ?> min="1000-01-01" max="<?php echo esc_attr(current_time('Y-m-d')); ?>"<?php endif; ?> required></p>
            <?php endforeach; ?>
            <h3><?php echo esc_html(self::translate('title_health')); ?></h3>
            <?php self::render_health_fields([self::class, 'translate'], $prefix); ?>
            <h3><?php echo esc_html(self::translate('title_appointment_info')); ?></h3>
            <?php foreach (['appointment_date' => ['date', 'label_appt_date'], 'appointment_time' => ['time', 'label_appt_time'], 'piercing_location' => ['text', 'label_location']] as $field => $config): ?>
                <p><label for="<?php echo esc_attr($prefix . $field); ?>"><?php echo esc_html(self::translate($config[1])); ?></label>
                <input id="<?php echo esc_attr($prefix . $field); ?>" type="<?php echo esc_attr($config[0]); ?>" name="udc_<?php echo esc_attr($field); ?>" maxlength="255" required></p>
            <?php endforeach; ?>
            <p><label for="<?php echo esc_attr($prefix . 'category'); ?>"><?php echo esc_html(self::translate('category')); ?></label>
            <select id="<?php echo esc_attr($prefix . 'category'); ?>" name="udc_piercing_category" required>
                <option value=""><?php echo esc_html(self::translate('answer_choose')); ?></option>
                <?php foreach (self::categories() as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select></p>
            <h3><?php echo esc_html(self::translate('subtitle_care')); ?></h3>
            <p><?php echo esc_html(self::translate('care_intro')); ?></p>
            <p><?php echo esc_html($snapshot['care']['base']); ?></p>
            <?php foreach (self::categories() as $key => $label): ?>
                <details><summary><?php echo esc_html($label); ?></summary>
                    <p><?php echo esc_html($snapshot['care'][$key]); ?></p>
                    <a href="<?php echo esc_url(add_query_arg(['action' => 'udc_piercing_care', 'category' => $key, 'language' => self::language()], admin_url('admin-post.php'))); ?>"><?php echo esc_html(self::translate('download') . ' — ' . $label); ?></a>
                </details>
            <?php endforeach; ?>
            <p><?php echo esc_html($snapshot['care']['alert']); ?></p>
            <h3><?php echo esc_html(self::translate('title_liability')); ?></h3>
            <?php foreach ($snapshot['consent'] as $key => $text): $field = 'liability_text' === $key ? 'liability_accepted' : $key; ?>
                <p><label><input type="checkbox" name="udc_<?php echo esc_attr($field); ?>" value="1" required> <?php echo esc_html($text); ?></label></p>
            <?php endforeach; ?>
            <?php if (get_privacy_policy_url()): ?>
                <p><a href="<?php echo esc_url(get_privacy_policy_url()); ?>"><?php echo esc_html(self::translate('privacy_link')); ?></a></p>
            <?php endif; ?>
            <p><button type="submit"><?php echo esc_html(self::translate('submit_btn')); ?></button></p>
            <p class="udc-resources">
                <a href="<?php echo esc_url($website_url); ?>"><?php echo esc_html(self::translate('website')); ?></a>
                <?php if ('' !== $review_url): ?>
                    <a href="<?php echo esc_url($review_url); ?>"><?php echo esc_html(self::translate('review')); ?></a>
                <?php endif; ?>
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handle_submission()
    {
        $this->save_submission(true);
    }

    public static function care_document($category)
    {
        if (!array_key_exists($category, self::categories())) { return false; }
        $data = self::snapshot();
        $title = self::translate('subtitle_care') . ' — ' . self::categories()[$category];
        $html = '<!doctype html><html lang="' . esc_attr(str_replace('_', '-', $data['language'])) . '"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($title) . '</title><style>body{max-width:760px;margin:32px auto;padding:0 20px;font:18px/1.6 system-ui;color:#222}h1{font-size:1.6em}</style><body><h1>' . esc_html($title) . '</h1>';
        foreach (['base', $category, 'alert'] as $key) { $html .= '<p>' . esc_html($data['care'][$key]) . '</p>'; }
        return $html . '<p>' . esc_html(self::translate('version') . ' ' . $data['version']) . '</p><p><a href="' . esc_url(home_url('/')) . '">' . esc_html(self::translate('website')) . '</a></p></body></html>';
    }

    public function download_care()
    {
        $category = isset($_GET['category']) && is_string($_GET['category']) ? wp_unslash($_GET['category']) : '';
        $language = isset($_GET['language']) && is_string($_GET['language']) ? wp_unslash($_GET['language']) : '';
        $languages = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'slug']) : array_merge(['en_US', get_locale()], get_available_languages());
        if (!is_array($languages) || !in_array($language, $languages, true)) { wp_die('Invalid language.', 'Error', ['response' => 400]); }
        self::$download_language = $language;
        $locale = $language;
        if (function_exists('pll_languages_list')) {
            $slugs = pll_languages_list(['fields' => 'slug']);
            $locales = pll_languages_list(['fields' => 'locale']);
            $index = is_array($slugs) ? array_search($language, $slugs, true) : false;
            if (false !== $index && is_array($locales) && isset($locales[$index])) { $locale = $locales[$index]; }
        }
        switch_to_locale($locale);
        $document = self::care_document($category);
        if (false === $document) { wp_die('Invalid category.', 'Error', ['response' => 400]); }
        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="piercing-care-' . $category . '.html"');
        echo $document;
        exit;
    }

    public function register_settings()
    {
        foreach (['udc_piercing_website_url', 'udc_piercing_review_url'] as $key) {
            register_setting('udc_piercing_settings', $key, ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_url'], 'default' => 'udc_piercing_website_url' === $key ? home_url('/') : '']);
        }
    }

    public static function sanitize_url($value)
    {
        if (!is_string($value) || !preg_match('~^https?://~i', trim($value))) { return ''; }
        $url = esc_url_raw(trim($value), ['http', 'https']);
        $parts = wp_parse_url($url);
        return is_array($parts) && !empty($parts['host']) ? $url : '';
    }

    public static function website_url()
    {
        return self::sanitize_url(get_option('udc_piercing_website_url', '')) ?: home_url('/');
    }

    public static function render_settings()
    {
        if (!current_user_can('manage_options')) { return; }
        echo '<p><code>[udc_piercing_form]</code></p><details><summary>' . esc_html__('Piercing links', 'user-data-collection') . '</summary>';
        echo '<form method="post" action="' . esc_url(admin_url('options.php')) . '">';
        settings_fields('udc_piercing_settings');
        foreach (['udc_piercing_website_url' => 'Website URL', 'udc_piercing_review_url' => 'Review URL (optional)'] as $key => $label) {
            echo '<p><label for="' . esc_attr($key) . '">' . esc_html__($label, 'user-data-collection') . '</label><br><input class="regular-text" type="url" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr(get_option($key, 'udc_piercing_website_url' === $key ? home_url('/') : '')) . '"></p>';
        }
        submit_button();
        echo '</form></details>';
    }
}

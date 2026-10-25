<?php
/** Synthetic behavior checks. Run: php tests/forms.php */
require __DIR__ . '/confirmation-removal.php';
function get_locale() { return $GLOBALS['locale'] ?? 'fr_FR'; }
function wp_json_encode($value) { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function home_url($path = '') { return 'https://example.test' . $path; }
function get_privacy_policy_url() { return 'https://example.test/privacy'; }
function wp_unique_id($prefix = '') { static $id = 0; return $prefix . ++$id; }
function wp_nonce_field($action, $name, $referer = true, $display = true) {
    $html = '<input type="hidden" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="synthetic-nonce">';
    if ($referer) { $html .= '<input type="hidden" id="_wp_http_referer" name="_wp_http_referer" value="/form">'; }
    if ($display) { echo $html; }
    return $html;
}
function add_menu_page(...$args) {}
function add_submenu_page(...$args) { $GLOBALS['submenus'][] = $args; }
function register_setting($group, $key, $args) { $GLOBALS['registered_settings'][$key] = $args; }
function get_available_languages() { return ['fr_FR']; }
function switch_to_locale($language) { $GLOBALS['locale'] = $language; return true; }
function nocache_headers() { $GLOBALS['nocache_calls'] = ($GLOBALS['nocache_calls'] ?? 0) + 1; }
function wp_register_script(...$args) {}
function wp_enqueue_script(...$args) {}
function wp_add_inline_script($handle, $script) { $GLOBALS['form_script'] = $script; }
function wp_register_style(...$args) {}
function wp_enqueue_style(...$args) {}
function wp_add_inline_style($handle, $css) { $GLOBALS['form_css'] = $css; }
function esc_url_raw($value, $protocols = []) { return preg_match('~^https?://~i', $value) ? $value : ''; }
function wp_die($message, $title = '', $args = []) { throw new RuntimeException('wp_die:' . ($args['response'] ?? 0)); }

$strings = UDC_i18n::get_strings();
foreach (['answer_choose', 'answer_yes', 'answer_no', 'answer_discuss'] as $key) { unset($strings[$key]); }
check(hash('sha256', serialize($strings)) === 'd92f05d6f65a14b66819aa3ea2905eb4e0def1f689cdd9a02938ae62e3a88cea', 'Every existing form string remains byte-for-byte unchanged');
$tattoo = new UDC_Shortcode();
$piercing = new UDC_Piercing_Form();
$_GET = [];
$tattoo_html = $tattoo->render_form();
check(substr_count($tattoo_html, '<select ') === 5 && !str_contains($tattoo_html, 'type="checkbox" name="udc_health_'), 'Existing form has five labelled health dropdowns');
check(substr_count($tattoo_html, 'value="2"') === 5 && substr_count($tattoo_html, 'value="0"') === 5, 'Each dropdown distinguishes No and Discuss');
$piercing_html = $piercing->render_form();
check(str_contains($piercing_html, 'value="udc_submit_piercing"') && substr_count($piercing_html, 'type="checkbox"') === 4, 'Piercing form has its own action and four explicit acknowledgements');
check(substr_count($piercing_html, '<details>') === 5 && substr_count($piercing_html, 'udc_piercing_care') === 5, 'Five care categories have accessible disclosures and downloads');
check(!str_contains($piercing_html, 'Laisser un avis'), 'Review link stays absent until a destination is configured');
$GLOBALS['options']['udc_piercing_review_url'] = 'https://example.test/review';
check(str_contains($piercing->render_form(), 'Laisser un avis'), 'Configured review link is optional and visible');
check(isset($GLOBALS['shortcodes']['udc_contact_form'], $GLOBALS['shortcodes']['udc_piercing_form']), 'Both independent shortcodes are registered');
check(isset($GLOBALS['hooks']['admin_post_nopriv_udc_submit_piercing'], $GLOBALS['hooks']['admin_post_nopriv_udc_piercing_care']), 'Public piercing submission and care download hooks are registered');

function form_post($piercing = false) {
    $post = ['udc_form_nonce' => 'synthetic-nonce'];
    foreach (synthetic_row() as $field => $value) { $post['udc_' . $field] = (string) $value; }
    $post['udc_health_allergies'] = '2';
    if ($piercing) {
        $json = wp_json_encode(UDC_Piercing_Form::snapshot());
        $post += ['udc_piercing_category' => 'oral', 'udc_evidence' => addslashes($json), 'udc_evidence_signature' => hash_hmac('sha256', $json, wp_salt('nonce')), 'udc_risk_text' => '1', 'udc_care_ack' => '1', 'udc_privacy_text' => '1'];
    }
    return $post;
}
function submit_form($object, $post) {
    global $wpdb;
    $wpdb = new SyntheticDatabase();
    $GLOBALS['transients'] = [];
    $_POST = $post;
    try { $object->handle_submission(); } catch (RedirectCaptured $redirect) { return [$redirect->getMessage(), $wpdb->inserts]; }
    return ['', $wpdb->inserts];
}
foreach ([false, true] as $is_piercing) {
    $object = $is_piercing ? $piercing : $tattoo;
    [$url, $inserts] = submit_form($object, form_post($is_piercing));
    check(str_contains($url, $is_piercing ? 'udc_piercing_status=success' : 'udc_status=success'), 'Valid form redirects with its own success parameter');
    $data = $inserts[0][1];
    check($data['health_allergies'] === 2 && $data['health_treatment'] === 0 && count($data) === count($inserts[0][2]), 'No and Discuss remain distinct with matching insert formats');
    check($data['form_type'] === ($is_piercing ? 'piercing' : 'tattoo'), 'The handler sets its own service type');
    foreach (['', '3', ['1'], null] as $bad_answer) {
        $post = form_post($is_piercing); $post['udc_health_allergies'] = $bad_answer;
        [$url, $inserts] = submit_form($object, $post);
        check(!$inserts && str_contains($url, 'error'), 'Missing, invalid and array health answers cannot be stored');
    }
    $post = form_post($is_piercing); unset($post['udc_health_good']);
    check(!submit_form($object, $post)[1], 'An omitted health answer is rejected');
}
$GLOBALS['expected_nonce_action'] = 'udc_form_action';
try { submit_form($piercing, form_post(true)); check(false, 'Cross-form nonce'); } catch (RuntimeException $error) { check($error->getMessage() === 'wp_die:403', 'Piercing validates its own nonce action'); }
unset($GLOBALS['expected_nonce_action']);
$post = form_post(true); $post['udc_form_type'] = 'tattoo';
[$url, $inserts] = submit_form($piercing, $post);
check($inserts[0][1]['form_type'] === 'piercing', 'A forged hidden service type cannot move a record');
$saved_piercing = $inserts[0][1] + ['id' => 9, 'created_at' => '2026-10-24 12:00:00'];
foreach (['udc_risk_text', 'udc_care_ack', 'udc_privacy_text', 'udc_liability_accepted', 'udc_piercing_category', 'udc_evidence_signature'] as $field) {
    $post = form_post(true); unset($post[$field]);
    check(!submit_form($piercing, $post)[1], 'Piercing rejects missing ' . $field);
}
$post = form_post(true); $post['udc_piercing_category'] = 'unsupported';
check(!submit_form($piercing, $post)[1], 'Unsupported care category is rejected');
$post = form_post(true); $post['udc_evidence'] = str_replace('infection', 'changed', $post['udc_evidence']);
check(!submit_form($piercing, $post)[1], 'Modified consent evidence fails its signature');
$post = form_post(true); $post['udc_form_nonce'] = 'wrong';
try { submit_form($piercing, $post); check(false, 'Nonce rejection'); } catch (RuntimeException $error) { check($error->getMessage() === 'wp_die:403', 'Piercing rejects invalid nonces before insertion'); }

$backup = new UDC_Backup();
$restored = invoke_private($backup, 'validate_backup_rows', [$saved_piercing]);
check(!is_wp_error($restored) && $restored[0]['health_allergies'] === 2 && $restored[0]['form_evidence'] === $saved_piercing['form_evidence'], 'Piercing backup retains unknown answers and the exact accepted text');
$legacy = synthetic_row(); $legacy['is_confirmed'] = 1; $legacy['zip_city'] = $legacy['city']; unset($legacy['city'], $legacy['zip']);
$normalized = invoke_private($backup, 'validate_backup_rows', [$legacy]);
$roundtrip = invoke_private($backup, 'validate_backup_rows', $normalized);
check(!is_wp_error($roundtrip) && $roundtrip[0]['zip'] === '' && $roundtrip[0]['form_type'] === 'legacy', 'Historical data survives normalization and a new-format restore without invented service classification');
foreach (['form_type' => 'invalid', 'form_evidence' => '{}', 'form_language' => 'mismatch', 'piercing_category' => 'unsupported', 'liability_accepted' => 2] as $field => $value) {
    $bad = $saved_piercing; $bad[$field] = $value;
    check(is_wp_error(invoke_private($backup, 'validate_backup_rows', [$bad])), 'Backup rejects invalid ' . $field);
}
$bad = synthetic_row(); $bad['zip'] = '';
check(is_wp_error(invoke_private($backup, 'validate_backup_rows', [$bad])), 'Untyped historical current-address backups still require a postal code');
$current_tattoo = invoke_private($backup, 'validate_backup_rows', [synthetic_row()]);
check(!is_wp_error(invoke_private($backup, 'validate_backup_rows', [$saved_piercing, $current_tattoo[0]])), 'A new-format snapshot supports both service types');
$wpdb = new SyntheticDatabase();
$added = invoke_private($backup, 'insert_backup_data', [$saved_piercing]);
check($added === 1 && count($wpdb->inserts[0][1]) === count($wpdb->inserts[0][2]), 'Piercing restore writes matching data and formats');

foreach ([false, true] as $is_piercing) {
    $table = new UDC_List_Table($is_piercing); $_GET = []; $_REQUEST = ['appt_filter' => 'upcoming'];
    $table->prepare_items();
    check(str_contains(end($wpdb->queries), $is_piercing ? "form_type = 'piercing'" : "form_type IN ('legacy', 'tattoo')"), 'List SQL isolates the selected service');
    $link = invoke_private($table, 'handle_row_actions', (object) synthetic_row(), 'last_name', 'last_name');
    check(str_contains($link, $is_piercing ? 'udc-piercings' : 'udc-submissions'), 'Detail links retain the correct service section');
}
$wpdb->columns = ['id']; $GLOBALS['options']['udc_db_version'] = '1.6.0';
UDC_Activator::activate();
check(get_option('udc_db_version') === '1.6.0', 'A failed form-column upgrade does not advance the schema version');
check(str_contains($GLOBALS['requested_schema'], "DEFAULT 'legacy'") && str_contains($GLOBALS['requested_schema'], 'form_evidence longtext'), 'Schema preserves unclassified history and includes evidence storage');
foreach (array_keys(UDC_Piercing_Form::categories()) as $key) {
    $document = UDC_Piercing_Form::care_document($key);
    check(str_contains($document, '<meta charset="utf-8">') && !str_contains($document, 'Synthetic') && !str_contains($document, '<script'), 'Care download is a standalone document without personal data or scripts');
}
check(UDC_Piercing_Form::care_document('../invalid') === false, 'Download rejects unsupported paths/categories');
check(UDC_Piercing_Form::sanitize_url('javascript:alert(1)') === '' && UDC_Piercing_Form::sanitize_url(['invalid']) === '', 'Link settings reject unsafe schemes and arrays');

$admin = new UDC_Admin();
$admin->add_admin_menu();
$menu = array_values(array_filter($GLOBALS['submenus'], fn($args) => $args[4] === 'udc-piercings'));
check(count($menu) === 1 && $menu[0][3] === 'manage_options', 'Piercing submenu uses the existing administrative capability');
$piercing->register_settings();
check(isset($GLOBALS['registered_settings']['udc_piercing_review_url']['sanitize_callback']), 'Piercing links use registered settings sanitization');
$GLOBALS['can_manage'] = false;
ob_start(); $admin->render_piercing_page(); $denied = ob_get_clean();
check($denied === '', 'Unauthorized users cannot render piercing administration');
$GLOBALS['can_manage'] = true;
class EvidenceDatabase extends SyntheticDatabase {
    public $record;
    public function get_row($sql) { $this->queries[] = $sql; return (object) $this->record; }
}
$wpdb = new EvidenceDatabase(); $wpdb->record = $saved_piercing;
(new ReflectionProperty($admin, 'is_piercing'))->setValue($admin, true);
ob_start(); invoke_private($admin, 'render_details_page', 9); $detail = ob_get_clean();
check(str_contains(end($wpdb->queries), "form_type = 'piercing'") && str_contains($detail, 'udc-piercings'), 'Direct piercing detail requests and back links retain the service boundary');
check(str_contains($detail, 'Accepted document') && str_contains($detail, 'Not sure / discuss in person') && str_contains($detail, 'fr_FR'), 'Piercing detail renders stored document, interface language and discussion answers');

if (in_array('--preview', $argv ?? [], true)) {
    $piercing->enqueue_styles();
    $tattoo->enqueue_form_behavior();
    $html = '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Synthetic consent preview</title><style>body{font:16px/1.6 system-ui;margin:24px auto;padding:0 16px;max-width:760px}input,select{max-width:100%;box-sizing:border-box}label{display:inline-block}h2{margin-top:40px}' . $GLOBALS['form_css'] . '</style><body>' . $tattoo_html . '<hr>' . $piercing_html . '<script>' . $GLOBALS['form_script'] . '</script></body></html>';
    file_put_contents(sys_get_temp_dir() . '/udc-forms-preview.html', $html);
    $GLOBALS['options']['udc_design_enabled'] = '1';
    file_put_contents(sys_get_temp_dir() . '/udc-forms-design-preview.html', '<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font:16px system-ui;background:#222;color:#fff;max-width:760px;margin:auto;padding:20px}</style><body>' . $tattoo->render_form() . $tattoo->render_form() . '</body></html>');
    $GLOBALS['options']['udc_design_enabled'] = '0';
    file_put_contents(sys_get_temp_dir() . '/udc-care-oral-preview.html', UDC_Piercing_Form::care_document('oral'));
}
echo "All form checks passed (synthetic WordPress/database doubles).\n";

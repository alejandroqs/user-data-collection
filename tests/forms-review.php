<?php
/** Review regressions with synthetic WordPress/database functions. */
require __DIR__ . '/forms.php';
function pll__($text) { return $GLOBALS['polylang'][$text] ?? $text; }
function pll_translate_string($text, $language) { return $GLOBALS['polylang_download'][$language][$text] ?? $text; }

$_GET = ['udc_status' => 'success', 'udc_piercing_status' => 'success'];
check(!str_contains($tattoo->render_form(), 'Thank you!') && !str_contains($piercing->render_form(), 'votre formulaire a été enregistré'), 'Changing a URL status alone cannot display a false success');
foreach ([false, true] as $is_piercing) {
    [$url, $inserts] = submit_form($is_piercing ? $piercing : $tattoo, form_post($is_piercing));
    parse_str(parse_url($url, PHP_URL_QUERY), $_GET);
    check(($GLOBALS['redirect_status'] ?? 0) === 303, 'Form results use a POST-to-GET 303 redirect');
    check(UDC_Shortcode::result_status($is_piercing) === 'success', 'A real insert creates a verifiable success receipt');
    check(UDC_Shortcode::result_status(!$is_piercing) === '', 'Result receipts cannot be moved to the other form');
    $key = $is_piercing ? 'udc_piercing_status' : 'udc_status';
    $_GET[$key] = 'error';
    check(UDC_Shortcode::result_status($is_piercing) === '', 'Modified result status invalidates the receipt');
    $_GET[$key] = 'success'; $_GET['udc_result'][12] = $_GET['udc_result'][12] === 'a' ? 'b' : 'a';
    check(UDC_Shortcode::result_status($is_piercing) === '', 'Modified receipt signatures are rejected');
    $expires = time() - 60;
    $_GET['udc_result'] = $expires . '.' . hash_hmac('sha256', $key . '|success|' . $expires, wp_salt('auth'));
    check(UDC_Shortcode::result_status($is_piercing) === '', 'Expired receipts are rejected');
    $_GET['udc_result'] = ['invalid'];
    check(UDC_Shortcode::result_status($is_piercing) === '', 'Array receipt parameters are ignored');
}
$_GET = ['udc_result' => 'synthetic'];
$tattoo->prevent_result_cache();
check(($GLOBALS['nocache_calls'] ?? 0) > 0, 'Result requests ask WordPress to send no-cache headers');
$GLOBALS['referer'] = 'https://example.test/form?udc_piercing_status=success&udc_result=old&lang=fr';
[$url] = submit_form($tattoo, form_post());
check(str_contains($url, 'lang=fr') && !str_contains($url, 'udc_piercing_status'), 'Redirects remove stale statuses and retain other page parameters');
$GLOBALS['referer'] = false;
[$url] = submit_form($tattoo, form_post());
check(str_starts_with($url, home_url('/')), 'A missing referer has a safe site-home fallback');
unset($GLOBALS['referer']);
$GLOBALS['fail_insert'] = true;
[$url, $inserts] = submit_form($tattoo, form_post());
parse_str(parse_url($url, PHP_URL_QUERY), $_GET);
check(!$inserts && UDC_Shortcode::result_status() === 'error', 'A failed database insert cannot create a success receipt');
unset($GLOBALS['fail_insert']);
$_GET = [];
$all_html = $tattoo->render_form() . $piercing->render_form() . $tattoo->render_form() . $piercing->render_form();
preg_match_all('/\bid="([^"]+)"/', $all_html, $matches);
check(count($matches[1]) === count(array_unique($matches[1])), 'Repeated forms have unique IDs including nonce and referer controls');
check(substr_count($all_html, 'name="udc_form_nonce"') === 4 && substr_count($all_html, 'name="udc_last_name"') === 4, 'Unique HTML IDs retain the original request field names');
foreach ([false, true] as $is_piercing) {
    $object = $is_piercing ? $piercing : $tattoo;
    foreach (['2999-01-01', '0000-01-01', '2026-02-30'] as $dob) {
        $post = form_post($is_piercing); $post['udc_dob'] = $dob;
        check(!submit_form($object, $post)[1], 'Future, year-zero and impossible birth dates are rejected');
    }
    $post = form_post($is_piercing); $post['udc_dob'] = '2000-01-01'; $post['udc_appointment_date'] = '1999-01-01';
    check(!submit_form($object, $post)[1], 'Birth cannot occur after the procedure date');
    $post = form_post($is_piercing); $post['udc_last_name'] = str_repeat('É', 255);
    [, $inserts] = submit_form($object, $post);
    check(count($inserts) === 1 && $inserts[0][1]['last_name'] === $post['udc_last_name'], 'A 255-character accented name fits the database character limit');
    $record = $inserts[0][1] + ['id' => 12, 'created_at' => '2026-10-24 12:00:00'];
    check(!is_wp_error(invoke_private($backup, 'validate_backup_rows', [$record])), 'Accented names accepted by submission also survive restore validation');
    $post['udc_last_name'] .= 'É';
    check(!submit_form($object, $post)[1], 'Names above the database character limit are rejected');
}
check(!UDC_Validation::within_length("\xff", 255), 'Invalid UTF-8 cannot pass character-length validation');
$GLOBALS['options']['udc_piercing_website_url'] = '';
check(UDC_Piercing_Form::care_data('oral')['website_url'] === home_url('/') && !str_contains($piercing->render_form(), 'href=""'), 'PDF website is derived automatically from WordPress');
foreach (['/relative', '//example.test', 'https://', 'https://legacy.example.test/', ['array']] as $url) {
    $GLOBALS['options']['udc_piercing_website_url'] = $url;
    check(UDC_Piercing_Form::care_data('oral')['website_url'] === home_url('/'), 'Legacy website values cannot override the automatic PDF URL');
}
$GLOBALS['options']['udc_piercing_review_url'] = 'javascript:alert(1)';
check(!str_contains($piercing->render_form(), 'Laisser un avis'), 'An invalid stored review destination is not rendered');
$GLOBALS['options']['udc_piercing_review_url'] = '';
$source = UDC_Piercing_Form::get_strings()['download'];
$GLOBALS['gettext'][$source] = 'Translated by gettext';
check(UDC_Piercing_Form::translate('download') === 'Translated by gettext', 'Missing Polylang strings fall back to gettext');
$GLOBALS['polylang'][$source] = 'Translated by Polylang';
check(UDC_Piercing_Form::translate('download') === 'Translated by Polylang', 'A provided Polylang translation keeps priority');
(new ReflectionProperty(UDC_Piercing_Form::class, 'download_language'))->setValue(null, 'en');
check(UDC_Piercing_Form::translate('download') === 'Translated by gettext', 'A download never inherits the frontend Polylang language');
$GLOBALS['polylang_download']['en'][$source] = 'Download in requested language';
check(UDC_Piercing_Form::translate('download') === 'Download in requested language', 'Downloads resolve explicit-language translations');
(new ReflectionProperty(UDC_Piercing_Form::class, 'download_language'))->setValue(null, '');
unset($GLOBALS['gettext'], $GLOBALS['polylang'], $GLOBALS['polylang_download']);
$wpdb = new EvidenceDatabase(); $wpdb->record = $saved_piercing;
$admin = new UDC_Admin();
foreach ([['1'], '-1', '1garbage', '0'] as $id) {
    $_GET = ['action' => 'view', 'id' => $id];
    ob_start(); $admin->render_admin_page(); $html = ob_get_clean();
    check(!$wpdb->queries && str_contains($html, 'Submission not found.'), 'Malformed administrative IDs cannot select another record');
}
$_GET = [];

foreach ([false, true] as $is_piercing) {
    $object = $is_piercing ? $piercing : $tattoo;
    foreach (['blank', 'omitted'] as $mode) {
        $post = form_post($is_piercing);
        foreach (['udc_address', 'udc_city', 'udc_zip'] as $field) {
            if ('blank' === $mode) { $post[$field] = ''; } else { unset($post[$field]); }
        }
        [$url, $inserts] = submit_form($object, $post);
        check(count($inserts) === 1 && !array_intersect(['address','city','zip'], array_keys($inserts[0][1])), 'Both handlers accept blank or omitted optional address/city/postal code');
        $row = $inserts[0][1] + ['id' => 91, 'created_at' => '2026-10-26 12:00:00'];
        $normalized = invoke_private($backup, 'validate_backup_rows', [$row]);
        check(!is_wp_error($normalized) && !array_intersect(['address','city','zip'], array_keys($normalized[0])), 'Retired contact data is absent after backup normalization');
        $wpdb = new SyntheticDatabase();
        check(invoke_private($backup, 'insert_backup_data', $normalized) === 1 && !array_intersect(['address','city','zip'], array_keys($wpdb->inserts[0][1])), 'Restore does not reintroduce retired contact fields');
    }
    foreach (['udc_address' => [str_repeat('a', 256), ['array']], 'udc_city' => [str_repeat('a', 256), ['array']], 'udc_zip' => [str_repeat('1', 51), ['array']]] as $field => $values) {
        foreach ($values as $value) {
            $post = form_post($is_piercing); $post[$field] = $value;
            check(count(submit_form($object, $post)[1]) === 1, 'Retired request fields are ignored, including stale and malformed values');
        }
    }
    $post = form_post($is_piercing); $post['udc_city'] = '';
    check(count(submit_form($object, $post)[1]) === 1, 'City can be blank in both handlers');
}
$wpdb = new EvidenceDatabase(); $wpdb->record = $saved_piercing;
$admin = new UDC_Admin();
(new ReflectionProperty($admin, 'is_piercing'))->setValue($admin, true);
ob_start(); invoke_private($admin, 'render_details_page', 9); $detail = ob_get_clean();
check(str_contains($detail, '<strong>Care category:</strong>') && str_contains($detail, esc_html(UDC_Piercing_Form::categories()['oral'])), 'Piercing detail explicitly labels the translated selected care category');
check(str_contains($detail, esc_html($saved_piercing['piercing_location'])) && str_contains($detail, 'Not sure / discuss in person'), 'Exact body location and selected health answers remain in details');
foreach (['udc-submissions', 'udc-piercings'] as $page) {
    $_GET = ['page' => $page]; $admin->enqueue_styles();
    check(str_contains($GLOBALS['form_css'], 'column-dob') && str_contains($GLOBALS['form_css'], 'white-space:nowrap'), 'Both admin tables receive nonwrapping date-heading styles');
}
$_GET = [];


// Tattoo evidence is immutable accepted copy, independent of current translations and versions.
$tattoo_post = form_post(false);
[$tattoo_url, $tattoo_inserts] = submit_form($tattoo, $tattoo_post);
$tattoo_record = $tattoo_inserts[0][1] + ['id' => 92, 'created_at' => '2026-10-26 12:00:00'];
$tattoo_snapshot = UDC_Shortcode::valid_evidence($tattoo_record['form_evidence']);
check($tattoo_snapshot && $tattoo_record['form_version'] === UDC_Shortcode::FORM_VERSION && $tattoo_snapshot['care']['version'] === UDC_Tattoo_Care_Document::VERSION, 'Tattoo stores its accepted document and independent form/care versions');
foreach (['udc_liability_accepted', 'udc_risk_text', 'udc_care_ack', 'udc_privacy_text'] as $field) {
    foreach (['missing', '0', '2', 'true', 'array'] as $mode) {
        $post = form_post(false);
        if ('missing' === $mode) { unset($post[$field]); } else { $post[$field] = 'array' === $mode ? ['1'] : $mode; }
        check(!submit_form($tattoo, $post)[1], 'Tattoo rejects absent or invalid explicit acceptance: ' . $field);
    }
}
check(substr_count($tattoo_html, 'type="checkbox"') === 5 && !str_contains($tattoo_html, 'waive all legal') && str_contains($tattoo_html, 'https://example.test/privacy'), 'Tattoo renders four acknowledgements, the minority declaration and a privacy link');
foreach (['liability_text', 'risk_text', 'care_ack', 'privacy_text'] as $key) {
    check(!empty($tattoo_snapshot['document'][$key]), 'Tattoo evidence includes accepted ' . $key);
}
check($tattoo_record['form_language'] === $tattoo_snapshot['language'] && $tattoo_record['form_evidence'] === wp_unslash($tattoo_post['udc_evidence']), 'Tattoo preserves the exact signed text and language');
check(isset($GLOBALS['shortcodes']['udc_tattoo_form'], $GLOBALS['shortcodes']['udc_contact_form']), 'New tattoo shortcode and compatibility alias both resolve');
foreach (['udc_evidence', 'udc_evidence_signature'] as $key) {
    foreach (['missing', 'array', 'invalid'] as $mode) {
        $post = form_post(false);
        if ('missing' === $mode) { unset($post[$key]); } else { $post[$key] = 'array' === $mode ? ['invalid'] : 'invalid'; }
        check(!submit_form($tattoo, $post)[1], 'Tattoo rejects missing, malformed and array document/signature fields');
    }
}
foreach (['form', 'care', 'text'] as $change) {
    $post = form_post(false); $data = json_decode(wp_unslash($post['udc_evidence']), true);
    if ('form' === $change) { $data['version'] = '0.9'; }
    elseif ('care' === $change) { $data['care']['version'] = '0.9'; }
    else { $data['document']['liability_text'] .= ' altered'; }
    $json = wp_json_encode($data); $post['udc_evidence'] = addslashes($json);
    if ('text' !== $change) { $post['udc_evidence_signature'] = hash_hmac('sha256', $json, wp_salt('nonce')); }
    check(!submit_form($tattoo, $post)[1], 'Tattoo rejects outdated form/care versions and edited signed text');
}
$post = form_post(false); $other = form_post(true);
$post['udc_evidence'] = $other['udc_evidence']; $post['udc_evidence_signature'] = $other['udc_evidence_signature'];
check(!submit_form($tattoo, $post)[1], 'Piercing evidence cannot be submitted as a tattoo document');
$post = form_post(true);
$post['udc_evidence'] = $tattoo_post['udc_evidence']; $post['udc_evidence_signature'] = $tattoo_post['udc_evidence_signature'];
check(!submit_form($piercing, $post)[1], 'Tattoo evidence cannot be submitted as a piercing document');
$normalized = invoke_private($backup, 'validate_backup_rows', [$tattoo_record]);
check(!is_wp_error($normalized) && $normalized[0]['form_evidence'] === $tattoo_record['form_evidence'], 'Tattoo accepted text survives backup normalization');
$wpdb = new SyntheticDatabase();
check(invoke_private($backup, 'insert_backup_data', $normalized) === 1 && $wpdb->inserts[0][1]['form_evidence'] === $tattoo_record['form_evidence'], 'Tattoo restore preserves exact accepted text');
foreach (['form_version' => '9.9', 'form_language' => 'invalid', 'piercing_category' => 'oral', 'liability_accepted' => 0, 'form_evidence' => '{}', 'form_type' => 'legacy'] as $field => $value) {
    $bad = $tattoo_record; $bad[$field] = $value;
    check(is_wp_error(invoke_private($backup, 'validate_backup_rows', [$bad])), 'Tattoo backup rejects inconsistent document metadata and service type');
}
$historical = $tattoo_record; $historical['form_language'] = ''; $historical['form_version'] = ''; $historical['form_evidence'] = '';
check(!is_wp_error(invoke_private($backup, 'validate_backup_rows', [$historical])), 'Historical tattoo records without document evidence remain restorable');
$source = UDC_i18n::get_strings()['tattoo_consent_procedure'];
$GLOBALS['gettext'][$source] = 'CURRENT REPLACEMENT TEXT';
$older = $tattoo_snapshot; $older['version'] = '0.9'; $older['care']['version'] = '0.8'; $older['document']['intro'] = 'HISTORICAL TATTOO INTRODUCTION';
$record = $tattoo_record; $record['form_version'] = '0.9'; $record['form_evidence'] = wp_json_encode($older);
$wpdb = new EvidenceDatabase(); $wpdb->record = $record;
$admin = new UDC_Admin();
ob_start(); invoke_private($admin, 'render_details_page', 92); $detail = ob_get_clean();
check(str_contains($detail, 'HISTORICAL TATTOO INTRODUCTION') && str_contains($detail, esc_html($tattoo_snapshot['document']['liability_text'])) && !str_contains($detail, 'CURRENT REPLACEMENT TEXT'), 'Tattoo details render stored historical text, not current translations');
check(str_contains($detail, 'Document version') && str_contains($detail, '0.9') && str_contains($detail, 'Care document version') && str_contains($detail, '0.8'), 'Tattoo details identify historical form and care versions separately');
foreach (['liability_text', 'risk_text', 'care_ack', 'privacy_text'] as $key) {
    check(str_contains($detail, esc_html($tattoo_snapshot['document'][$key])), 'Tattoo details show stored acceptance: ' . $key);
}
$wpdb->record = $historical;
ob_start(); invoke_private($admin, 'render_details_page', 92); $detail = ob_get_clean();
check(str_contains($detail, 'Document evidence is unavailable.'), 'Tattoo history without a snapshot is explicitly marked unavailable');
$wpdb->record = $record; $wpdb->record['form_version'] = '9.9';
ob_start(); invoke_private($admin, 'render_details_page', 92); $detail = ob_get_clean();
check(str_contains($detail, 'Document evidence is unavailable.'), 'A mismatched stored tattoo version is not displayed as valid evidence');
unset($GLOBALS['gettext'][$source]);

echo "All review checks passed (synthetic WordPress/database doubles).\n";

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
check(UDC_Piercing_Form::website_url() === home_url('/') && !str_contains($piercing->render_form(), 'href=""'), 'Clearing the website setting preserves a working home link');
foreach (['/relative', '//example.test', 'https://', 'javascript:alert(1)', ['array']] as $url) {
    check(UDC_Piercing_Form::sanitize_url($url) === '', 'Link settings require an absolute HTTP(S) destination with a host');
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
echo "All review checks passed (synthetic WordPress/database doubles).\n";

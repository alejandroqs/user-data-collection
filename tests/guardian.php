<?php
/** Shared guardian, age-boundary, settings and migration regressions using doubles. */
require __DIR__ . '/forms-review.php';
function get_post_type($id) { return $GLOBALS['media'][$id]['type'] ?? false; }
function get_post_status($id) { return $GLOBALS['media'][$id]['status'] ?? 'inherit'; }
function wp_get_attachment_url($id) { return $GLOBALS['media'][$id]['url'] ?? false; }
function get_post_mime_type($id) { return $GLOBALS['media'][$id]['mime'] ?? false; }
function wp_check_filetype($name, $types) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    foreach ($types as $extensions => $mime) { if (in_array($ext, explode('|', $extensions), true)) { return ['ext' => $ext, 'type' => $mime]; } }
    return ['ext' => false, 'type' => false];
}
function add_settings_error($option, $code, $message, $type) { $GLOBALS['setting_errors'][] = $code; }
function pll_current_language($field = 'slug') { return $GLOBALS['current_language'] ?? false; }

foreach ([['2008-10-24', '2026-10-24', false], ['2008-10-25', '2026-10-24', true], ['2008-10-23', '2026-10-24', false], ['2008-02-29', '2026-02-28', true], ['2008-02-29', '2026-03-01', false], ['2027-01-01', '2026-10-24', false], ['2008-02-30', '2026-10-24', false]] as $case) {
    check(UDC_Validation::is_minor($case[0], $case[1]) === $case[2], 'Calendar age boundary: ' . $case[0] . '/' . $case[1]);
}
foreach ([false, true] as $is_piercing) {
    $object = $is_piercing ? $piercing : $tattoo;
    $post = form_post($is_piercing); $post['udc_dob'] = '2010-01-01';
    [$url, $inserts] = submit_form($object, $post);
    check(!$inserts && str_contains($url, 'minor_error'), 'Both endpoints reject unchecked minor before insertion');
    parse_str(parse_url($url, PHP_URL_QUERY), $_GET);
    check(UDC_Shortcode::result_status($is_piercing) === 'minor_error', 'Minor result has a valid signed receipt');
    check(str_contains($object->render_form(), esc_html(UDC_Guardian::translate('error'))), 'Server rejection renders the specific translated message');
    $post['udc_minor_declared'] = '1';
    [$url, $inserts] = submit_form($object, $post);
    check(count($inserts) === 1 && $inserts[0][1]['minor_declared'] === 1, 'Explicit minority declaration is persisted');
    $row = $inserts[0][1] + ['id' => 111, 'created_at' => '2026-10-24 12:00:00'];
    $normalized = invoke_private($backup, 'validate_backup_rows', [$row]);
    check(!is_wp_error($normalized) && $normalized[0]['minor_declared'] === 1, 'Minor declaration survives normalization');
    $row['minor_declared'] = 2;
    check(is_wp_error(invoke_private($backup, 'validate_backup_rows', [$row])), 'Malformed stored minority declaration is rejected');
    foreach (['0', 'yes', ['1']] as $value) {
        $post['udc_minor_declared'] = $value;
        check(!submit_form($object, $post)[1], 'Malformed submitted minority declaration cannot pass');
    }
    $post = form_post($is_piercing);
    [$url, $inserts] = submit_form($object, $post);
    check(count($inserts) === 1 && $inserts[0][1]['minor_declared'] === 0, 'Adult can submit with an unchecked declaration');
}
$history = invoke_private($backup, 'validate_backup_rows', [synthetic_row()]);
check(!is_wp_error($history) && array_key_exists('minor_declared', $history[0]) && null === $history[0]['minor_declared'], 'History is unknown rather than a false minority declaration');
check(!array_intersect(['address', 'city', 'zip'], array_keys($history[0])), 'Historical contact fields are discarded on restore');

UDC_Guardian::register_settings();
foreach (['fr','de','it','en','es'] as $language) {
    $option = 'udc_guardian_document_' . $language;
    check(isset($GLOBALS['registered_settings'][$option]), 'Independent setting registered: ' . $language);
    $GLOBALS['options'][$option] = 9;
    $sanitize = $GLOBALS['registered_settings'][$option]['sanitize_callback'];
    check($sanitize('0') === 0 && $sanitize(['1']) === 9 && $sanitize('-1') === 9, 'Clear works; malformed selection preserves existing attachment');
}
foreach (['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','avif'=>'image/avif'] as $extension => $mime) {
    $GLOBALS['media'][7] = ['type'=>'attachment','url'=>'https://example.test/model.' . $extension,'mime'=>$mime];
    check(UDC_Guardian::valid_attachment(7), 'Allowed attachment: ' . $extension);
    check($sanitize('7') === 7, 'Valid attachment can be saved: ' . $extension);
}
foreach ([['type'=>'post'], ['mime'=>'text/html'], ['status'=>'trash'], ['url'=>'https://example.test/file.svg']] as $override) {
    $GLOBALS['media'][7] = $override + ['type'=>'attachment','url'=>'https://example.test/model.pdf','mime'=>'application/pdf'];
    check(!UDC_Guardian::valid_attachment(7), 'Invalid attachment is rejected');
}
$GLOBALS['media'][7] = ['type'=>'attachment','url'=>'https://example.test/model.pdf','mime'=>'application/pdf'];
foreach (['fr_FR'=>'fr','de_CH'=>'de','it_CH'=>'it','en_GB'=>'en','es_ES'=>'es'] as $locale => $language) {
    $GLOBALS['current_language'] = $locale;
    $GLOBALS['options']['udc_guardian_document_' . $language] = 7;
    check(UDC_Guardian::language() === $language, 'Locale resolves to the corresponding language: ' . $locale);
    ob_start(); UDC_Guardian::render('test-' . $language); $html = ob_get_clean();
    check(str_contains($html, 'language=' . $language) && str_contains($html, 'udc_guardian_download'), 'Correct language document is rendered');
}
$GLOBALS['current_language'] = 'pt_PT';
ob_start(); UDC_Guardian::render('unsupported-'); $html = ob_get_clean();
check(!str_contains($html, 'udc_guardian_download') && str_contains($html, esc_html(UDC_Guardian::translate('missing'))), 'Missing language never silently downloads a different document');
unset($GLOBALS['current_language']);
$wpdb = new SyntheticDatabase();
UDC_Activator::activate();
check(!array_intersect(['address','city','zip'], $wpdb->columns), 'Migration removes all three contact columns');
check(!preg_match('/(?:address|city|zip) varchar/', $GLOBALS['requested_schema']), 'Clean installation omits retired columns');
check(str_contains($GLOBALS['requested_schema'], 'minor_declared tinyint(1) DEFAULT NULL'), 'New declaration column preserves unknown history');
echo "All guardian regressions passed (synthetic checks).\n";

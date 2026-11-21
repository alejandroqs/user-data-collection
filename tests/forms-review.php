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
check(!$wpdb->suppress_errors, 'Sensitive submission insert restores the prior wpdb error-suppression setting');
$GLOBALS['options']['udc_diagnostic_email_enabled'] = 1;
$GLOBALS['options']['udc_diagnostic_email_address'] = 'support@example.test';
$GLOBALS['options']['udc_diagnostic_email_sender_address'] = 'sender@example.test';
$GLOBALS['options']['udc_diagnostic_email_sender_name'] = 'Synthetic Sender';
$GLOBALS['captured_mail'] = [];
$GLOBALS['active_filters'] = [];
$GLOBALS['mail_sender_filter_counts'] = [];
$GLOBALS['options']['udc_mail_transport'] = 'wordpress';
check(UDC_Email_Sync::send_diagnostic('insert_failed', 'tattoo'), 'Eligible diagnostic failure sends through the synthetic mail double');
check(count($GLOBALS['captured_mail']) === 1 && $GLOBALS['captured_mail'][0][4] === [], 'Diagnostic email has no attachments');
check(!str_contains($GLOBALS['captured_mail'][0][2], 'Synthetic') && !str_contains($GLOBALS['captured_mail'][0][2], '5550100') && !str_contains($GLOBALS['captured_mail'][0][2], 'support@example.test') && str_contains($GLOBALS['captured_mail'][0][2], 'UDC_SUBMISSION_INSERT_FAILED') && str_contains($GLOBALS['captured_mail'][0][2], 'Form type: Tattoo'), 'Diagnostic body excludes synthetic identity/contact values, uses a localized form label and retains the log event code');
check(empty($GLOBALS['active_filters']['wp_mail_from']) && empty($GLOBALS['active_filters']['wp_mail_from_name']), 'Diagnostic sender filters are removed after mail');
check(!UDC_Email_Sync::send_diagnostic('insert_failed', 'piercing') && count($GLOBALS['captured_mail']) === 1, 'Site-wide transient throttles later diagnostic attempts');
unset($GLOBALS['transients']['udc_diagnostic_mail_throttle']);
$GLOBALS['mail_result'] = false;
$phpmailer_count = count($GLOBALS['phpmailer_captures'] ?? []);
check(!UDC_Email_Sync::send_diagnostic('submission_limit_failure', 'piercing'), 'A failed mail result does not escape as a submission failure');
check(str_contains(end($GLOBALS['captured_mail'])[2], 'Form type: Piercing') && str_contains(end($GLOBALS['captured_mail'])[2], 'UDC_SUBMISSION_LIMIT_FAILURE'), 'Piercing diagnostics use the localized form label and stable event code');
check(count($GLOBALS['phpmailer_captures'] ?? []) === $phpmailer_count, 'A failed WordPress mail call does not switch to PHP mail()');
check(empty($GLOBALS['active_filters']['wp_mail_from']) && empty($GLOBALS['active_filters']['wp_mail_from_name']), 'Sender filters are removed after a failed mail result');
unset($GLOBALS['mail_result'], $GLOBALS['transients']['udc_diagnostic_mail_throttle']);
$GLOBALS['throw_mail'] = true;
check(!UDC_Email_Sync::send_diagnostic('insert_failed', 'tattoo'), 'A thrown mail error is contained');
check(empty($GLOBALS['active_filters']['wp_mail_from']) && empty($GLOBALS['active_filters']['wp_mail_from_name']), 'Sender filters are removed after a thrown mail error');
unset($GLOBALS['throw_mail'], $GLOBALS['transients']['udc_diagnostic_mail_throttle']);
$GLOBALS['fail_transient_write'] = true;
$mail_count = count($GLOBALS['captured_mail']);
check(!UDC_Email_Sync::send_diagnostic('insert_failed', 'tattoo') && count($GLOBALS['captured_mail']) === $mail_count, 'A failed throttle claim skips mail');
unset($GLOBALS['fail_transient_write']);
check(UDC_Email_Sync::diagnostic_settings()['enabled'] === 1, 'Saved diagnostic enable setting resolves independently');
unset($GLOBALS['options']['udc_diagnostic_email_enabled'], $GLOBALS['options']['udc_diagnostic_email_address']);
check(UDC_Email_Sync::diagnostic_settings()['enabled'] === 0, 'Unconfigured diagnostics default to disabled');
$rate_key = 'udc_submission_' . hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), wp_salt('auth'));
$GLOBALS['submit_transients'] = [$rate_key => ['count' => 5]];
try { submit_form($tattoo, form_post()); check(false, 'Submission limit must reject an over-limit request'); }
catch (RuntimeException $error) { check($error->getMessage() === 'wp_die:429' && $GLOBALS['last_wp_die'][1] === 'Submission error', 'Submission limit retains HTTP 429 and uses the localized error title'); }
$settings = new UDC_Settings();
$settings->register_settings();
$invalid_diagnostic_settings = ['enabled' => 1, 'recipient' => ''];
ob_start(); invoke_private($settings, 'render_diagnostic_email_configuration_notice', $invalid_diagnostic_settings); $diagnostic_notice = ob_get_clean();
check(str_contains($diagnostic_notice, 'effective destination address is missing or invalid') && !str_contains($diagnostic_notice, '@'), 'Enabled diagnostics show an address-free configuration notice when the effective recipient is blank');
ob_start(); invoke_private($settings, 'render_diagnostic_email_configuration_notice', ['enabled' => 1, 'recipient' => 'invalid-address']); $invalid_diagnostic_notice = ob_get_clean();
check(str_contains($invalid_diagnostic_notice, 'effective destination address is missing or invalid') && !str_contains($invalid_diagnostic_notice, 'invalid-address'), 'Enabled diagnostics show an address-free configuration notice when the effective recipient is invalid');
ob_start(); invoke_private($settings, 'render_diagnostic_email_configuration_notice', ['enabled' => 1, 'recipient' => 'valid@example.test']); $no_diagnostic_notice = ob_get_clean();
check('' === $no_diagnostic_notice, 'A valid effective recipient does not show the diagnostic configuration notice');
unset($GLOBALS['options']['udc_mail_transport']);
check(UDC_Email_Sync::mail_transport() === 'mail', 'A missing transport setting selects PHP mail()');
$GLOBALS['options']['udc_mail_transport'] = 'invalid';
check(UDC_Email_Sync::mail_transport() === 'mail', 'An invalid transport setting selects PHP mail()');
unset($GLOBALS['options']['udc_mail_transport']);
check($settings->sanitize_mail_transport('wordpress') === 'wordpress' && $settings->sanitize_mail_transport('invalid') === 'mail', 'Transport registration allowlists both supported choices');
check(in_array('udc_mail_transport', $GLOBALS['registered_setting_groups']['udc_mail_transport_settings'], true) && !in_array('udc_mail_transport', $GLOBALS['registered_setting_groups']['udc_general_settings'], true), 'Transport settings use a separate Settings API group from guardian documents');
$GLOBALS['options']['udc_mail_transport'] = 'wordpress';
check(UDC_Email_Sync::mail_transport() === 'wordpress', 'The WordPress transport choice survives option reload');
$GLOBALS['options']['udc_guardian_document_fr'] = 0;
check(update_option('udc_mail_transport', 'mail') && UDC_Email_Sync::mail_transport() === 'mail', 'The PHP mail() transport choice survives option reload');
check(get_option('udc_guardian_document_fr') === 0, 'Saving transport selection leaves guardian settings untouched');
$GLOBALS['options']['udc_mail_transport'] = 'wordpress';
$GLOBALS['options']['admin_email'] = 'admin@admin-domain.test';
$GLOBALS['options']['udc_email_address'] = 'backup-destination@example.test';
$GLOBALS['options']['udc_email_sender_address'] = 'backup-sender@example.test';
$GLOBALS['options']['udc_email_sender_name'] = 'Backup Sender';
foreach (['udc_diagnostic_email_address', 'udc_diagnostic_email_sender_address', 'udc_diagnostic_email_sender_name'] as $option) { unset($GLOBALS['options'][$option]); }
$inherited = UDC_Email_Sync::diagnostic_settings();
check($inherited['recipient'] === 'backup-destination@example.test' && $inherited['sender_email'] === 'backup-sender@example.test' && $inherited['sender_name'] === 'Backup Sender', 'All absent diagnostic mail values inherit their backup counterparts');
$GLOBALS['options']['udc_diagnostic_email_address'] = 'diagnostic-destination@example.test';
$GLOBALS['options']['udc_diagnostic_email_sender_address'] = 'diagnostic-sender@example.test';
$overridden = UDC_Email_Sync::diagnostic_settings();
check($overridden['recipient'] === 'diagnostic-destination@example.test' && $overridden['sender_email'] === 'diagnostic-sender@example.test' && $overridden['sender_name'] === 'Backup Sender', 'Diagnostic overrides remain independent by field');
unset($GLOBALS['options']['udc_diagnostic_email_address'], $GLOBALS['options']['udc_diagnostic_email_sender_address']);
foreach (['udc_diagnostic_email_address', 'udc_diagnostic_email_sender_address', 'udc_diagnostic_email_sender_name'] as $option) {
    check(!array_key_exists('default', $GLOBALS['registered_settings'][$option]), 'Diagnostic text registration has no empty default: ' . $option);
    check(update_option($option, ''), 'An explicit blank first save is persisted: ' . $option);
}
$blank_first_save = UDC_Email_Sync::diagnostic_settings();
check($blank_first_save['recipient'] === '' && $blank_first_save['sender_email'] === '' && $blank_first_save['sender_name'] === '', 'Explicit blank first saves override populated backup values');
UDC_Email_Sync::send_with_sender('recipient@example.test', 'Synthetic', 'Synthetic message', [], '', '');
check($GLOBALS['mail_sender_filter_counts'][count($GLOBALS['mail_sender_filter_counts']) - 1] === [0, 0], 'Blank sender overrides leave WordPress mail defaults active');
$wordpress_default_mail = end($GLOBALS['captured_mail']);
check($wordpress_default_mail[5] === 'wordpress@site-domain.test' && $wordpress_default_mail[6] === 'WordPress', 'WordPress transport uses the core site-host sender baseline');

$attachment_dir = wp_upload_dir()['basedir'] . '/udc-transport-test';
if (!is_dir($attachment_dir)) { mkdir($attachment_dir, 0777, true); }
$attachment_file = $attachment_dir . '/synthetic-backup.json';
file_put_contents($attachment_file, '{"synthetic":true}');
$GLOBALS['phpmailer_captures'] = [];
$wp_mail_count = count($GLOBALS['captured_mail']);
$GLOBALS['options']['udc_mail_transport'] = 'mail';
$GLOBALS['options']['admin_email'] = 'admin@example.test';
check(UDC_Email_Sync::send_with_sender('recipient@example.test', 'Synthétic subject', 'Plain text body', [$attachment_file], 'override@example.test', 'Override Sender'), 'PHP mail() transport sends with the isolated PHPMailer double');
$php_capture = end($GLOBALS['phpmailer_captures']);
check($php_capture['transport'] === 'mail' && $php_capture['charset'] === 'UTF-8' && false === $php_capture['is_html'] && false === $php_capture['auto_reply_to'], 'PHP transport selects mail mode, UTF-8 and plain text');
check($php_capture['addresses'] === ['recipient@example.test'] && $php_capture['from'] === 'override@example.test' && $php_capture['from_name'] === 'Override Sender', 'PHP transport applies validated recipient and sender overrides');
check($php_capture['attachments'] === [$attachment_file] && count($GLOBALS['captured_mail']) === $wp_mail_count, 'PHP transport includes attachments and bypasses wp_mail()');
$GLOBALS['options']['udc_mail_transport'] = 'mail';
check(UDC_Email_Sync::send_with_sender('recipient@example.test', 'Synthetic', 'Plain text', [], '', ''), 'PHP transport sends with blank sender overrides');
$php_default_capture = end($GLOBALS['phpmailer_captures']);
check($php_default_capture['from'] === 'wordpress@site-domain.test' && $php_default_capture['from_name'] === 'WordPress', 'PHP transport uses the WordPress core site-host sender baseline instead of admin_email');
$GLOBALS['options']['admin_email'] = 'invalid-address';
check(UDC_Email_Sync::send_with_sender('recipient@example.test', 'Synthetic', 'Plain text', [], '', ''), 'PHP transport accepts the core fallback sender when the admin email is invalid');
check(end($GLOBALS['phpmailer_captures'])['from'] === 'wordpress@site-domain.test', 'PHP transport retains the WordPress site-host sender when admin_email is invalid');
$GLOBALS['options']['admin_email'] = 'admin@admin-domain.test';
$wp_mail_count = count($GLOBALS['captured_mail']);
check(!UDC_Email_Sync::send_with_sender('recipient@example.test', 'Synthetic', 'Plain text', [$attachment_dir . '/missing.json'], '', ''), 'PHP transport fails when an expected attachment cannot be added');
check(count($GLOBALS['captured_mail']) === $wp_mail_count, 'An attachment failure does not switch to wp_mail()');
$GLOBALS['phpmailer_result'] = false;
check(!UDC_Email_Sync::send_with_sender('recipient@example.test', 'Synthetic', 'Plain text', [], '', ''), 'A failed PHP transport returns failure');
check(count($GLOBALS['captured_mail']) === $wp_mail_count, 'A failed PHP transport does not switch to wp_mail()');
unset($GLOBALS['phpmailer_result']);
$GLOBALS['options']['udc_mail_transport'] = 'wordpress';
$phpmailer_count = count($GLOBALS['phpmailer_captures']);
check(UDC_Email_Sync::send_with_sender('recipient@example.test', 'Synthetic', 'Plain text', [$attachment_file], 'override@example.test', 'Override Sender'), 'WordPress transport sends through wp_mail()');
check(count($GLOBALS['captured_mail']) === $wp_mail_count + 1 && count($GLOBALS['phpmailer_captures']) === $phpmailer_count, 'WordPress transport calls wp_mail() exactly once and does not construct PHPMailer');
$wordpress_capture = end($GLOBALS['captured_mail']);
check($wordpress_capture[4] === [$attachment_file] && $wordpress_capture[5] === 'override@example.test' && $wordpress_capture[6] === 'Override Sender', 'WordPress transport preserves attachment and scoped sender overrides');

$GLOBALS['options']['udc_email_backup_enabled'] = 1;
$GLOBALS['options']['udc_email_address'] = 'backup@example.test';
$GLOBALS['options']['udc_email_sender_address'] = '';
$GLOBALS['options']['udc_email_sender_name'] = '';
$backup_file = wp_upload_dir()['basedir'] . '/udc-backups/synthetic-mail-test.json';
if (!is_dir(dirname($backup_file))) { mkdir(dirname($backup_file), 0777, true); }
file_put_contents($backup_file, '{"synthetic_backup":true}');
$GLOBALS['options']['udc_mail_transport'] = 'mail';
check((new UDC_Email_Sync())->send_backup() === true && end($GLOBALS['phpmailer_captures'])['attachments'] === [$backup_file], 'PHP backup email retains its JSON attachment');
$GLOBALS['options']['udc_mail_transport'] = 'wordpress';
$wp_mail_count = count($GLOBALS['captured_mail']);
check((new UDC_Email_Sync())->send_backup() === true && count($GLOBALS['captured_mail']) === $wp_mail_count + 1 && end($GLOBALS['captured_mail'])[4] === [$backup_file], 'WordPress backup email retains its JSON attachment and sends once');

$GLOBALS['options']['udc_diagnostic_email_enabled'] = 1;
$GLOBALS['options']['udc_diagnostic_email_address'] = 'diagnostic@example.test';
$GLOBALS['options']['udc_mail_transport'] = 'mail';
unset($GLOBALS['transients']['udc_diagnostic_mail_throttle']);
$wp_mail_count = count($GLOBALS['captured_mail']);
check(UDC_Email_Sync::send_diagnostic('insert_failed', 'tattoo') && end($GLOBALS['phpmailer_captures'])['attachments'] === [] && count($GLOBALS['captured_mail']) === $wp_mail_count, 'PHP diagnostic email has no attachments and bypasses wp_mail()');
$GLOBALS['options']['udc_mail_transport'] = 'wordpress';
unset($GLOBALS['transients']['udc_diagnostic_mail_throttle']);
check(UDC_Email_Sync::send_diagnostic('insert_failed', 'tattoo') && end($GLOBALS['captured_mail'])[4] === [], 'WordPress diagnostic email has no attachments');

$GLOBALS['options']['udc_mail_transport'] = 'mail';
$GLOBALS['options']['udc_diagnostic_email_enabled'] = 1;
$GLOBALS['phpmailer_result'] = false;
unset($GLOBALS['transients']['udc_diagnostic_mail_throttle']);
$phpmailer_count = count($GLOBALS['phpmailer_captures'] ?? []);
$wp_mail_count = count($GLOBALS['captured_mail']);
$GLOBALS['fail_insert'] = true;
[$failed_url] = submit_form($tattoo, form_post());
parse_str(parse_url($failed_url, PHP_URL_QUERY), $_GET);
check(UDC_Shortcode::result_status() === 'error', 'Failed diagnostic mail preserves the public submission error result');
check(count($GLOBALS['phpmailer_captures'] ?? []) === $phpmailer_count + 1, 'Failed PHP diagnostic mail makes exactly one PHPMailer attempt');
check(count($GLOBALS['captured_mail']) === $wp_mail_count, 'Failed PHP diagnostic mail makes zero additional wp_mail() calls');
unset($GLOBALS['fail_insert'], $GLOBALS['phpmailer_result'], $GLOBALS['options']['udc_diagnostic_email_enabled']);
$GLOBALS['options']['udc_mail_transport'] = 'wordpress';
$GLOBALS['options']['udc_diagnostic_email_enabled'] = 1;
$GLOBALS['options']['udc_diagnostic_email_address'] = 'support@example.test';
$GLOBALS['throw_mail'] = true;
$GLOBALS['throw_insert'] = true;
$GLOBALS['initial_suppression'] = true;
unset($GLOBALS['transients']['udc_diagnostic_mail_throttle']);
$mail_attempts_before = count($GLOBALS['captured_mail']);
[$exception_url] = submit_form($tattoo, form_post());
parse_str(parse_url($exception_url, PHP_URL_QUERY), $_GET);
check(UDC_Shortcode::result_status() === 'error', 'A thrown insert and failed alert preserve the controlled submission error response');
check($wpdb->insert_attempts === 1, 'A throwing database double receives exactly one insert attempt');
check($wpdb->suppress_errors === true, 'A throwing insert restores the prior wpdb error-suppression state');
check(count($GLOBALS['captured_mail']) === $mail_attempts_before + 1 && str_contains(end($GLOBALS['captured_mail'])[2], 'UDC_SUBMISSION_INSERT_FAILED') && !str_contains(end($GLOBALS['captured_mail'])[2], 'synthetic-sensitive-insert-exception'), 'The fixed insertion diagnostic is attempted without exposing exception text');
unset($GLOBALS['throw_mail'], $GLOBALS['throw_insert'], $GLOBALS['initial_suppression'], $GLOBALS['options']['udc_diagnostic_email_enabled']);
$wpdb->suppress_errors(false);
unlink($backup_file);
unlink($attachment_file);
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
    check(str_contains($GLOBALS['admin_form_css'] ?? $GLOBALS['form_css'], 'column-dob') && str_contains($GLOBALS['admin_form_css'] ?? $GLOBALS['form_css'], 'white-space:nowrap'), 'Both admin tables receive nonwrapping date-heading styles');
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

// Both endpoints must apply the same trap contract before parsing any other fields.
foreach ([false, true] as $is_piercing) {
    $object = $is_piercing ? $piercing : $tattoo;
    foreach (['', 'missing'] as $value) {
        $post = form_post($is_piercing);
        if ('' === $value) { $post['udc_contact_reference'] = ''; }
        [$url, $inserts] = submit_form($object, $post);
        check(count($inserts) === 1 && str_contains($url, 'status=success'), 'Empty/absent honeypot preserves normal saving for each service');
        check(!array_key_exists('contact_reference', $inserts[0][1]) && !str_contains($inserts[0][1]['form_evidence'], 'udc_contact_reference'), 'Honeypot is absent from rows and accepted documents');
    }
    foreach (['text', ' ', '0', '<b></b>', '\\\\', [], ['text'], null, 0, false] as $value) {
        $post = form_post($is_piercing); $post['udc_contact_reference'] = $value;
        [$url, $inserts] = submit_form($object, $post);
        parse_str(parse_url($url, PHP_URL_QUERY), $_GET);
        check(!$inserts && UDC_Shortcode::result_status($is_piercing) === 'error', 'Nonempty or non-string honeypots produce a signed generic error');
        check($GLOBALS['transients'] === [], 'Honeypot rejection consumes no submission attempts');
    }
    $post = form_post($is_piercing); $post['udc_contact_reference'] = ['trap']; $post['udc_form_nonce'] = 'wrong';
    try { submit_form($object, $post); check(false, 'Nonce must reject first'); }
    catch (RuntimeException $error) { check($error->getMessage() === 'wp_die:403' && !$wpdb->inserts && !$GLOBALS['transients'], 'Invalid nonce precedes the trap and all writes'); }
    foreach (['success', 'error', 'minor_error'] as $status) {
        $key = $is_piercing ? 'udc_piercing_status' : 'udc_status'; $expires = time() + 120;
        $_GET = [$key => $status, 'udc_result' => $expires . '.' . hash_hmac('sha256', $key . '|' . $status . '|' . $expires, wp_salt('auth'))];
        $html = $object->render_form();
        check((bool) preg_match('~</h2>\s*<div class="udc-result udc-result--' . ('success' === $status ? 'success' : 'error') . '"~', $html), 'Authenticated feedback follows the form heading');
        check(substr_count($html, 'class="udc-result ') === 1, 'Each form has exactly one authenticated result notice');
    }
}
$_GET = [];
foreach (['0', '1'] as $enabled) {
    $GLOBALS['options']['udc_design_enabled'] = $enabled;
    $html = $tattoo->render_form() . $tattoo->render_form() . $piercing->render_form() . $piercing->render_form();
    preg_match_all('~<input type="text" id="([^"]+)" name="udc_contact_reference" value="" autocomplete="off" tabindex="-1">~', $html, $matches);
    check(count($matches[1]) === 4 && count(array_unique($matches[1])) === 4, 'Repeated forms have exactly one empty enabled text trap with unique IDs in either design');
    check(substr_count($html, 'hidden aria-hidden="true" style="display:none!important"') === 4, 'Traps are hidden without JavaScript');
}
$GLOBALS['options']['udc_design_enabled'] = '0';
echo "All honeypot/result checks passed (synthetic WordPress/database doubles).\n";

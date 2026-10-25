<?php
/** Isolated test endpoint; never a WordPress or production bootstrap. */
if (PHP_SAPI !== 'cli-server') { exit; }
$download_request = $_GET;
ob_start();
require dirname(__DIR__) . '/forms.php';
ob_end_clean();
$_GET = $download_request;
if (isset($download_request['simulate_polylang'])) {
    function pll_languages_list($args = []) { return ($args['fields'] ?? 'slug') === 'locale' ? ['fr_FR', 'en_US'] : ['fr', 'en']; }
    function pll_translate_string($text, $language) { return $text; }
    $source = UDC_Piercing_Form::get_strings()['subtitle_care'];
    $GLOBALS['gettext']['en_US'][$source] = 'CARE IN ENGLISH';
}
try {
    (new UDC_Piercing_Form())->download_care();
} catch (RuntimeException $error) {
    http_response_code(str_starts_with($error->getMessage(), 'wp_die:400') ? 400 : 500);
    echo 'Synthetic request rejected.';
}

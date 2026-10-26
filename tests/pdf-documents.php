<?php
/** Real scoped engine, synthetic WordPress context; no personal data. */
error_reporting(E_ALL);
define('ABSPATH', __DIR__);
define('UDC_PLUGIN_DIR', dirname(__DIR__) . '/');
$catalogs = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$GLOBALS['locale'] = 'fr_FR';
$GLOBALS['locales'] = [];
function get_locale() { return $GLOBALS['locale']; }
function get_available_languages() { return array_keys($GLOBALS['catalogs']); }
function switch_to_locale($locale) {
    if ($locale === get_locale()) { return false; }
    $GLOBALS['locales'][] = get_locale(); $GLOBALS['locale'] = $locale; return true;
}
function restore_previous_locale() { $GLOBALS['locale'] = array_pop($GLOBALS['locales']); }
function __($text, $domain = '') { return $GLOBALS['catalogs'][get_locale()][$text] ?? $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_url($url, $protocols = []) { return preg_match('~^https?://~', $url) ? esc_html($url) : ''; }
function esc_url_raw($url, $protocols = []) { return preg_match('~^https?://~', $url) ? $url : ''; }
function wp_parse_url($url) { return parse_url($url); }
function home_url($path = '') { return 'https://example.test' . $path; }
function get_option($key, $default = false) { return $key === 'udc_piercing_website_url' ? 'https://legacy.example.test/' : $default; }
function get_temp_dir() { return sys_get_temp_dir() . '/'; }
function wp_unslash($text) { return stripslashes($text); }
function add_action(...$args) {}
function add_shortcode(...$args) {}
function wp_die($message, $title = '', $args = []) { throw new RuntimeException('HTTP ' . ($args['response'] ?? 0)); }
foreach (['document', 'pdf-renderer', 'document-download', 'tattoo-care-document', 'shortcode', 'piercing-form', 'piercing-care-document'] as $file) {
    require UDC_PLUGIN_DIR . 'includes/class-udc-' . $file . '.php';
}
function verify($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
UDC_Document_Download::register('piercing-care', new UDC_Piercing_Care_Document());
UDC_Document_Download::register('tattoo-care', new UDC_Tattoo_Care_Document());
$output = UDC_PLUGIN_DIR . 'tmp/pdfs';
if (!is_dir($output)) { mkdir($output, 0700, true); }
foreach (array_keys($catalogs) as $requested_locale) {
    $response = UDC_Document_Download::prepare('tattoo-care', ['language' => $requested_locale]);
    verify(0 === strpos($response['body'], '%PDF-') && $response['filename'] === 'tattoo-care-' . $requested_locale . '.pdf', 'Tattoo PDF response is invalid');
    verify(get_locale() === 'fr_FR' && !$GLOBALS['locales'], 'Tattoo download leaked locale');
    $switched = switch_to_locale($requested_locale);
    $tattoo_document = (new UDC_Tattoo_Care_Document())->build(['language' => $requested_locale])->data();
    foreach (range(1, 6) as $number) {
        verify($tattoo_document['sections'][$number - 1]['text'] === UDC_Tattoo_Care_Document::translate('care_' . $number), 'Tattoo PDF differs from displayed care');
    }
    if ($switched) { restore_previous_locale(); }
    file_put_contents($output . '/' . $response['filename'], $response['body']);
    file_put_contents($output . '/' . $response['filename'] . '.json', json_encode($tattoo_document, JSON_UNESCAPED_UNICODE));
    foreach (['lobe', 'cartilage', 'face', 'oral', 'body'] as $category) {
        $response = UDC_Document_Download::prepare('piercing-care', ['language' => $requested_locale, 'category' => $category]);
        verify(0 === strpos($response['body'], '%PDF-'), 'Missing PDF signature');
        verify($response['filename'] === 'piercing-care-' . $category . '-' . $requested_locale . '.pdf', 'Wrong filename');
        verify(get_locale() === 'fr_FR' && !$GLOBALS['locales'], 'Locale leaked after download');
        $switched = switch_to_locale($requested_locale);
        $expected = UDC_Piercing_Form::snapshot();
        $document = UDC_Piercing_Form::care_data($category, $requested_locale);
        verify($document['website_url'] === home_url('/') && $document['website_label'] === '', 'Automatic PDF website was overridden by legacy settings');
        if ($switched) { restore_previous_locale(); }
        foreach (['base', $category, 'alert'] as $index => $key) {
            verify($document['sections'][$index]['text'] === $expected['care'][$key], 'Care content diverged');
        }
        file_put_contents($output . '/' . $response['filename'], $response['body']);
        file_put_contents($output . '/' . $response['filename'] . '.json', json_encode($document, JSON_UNESCAPED_UNICODE));
    }
}
foreach ([['language' => 'invalid', 'category' => 'oral'], ['language' => ['fr_FR'], 'category' => 'oral'],
          ['language' => 'fr_FR', 'category' => '../oral'], ['language' => 'fr_FR', 'category' => ['oral']]] as $parameters) {
    try { UDC_Document_Download::prepare('piercing-care', $parameters); throw new LogicException('Invalid request accepted'); }
    catch (InvalidArgumentException $error) {}
    verify(get_locale() === 'fr_FR' && !$GLOBALS['locales'], 'Locale leaked after rejection');
}
try { UDC_Document_Download::prepare('unknown', ['language' => 'fr_FR']); throw new LogicException('Unknown provider accepted'); }
catch (InvalidArgumentException $error) {}

// Second provider proves reuse, escaping and page breaks without changing the engine/controller.
class SyntheticDocumentProvider implements UDC_Document_Provider {
    public function build(array $parameters) {
        $sections = [];
        for ($index = 0; $index < 18; $index++) {
            $sections[] = ['heading' => 'Section ' . $index, 'text' => str_repeat('Été, español, Straße, français, italiano. <script>alert(1)</script> ', 12)];
        }
        return new UDC_Document(['title' => 'Synthetic reusable document', 'language' => $parameters['language'],
            'filename' => 'synthetic-document.pdf', 'version' => 'Version 1.0', 'sections' => $sections,
            'website_label' => 'Website', 'website_url' => 'https://example.test/']);
    }
}
UDC_Document_Download::register('synthetic', new SyntheticDocumentProvider());
$response = UDC_Document_Download::prepare('synthetic', ['language' => 'fr_FR']);
file_put_contents($output . '/synthetic-document.pdf', $response['body']);
$html = UDC_Pdf_Renderer::html((new SyntheticDocumentProvider())->build(['language' => 'fr_FR']));
verify(false === strpos($html, '<script>'), 'Provider text was treated as HTML');
verify(!class_exists('Dompdf\\Dompdf', false), 'Dependency escaped its namespace');
class FailingDocumentProvider implements UDC_Document_Provider {
    public function build(array $parameters) { throw new RuntimeException('Synthetic failure'); }
}
UDC_Document_Download::register('failing', new FailingDocumentProvider());
try { UDC_Document_Download::send('failing', ['language' => 'es_ES']); }
catch (RuntimeException $error) { verify($error->getMessage() === 'HTTP 503', 'Uncontrolled failure'); }
verify(get_locale() === 'fr_FR' && !$GLOBALS['locales'], 'Locale leaked after failure');
// Conditional declarations model Polylang only after testing the native locale path.
if (true) {
    function pll_languages_list($args) { return $args['fields'] === 'locale' ? ['fr_FR', 'es_ES'] : ['fr', 'es']; }
    function pll_translate_string($text, $language) {
        if ('es' === $language && $text === UDC_Tattoo_Care_Document::get_strings()['title']) { return 'TATTOO POLYLANG TEST'; }
        return 'es' === $language && $text === UDC_Piercing_Form::get_strings()['subtitle_care'] ? 'SOINS POLYLANG TEST' : $text;
    }
}
$response = UDC_Document_Download::prepare('piercing-care', ['language' => 'es', 'category' => 'oral']);
verify('piercing-care-oral-es.pdf' === $response['filename'], 'Polylang slug/locale mapping failed');
$switched = switch_to_locale('es_ES');
$data = UDC_Piercing_Form::care_data('oral', 'es');
if ($switched) { restore_previous_locale(); }
verify(0 === strpos($data['title'], 'SOINS POLYLANG TEST'), 'Explicit Polylang override was ignored');
verify($data['sections'][0]['text'] === $catalogs['es_ES'][UDC_Piercing_Form::get_strings()['care_base']], 'Requested-locale gettext fallback failed');
verify(UDC_Piercing_Form::snapshot()['language'] === 'fr_FR', 'Explicit download language leaked');
$response = UDC_Document_Download::prepare('tattoo-care', ['language' => 'es']);
verify($response['filename'] === 'tattoo-care-es.pdf' && get_locale() === 'fr_FR', 'Tattoo Polylang slug mapping or locale restoration failed');
$switched = switch_to_locale('es_ES');
$data = (new UDC_Tattoo_Care_Document())->build(['language' => 'es'])->data();
if ($switched) { restore_previous_locale(); }
verify($data['title'] === 'TATTOO POLYLANG TEST', 'Tattoo Polylang override ignored');
verify($data['sections'][0]['text'] === $catalogs['es_ES'][UDC_Tattoo_Care_Document::get_strings()['care_1']], 'Tattoo gettext fallback ignored');
echo 'PASS: 54 translated care PDFs, multipage provider, escaping, locale restoration and controlled failures.' . PHP_EOL;

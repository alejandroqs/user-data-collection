<?php
/** Isolated cache and AJAX checks; WordPress and database are doubles. */
ob_start();
require __DIR__ . '/forms.php';
ob_clean();
class WP_Post { public $post_content = ''; }
class JsonCaptured extends RuntimeException { public $payload; public function __construct($payload, $status) { parent::__construct('', $status); $this->payload = $payload; } }
function is_singular() { return true; }
function get_queried_object() { return $GLOBALS['test_page']; }
function get_queried_object_id() { return $GLOBALS['test_page_id']; }
function has_shortcode($content, $tag) { return str_contains($content, '[' . $tag . ']'); }
function wp_create_nonce($action) { return 'nonce-' . $action; }
function wp_send_json_error($data, $status = 200) { throw new JsonCaptured(['success' => false, 'data' => $data], $status); }
function wp_send_json_success($data, $status = 200) { throw new JsonCaptured(['success' => true, 'data' => $data], $status); }

check(isset($GLOBALS['hooks']['wp_ajax_udc_refresh_nonce'], $GLOBALS['hooks']['wp_ajax_nopriv_udc_refresh_nonce']), 'Both AJAX hooks are registered');
$_GET = []; $_SERVER['REQUEST_METHOD'] = 'GET';
$GLOBALS['test_page'] = new WP_Post(); $GLOBALS['test_page_id'] = 20;
$GLOBALS['test_filters']['udc_form_page_ids'] = [20, 21];
$before = $GLOBALS['nocache_calls']; $tattoo->prevent_result_cache();
check($GLOBALS['nocache_calls'] === $before + 1, 'Configured Divi page requests no-cache');
$GLOBALS['test_page_id'] = 30;
$before = $GLOBALS['nocache_calls']; $tattoo->prevent_result_cache();
check($GLOBALS['nocache_calls'] === $before, 'Unrelated page keeps its existing cache policy');
foreach (['udc_tattoo_form', 'udc_contact_form', 'udc_piercing_form'] as $tag) {
    $GLOBALS['test_page']->post_content = '[' . $tag . ']';
    $before = $GLOBALS['nocache_calls']; $tattoo->prevent_result_cache();
    check($GLOBALS['nocache_calls'] === $before + 1, 'Shortcode page requests no-cache: ' . $tag);
}
foreach (['tattoo' => 'udc_form_action', 'piercing' => 'udc_piercing_action'] as $type => $action) {
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['form_type' => $type];
    try { $tattoo->refresh_nonce(); check(false, 'AJAX response expected'); }
    catch (JsonCaptured $response) { check($response->payload['data']['nonce'] === 'nonce-' . $action, 'Correct nonce action: ' . $type); }
}
foreach ([['GET', 'tattoo', 405], ['POST', 'other', 400]] as [$method, $type, $status]) {
    $_SERVER['REQUEST_METHOD'] = $method; $_POST = ['form_type' => $type];
    try { $tattoo->refresh_nonce(); check(false, 'Invalid request must fail'); }
    catch (JsonCaptured $response) { check($response->getCode() === $status && !$response->payload['success'], 'Invalid AJAX request rejected'); }
}
echo "Nonce and cache checks passed with synthetic WordPress doubles.\n";
ob_end_flush();

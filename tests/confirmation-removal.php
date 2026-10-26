<?php
/**
 * Focused behavior checks using WordPress/database doubles, not a live installation.
 * Run: php tests/confirmation-removal.php
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/fixtures/');
define('MINUTE_IN_SECONDS', 60);
define('ARRAY_A', 'ARRAY_A');
$GLOBALS['options'] = [];
$GLOBALS['hooks'] = [];
$GLOBALS['transients'] = [];
function add_action($hook, $callback) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_shortcode($tag, $callback) { $GLOBALS['shortcodes'][$tag] = $callback; }
function add_filter($hook, $callback, $priority = 10) {}
function has_filter($hook, $callback) { return false; }
function register_activation_hook($file, $callback) {}
function register_deactivation_hook($file, $callback) {}
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'https://example.test/plugin/'; }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name, $value) { $GLOBALS['options'][$name] = $value; }
function is_admin() { return true; }
function load_plugin_textdomain(...$args) {}
function wp_upload_dir() { return ['basedir' => sys_get_temp_dir() . '/udc-synthetic-tests']; }
function wp_next_scheduled($hook) { return 123; }
function __($value, $domain = '') { return $GLOBALS['gettext'][$GLOBALS['locale'] ?? 'fr_FR'][$value] ?? $GLOBALS['gettext'][$value] ?? $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function esc_html__($value, $domain = '') { return esc_html($value); }
function esc_html_e($value, $domain = '') { echo esc_html($value); }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg($args, $value = '', $url = '') {
    if (is_array($args)) { $url = $value; } else { $args = [$args => $value]; }
    $fragment = ''; $parts = explode('#', $url, 2);
    if (isset($parts[1])) { $fragment = '#' . $parts[1]; }
    $parts = explode('?', $parts[0], 2); $query = [];
    if (isset($parts[1])) { parse_str($parts[1], $query); }
    foreach ($args as $key => $value) { if (false === $value) { unset($query[$key]); } else { $query[$key] = $value; } }
    return $parts[0] . ($query ? '?' . http_build_query($query) : '') . $fragment;
}
function remove_query_arg($keys, $url) { return add_query_arg(array_fill_keys((array) $keys, false), $url); }
function wp_parse_url($url) { return parse_url($url); }
function current_user_can($capability) { return $GLOBALS['can_manage'] ?? true; }
function current_time($format) { return $format === 'Y-m-d' ? '2026-10-24' : '2026-10-24 12:00:00'; }
function date_i18n($format, $timestamp) { return date($format ?: 'Y-m-d', $timestamp); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_unslash($value) { return stripslashes($value); }
function esc_sql($value) { return $value; }
function wp_verify_nonce($value, $action) { return $value === 'synthetic-nonce' && (!isset($GLOBALS['expected_nonce_action']) || $action === $GLOBALS['expected_nonce_action']); }
function apply_filters($hook, $value) { return $value; }
function wp_salt($scheme) { return 'synthetic-test-salt'; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $expiry) { $GLOBALS['transients'][$key] = $value; return true; }
function wp_get_referer() { return array_key_exists('referer', $GLOBALS) ? $GLOBALS['referer'] : 'https://example.test/form'; }
class RedirectCaptured extends RuntimeException {}
function wp_safe_redirect($url, $status = 302) { $GLOBALS['redirect_status'] = $status; throw new RedirectCaptured($url); }
class WP_Error {
    public function __construct(public $code, public $message) {}
}
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_List_Table {
    protected $_column_headers;
    public $items;
    protected $_pagination_args;
    public function __construct($args = []) {}
    public function get_pagenum() { return 2; }
    protected function set_pagination_args($args) { $this->_pagination_args = $args; }
    protected function row_actions($actions) { return implode(' ', $actions); }
}
class SyntheticDatabase {
    public $prefix = 'synthetic_';
    public $last_error = '';
    public $columns = ['id', 'first_name', 'is_confirmed', 'form_type', 'piercing_category', 'form_language', 'form_version', 'form_evidence'];
    public $failInspection = false;
    public $failDrop = false;
    public $queries = [];
    public $inserts = [];
    public function get_charset_collate() { return ''; }
    public function get_col($sql) {
        $this->queries[] = $sql;
        if ($this->failInspection) { $this->last_error = 'synthetic failure'; return []; }
        $this->last_error = '';
        return $this->columns;
    }
    public function query($sql) {
        $this->queries[] = $sql;
        if (str_contains($sql, 'DROP COLUMN')) {
            if ($this->failDrop) { return false; }
            $this->columns = array_values(array_diff($this->columns, ['is_confirmed']));
        }
        return 1;
    }
    public function prepare($sql, ...$args) {
        foreach ($args as $value) { $sql = preg_replace('/%[ds]/', (string) $value, $sql, 1); }
        return $sql;
    }
    public function get_results($sql, $format = null) { $this->queries[] = $sql; return []; }
    public function get_var($sql) { return str_contains($sql, 'ENGINE') ? 'InnoDB' : 0; }
    public function insert($table, $data, $formats) {
        if (!empty($GLOBALS['fail_insert'])) { return false; }
        $this->inserts[] = [$table, $data, $formats];
        return 1;
    }
    public function get_row($sql) { return (object) synthetic_row(); }
}
function synthetic_row() {
    return [
        'id' => 7, 'last_name' => 'Example', 'first_name' => 'Synthetic',
        'dob' => '1990-01-01', 'address' => 'Test address', 'city' => 'Test city',
        'zip' => '1000', 'phone' => '5550100', 'health_good' => 1,
        'health_treatment' => 0, 'health_blood_thinners' => 0,
        'health_allergies' => 0, 'health_pregnant' => 0, 'liability_accepted' => 1,
        'appointment_date' => '2026-11-01', 'appointment_time' => '12:30:00',
        'piercing_location' => 'Synthetic service', 'created_at' => '2026-10-24 12:00:00',
    ];
}
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
function invoke_private($object, $method, ...$args) {
    $reflection = new ReflectionMethod($object, $method);
    return $reflection->invoke($object, ...$args);
}
$wpdb = new SyntheticDatabase();
require dirname(__DIR__) . '/user-data-collection.php';

UDC_Activator::activate();
check(!str_contains($GLOBALS['requested_schema'], 'is_confirmed'), 'New schema omits approval');
check(!in_array('is_confirmed', $wpdb->columns, true), 'Upgrade removes only the retired column');
check(get_option('udc_db_version') === UDC_DB_VERSION, 'Successful upgrade records schema version');
$wpdb->queries = [];
UDC_Activator::activate();
check(count(array_filter($wpdb->queries, fn($sql) => str_contains($sql, 'DROP COLUMN'))) === 0, 'Repeated activation does not drop an absent column');
foreach (['failInspection', 'failDrop'] as $failure) {
    $wpdb = new SyntheticDatabase();
    $wpdb->$failure = true;
    $GLOBALS['options']['udc_db_version'] = '1.5.0';
    UDC_Activator::activate();
    check(get_option('udc_db_version') === '1.5.0', "$failure leaves upgrade pending");
}
$wpdb = new SyntheticDatabase();
$GLOBALS['options']['udc_db_version'] = UDC_DB_VERSION;
udc_init_plugin();
check(!isset($GLOBALS['hooks']['wp_ajax_udc_confirm_submission']) && !isset($GLOBALS['hooks']['wp_ajax_udc_unconfirm_submission']), 'Initialization does not register approval endpoints');

$backup = new UDC_Backup();
$current = synthetic_row();
$previous = $current + ['is_confirmed' => '1'];
$legacy = $previous;
unset($legacy['city'], $legacy['zip']);
$legacy['zip_city'] = '1000 Test city';
foreach (['current' => $current, 'previous' => $previous, 'legacy' => $legacy] as $name => $row) {
    $result = invoke_private($backup, 'validate_backup_rows', [$row]);
    check(!is_wp_error($result) && !array_key_exists('is_confirmed', $result[0]), "$name backup imports without approval");
}
$bad = $previous;
$bad['is_confirmed'] = 'yes';
check(is_wp_error(invoke_private($backup, 'validate_backup_rows', [$bad])), 'Malformed retired values are rejected');
$bad = $current + ['unexpected' => 'value'];
check(is_wp_error(invoke_private($backup, 'validate_backup_rows', [$bad])), 'Unexpected backup fields remain rejected');
check(is_wp_error(invoke_private($backup, 'validate_backup_rows', [$current, $previous])), 'Mixed backup schemas remain rejected');
check(is_wp_error(invoke_private($backup, 'validate_backup_rows', [$previous, $legacy])), 'Mixed postal-code schemas remain rejected');
$added = invoke_private($backup, 'insert_backup_data', [$previous]);
$insert = end($wpdb->inserts);
check($added === 1 && count($insert[1]) === count($insert[2]) && !isset($insert[1]['is_confirmed']), 'Restore writes matching data and formats without retired column');

$table = new UDC_List_Table();
$_GET = ['orderby' => 'is_confirmed'];
$_REQUEST = ['appt_filter' => 'upcoming'];
$table->prepare_items();
$columns = $table->get_columns();
check(count($columns) === 8 && !isset($columns['is_confirmed']) && !isset($columns['action']), 'List contains eight data columns without empty action/status columns');
$query = end($wpdb->queries);
check(str_contains($query, 'ORDER BY appointment_date ASC LIMIT 20 OFFSET 20'), 'Old status sort URL falls back while retaining pagination');
$headers = (new ReflectionProperty(WP_List_Table::class, '_column_headers'))->getValue($table);
check($headers[3] === 'id', 'Responsive primary column uses the first ID column and retains the detail action');
check(array_key_first($columns) === $headers[3], 'Primary column comes first so mobile CSS hides every secondary column');
check(invoke_private($table, 'handle_row_actions', (object) $current, 'last_name', 'id') === '', 'Secondary columns do not emit detail actions or extra expansion controls');
check(str_contains(invoke_private($table, 'handle_row_actions', (object) $current, 'id', 'id'), 'action=view') && str_contains(invoke_private($table, 'handle_row_actions', (object) $current, 'id', 'id'), 'View Details'), 'Detail link remains available');
$_GET = [];
$_REQUEST = ['appt_filter' => 'past'];
$table->prepare_items();
$query = end($wpdb->queries);
check(str_contains($query, "appointment_date <") && str_contains($query, 'ORDER BY created_at DESC'), 'Past appointment filter and ordering remain available');

$admin = new UDC_Admin();
ob_start();
invoke_private($admin, 'render_details_page', 7);
$html = ob_get_clean();
check(!preg_match('/is_confirmed|udc-(?:un)?confirm|bottom-actions|<script|Status:/', $html), 'Detail has no approval state, empty action footer, or obsolete script');
check(str_contains($html, 'Appointment Info') && str_contains($html, 'Liability Notice') && str_contains($html, 'Back to List'), 'Appointment information, consent, and navigation remain');

$wpdb = new SyntheticDatabase();
$_POST = ['udc_form_nonce' => 'synthetic-nonce'];
foreach ($current as $field => $value) { $_POST['udc_' . $field] = (string) $value; }
// Health dropdowns require an explicit answer, including No (0).
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
try { (new UDC_Shortcode())->handle_submission(); } catch (RedirectCaptured $redirect) {
    check(str_contains($redirect->getMessage(), 'udc_status=success'), 'Public submission still redirects to success');
}
$insert = end($wpdb->inserts);
check($insert && count($insert[1]) === count($insert[2]) && !isset($insert[1]['is_confirmed']), 'Public submission writes matching data and formats without approval');
echo "All focused checks passed (WordPress/database doubles; no live database).\n";

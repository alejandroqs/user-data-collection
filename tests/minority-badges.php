<?php
/** Minority display semantics with synthetic WordPress/database context. */
require __DIR__ . '/guardian.php';

$cases = [
    ['minor_declared' => 1, 'dob' => '1990-01-01', 'expected' => true],
    ['minor_declared' => 0, 'dob' => '2010-01-01', 'expected' => false],
    ['minor_declared' => '1', 'dob' => '1990-01-01', 'expected' => true],
    ['minor_declared' => '0', 'dob' => '2010-01-01', 'expected' => false],
    ['minor_declared' => null, 'dob' => '2010-01-01', 'expected' => true],
    ['minor_declared' => null, 'dob' => '1990-01-01', 'expected' => false],
    ['dob' => '2008-10-24', 'expected' => false],
    ['dob' => '2008-10-25', 'expected' => true],
    ['dob' => '2008-02-30', 'expected' => null],
    ['dob' => '2099-01-01', 'expected' => null],
    ['expected' => null],
];
foreach ($cases as $case) {
    $expected = $case['expected']; unset($case['expected']);
    $record = (object) $case; $original = serialize($record);
    check(UDC_Guardian::record_is_minor($record) === $expected, 'Saved zero/one wins; missing history uses calendar age; invalid history stays unknown');
    $badge = UDC_Guardian::badge($record);
    $state = null === $expected ? 'unknown' : ($expected ? 'yes' : 'no');
    $label = null === $expected ? UDC_Guardian::translate('unknown') : UDC_i18n::translate($expected ? 'answer_yes' : 'answer_no');
    check(str_contains($badge, 'udc-minority-' . $state) && str_contains($badge, '>' . esc_html($label) . '</span>'), 'Badge has matching semantic state and translated text');
    check(serialize($record) === $original, 'Rendering does not modify a stored declaration or date');
}
foreach ([false, true] as $piercing_view) {
    $table = new UDC_List_Table($piercing_view);
    $columns = $table->get_columns();
    check(count($columns) === 9 && array_key_last($columns) === 'minor' && $columns['minor'] === UDC_Guardian::translate('is_minor'), 'Both service lists end with the translated minority column');
    check(!array_key_exists('minor', invoke_private($table, 'get_sortable_columns')), 'Derived minority status is not treated as an SQL sort column');
    foreach ([0, 1, null] as $declared) {
        $record = (object) ['minor_declared' => $declared, 'dob' => '2010-01-01'];
        check($table->column_default($record, 'minor') === UDC_Guardian::badge($record), 'List uses the shared declaration/age badge');
        $wpdb = new EvidenceDatabase();
        $wpdb->record = ($piercing_view ? $saved_piercing : $tattoo_record);
        $wpdb->record['minor_declared'] = $declared;
        $wpdb->record['dob'] = '2010-01-01';
        $admin = new UDC_Admin();
        (new ReflectionProperty($admin, 'is_piercing'))->setValue($admin, $piercing_view);
        ob_start(); invoke_private($admin, 'render_details_page', 91); $html = ob_get_clean();
        check((bool) preg_match('~Date of Birth.*?2010-01-01.*?</tr>\s*<tr><th>' . preg_quote(esc_html(UDC_Guardian::translate('is_minor')), '~') . ':</th><td>' . preg_quote(UDC_Guardian::badge($record), '~') . '~s', $html), 'Detail displays the same badge immediately below date of birth');
    }
}
echo "All minority badge checks passed.\n";

if (in_array('--locales', $argv ?? [], true)) {
    foreach (json_decode(stream_get_contents(STDIN), true) as $locale => $catalog) {
        $GLOBALS['locale'] = $locale; $GLOBALS['gettext'] = [$locale => $catalog];
        $table = new UDC_List_Table();
        check($table->get_columns()['minor'] === $catalog['Is a minor'], $locale . ': translated minority heading');
        foreach ([0 => 'No', 1 => 'Yes'] as $value => $source) {
            $badge = $table->column_default((object) ['minor_declared' => $value, 'dob' => '1990-01-01'], 'minor');
            check(str_contains($badge, '>' . esc_html($catalog[$source]) . '</span>'), $locale . ': translated ' . $source . ' badge');
        }
    }
}

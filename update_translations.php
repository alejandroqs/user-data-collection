<?php
$translations = [
    'es_ES' => [
        'City *' => 'Ciudad *',
        'Zip Code *' => 'Código Postal *',
        'City' => 'Ciudad',
        'Zip Code' => 'Código Postal',
    ],
    'fr_FR' => [
        'City *' => 'Ville *',
        'Zip Code *' => 'Code Postal *',
        'City' => 'Ville',
        'Zip Code' => 'Code Postal',
    ]
];

foreach (['es_ES', 'fr_FR'] as $lang) {
    $file_path = __DIR__ . '/languages/user-data-collection-' . $lang . '.po';
    if (!file_exists($file_path))
        continue;

    $po_content = file_get_contents($file_path);
    foreach ($translations[$lang] as $msgid => $msgstr) {
        $search = 'msgid "' . $msgid . '"' . "\n" . 'msgstr ""';
        $replace = 'msgid "' . $msgid . '"' . "\n" . 'msgstr "' . $msgstr . '"';
        $po_content = str_replace($search, $replace, $po_content);
    }
    file_put_contents($file_path, $po_content);
}

// Fallback to English for German/Italian
foreach (['de_DE', 'it_IT'] as $lang) {
    $file_path = __DIR__ . '/languages/user-data-collection-' . $lang . '.po';
    if (!file_exists($file_path))
        continue;

    $po_content = file_get_contents($file_path);
    foreach ($translations['es_ES'] as $msgid => $_) {
        $search = 'msgid "' . $msgid . '"' . "\n" . 'msgstr ""';
        $replace = 'msgid "' . $msgid . '"' . "\n" . 'msgstr "' . $msgid . '"';
        $po_content = str_replace($search, $replace, $po_content);
    }
    file_put_contents($file_path, $po_content);
}

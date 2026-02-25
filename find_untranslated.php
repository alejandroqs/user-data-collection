<?php
$file = file_get_contents(__DIR__ . '/languages/user-data-collection-es_ES.po');

preg_match_all('/msgid "(.*?)"\nmsgstr ""/m', $file, $matches);
print_r(array_unique($matches[1]));

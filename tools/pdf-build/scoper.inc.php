<?php
use Symfony\Component\Finder\Finder;

return [
    'prefix' => 'UDCPdfVendor',
    'expose-global-constants' => false,
    'expose-global-classes' => false,
    'expose-global-functions' => false,
    // These upstream classes construct names dynamically, beyond Scoper's static resolution.
    'patchers' => [static function ($path, $prefix, $contents) {
        $path = str_replace('\\', '/', $path);
        if (preg_match('~/(dompdf/src/Frame/Factory|php-font-lib/src/FontLib/(Font|TrueType/File))\.php$~', $path)) {
            $contents = str_replace(['"Dompdf\\\\', '"FontLib\\\\', '"\\\\Dompdf\\\\'],
                ['"' . $prefix . '\\\\Dompdf\\\\', '"' . $prefix . '\\\\FontLib\\\\', '"\\\\' . $prefix . '\\\\Dompdf\\\\'], $contents);
            $contents = str_replace(chr(39) . '\\Dompdf', chr(39) . '\\' . $prefix . '\\Dompdf', $contents);
            foreach (['TrueType', 'OpenType', 'WOFF', 'EOT'] as $relative) {
                $contents = str_replace('"' . $prefix . '\\\\' . $relative . '\\\\', '"' . $relative . '\\\\', $contents);
            }
            // getFontType() indexes a fully qualified class name; isolation adds one component.
            $contents = str_replace('return $class_parts[1];', 'return $class_parts[2];', $contents);
        }
        return $contents;
    }],
    'finders' => [Finder::create()->files()->ignoreVCS(true)
        ->exclude(['composer', 'bin', 'tests', 'test', '.github'])
        ->notName('autoload.php')
        ->in(dirname(__DIR__, 2) . '/vendor')],
];

<?php
if (!defined('ABSPATH')) { exit; }

/** The only module aware of the PDF engine and its print layout. */
final class UDC_Pdf_Renderer
{
    public static function html(UDC_Document $document)
    {
        $data = $document->data();
        $html = '<!doctype html><html lang="' . esc_attr(str_replace('_', '-', $data['language'])) . '"><head><meta charset="utf-8"><title>' . esc_html($data['title']) . '</title><style>' .
            '@page{margin:22mm 20mm 24mm}body{font-family:"DejaVu Sans",sans-serif;font-size:10.5pt;line-height:1.55;color:#252525}' .
            'h1{font-size:20pt;line-height:1.25;color:#650e19;border-bottom:2pt solid #650e19;padding-bottom:12pt;margin:0 0 20pt}' .
            'h2{font-size:12pt;color:#650e19;page-break-after:avoid}p{margin:0 0 13pt;orphans:3;widows:3}' .
            '.alert{border-left:3pt solid #650e19;padding-left:10pt}.metadata{border-top:1pt solid #ddd;padding-top:12pt;font-size:9pt;color:#555}' .
            'a{color:#650e19;word-wrap:break-word}</style></head><body><h1>' . esc_html($data['title']) . '</h1>';
        foreach ($data['sections'] as $section) {
            if (!empty($section['heading'])) { $html .= '<h2>' . esc_html($section['heading']) . '</h2>'; }
            $html .= '<p' . (!empty($section['alert']) ? ' class="alert"' : '') . '>' . nl2br(esc_html($section['text'])) . '</p>';
        }
        $html .= '<div class="metadata"><p>' . esc_html($data['version']) . '</p>';
        $url = esc_url($data['website_url'], ['http', 'https']);
        if ('' !== $url) {
            $label = '' !== $data['website_label'] ? $data['website_label'] : $data['website_url'];
            $html .= '<p><a href="' . $url . '">' . esc_html($label) . '</a>';
            if ($label !== $data['website_url']) { $html .= '<br>' . esc_html($data['website_url']); }
            $html .= '</p>';
        }
        return $html . '</div></body></html>';
    }

    public function render(UDC_Document $document)
    {
        if (PHP_VERSION_ID < 70400 || !extension_loaded('dom') || !extension_loaded('mbstring')) {
            throw new RuntimeException('PDF support requires PHP 7.4, DOM and mbstring.');
        }
        $runtime = UDC_PLUGIN_DIR . 'runtime/pdf';
        if (!is_file($runtime . '/autoload.php')) { throw new RuntimeException('PDF runtime is missing.'); }
        require_once $runtime . '/autoload.php';
        // Keep font cache artifacts separate from other plugins and requests.
        $temporary = get_temp_dir();
        if (!is_dir($temporary) || !is_writable($temporary)) { throw new RuntimeException('PDF temporary directory is unavailable.'); }
        $workspace = @tempnam($temporary, 'udc-pdf-');
        if (false === $workspace) { throw new RuntimeException('PDF workspace is unavailable.'); }
        if (!@unlink($workspace) || !@mkdir($workspace, 0700)) { throw new RuntimeException('PDF workspace is unavailable.'); }
        $buffer_level = ob_get_level();
        ob_start();
        try {
            $options = new \UDCPdfVendor\Dompdf\Options();
            $options->setIsRemoteEnabled(false);
            $options->setIsPhpEnabled(false);
            $options->setIsJavascriptEnabled(false);
            $options->setAllowedProtocols(['file://']);
            $options->setChroot([$runtime]);
            $options->setTempDir($workspace);
            $options->setFontCache($workspace);
            $options->setLogOutputFile('');
            $options->setDefaultFont('DejaVu Sans');
            $pdf = new \UDCPdfVendor\Dompdf\Dompdf($options);
            $pdf->setPaper('A4', 'portrait');
            $pdf->loadHtml(self::html($document), 'UTF-8');
            $pdf->render();
            $canvas = $pdf->getCanvas();
            $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
            $canvas->page_text(280, 805, '{PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.35, 0.35, 0.35]);
            $bytes = $pdf->output();
            if ('' !== ob_get_contents() || !is_string($bytes) || 0 !== strpos($bytes, '%PDF-')) { throw new RuntimeException('Invalid PDF output.'); }
            return $bytes;
        } finally {
            while (ob_get_level() > $buffer_level) { ob_end_clean(); }
            $cleanup_failed = false;
            foreach (new DirectoryIterator($workspace) as $file) {
                if (!$file->isDot() && ($file->isFile() || $file->isLink()) && !@unlink($file->getPathname())) { $cleanup_failed = true; }
            }
            if (!@rmdir($workspace)) { $cleanup_failed = true; }
            if ($cleanup_failed) { error_log('UDC_PDF_TEMP_CLEANUP_FAILED'); }
        }
    }
}

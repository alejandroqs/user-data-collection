<?php
if (!defined('ABSPATH')) { exit; }

/** Registry of public documents only; does not accept arbitrary HTML, paths or submission IDs. */
final class UDC_Document_Download
{
    private static $providers = [];

    public static function get_strings()
    {
        return [
            'invalid_request' => 'Invalid document request.',
            'unavailable' => 'PDF temporarily unavailable. Please try again later.',
        ];
    }

    private static function message($key)
    {
        return __(self::get_strings()[$key], 'user-data-collection');
    }

    public function __construct()
    {
        add_action('admin_post_udc_download_document', [$this, 'handle']);
        add_action('admin_post_nopriv_udc_download_document', [$this, 'handle']);
    }

    public static function register($key, UDC_Document_Provider $provider)
    {
        if (!preg_match('/^[a-z][a-z0-9-]{0,49}$/D', $key)) { throw new InvalidArgumentException('Invalid document key.'); }
        self::$providers[$key] = $provider;
    }

    public function handle()
    {
        $key = isset($_GET['document']) && is_string($_GET['document']) ? wp_unslash($_GET['document']) : '';
        self::send($key, $_GET);
    }

    /** Return a complete response before emitting headers, also usable by isolated tests. */
    public static function prepare($key, array $parameters)
    {
        if (!isset(self::$providers[$key])) { throw new InvalidArgumentException('Invalid document.'); }
        $language = $parameters['language'] ?? '';
        if (!is_string($language) || !preg_match('/^[A-Za-z0-9_@-]{1,32}$/D', $language)) {
            throw new InvalidArgumentException('Invalid language.');
        }
        $language = wp_unslash($language);
        $languages = function_exists('pll_languages_list') ? pll_languages_list(['fields' => 'slug']) : array_merge(['en_US', get_locale()], get_available_languages());
        if (!is_array($languages) || !in_array($language, $languages, true)) { throw new InvalidArgumentException('Invalid language.'); }
        $locale = $language;
        if (function_exists('pll_languages_list')) {
            $locales = pll_languages_list(['fields' => 'locale']);
            $index = array_search($language, $languages, true);
            if (!is_array($locales) || !isset($locales[$index]) || !is_string($locales[$index])) {
                throw new InvalidArgumentException('Invalid language mapping.');
            }
            $locale = $locales[$index];
        }
        $switched = switch_to_locale($locale);
        try {
            if (get_locale() !== $locale) { throw new RuntimeException('Requested locale is unavailable.'); }
            $parameters['language'] = $language;
            $document = self::$providers[$key]->build($parameters);
            $bytes = (new UDC_Pdf_Renderer())->render($document);
            return ['body' => $bytes, 'filename' => $document->data()['filename']];
        } finally {
            if ($switched) { restore_previous_locale(); }
        }
    }

    public static function send($key, array $parameters)
    {
        try {
            $response = self::prepare($key, $parameters);
        } catch (InvalidArgumentException $error) {
            wp_die(esc_html(self::message('invalid_request')), 'Error', ['response' => 400]);
            return;
        } catch (Throwable $error) {
            // Do not disclose filesystem paths, dependency details or submitted information.
            wp_die(esc_html(self::message('unavailable')), 'Error', ['response' => 503]);
            return;
        }
        if (headers_sent()) { wp_die(esc_html(self::message('unavailable')), 'Error', ['response' => 503]); return; }
        nocache_headers();
        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $response['filename'] . '"');
        echo $response['body'];
        exit;
    }
}

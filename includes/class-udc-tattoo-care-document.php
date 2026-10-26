<?php
if (!defined('ABSPATH')) { exit; }

/** Tattoo aftercare shared by the public form and its PDF, independent of legacy piercing copy. */
final class UDC_Tattoo_Care_Document implements UDC_Document_Provider
{
    const VERSION = '1.0';

    public static function get_strings()
    {
        return [
            'title' => 'TATTOO AFTERCARE INSTRUCTIONS',
            'care_1' => 'Dressing: Follow your tattoo artist’s instructions for the type of dressing used and when to remove or replace it. Wash your hands before handling it.',
            'care_2' => 'Cleaning: Gently wash the tattoo with water and mild, fragrance-free soap, then rinse and pat dry with clean disposable paper. Do not scrub.',
            'care_3' => 'Skin care: After removing the dressing, use a thin layer of fragrance-free aftercare moisturiser as advised by your tattoo artist. Do not scratch or pick scabs. Do not use disinfectants or antibiotic creams unless prescribed by a healthcare professional.',
            'care_4' => 'While healing: Wear clean, loose clothing and avoid rubbing, swimming, soaking, saunas and direct sun until the skin has healed. Showering is fine.',
            'care_5' => 'Warning signs: Seek prompt medical advice for increasing redness, pain, warmth, swelling or pus. Seek urgent medical care for fever, rapidly spreading redness or feeling unwell.',
            'care_6' => 'Follow-up and sun protection: Healing varies. Ask your tattoo artist about follow-up. Once healed, protect exposed tattooed skin with broad-spectrum SPF 30 or higher sunscreen.',
            'download' => 'Download tattoo aftercare (PDF)',
            'version' => 'Version',
        ];
    }

    public static function register_strings()
    {
        if (!function_exists('pll_register_string')) { return; }
        foreach (self::get_strings() as $key => $text) {
            pll_register_string('udc_tattoo_care_' . $key, $text, 'UDC Tattoo Care', strlen($text) > 100);
        }
    }

    public static function language()
    {
        $language = function_exists('pll_current_language') ? pll_current_language('slug') : false;
        return $language ?: get_locale();
    }

    public static function translate($key, $language = '')
    {
        $text = self::get_strings()[$key] ?? $key;
        if ('' !== $language && function_exists('pll_translate_string')) {
            $translated = pll_translate_string($text, $language);
            if ($translated !== $text) { return $translated; }
        } elseif ('' === $language && function_exists('pll__')) {
            $translated = pll__($text);
            if ($translated !== $text) { return $translated; }
        }
        return __($text, 'user-data-collection');
    }

    public function build(array $parameters)
    {
        $language = $parameters['language'] ?? '';
        if (!is_string($language) || !preg_match('/^[A-Za-z0-9_@-]{1,32}$/D', $language)) {
            throw new InvalidArgumentException('Invalid language.');
        }
        $sections = [];
        foreach (range(1, 6) as $number) {
            $sections[] = ['text' => self::translate('care_' . $number, $language), 'alert' => 5 === $number];
        }
        return new UDC_Document([
            'title' => self::translate('title', $language), 'language' => $language,
            'filename' => 'tattoo-care-' . $language . '.pdf',
            'version' => self::translate('version', $language) . ' ' . self::VERSION,
            'sections' => $sections, 'website_label' => '', 'website_url' => home_url('/'),
        ]);
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

final class UDC_Piercing_Care_Document implements UDC_Document_Provider
{
    public function build(array $parameters)
    {
        $category = $parameters['category'] ?? '';
        $language = $parameters['language'] ?? '';
        if (!is_string($category) || !is_string($language)) {
            throw new InvalidArgumentException('Invalid category or language.');
        }
        $data = UDC_Piercing_Form::care_data($category, $language);
        if (false === $data) { throw new InvalidArgumentException('Invalid category.'); }
        return new UDC_Document($data);
    }
}

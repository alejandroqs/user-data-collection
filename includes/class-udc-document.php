<?php
if (!defined('ABSPATH')) { exit; }

/** Public information only: private submission exports need a separate authorized endpoint. */
interface UDC_Document_Provider
{
    /** @return UDC_Document */
    public function build(array $parameters);
}

/** Renderer-independent document containing plain text, never caller-provided HTML. */
final class UDC_Document
{
    private $data;

    public function __construct(array $data)
    {
        foreach (['title', 'language', 'filename', 'version', 'website_label', 'website_url'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key])) {
                throw new InvalidArgumentException('Invalid document metadata.');
            }
        }
        if ('' === trim($data['title']) || !preg_match('/^[A-Za-z0-9_@-]{1,32}$/D', $data['language']) ||
            !preg_match('/^[a-zA-Z0-9_-]{1,100}\.pdf$/D', $data['filename']) ||
            empty($data['sections']) || !is_array($data['sections']) || count($data['sections']) > 30) {
            throw new InvalidArgumentException('Invalid document.');
        }
        foreach ($data['sections'] as $section) {
            if (!is_array($section) || !isset($section['text']) || !is_string($section['text']) ||
                strlen($section['text']) > 32000 ||
                (isset($section['heading']) && !is_string($section['heading']))) {
                throw new InvalidArgumentException('Invalid document section.');
            }
        }
        $this->data = $data;
    }

    public function data() { return $this->data; }
}

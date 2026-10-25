from pathlib import Path
import json
import re
import subprocess
import gettext
import argparse
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
DOMAIN = 'user-data-collection'
ADMIN = ['Piercings', 'Piercing Submissions', 'Accepted document', 'Document evidence is unavailable.', 'Piercing consent accepted by the user.', 'Piercing links', 'Website URL', 'Review URL (optional)']

def sources():
    php = """<?php define('ABSPATH', __DIR__); require 'includes/class-udc-i18n.php'; require 'includes/class-udc-shortcode.php'; require 'includes/class-udc-piercing-form.php'; echo json_encode(['piercing'=>UDC_Piercing_Form::get_strings(),'health'=>array_intersect_key(UDC_i18n::get_strings(),array_flip(['answer_choose','answer_yes','answer_no','answer_discuss']))], JSON_UNESCAPED_UNICODE);"""
    return json.loads(subprocess.check_output(['php'], input=php, text=True, encoding='utf-8', cwd=ROOT))

def entries(text):
    result = {}
    for block in re.split(r'\n\s*\n', text.strip()):
        values, field, flags = {}, None, []
        for line in block.splitlines():
            if line.startswith('#,'):
                flags += [item.strip() for item in line[2:].split(',')]
            elif line.startswith('#'):
                continue
            elif line.startswith('msgid_plural') or line.startswith('msgctxt') or line.startswith('msgstr['):
                raise ValueError('Context/plural entries need an explicit parser extension; refusing a lossy update')
            elif line.startswith('msgid ') or line.startswith('msgstr '):
                field, value = line.split(' ', 1)
                values[field] = json.loads(value)
            elif line.startswith('"'):
                values[field] += json.loads(line)
        if 'msgid' in values:
            key = values['msgid']
            if key in result:
                raise ValueError(f'Duplicate msgid: {key}')
            result[key] = {'translation': values.get('msgstr', ''), 'flags': flags, 'block': block}
    return result

def deduplicate_template(text):
    blocks, seen, removed = [], {}, 0
    for block in re.split(r'\n\s*\n', text.strip()):
        parsed = entries(block)
        if not parsed:
            blocks.append(block)
            continue
        msgid, entry = next(iter(parsed.items()))
        if msgid in seen:
            if seen[msgid] != entry['translation']:
                raise ValueError('Conflicting template translations: ' + msgid)
            removed += 1
        else:
            seen[msgid] = entry['translation']
            blocks.append(block)
    if removed:
        print(f'Normalized {removed} identical pre-existing duplicate POT entries')
    return '\n\n'.join(blocks) + '\n'


def quote(value):
    return json.dumps(value, ensure_ascii=False)

def header(text, version, stamp, locale=None):
    original = entries(text)['']
    metadata = {}
    for line in original['translation'].splitlines():
        if ': ' in line:
            key, value = line.split(': ', 1)
            metadata[key] = value
    metadata['Project-Id-Version'] = 'User Data Collection ' + version
    metadata['POT-Creation-Date'] = stamp
    metadata['X-Generator'] = 'scripts/update-translations.py'
    metadata['X-Domain'] = DOMAIN
    if locale:
        metadata['PO-Revision-Date'] = stamp
        metadata['Language'] = locale
        metadata['Plural-Forms'] = 'nplurals=2; plural=(n > 1);' if locale.startswith('fr') else 'nplurals=2; plural=(n != 1);'
    replacement = 'msgid ""\nmsgstr ""\n' + '\n'.join(quote(key + ': ' + value + '\n') for key, value in metadata.items())
    return text.replace(original['block'], replacement, 1)

def required(source, defaults, locale):
    language = locale.split('_')[0]
    data = defaults[language]
    result = {}
    for group in ['piercing', 'health']:
        if group in data and set(data[group]) != set(source[group]):
            raise ValueError(f'{language}/{group}: translation keys do not match active source')
        for key, msgid in source[group].items():
            value = msgid if language == 'fr' and group == 'piercing' else data[group][key]
            if locale == 'de_CH':
                value = value.replace('ß', 'ss')
            result[msgid] = (value, 'includes/class-udc-piercing-form.php' if group == 'piercing' else 'includes/class-udc-i18n.php')
    if set(data['admin']) != set(ADMIN):
        raise ValueError(f'{language}/admin: incomplete translation keys')
    for msgid in ADMIN:
        result[msgid] = (data['admin'][msgid], 'includes/class-udc-admin.php includes/class-udc-piercing-form.php')
    return result

def update_catalog(path, required_entries, version, stamp, locale=None):
    text = path.read_text(encoding='utf-8-sig')
    if path.suffix == '.pot':
        text = deduplicate_template(text)
    previous = entries(text)
    text = header(text, version, stamp, locale)
    for msgid, (translation, reference) in required_entries.items():
        if not locale:
            translation = ''
        if msgid in previous and (previous[msgid]['translation'] or not locale):
            continue
        block = '#. Default form translation; client wording review remains separate.\n#: ' + reference + '\nmsgid ' + quote(msgid) + '\nmsgstr ' + quote(translation)
        if msgid in previous:
            text = text.replace(previous[msgid]['block'], block, 1)
        else:
            text = text.rstrip() + '\n\n' + block + '\n'
    current = entries(text)
    for msgid, old in previous.items():
        if msgid and old['translation'] and current[msgid]['translation'] != old['translation']:
            raise ValueError(f'{path.name}: existing translation changed: {msgid}')
    path.write_text(text.rstrip() + '\n', encoding='utf-8', newline='\n')

def validate(path, expected, version, locale):
    po = entries(path.read_text(encoding='utf-8-sig'))
    metadata = po['']['translation']
    assert 'Language: ' + locale + '\n' in metadata, f'{locale}: incorrect language header'
    assert 'Project-Id-Version: User Data Collection ' + version + '\n' in metadata
    with path.with_suffix('.mo').open('rb') as stream:
        compiled = gettext.GNUTranslations(stream)
    assert compiled.info().get('language') == locale, f'{locale}: incorrect compiled language header'
    for msgid in expected:
        assert msgid in po and po[msgid]['translation'] and 'fuzzy' not in po[msgid]['flags'], f'{locale}: untranslated/fuzzy: {msgid}'
        assert msgid in compiled._catalog and compiled.gettext(msgid) == po[msgid]['translation'], f'{locale}: PO/MO mismatch: {msgid}'
    for msgid, entry in po.items():
        if msgid and entry['translation'] and 'fuzzy' not in entry['flags']:
            assert compiled.gettext(msgid) == entry['translation'], f'{locale}: existing PO/MO mismatch: {msgid}'
    print(f'PASS: {locale}: {len(expected)} required strings, preserved existing entries, matching compiled MO')

def validate_php_fallback(catalogs):
    payload = {}
    for path in catalogs:
        with path.with_suffix('.mo').open('rb') as stream:
            compiled = gettext.GNUTranslations(stream)
        payload[path.stem[len(DOMAIN) + 1:]] = {key: value for key, value in compiled._catalog.items() if isinstance(key, str)}
    php = r"""
    define('ABSPATH', __DIR__);
    function __($text, $domain = '') { return $GLOBALS['catalog'][$text] ?? $text; }
    function get_locale() { return $GLOBALS['locale']; }
    function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
    function esc_attr($text) { return esc_html($text); }
    function esc_url($text) { return esc_html($text); }
    function home_url($path = '') { return 'https://example.test' . $path; }
    require 'includes/class-udc-i18n.php';
    require 'includes/class-udc-shortcode.php';
    require 'includes/class-udc-piercing-form.php';
    foreach (json_decode(stream_get_contents(STDIN), true) as $locale => $catalog) {
        $GLOBALS['locale'] = $locale;
        $GLOBALS['catalog'] = $catalog;
        foreach (UDC_Piercing_Form::get_strings() as $key => $source) {
            if (UDC_Piercing_Form::translate($key) !== $catalog[$source]) { throw new RuntimeException($locale . ': PHP fallback mismatch: ' . $key); }
        }
        foreach (['answer_choose','answer_yes','answer_no','answer_discuss'] as $key) {
            $source = UDC_i18n::get_strings()[$key];
            if (UDC_i18n::translate($key) !== $catalog[$source]) { throw new RuntimeException($locale . ': existing form dropdown mismatch'); }
        }
        $snapshot = UDC_Piercing_Form::snapshot();
        if ($snapshot['language'] !== $locale || !UDC_Piercing_Form::valid_evidence(json_encode($snapshot))) { throw new RuntimeException($locale . ': translated evidence invalid'); }
        $document = UDC_Piercing_Form::care_document('oral');
        $version = esc_html(UDC_Piercing_Form::translate('version') . ' ' . UDC_Piercing_Form::FORM_VERSION);
        if (!str_contains($document, $version) || !str_contains($document, esc_html(UDC_Piercing_Form::translate('subtitle_care')))) { throw new RuntimeException($locale . ': care document translation missing'); }
        echo 'PASS: ' . $locale . ': PHP fallback, dropdowns, snapshot and care document use compiled translations' . PHP_EOL;
    }
    """
    result = subprocess.run(['php', '-r', php], input=json.dumps(payload, ensure_ascii=False), text=True, encoding='utf-8', cwd=ROOT, capture_output=True, check=True)
    print(result.stdout, end='')


def main():
    parser = argparse.ArgumentParser(description='Add default form translations, compile gettext catalogs, or validate without writing.')
    parser.add_argument('--write', action='store_true', help='Fill missing strings and rebuild PO/MO/POT; existing nonempty translations are preserved')
    args = parser.parse_args()
    source = sources()
    defaults = json.loads((ROOT / 'scripts/form-translations.json').read_text(encoding='utf-8'))
    version = re.search(r"define\('UDC_DB_VERSION',\s*'([^']+)'", (ROOT / 'user-data-collection.php').read_text(encoding='utf-8')).group(1)
    stamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M+0000')
    catalogs = sorted((ROOT / 'languages').glob(DOMAIN + '-*.po'))
    for path in catalogs:
        locale = path.stem[len(DOMAIN) + 1:]
        expected = required(source, defaults, locale)
        if args.write:
            update_catalog(path, expected, version, stamp, locale)
            subprocess.run(['msgfmt', '--check', '--check-format', '-o', str(path.with_suffix('.mo')), str(path)], check=True)
        validate(path, expected, version, locale)
    template = ROOT / 'languages' / (DOMAIN + '.pot')
    expected = required(source, defaults, 'fr')
    if args.write:
        update_catalog(template, expected, version, stamp)
    pot = entries(template.read_text(encoding='utf-8-sig'))
    assert set(expected).issubset(pot), 'POT is missing form strings'
    validate_php_fallback(catalogs)
    print(f'PASS: {len(catalogs)} locale catalogs and POT cover the active new form, dropdowns and new admin strings')

if __name__ == '__main__':
    main()

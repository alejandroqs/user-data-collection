"""Exercise PDF generation and parse text, fonts, metadata and page format with pypdf."""
import gettext
import json
from pathlib import Path
import subprocess
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
catalogs = {}
for path in sorted((ROOT / 'languages').glob('user-data-collection-*.mo')):
    with path.open('rb') as stream:
        catalog = gettext.GNUTranslations(stream)
    catalogs[path.stem.removeprefix('user-data-collection-')] = {
        key: value for key, value in catalog._catalog.items() if isinstance(key, str)}
subprocess.run(['php', 'tests/pdf-documents.php'], cwd=ROOT,
               input=json.dumps(catalogs, ensure_ascii=False), text=True, encoding='utf-8', check=True)

def normalize(text):
    return ' '.join(text.split())


for path in sorted((ROOT / 'tmp/pdfs').glob('piercing-care-*.pdf')):
    data = json.loads(path.with_suffix('.pdf.json').read_text(encoding='utf-8'))
    reader = PdfReader(path, strict=True)
    extracted = '\n'.join(page.extract_text() for page in reader.pages)
    for expected in [data['title'], data['version'], *[section['text'] for section in data['sections']]]:
        assert normalize(expected) in normalize(extracted), f'PDF text missing: {path.name}'
    assert reader.metadata.title == data['title'], f'Incorrect title: {path.name}'
    assert data['website_url'] in extracted and 'legacy.example.test' not in extracted
    assert extracted.count(data['website_url']) == 1, 'Automatic website URL duplicated in footer'
    assert '/JavaScript' not in reader.trailer['/Root'].get('/Names', {})
    for number, page in enumerate(reader.pages, 1):
        assert abs(float(page.mediabox.width) - 595.28) < 1 and abs(float(page.mediabox.height) - 841.89) < 1
        assert f'{number} / {len(reader.pages)}' in page.extract_text(), 'Missing page numbering'
        for font in page['/Resources']['/Font'].values():
            descriptor = font.get_object()['/DescendantFonts'][0].get_object()['/FontDescriptor']
            assert '/FontFile2' in descriptor, 'Unicode font was not embedded'
reader = PdfReader(ROOT / 'tmp/pdfs/synthetic-document.pdf', strict=True)
extracted = '\n'.join(page.extract_text() for page in reader.pages)
assert 'Section 17' in extracted and 'Été, español, Straße' in extracted and '<script>alert(1)</script>' in extracted
assert len(reader.pages) > 1, 'Second provider did not exercise multiple pages'
print('PASS: 46 PDFs parsed; care text, Unicode fonts, metadata, A4, page numbers and multipage content verified.')

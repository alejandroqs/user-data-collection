"""Build a strictly allowlisted, self-contained WordPress ZIP. No Git mutations."""
import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]


def build(output, tag=None):
    entry = ROOT / 'user-data-collection.php'
    source = entry.read_text(encoding='utf-8-sig')
    header = re.search(r'^ \* Version:\s*(\S+)', source, re.M).group(1)
    schema = re.search(r"define\('UDC_DB_VERSION',\s*'([^']+)'\)", source).group(1)
    assert header == schema, 'Header/schema versions disagree'
    if tag:
        assert tag == 'v' + header, 'Tag/header versions disagree'
    files = [entry, *sorted((ROOT / 'includes').glob('*.php'))]
    assert len(list((ROOT / 'includes').glob('class-udc-*.php'))) == 16, 'Unexpected class inventory'
    files += sorted(path for path in (ROOT / 'assets/qr').iterdir() if path.is_file())
    files += sorted(path for folder in ['assets/forms', 'assets/fonts'] for path in (ROOT / folder).iterdir() if path.is_file())
    files += sorted(path for path in (ROOT / 'languages').iterdir() if path.suffix in ('.po', '.mo', '.pot'))
    runtime = ROOT / 'runtime/pdf'
    manifest = json.loads((runtime / 'manifest.json').read_text(encoding='utf-8'))
    actual = {path.relative_to(runtime).as_posix() for path in runtime.rglob('*') if path.is_file()}
    assert actual == set(manifest) | {'manifest.json'}, 'Runtime manifest/file mismatch'
    required = {'autoload.php', 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf',
                'vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf', 'vendor/dompdf/dompdf/LICENSE.LGPL'}
    assert required <= set(manifest), 'Missing runtime, fonts or license'
    for relative, digest in manifest.items():
        path = PurePosixPath(relative)
        assert not path.is_absolute() and '..' not in path.parts and '\\' not in relative
        assert relative == 'autoload.php' or relative.startswith(('vendor/dompdf/', 'vendor/masterminds/html5/', 'vendor/sabberworm/php-css-parser/'))
        file = runtime / relative
        assert not file.is_symlink() and hashlib.sha256(file.read_bytes()).hexdigest() == digest, 'Runtime hash mismatch'
        files.append(file)
    files.append(runtime / 'manifest.json')
    for file in files:
        assert not file.is_symlink(), 'Symlinks are not package inputs'
        if file.suffix == '.php':
            subprocess.run(['php', '-l', str(file)], check=True, capture_output=True)
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as archive:
        for file in files:
            name = 'user-data-collection/' + file.relative_to(ROOT).as_posix()
            info = zipfile.ZipInfo(name, (2026, 1, 1, 0, 0, 0))
            info.external_attr = 0o100644 << 16
            archive.writestr(info, file.read_bytes(), compress_type=zipfile.ZIP_DEFLATED)
    with zipfile.ZipFile(output) as archive:
        assert archive.testzip() is None
        assert len(archive.namelist()) == len(set(archive.namelist())) == len(files)
        assert all(not (item.external_attr >> 16) & 0o111 for item in archive.infolist())
    print(f'PASS: {len(files)} allowlisted files, PHP lint, runtime hashes and ZIP integrity: {output}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--tag')
    parser.add_argument('--output', type=Path, default=ROOT / 'tmp/user-data-collection.zip')
    args = parser.parse_args()
    build(args.output, args.tag)
